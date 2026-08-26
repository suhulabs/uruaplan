<?php
/**
 * Autenticación: usuarios, login con doble alias, límite de intentos
 * y control de sesión.
 *
 * Reglas clave:
 *  - Las contraseñas SIEMPRE viven como hash de password_hash(). Nunca en claro.
 *  - Cada persona tiene dos alias válidos (nombre corto y profesión).
 *  - Tras MAX_INTENTOS fallos se bloquea temporalmente esa combinación
 *    usuario+IP, y además la IP completa a un umbral más alto.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/funciones.php';
require_once __DIR__ . '/bd.php';

// ---------------------------------------------------------------------------
// USUARIOS
// ---------------------------------------------------------------------------

/**
 * Da forma a la fila que devuelve MySQL: booleanos de verdad y la lista de
 * alias adjunta, para que el resto del código la use como antes.
 */
function usuario_desde_fila(array $fila)
{
    $fila['debe_cambiar'] = !empty($fila['debe_cambiar']);
    $fila['alias'] = bd_columna(
        'SELECT alias FROM usuario_alias WHERE usuario_id = ? ORDER BY alias',
        array($fila['id'])
    );

    return $fila;
}



/**
 * Busca un usuario por cualquiera de sus alias.
 *
 * Los alias se guardan siempre en minúsculas, así que la búsqueda es una
 * consulta por clave primaria: exacta, con índice y sin LIKE de por medio.
 *
 * @return array|null  El usuario, o null si ese alias no existe.
 */
function buscar_usuario_por_alias($alias)
{
    $alias = mb_strtolower(trim((string) $alias), 'UTF-8');
    if ($alias === '') {
        return null;
    }

    $fila = bd_fila(
        'SELECT u.* FROM usuario_alias a
           JOIN usuarios u ON u.id = a.usuario_id
          WHERE a.alias = ?',
        array($alias)
    );

    return $fila === null ? null : usuario_desde_fila($fila);
}

/**
 * @return array|null  El usuario, o null si ya no existe.
 */
function buscar_usuario_por_id($id)
{
    $fila = bd_fila('SELECT * FROM usuarios WHERE id = ?', array((string) $id));

    return $fila === null ? null : usuario_desde_fila($fila);
}

/**
 * Crea o reemplaza un usuario con sus alias. La usa el instalador.
 *
 * @param string[] $alias  Nombres con los que podrá entrar.
 */
function guardar_usuario($id, $nombre, $hash, array $alias, $debeCambiar = true)
{
    $id = mb_strtolower(trim((string) $id), 'UTF-8');

    return bd_transaccion(function () use ($id, $nombre, $hash, $alias, $debeCambiar) {
        bd_ejecutar(
            'INSERT INTO usuarios (id, nombre, hash, debe_cambiar, creado)
                  VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                  nombre = VALUES(nombre),
                  hash = VALUES(hash),
                  debe_cambiar = VALUES(debe_cambiar)',
            array($id, (string) $nombre, (string) $hash, $debeCambiar ? 1 : 0, date('Y-m-d H:i:s'))
        );

        bd_ejecutar('DELETE FROM usuario_alias WHERE usuario_id = ?', array($id));

        foreach ($alias as $a) {
            $a = mb_strtolower(trim((string) $a), 'UTF-8');
            if ($a === '') {
                continue;
            }

            // Si dos personas pidieran el mismo alias, gana quien lo tuviera
            // antes: el INSERT IGNORE no pisa el que ya está.
            bd_ejecutar(
                'INSERT IGNORE INTO usuario_alias (alias, usuario_id) VALUES (?, ?)',
                array($a, $id)
            );
        }

        return true;
    });
}

// ---------------------------------------------------------------------------
// LÍMITE DE INTENTOS (anti fuerza bruta)
// ---------------------------------------------------------------------------

// ip_cliente() vive en funciones.php: la necesitan también la página
// pública y el módulo de flyers, que no cargan este archivo.

