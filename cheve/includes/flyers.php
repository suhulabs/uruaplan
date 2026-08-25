<?php
/**
 * ===========================================================================
 *  FLYERS — MODELO
 * ===========================================================================
 *  Todo lo que se puede hacer con una solicitud de evento vive aquí: crearla
 *  desde el formulario público, listarla en el panel, moderarla y publicarla.
 *
 *  LOS DOS INTERRUPTORES
 *  ---------------------
 *  Un flyer tiene un ESTADO y, aparte, una marca de PUBLICADO:
 *
 *    estado      pendiente → aceptado → archivado
 *                     └───→ rechazado
 *    publicado   sí / no   (solo tiene sentido si el estado es "aceptado")
 *
 *  Están separados a propósito. Aceptar es decir "este evento está bien";
 *  publicar es decir "sácalo ya en la web". Un evento aprobado puede esperar
 *  sin estar visible, y se puede quitar de la web sin rechazarlo. Al sacar un
 *  flyer del estado "aceptado" se despublica solo: no puede quedar en la web
 *  algo que se acaba de rechazar.
 *
 *  DÓNDE ESTÁ LA FOTO
 *  ------------------
 *  Mientras se revisa, la foto vive en cheve/data/flyers/, que está cerrada
 *  al público por .htaccess: nadie puede ver desde internet la foto de un
 *  evento que aún no se aprobó, ni la de uno rechazado. El panel la enseña a
 *  través de flyer-imagen.php, que exige sesión.
 *
 *  Al PUBLICAR se hace una copia en img/uploads/, que sí es pública, y esa
 *  es la que ve el visitante. Al despublicar, esa copia se borra y el
 *  original sigue guardado. Así el archivo público existe exactamente
 *  mientras el flyer está publicado, y ni un minuto más.
 * ===========================================================================
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/funciones.php';
require_once __DIR__ . '/bd.php';
require_once __DIR__ . '/subidas.php';

/** Estados válidos, con su etiqueta y su color en el panel. */
function flyer_estados()
{
    return array(
        'pendiente' => array('etiqueta' => 'Pendiente', 'icono' => 'fa-solid fa-hourglass-half', 'clase' => 'estado-pendiente'),
        'aceptado' => array('etiqueta' => 'Aceptado', 'icono' => 'fa-solid fa-circle-check', 'clase' => 'estado-aceptado'),
        'rechazado' => array('etiqueta' => 'Rechazado', 'icono' => 'fa-solid fa-circle-xmark', 'clase' => 'estado-rechazado'),
        'archivado' => array('etiqueta' => 'Archivado', 'icono' => 'fa-solid fa-box-archive', 'clase' => 'estado-archivado'),
    );
}

function flyer_estado_valido($estado)
{
    return array_key_exists((string) $estado, flyer_estados());
}

// ---------------------------------------------------------------------------
// LA CARPETA PRIVADA DE LAS FOTOS
// ---------------------------------------------------------------------------

function flyers_dir()
{
    return DATA_DIR . '/flyers';
}

/**
 * Crea cheve/data/flyers/ con su .htaccess si no existe.
 *
 * El .htaccess se escribe desde aquí y no se sube a mano porque es el que
 * impide que las fotos pendientes se puedan abrir desde internet: si alguien
 * borra la carpeta, al siguiente envío se vuelve a proteger sola.
 *
 * @return string  '' si todo bien, o el problema encontrado.
 */
function flyers_preparar_carpeta()
{
    $dir = flyers_dir();

    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        return 'No se pudo crear la carpeta cheve/data/flyers/. Créala en cPanel con permisos 755.';
    }

    if (!is_writable($dir)) {
        return 'La carpeta cheve/data/flyers/ no tiene permiso de escritura. Ponla en 755.';
    }

    $htaccess = $dir . '/.htaccess';

    if (!is_file($htaccess)) {
        $reglas = "# Las fotos de los flyers no se sirven nunca directamente.\n"
            . "# El panel las enseña a través de cheve/flyer-imagen.php, que exige sesión,\n"
            . "# y las publicadas se copian a img/uploads/. Aquí no entra nadie.\n"
            . "<IfModule mod_authz_core.c>\n"
            . "    Require all denied\n"
            . "</IfModule>\n"
            . "<IfModule !mod_authz_core.c>\n"
            . "    Order allow,deny\n"
            . "    Deny from all\n"
            . "</IfModule>\n";

        @file_put_contents($htaccess, $reglas);
        @chmod($htaccess, 0644);
    }

    return '';
}

