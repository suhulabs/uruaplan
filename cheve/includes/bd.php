<?php
/**
 * ===========================================================================
 *  ACCESO A LA BASE DE DATOS
 * ===========================================================================
 *  Capa fina sobre PDO. Todo el panel y la página pública pasan por aquí.
 *
 *  Reglas que se respetan en TODO el proyecto:
 *
 *   1. NUNCA se concatena un valor dentro de una consulta. Todo va con
 *      marcadores (?), que es lo que hace imposible una inyección SQL.
 *      Lo único que se interpola alguna vez son nombres de columna, y
 *      siempre a partir de una lista blanca del código, jamás del POST.
 *
 *   2. El detalle técnico de un fallo de base de datos NUNCA se le enseña
 *      al visitante: podría llevar el nombre de la base o del usuario.
 *      Va al log de errores de PHP (cPanel → Métricas → Errores) y en
 *      pantalla queda un mensaje genérico.
 *
 *   3. La conexión es perezosa: se abre la primera vez que alguien
 *      pregunta algo, no al incluir el archivo. Así una página que no
 *      necesite datos no paga el coste de conectar.
 * ===========================================================================
 */

require_once __DIR__ . '/config.php';

if (!isset($GLOBALS['BD'])) {
    require_once __DIR__ . '/config-bd.php';
}

/**
 * Excepción propia, para poder distinguir "falló la base de datos" de
 * cualquier otro error al capturar.
 */
class BdError extends RuntimeException
{
}

// ---------------------------------------------------------------------------
// CONEXIÓN
// ---------------------------------------------------------------------------

/**
 * Devuelve la conexión PDO, creándola la primera vez.
 *
 * @throws BdError si no se puede conectar.
 */
function bd()
{
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    if (!extension_loaded('pdo_mysql')) {
        throw new BdError(
            'Este servidor no tiene activada la extensión pdo_mysql de PHP. '
                . 'Actívala en cPanel → Selector de PHP → Extensiones.'
        );
    }

    $cfg = isset($GLOBALS['BD']) && is_array($GLOBALS['BD']) ? $GLOBALS['BD'] : array();

    $host    = isset($cfg['host']) ? (string) $cfg['host'] : 'localhost';
    $puerto  = isset($cfg['puerto']) ? (int) $cfg['puerto'] : 3306;
    $base    = isset($cfg['base']) ? (string) $cfg['base'] : '';
    $usuario = isset($cfg['usuario']) ? (string) $cfg['usuario'] : '';
    $clave   = isset($cfg['password']) ? (string) $cfg['password'] : '';
    $charset = isset($cfg['charset']) ? (string) $cfg['charset'] : 'utf8mb4';

    if ($base === '' || $usuario === '') {
        throw new BdError(
            'Faltan los datos de conexión. Edita cheve/includes/config-bd.php '
                . 'con el nombre de la base, el usuario y la contraseña que creaste en cPanel.'
        );
    }

    $dsn = 'mysql:host=' . $host . ';port=' . $puerto . ';dbname=' . $base . ';charset=' . $charset;

    try {
        $pdo = new PDO($dsn, $usuario, $clave, array(
            // Los errores llegan como excepción; nunca se ignoran en silencio.
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

            // Prepared statements REALES del servidor, no emulados por PDO.
            // Es lo que garantiza que el valor nunca se mezcle con el SQL.
            PDO::ATTR_EMULATE_PREPARES   => false,

            // Los enteros llegan como int, no como cadena.
            PDO::ATTR_STRINGIFY_FETCHES  => false,

            PDO::ATTR_TIMEOUT            => 5,
        ));
    } catch (PDOException $e) {
        // El mensaje de PDO puede llevar el host y el nombre de la base:
        // se registra, pero no se propaga hacia la pantalla.
        error_log('[uruaplan] No se pudo conectar a MySQL: ' . $e->getMessage());

        throw new BdError(bd_traducir_conexion($e));
    }

    // La zona horaria de la sesión de MySQL se alinea con la de PHP para que
    // NOW() y date() no discrepen (en cPanel es fácil que no coincidan).
    try {
        $desfase = bd_desfase_horario();
        $pdo->exec("SET time_zone = '" . $desfase . "'");
    } catch (PDOException $e) {
        // Algunos hostings compartidos no dejan cambiarla. No es crítico:
        // las fechas las escribe PHP, NOW() casi no se usa.
        error_log('[uruaplan] No se pudo fijar la zona horaria de MySQL: ' . $e->getMessage());
    }

    return $pdo;
}