/**
 * Normaliza la clave del contador.
 *
 * Una de las claves lleva dentro el alias que se tecleó en el login, y eso
 * es texto libre del visitante: si alguien manda mil caracteres no puede
 * reventar la columna. Por encima del largo útil se sustituye por su hash,
 * que sigue siendo único por alias y siempre mide lo mismo.
 */
function intentos_clave($clave)
{
    $clave = (string) $clave;

    if (strlen($clave) <= 180) {
        return $clave;
    }

    return substr($clave, 0, 110) . '#' . hash('sha256', $clave);
}

/**
 * Tira los contadores que ya caducaron, para que la tabla no crezca sola.
 * Se llama al anotar un fallo, que es cuando puede aparecer una fila nueva.
 */
function intentos_limpiar_caducados()
{
    // Probabilidad 1 de cada 10 para no sobrecargar de escrituras la BD.
    if (random_int(1, 10) === 1) {
        bd_ejecutar('DELETE FROM intentos WHERE ultimo < ?', array(time() - 86400));
    }
}

/**
 * Estado de bloqueo de una clave concreta.
 *
 * @return array('bloqueado' => bool, 'restante' => int, 'fallos' => int)
 */
function intentos_estado($clave, $limite)
{
    $ahora = time();

    $registro = bd_fila(
        'SELECT fallos, ultimo FROM intentos WHERE clave = ?',
        array(intentos_clave($clave))
    );

    if ($registro === null) {
        return array('bloqueado' => false, 'restante' => 0, 'fallos' => 0);
    }

    $fallos = (int) $registro['fallos'];
    $ultimo = (int) $registro['ultimo'];

    // Si ya pasó la ventana de bloqueo, el contador se considera limpio.
    if ($ahora - $ultimo >= BLOQUEO_SEGUNDOS) {
        return array('bloqueado' => false, 'restante' => 0, 'fallos' => 0);
    }

    if ($fallos >= $limite) {
        return array(
            'bloqueado' => true,
            'restante'  => BLOQUEO_SEGUNDOS - ($ahora - $ultimo),
            'fallos'    => $fallos,
        );
    }

    return array('bloqueado' => false, 'restante' => 0, 'fallos' => $fallos);
}

function intentos_registrar_fallo($clave)
{
    $clave = intentos_clave($clave);
    $ahora = time();

    intentos_limpiar_caducados();

    // El contador solo acumula dentro de la ventana de bloqueo: si el último
    // fallo fue hace más de BLOQUEO_SEGUNDOS, se empieza a contar de cero.
    // Esa decisión se toma en el propio UPDATE, con lo cual dos intentos
    // simultáneos no pueden pisarse el uno al otro.
    bd_ejecutar(
        'INSERT INTO intentos (clave, fallos, ultimo)
              VALUES (?, 1, ?)
         ON DUPLICATE KEY UPDATE
              fallos = IF(VALUES(ultimo) - ultimo < ?, fallos + 1, 1),
              ultimo = VALUES(ultimo)',
        array($clave, $ahora, BLOQUEO_SEGUNDOS)
    );
}

function intentos_limpiar($clave)
{
    bd_ejecutar('DELETE FROM intentos WHERE clave = ?', array(intentos_clave($clave)));
}

// ---------------------------------------------------------------------------
// LOGIN
// ---------------------------------------------------------------------------

/**
 * Intenta autenticar. Devuelve array('ok' => bool, 'error' => string).
 *
 * El mensaje de error es genérico a propósito: no revela si el alias existe.
 */