/**
 * Guarda en la carpeta privada la foto que acaba de llegar por el formulario.
 *
 * Se le pasa el contenido ya leído y validado (tipo real comprobado con
 * finfo) porque enviar-evento.php necesita ese mismo contenido para adjuntarlo
 * al correo, y no tiene sentido leer el archivo dos veces.
 *
 * @return string  Nombre del archivo guardado, o '' si no se pudo.
 */
function flyer_guardar_imagen($contenido, $mime)
{
    if ($contenido === '' || !isset($GLOBALS['MIME_IMAGEN'][$mime])) {
        return '';
    }

    if (flyers_preparar_carpeta() !== '') {
        return '';
    }

    $nombre = 'flyer-' . date('Ymd') . '-' . bin2hex(random_bytes(6))
        . '.' . $GLOBALS['MIME_IMAGEN'][$mime];

    if (@file_put_contents(flyers_dir() . '/' . $nombre, $contenido) === false) {
        return '';
    }

    @chmod(flyers_dir() . '/' . $nombre, 0644);

    return $nombre;
}

/**
 * Ruta en disco de la foto privada de un flyer.
 *
 * El nombre se valida aunque venga de nuestra propia base de datos: es la
 * única barrera entre un valor manipulado y un file_get_contents() a
 * cualquier parte del servidor.
 *
 * @return string  Ruta absoluta, o '' si el nombre no es aceptable.
 */
function flyer_ruta_imagen($nombre)
{
    $nombre = (string) $nombre;

    if (!preg_match('/^flyer-\d{8}-[a-f0-9]{12}\.(jpg|png|webp)$/', $nombre)) {
        return '';
    }

    $ruta = flyers_dir() . '/' . $nombre;

    return is_file($ruta) ? $ruta : '';
}

/**
 * Borra la foto privada de un flyer.
 */
function flyer_borrar_imagen($nombre)
{
    $ruta = flyer_ruta_imagen($nombre);

    return $ruta !== '' ? @unlink($ruta) : false;
}

// ---------------------------------------------------------------------------
// ALTA (desde el formulario público)
// ---------------------------------------------------------------------------

/**
 * Registra una solicitud nueva.
 *
 * @param array $datos  Campos ya limpios que vienen del formulario.
 *
 * @return int  El id del flyer creado.
 * @throws BdError
 */
function flyer_crear(array $datos)
{
    $ahora = date('Y-m-d H:i:s');

    $agente = isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';

    $ip = ip_cliente();
    $coordsGps = isset($datos['geo_coords']) ? trim((string) $datos['geo_coords']) : '';

    $geoOrigen = '';
    if ($coordsGps !== '' && preg_match('/^-?\d+\.\d+,\s*-?\d+\.\d+$/', $coordsGps)) {
        $geoOrigen = 'GPS: ' . $coordsGps;
    }

    bd_ejecutar(
        'INSERT INTO flyers
            (evento, subtitulo, fecha, hora, ubicacion, precio, contacto,
             descripcion, comentarios, imagen, imagen_mime, imagen_ancho,
             imagen_alto, imagen_bytes, nota, ip, agente, geo_origen, correo_ok,
             recibido, actualizado)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
        array(
            (string) flyer_valor($datos, 'evento'),
            (string) flyer_valor($datos, 'subtitulo'),
            flyer_valor($datos, 'fecha') !== '' ? flyer_valor($datos, 'fecha') : null,
            flyer_valor($datos, 'hora') !== '' ? flyer_valor($datos, 'hora') . ':00' : null,
            (string) flyer_valor($datos, 'ubicacion'),
            (string) flyer_valor($datos, 'precio'),
            (string) flyer_valor($datos, 'contacto'),
            (string) flyer_valor($datos, 'descripcion'),
            (string) flyer_valor($datos, 'comentarios'),
            (string) flyer_valor($datos, 'imagen'),
            (string) flyer_valor($datos, 'imagen_mime'),
            (int) flyer_valor($datos, 'imagen_ancho', 0),
            (int) flyer_valor($datos, 'imagen_alto', 0),
            (int) flyer_valor($datos, 'imagen_bytes', 0),
            '',
            substr($ip, 0, 45),
            substr($agente, 0, 255),
            substr($geoOrigen, 0, 150),
            !empty($datos['correo_ok']) ? 1 : 0,
            $ahora,
            $ahora,
        )
    );

    $id = bd_ultimo_id();

    flyer_anotar($id, 'recibido', null, 'Llegó por el formulario de la web.');

    return $id;
}