/**
 * Desfase horario actual de PHP en el formato que entiende MySQL (+HH:MM).
 */
function bd_desfase_horario()
{
    $minutos = (int) round(date('Z') / 60);
    $signo   = $minutos < 0 ? '-' : '+';
    $minutos = abs($minutos);

    return $signo . str_pad((string) intdiv($minutos, 60), 2, '0', STR_PAD_LEFT)
        . ':' . str_pad((string) ($minutos % 60), 2, '0', STR_PAD_LEFT);
}

/**
 * Traduce los fallos de conexión más habituales a algo accionable,
 * sin repetir el mensaje crudo de MySQL.
 */
function bd_traducir_conexion(PDOException $e)
{
    $codigo = (string) $e->getCode();

    if ($codigo === '1045') {
        return 'MySQL rechazó el usuario o la contraseña. Revísalos en '
            . 'cheve/includes/config-bd.php y comprueba en cPanel que el usuario '
            . 'esté añadido a la base con TODOS LOS PRIVILEGIOS.';
    }

    if ($codigo === '1049') {
        return 'La base de datos no existe. Créala en cPanel → Bases de datos MySQL '
            . 'y copia su nombre completo (con el prefijo de la cuenta) en config-bd.php.';
    }

    if ($codigo === '2002' || strpos($e->getMessage(), 'refused') !== false) {
        return 'No se pudo contactar con el servidor de MySQL. Comprueba el valor '
            . 'de «host» en config-bd.php (en cPanel casi siempre es localhost).';
    }

    return 'No se pudo conectar con la base de datos. El detalle del error quedó '
        . 'en el log del servidor (cPanel → Métricas → Errores, líneas «[uruaplan]»).';
}

/**
 * ¿Se puede hablar con la base de datos?
 *
 * @param string $error  Se rellena con el motivo si no se puede.
 */
function bd_disponible(&$error = '')
{
    $error = '';

    try {
        bd();
        return true;
    } catch (BdError $e) {
        $error = $e->getMessage();
        return false;
    }
}

// ---------------------------------------------------------------------------
// CONSULTAS
// ---------------------------------------------------------------------------

/**
 * Prepara y ejecuta. Es la única función que habla con PDO directamente.
 *
 * @param string $sql      Con marcadores ? para TODOS los valores.
 * @param array  $params   Valores, en el mismo orden que los marcadores.
 *
 * @throws BdError
 */
function bd_ejecutar($sql, array $params = array())
{
    try {
        $sentencia = bd()->prepare($sql);
        $sentencia->execute($params);
        return $sentencia;
    } catch (PDOException $e) {
        // La consulta sí se registra: es información nuestra, no del visitante,
        // y sin ella un fallo de esquema es imposible de localizar.
        error_log('[uruaplan] Error SQL: ' . $e->getMessage() . ' | SQL: ' . $sql);

        throw new BdError('La base de datos rechazó una operación. '
            . 'El detalle quedó en el log del servidor.');
    }
}

/**
 * Todas las filas de una consulta.
 */
function bd_filas($sql, array $params = array())
{
    return bd_ejecutar($sql, $params)->fetchAll();
}

/**
 * La primera fila, o null si no hay ninguna.
 */
function bd_fila($sql, array $params = array())
{
    $fila = bd_ejecutar($sql, $params)->fetch();

    return $fila === false ? null : $fila;
}

/**
 * El primer valor de la primera fila (COUNT(*), MAX(...), etc.).
 */
function bd_valor($sql, array $params = array(), $porDefecto = null)
{
    $valor = bd_ejecutar($sql, $params)->fetchColumn();

    return $valor === false ? $porDefecto : $valor;
}

/**
 * Una columna entera como array plano.
 */
function bd_columna($sql, array $params = array())
{
    return bd_ejecutar($sql, $params)->fetchAll(PDO::FETCH_COLUMN, 0);
}

/**
 * Cuántas filas cambió el último INSERT/UPDATE/DELETE.
 */