function intentar_login($alias, $password)
{
    $alias = trim((string) $alias);
    $ip    = ip_cliente();

    $clave_ip = 'ip:' . $ip;

    // La IP sola tiene un umbral más alto: frena a quien rota alias.
    $estado_ip = intentos_estado($clave_ip, MAX_INTENTOS * 3);
    if ($estado_ip['bloqueado']) {
        return array(
            'ok'    => false,
            'error' => 'Demasiados intentos desde esta conexión. Espera '
                . ceil($estado_ip['restante'] / 60) . ' minuto(s).',
        );
    }

    if ($alias === '' || $password === '') {
        return array('ok' => false, 'error' => 'Escribe tu usuario y tu contraseña.');
    }

    $usuario = buscar_usuario_por_alias($alias);

    // El contador se lleva por CUENTA, no por alias: si no fuera así,
    // probar "luis" y luego "contador" daría el doble de intentos sobre
    // la misma persona. Para alias inexistentes se usa el texto tecleado.
    $identidad = $usuario !== null
        ? $usuario['id']
        : 'desconocido:' . mb_strtolower($alias, 'UTF-8');

    $clave_usuario = 'u:' . $identidad . '|' . $ip;

    $estado_usuario = intentos_estado($clave_usuario, MAX_INTENTOS);
    if ($estado_usuario['bloqueado']) {
        return array(
            'ok'    => false,
            'error' => 'Cuenta bloqueada temporalmente por intentos fallidos. Espera '
                . ceil($estado_usuario['restante'] / 60) . ' minuto(s).',
        );
    }

    if ($usuario === null) {
        // Verificación señuelo: gasta el mismo tiempo que un hash real para
        // que no se pueda deducir por latencia qué alias existen.
        password_verify($password, '$2y$10$usuarioInexistenteXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX');
        intentos_registrar_fallo($clave_usuario);
        intentos_registrar_fallo($clave_ip);
        return array('ok' => false, 'error' => 'Usuario o contraseña incorrectos.');
    }

    if (empty($usuario['hash']) || !password_verify($password, $usuario['hash'])) {
        intentos_registrar_fallo($clave_usuario);
        intentos_registrar_fallo($clave_ip);

        $restantes = MAX_INTENTOS - ($estado_usuario['fallos'] + 1);
        $aviso = $restantes > 0 && $restantes <= 2
            ? ' Te quedan ' . $restantes . ' intento(s) antes del bloqueo.'
            : '';

        return array('ok' => false, 'error' => 'Usuario o contraseña incorrectos.' . $aviso);
    }

    // --- Credenciales correctas ---

    intentos_limpiar($clave_usuario);
    intentos_limpiar($clave_ip);

    // Rehash transparente si el coste por defecto de PHP subió con el tiempo.
    // Solo se rehashea hacia ARRIBA: si el hosting corre una versión de PHP
    // con coste por defecto menor, no degradamos un hash ya más fuerte.
    if (password_needs_rehash($usuario['hash'], PASSWORD_DEFAULT) && !hash_es_mas_fuerte($usuario['hash'])) {
        bd_ejecutar(
            'UPDATE usuarios SET hash = ? WHERE id = ?',
            array(password_hash($password, PASSWORD_DEFAULT), $usuario['id'])
        );
    }

    // Evita la fijación de sesión: ID nuevo tras autenticar.
    session_regenerate_id(true);

    $_SESSION['usuario_id']     = $usuario['id'];
    $_SESSION['usuario_nombre'] = isset($usuario['nombre']) ? $usuario['nombre'] : $usuario['id'];
    $_SESSION['alias_usado']    = mb_strtolower($alias, 'UTF-8');
    $_SESSION['ultimo_uso']     = time();
    $_SESSION['huella']         = huella_cliente();

    // Registra el acceso.
    bd_ejecutar(
        'UPDATE usuarios SET ultimo_acceso = ? WHERE id = ?',
        array(date('Y-m-d H:i:s'), $usuario['id'])
    );

    return array('ok' => true, 'error' => '');
}

/**
 * ¿El hash existente ya es más costoso que el que produciría este PHP?
 *
 * Los hashes se generaron con PHP 8.5 (coste bcrypt 12). Si el hosting
 * corriera PHP 7.4 (coste 10), password_needs_rehash() querría rebajarlos.
 * Esta comprobación lo impide.
 */
function hash_es_mas_fuerte($hash)
{
    $info = password_get_info($hash);

    if (empty($info['options']['cost'])) {
        return false;
    }

    $costeActual = defined('PASSWORD_BCRYPT_DEFAULT_COST') ? PASSWORD_BCRYPT_DEFAULT_COST : 10;

    return (int) $info['options']['cost'] > (int) $costeActual;
}