/**
 * Atajo interno: valor de un array con respaldo.
 */
function flyer_valor(array $datos, $clave, $porDefecto = '')
{
    return isset($datos[$clave]) ? $datos[$clave] : $porDefecto;
}

// ---------------------------------------------------------------------------
// CONSULTAS
// ---------------------------------------------------------------------------

function flyer_obtener($id)
{
    return bd_fila('SELECT * FROM flyers WHERE id = ?', array((int) $id));
}

/**
 * Bandeja del panel.
 *
 * @param array $filtros  ('estado' => string, 'busqueda' => string,
 *                         'pagina' => int, 'por_pagina' => int)
 *
 * @return array ('filas' => array[], 'total' => int, 'paginas' => int)
 */
function flyers_listar(array $filtros = array())
{
    $donde = array();
    $params = array();

    $estado = isset($filtros['estado']) ? (string) $filtros['estado'] : '';
    if (flyer_estado_valido($estado)) {
        $donde[] = 'estado = ?';
        $params[] = $estado;
    }

    $busqueda = isset($filtros['busqueda']) ? trim((string) $filtros['busqueda']) : '';
    if ($busqueda !== '') {
        // El texto del buscador va como PARÁMETRO; lo único que se construye
        // es el patrón, y los comodines propios de LIKE se escapan para que
        // un "%" escrito por alguien no acabe casando con todo.
        $patron = '%' . str_replace(array('\\', '%', '_'), array('\\\\', '\%', '\_'), $busqueda) . '%';

        $donde[] = '(evento LIKE ? OR subtitulo LIKE ? OR ubicacion LIKE ? OR contacto LIKE ?)';
        array_push($params, $patron, $patron, $patron, $patron);
    }

    $sqlDonde = $donde ? ' WHERE ' . implode(' AND ', $donde) : '';

    $total = (int) bd_valor('SELECT COUNT(*) FROM flyers' . $sqlDonde, $params, 0);

    $porPagina = isset($filtros['por_pagina']) ? max(1, min(100, (int) $filtros['por_pagina'])) : 20;
    $paginas = max(1, (int) ceil($total / $porPagina));
    $pagina = isset($filtros['pagina']) ? max(1, min($paginas, (int) $filtros['pagina'])) : 1;
    $salto = ($pagina - 1) * $porPagina;

    // LIMIT no admite marcadores en MySQL con prepared statements reales,
    // así que van interpolados: son enteros que acabamos de calcular
    // nosotros con max()/min(), nunca texto que venga del navegador.
    $filas = bd_filas(
        'SELECT * FROM flyers' . $sqlDonde
        . ' ORDER BY recibido DESC, id DESC'
        . ' LIMIT ' . (int) $porPagina . ' OFFSET ' . (int) $salto,
        $params
    );

    return array(
        'filas' => $filas,
        'total' => $total,
        'pagina' => $pagina,
        'paginas' => $paginas,
    );
}

/**
 * Cuántos flyers hay en cada estado. Alimenta las pestañas y el contador
 * rojo de la barra superior.
 */
function flyers_conteo()
{
    $conteo = array('total' => 0, 'publicados' => 0);

    foreach (array_keys(flyer_estados()) as $estado) {
        $conteo[$estado] = 0;
    }

    foreach (bd_filas('SELECT estado, COUNT(*) AS n FROM flyers GROUP BY estado') as $fila) {
        $conteo[$fila['estado']] = (int) $fila['n'];
        $conteo['total'] += (int) $fila['n'];
    }

    $conteo['publicados'] = (int) bd_valor(
        "SELECT COUNT(*) FROM flyers WHERE publicado = 1 AND estado = 'aceptado'",
        array(),
        0
    );

    return $conteo;
}