function bd_afectadas($sql, array $params = array())
{
    return bd_ejecutar($sql, $params)->rowCount();
}

/**
 * Id que MySQL acaba de asignar en un INSERT con AUTO_INCREMENT.
 */
function bd_ultimo_id()
{
    return (int) bd()->lastInsertId();
}

/**
 * Ejecuta varias operaciones como un bloque: o entran todas o no entra
 * ninguna. Se usa al guardar el contenido (decenas de escrituras seguidas)
 * y al moderar un flyer (cambio de estado + apunte en el historial).
 *
 * @param callable $trabajo  Recibe el PDO. Lo que devuelva se devuelve.
 */
function bd_transaccion(callable $trabajo)
{
    $pdo = bd();

    // Las transacciones no se anidan: si ya hay una abierta, se participa
    // en ella en lugar de abrir otra (MySQL haría un commit implícito).
    if ($pdo->inTransaction()) {
        return $trabajo($pdo);
    }

    $pdo->beginTransaction();

    try {
        $resultado = $trabajo($pdo);
        $pdo->commit();
        return $resultado;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

// ---------------------------------------------------------------------------
// UTILIDADES
// ---------------------------------------------------------------------------

/**
 * ¿Existe esa tabla en la base configurada?
 *
 * Se pregunta al catálogo con el nombre como PARÁMETRO, no interpolado.
 */
function bd_tabla_existe($tabla)
{
    try {
        $n = bd_valor(
            'SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            array((string) $tabla),
            0
        );
    } catch (BdError $e) {
        return false;
    }

    return (int) $n > 0;
}

/**
 * ¿Están ya creadas las tablas del panel?
 * Es lo que decide si hay que mandar a la gente a instalar.php.
 */
function bd_instalada()
{
    static $cache = null;

    if ($cache === null) {
        $cache = bd_tabla_existe('contenido') && bd_tabla_existe('usuarios') && bd_tabla_existe('flyers');
    }

    return $cache;
}

/**
 * Puerta de entrada de las páginas del panel.
 *
 * Si la base no responde o todavía no está instalada, corta aquí con una
 * pantalla que dice exactamente qué hacer, en vez de dejar un error 500 sin
 * explicación. Hay que llamarla ANTES de imprimir nada.
 *
 * @param string $destinoInstalar  Ruta relativa a instalar.php desde la
 *                                 página que llama.
 */
function bd_exigir($destinoInstalar = 'instalar.php')
{
    $error = '';

    if (!bd_disponible($error)) {
        bd_pantalla_error(
            'No hay conexión con la base de datos',
            $error,
            'Cuando lo arregles, recarga esta página.'
        );
    }

    if (!bd_instalada()) {
        header('Location: ' . $destinoInstalar);
        exit;
    }
}

/**
 * Página de error mínima y autocontenida (sin CSS externo: si la base falla,
 * lo último que queremos es depender de nada más).
 */
function bd_pantalla_error($titulo, $detalle, $pie = '')
{
    if (!headers_sent()) {
        http_response_code(503);
        header('Content-Type: text/html; charset=UTF-8');
        header('Retry-After: 120');
    }

    $esc = function ($t) {
        return htmlspecialchars((string) $t, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    };

    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<meta name="robots" content="noindex,nofollow">'
        . '<title>' . $esc($titulo) . '</title>'
        . '<style>'
        . 'body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;'
        . 'background:#12151a;color:#e8eaed;font:16px/1.6 system-ui,-apple-system,Segoe UI,sans-serif;padding:1.5rem}'
        . '.caja{max-width:34rem;background:#1b1f26;border:1px solid #2c323c;border-radius:14px;padding:2rem}'
        . 'h1{margin:0 0 .75rem;font-size:1.35rem;color:#ff8a80}'
        . 'p{margin:0 0 1rem}code{background:#12151a;padding:.15rem .4rem;border-radius:5px;font-size:.9em}'
        . '.pie{color:#9aa3ae;font-size:.9rem;margin:0}'
        . '</style></head><body><div class="caja">'
        . '<h1>' . $esc($titulo) . '</h1>'
        . '<p>' . $esc($detalle) . '</p>'
        . ($pie !== '' ? '<p class="pie">' . $esc($pie) . '</p>' : '')
        . '</div></body></html>';

    exit;
}