/**
 * Huella débil del navegador. Sirve para invalidar una cookie de sesión
 * robada que se reutilice desde otro agente. No es una medida fuerte,
 * es una capa barata más.
 */
function huella_cliente()
{
    $agente = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
    return hash('sha256', $agente . '|uruaplan');
}

// ---------------------------------------------------------------------------
// CONTROL DE SESIÓN
// ---------------------------------------------------------------------------

function sesion_activa()
{
    if (empty($_SESSION['usuario_id'])) {
        return false;
    }

    if (empty($_SESSION['huella']) || !hash_equals($_SESSION['huella'], huella_cliente())) {
        return false;
    }

    $ultimo = isset($_SESSION['ultimo_uso']) ? (int) $_SESSION['ultimo_uso'] : 0;
    if (time() - $ultimo > SESION_INACTIVIDAD) {
        return false;
    }

    // La cuenta pudo eliminarse mientras la sesión seguía viva.
    if (buscar_usuario_por_id($_SESSION['usuario_id']) === null) {
        return false;
    }

    return true;
}

function usuario_actual()
{
    if (empty($_SESSION['usuario_id'])) {
        return null;
    }

    return buscar_usuario_por_id($_SESSION['usuario_id']);
}

/**
 * Puerta de entrada de todas las páginas privadas del panel.
 *
 * Llamarla ANTES de imprimir cualquier cosa. Si no hay sesión válida
 * redirige al login; si el usuario todavía tiene la contraseña temporal,
 * lo manda a cambiarla.
 *
 * @param bool $es_pagina_de_cambio true solo en cambiar-password.php,
 *                                  para no crear un bucle de redirección.
 */
function exigir_sesion($es_pagina_de_cambio = false)
{
    iniciar_sesion_panel();

    if (!sesion_activa()) {
        cerrar_sesion(false);
        header('Location: index.php?e=sesion');
        exit;
    }

    // Renueva la marca de actividad en cada carga.
    $_SESSION['ultimo_uso'] = time();

    $usuario = usuario_actual();

    if (!$es_pagina_de_cambio && !empty($usuario['debe_cambiar'])) {
        header('Location: cambiar-password.php?obligatorio=1');
        exit;
    }

    return $usuario;
}

function cerrar_sesion($destruir_cookie = true)
{
    $_SESSION = array();

    if ($destruir_cookie && ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $p['path'],
            $p['domain'],
            $p['secure'],
            $p['httponly']
        );
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

// ---------------------------------------------------------------------------
// CAMBIO DE CONTRASEÑA
// ---------------------------------------------------------------------------

/**
 * Valida la robustez mínima de una contraseña nueva.
 *
 * @return string  Mensaje de error, o '' si es válida.
 */
function validar_password_nueva($nueva, $repetida)
{
    if (mb_strlen($nueva, 'UTF-8') < MIN_LARGO_PASSWORD) {
        return 'La contraseña debe tener al menos ' . MIN_LARGO_PASSWORD . ' caracteres.';
    }

    if (mb_strlen($nueva, 'UTF-8') > 200) {
        return 'La contraseña es demasiado larga (máximo 200 caracteres).';
    }

    if ($nueva !== $repetida) {
        return 'Las dos contraseñas no coinciden.';
    }

    if (!preg_match('/[A-Za-zÁÉÍÓÚáéíóúÑñ]/u', $nueva) || !preg_match('/[0-9]/', $nueva)) {
        return 'La contraseña debe incluir al menos una letra y un número.';
    }

    return '';
}

/**
 * Guarda el hash nuevo y quita la marca de contraseña temporal.
 */
function cambiar_password($id_usuario, $nueva)
{
    if (buscar_usuario_por_id($id_usuario) === null) {
        return false;
    }

    bd_ejecutar(
        'UPDATE usuarios
            SET hash = ?, debe_cambiar = 0, password_actualizada = ?
          WHERE id = ?',
        array(password_hash($nueva, PASSWORD_DEFAULT), date('Y-m-d H:i:s'), (string) $id_usuario)
    );

    return true;
}