/**
 * Cuántos están esperando revisión. Se usa en la barra del panel y tiene que
 * ser barato: se llama en cada carga de página.
 */
function flyers_pendientes()
{
    try {
        return (int) bd_valor("SELECT COUNT(*) FROM flyers WHERE estado = 'pendiente'", array(), 0);
    } catch (BdError $e) {
        return 0;
    }
}

/**
 * Los flyers que se enseñan en la web pública.
 *
 * Se piden los aceptados Y publicados, con la foto ya copiada a la carpeta
 * pública. Los eventos cuya fecha ya pasó salen al final en vez de
 * desaparecer, porque hay quien pone la fecha de inicio de algo que dura
 * varios días.
 */
function flyers_publicados($limite = 12)
{
    try {
        return bd_filas(
            "SELECT * FROM flyers
              WHERE publicado = 1 AND estado = 'aceptado' AND imagen_publica <> ''
              ORDER BY (fecha IS NULL OR fecha < CURDATE()), fecha ASC, id DESC
              LIMIT " . max(1, min(60, (int) $limite))
        );
    } catch (BdError $e) {
        // La cartelera es un extra: si la base no responde, la página se
        // dibuja sin ella en lugar de caerse entera.
        error_log('[uruaplan] No se pudo leer la cartelera: ' . $e->getMessage());
        return array();
    }
}

// ---------------------------------------------------------------------------
// HISTORIAL
// ---------------------------------------------------------------------------

/**
 * Deja constancia de algo que le pasó a un flyer.
 *
 * @param array|null $usuario  El del panel, o null si lo hizo el visitante.
 */
function flyer_anotar($flyerId, $accion, $usuario = null, $detalle = '')
{
    bd_ejecutar(
        'INSERT INTO flyer_historial (flyer_id, accion, usuario_id, usuario_nombre, detalle, cuando)
         VALUES (?, ?, ?, ?, ?, ?)',
        array(
            (int) $flyerId,
            substr((string) $accion, 0, 30),
            $usuario !== null && isset($usuario['id']) ? $usuario['id'] : null,
            $usuario !== null
            ? substr((string) (isset($usuario['nombre']) ? $usuario['nombre'] : $usuario['id']), 0, 120)
            : '',
            (string) $detalle,
            date('Y-m-d H:i:s'),
        )
    );
}

function flyer_historial($flyerId)
{
    return bd_filas(
        'SELECT * FROM flyer_historial WHERE flyer_id = ? ORDER BY cuando DESC, id DESC',
        array((int) $flyerId)
    );
}

// ---------------------------------------------------------------------------
// MODERACIÓN
// ---------------------------------------------------------------------------

/**
 * Cambia el estado de un flyer y lo apunta en el historial.
 *
 * @param string $nota  Motivo o comentario interno (opcional).
 *
 * @return string  '' si salió bien, o el error para enseñar en pantalla.
 */
function flyer_cambiar_estado($id, $estado, array $usuario, $nota = '')
{
    $id = (int) $id;

    if (!flyer_estado_valido($estado)) {
        return 'Ese estado no existe.';
    }

    $flyer = flyer_obtener($id);
    if ($flyer === null) {
        return 'Ese flyer ya no existe.';
    }

    if ($flyer['estado'] === $estado) {
        return '';   // Nada que hacer, y nada que anotar.
    }

    // Salir de "aceptado" implica salir de la web: no puede quedarse
    // publicado algo que se acaba de rechazar o archivar.
    $despublicar = $estado !== 'aceptado' && !empty($flyer['publicado']);

    bd_transaccion(function () use ($id, $estado, $usuario, $nota, $flyer, $despublicar) {
        bd_ejecutar(
            'UPDATE flyers
                SET estado = ?, publicado = ?, imagen_publica = ?,
                    nota = ?, revisado_por = ?, revisado_en = ?, actualizado = ?
              WHERE id = ?',
            array(
                $estado,
                $despublicar ? 0 : (int) $flyer['publicado'],
                $despublicar ? '' : $flyer['imagen_publica'],
                $nota !== '' ? $nota : $flyer['nota'],
                $usuario['id'],
                date('Y-m-d H:i:s'),
                date('Y-m-d H:i:s'),
                $id,
            )
        );

        $detalle = 'De «' . $flyer['estado'] . '» a «' . $estado . '».'
            . ($nota !== '' ? ' Nota: ' . $nota : '')
            . ($despublicar ? ' Se quitó de la web.' : '');

        flyer_anotar($id, $estado, $usuario, $detalle);
    });

    // El archivo se borra DESPUÉS de que la base confirme el cambio. Al revés,
    // si el UPDATE fallara y se deshiciera, la foto ya no estaría pero el
    // flyer seguiría marcado como publicado: la web quedaría con un hueco.
    if ($despublicar) {
        flyer_quitar_copia_publica($flyer);
    }

    return '';
}

/**
 * Publica un flyer: copia su foto a la carpeta pública y lo marca visible.
 *
 * @return string  '' si salió bien, o el motivo del rechazo.
 */
function flyer_publicar($id, array $usuario)
{
    $flyer = flyer_obtener((int) $id);

    if ($flyer === null) {
        return 'Ese flyer ya no existe.';
    }

    if ($flyer['estado'] !== 'aceptado') {
        return 'Antes de publicarlo hay que aceptarlo.';
    }

    if (!empty($flyer['publicado']) && $flyer['imagen_publica'] !== '') {
        return '';   // Ya estaba publicado.
    }

    $origen = flyer_ruta_imagen($flyer['imagen']);
    if ($origen === '') {
        return 'No se encuentra la foto de este flyer en el servidor, así que no se puede publicar.';
    }

    $errorCarpeta = verificar_carpeta_subidas();
    if ($errorCarpeta !== '') {
        return $errorCarpeta;
    }

    $extension = isset($GLOBALS['MIME_IMAGEN'][$flyer['imagen_mime']])
        ? $GLOBALS['MIME_IMAGEN'][$flyer['imagen_mime']]
        : 'jpg';

    $nombre = 'evento-' . (int) $flyer['id'] . '-' . bin2hex(random_bytes(5)) . '.' . $extension;

    if (!@copy($origen, UPLOADS_DIR . '/' . $nombre)) {
        return 'No se pudo copiar la foto a img/uploads/. Revisa que la carpeta tenga permisos 755.';
    }

    @chmod(UPLOADS_DIR . '/' . $nombre, 0644);

    $rutaPublica = UPLOADS_REL . '/' . $nombre;

    try {
        bd_transaccion(function () use ($flyer, $rutaPublica, $usuario) {
            bd_ejecutar(
                'UPDATE flyers SET publicado = 1, imagen_publica = ?, actualizado = ? WHERE id = ?',
                array($rutaPublica, date('Y-m-d H:i:s'), (int) $flyer['id'])
            );

            flyer_anotar($flyer['id'], 'publicado', $usuario, 'Ya se ve en la cartelera de la web.');
        });
    } catch (BdError $e) {
        // Si la base falla, la copia pública no debe quedarse suelta.
        borrar_subida_si_procede($rutaPublica);
        return $e->getMessage();
    }

    return '';
}

/**
 * Lo quita de la web sin cambiar su estado ni perder nada.
 */
function flyer_despublicar($id, array $usuario)
{
    $flyer = flyer_obtener((int) $id);

    if ($flyer === null) {
        return 'Ese flyer ya no existe.';
    }

    if (empty($flyer['publicado'])) {
        return '';
    }

    bd_transaccion(function () use ($flyer, $usuario) {
        bd_ejecutar(
            "UPDATE flyers SET publicado = 0, imagen_publica = '', actualizado = ? WHERE id = ?",
            array(date('Y-m-d H:i:s'), (int) $flyer['id'])
        );

        flyer_anotar($flyer['id'], 'despublicado', $usuario, 'Se quitó de la cartelera de la web.');
    });

    // Igual que al cambiar de estado: primero se confirma en la base y solo
    // entonces se borra el archivo.
    flyer_quitar_copia_publica($flyer);

    return '';
}

/**
 * Borra la copia pública de la foto. El original en data/flyers/ no se toca:
 * si el flyer se vuelve a publicar, se hace una copia nueva.
 */
function flyer_quitar_copia_publica(array $flyer)
{
    if (!empty($flyer['imagen_publica'])) {
        borrar_subida_si_procede($flyer['imagen_publica']);
    }
}

/**
 * Guarda los cambios que el editor hizo en los datos del flyer.
 *
 * Devuelve el número de campos que cambiaron, para poder decir "no había
 * nada que guardar" en vez de fingir un guardado.
 *
 * @param array $campos  Solo se miran las claves de la lista blanca de abajo.
 */
function flyer_actualizar($id, array $campos, array $usuario)
{
    $id = (int) $id;
    $flyer = flyer_obtener($id);

    if ($flyer === null) {
        return array('ok' => false, 'error' => 'Ese flyer ya no existe.', 'cambios' => 0);
    }

    // Lista blanca: lo que no esté aquí no se guarda, venga como venga.
    $editables = array(
        'evento' => 'Nombre del evento',
        'subtitulo' => 'Subtítulo',
        'fecha' => 'Fecha',
        'hora' => 'Hora',
        'ubicacion' => 'Ubicación',
        'precio' => 'Precio',
        'contacto' => 'Contacto',
        'descripcion' => 'Descripción',
        'comentarios' => 'Comentarios del solicitante',
        'nota' => 'Nota interna',
        'pos_x' => 'Encuadre horizontal',
        'pos_y' => 'Encuadre vertical',
        'zoom' => 'Zoom',
    );

    $sets = array();
    $params = array();
    $tocados = array();

    foreach ($editables as $campo => $etiqueta) {
        if (!array_key_exists($campo, $campos)) {
            continue;
        }

        $nuevo = $campos[$campo];
        $actual = $flyer[$campo];

        // La hora llega como HH:MM y MySQL la guarda como HH:MM:SS.
        if ($campo === 'hora') {
            $nuevo = $nuevo !== '' ? substr($nuevo, 0, 5) : null;
            $actual = $actual !== null ? substr($actual, 0, 5) : null;
        }

        if ($campo === 'fecha') {
            $nuevo = $nuevo !== '' ? $nuevo : null;
        }

        if (in_array($campo, array('pos_x', 'pos_y', 'zoom'), true)) {
            // Comparación numérica: "50" y "50.00" son el mismo encuadre.
            if (abs((float) $nuevo - (float) $actual) < 0.005) {
                continue;
            }
        } elseif ((string) $nuevo === (string) $actual) {
            continue;
        }

        // El nombre de la columna sale de $editables, que es código nuestro;
        // el valor siempre va como parámetro.
        $sets[] = '`' . $campo . '` = ?';
        $params[] = $nuevo;
        $tocados[] = $etiqueta;
    }

    if (!$sets) {
        return array('ok' => true, 'error' => '', 'cambios' => 0);
    }

    $sets[] = 'actualizado = ?';
    $params[] = date('Y-m-d H:i:s');
    $params[] = $id;

    bd_transaccion(function () use ($sets, $params, $id, $usuario, $tocados) {
        bd_ejecutar('UPDATE flyers SET ' . implode(', ', $sets) . ' WHERE id = ?', $params);

        flyer_anotar($id, 'editado', $usuario, 'Se cambió: ' . implode(', ', $tocados) . '.');
    });

    return array('ok' => true, 'error' => '', 'cambios' => count($tocados));
}

/**
 * Reemplaza la foto de un flyer por otra subida desde el panel.
 *
 * @return string  '' si salió bien, o el error.
 */
function flyer_reemplazar_imagen($id, array $archivo, array $usuario)
{
    $flyer = flyer_obtener((int) $id);

    if ($flyer === null) {
        return 'Ese flyer ya no existe.';
    }

    // 1. Usar la función unificada de validación
    $val = validar_imagen_subida($archivo, false, MAX_BYTES_IMAGEN);

    if ($val['vacio']) {
        return '';   // No se eligió ninguna foto nueva: se conserva la actual.
    }

    if (!$val['ok']) {
        return $val['error'];
    }

    $mime = $val['mime'];
    $dimensiones = $val['dimensiones'];

    $contenido = @file_get_contents($archivo['tmp_name']);
    if ($contenido === false || $contenido === '') {
        return 'No se pudo leer la foto. Inténtalo de nuevo.';
    }

    $nombreNuevo = flyer_guardar_imagen($contenido, $mime);
    if ($nombreNuevo === '') {
        return 'No se pudo guardar la foto en el servidor. Revisa los permisos de cheve/data/flyers/.';
    }

    $anterior = $flyer['imagen'];
    $publicaAnterior = $flyer['imagen_publica'];

    try {
        bd_transaccion(function () use ($flyer, $nombreNuevo, $mime, $dimensiones, $archivo, $usuario) {
            bd_ejecutar(
                "UPDATE flyers
                    SET imagen = ?, imagen_mime = ?, imagen_ancho = ?, imagen_alto = ?,
                        imagen_bytes = ?, imagen_publica = '', publicado = 0, actualizado = ?
                  WHERE id = ?",
                array(
                    $nombreNuevo,
                    $mime,
                    (int) $dimensiones[0],
                    (int) $dimensiones[1],
                    (int) $archivo['size'],
                    date('Y-m-d H:i:s'),
                    (int) $flyer['id'],
                )
            );

            flyer_anotar(
                $flyer['id'],
                'foto',
                $usuario,
                'Se cambió la foto del flyer.'
                . (!empty($flyer['publicado']) ? ' Como estaba publicado, se quitó de la web '
                    . 'para que nadie viera la foto vieja: vuelve a publicarlo cuando lo revises.' : '')
            );
        });
    } catch (BdError $e) {
        @unlink(flyers_dir() . '/' . $nombreNuevo);
        return $e->getMessage();
    }

    // Ya está guardada la nueva: eliminamos la versión vieja y su copia pública
    flyer_borrar_imagen($anterior);
    if ($publicaAnterior !== '') {
        borrar_subida_si_procede($publicaAnterior);
    }

    return '';
}


/**
 * Borra un flyer para siempre, con su foto y su historial.
 *
 * Es lo único que no tiene vuelta atrás, por eso el panel lo esconde detrás
 * de una confirmación y solo lo ofrece sobre flyers ya archivados o
 * rechazados. Para todo lo demás está "archivar".
 */
function flyer_eliminar($id, array $usuario)
{
    $flyer = flyer_obtener((int) $id);

    if ($flyer === null) {
        return 'Ese flyer ya no existe.';
    }

    // El historial se va solo con el ON DELETE CASCADE de la tabla.
    bd_ejecutar('DELETE FROM flyers WHERE id = ?', array((int) $flyer['id']));

    flyer_quitar_copia_publica($flyer);
    flyer_borrar_imagen($flyer['imagen']);

    error_log('[uruaplan] Flyer #' . (int) $flyer['id'] . ' («' . $flyer['evento'] . '») '
        . 'eliminado por ' . $usuario['id'] . '.');

    return '';
}

/**
 * Purga los datos personales del remitente (IP y User-Agent) en flyers
 * con más de $meses de antigüedad para cumplir con retención de datos (ej. LFPDPPP).
 *
 * @param int $meses Antigüedad en meses (por defecto 6).
 * @param array $usuario Datos del usuario del panel que ejecuta la acción (opcional).
 *
 * @return int Número de flyers purgados.
 * @throws BdError
 */
function flyers_purgar_datos_personales($meses = 6, array $usuario = array())
{
    $meses = max(1, (int) $meses);
    $limite = date('Y-m-d H:i:s', strtotime("-{$meses} months"));

    $afectados = bd_afectadas(
        "UPDATE flyers
            SET ip = '', agente = ''
          WHERE (ip != '' OR agente != '')
            AND recibido < ?",
        array($limite)
    );

    if ($afectados > 0) {
        $quien = isset($usuario['id']) ? $usuario['id'] : 'sistema';
        error_log('[uruaplan] Purga de datos personales: ' . $afectados . ' flyer(s) de más de '
            . $meses . ' meses limpiados por ' . $quien . '.');
    }

    return $afectados;
}

