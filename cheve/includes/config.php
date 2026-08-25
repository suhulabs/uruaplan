<?php
/**
 * Configuración central del panel Uruaplan.
 *
 * Este archivo NO debe imprimir nada. Se incluye desde todas las páginas
 * del panel y desde la página pública (index.php de la raíz).
 */

// ---------------------------------------------------------------------------
// RUTAS EN DISCO
// ---------------------------------------------------------------------------

// .../public_html/cheve/includes  ->  .../public_html/cheve
define('CHEVE_DIR', dirname(__DIR__));

// .../public_html  (raíz pública del sitio)
define('SITIO_DIR', dirname(CHEVE_DIR));

define('DATA_DIR', CHEVE_DIR . '/data');
define('UPLOADS_DIR', SITIO_DIR . '/img/uploads');

// Prefijo que se guarda DENTRO del JSON. Siempre relativo a la raíz del sitio,
// nunca con "/" inicial, para que funcione igual en local y en el hosting.
define('UPLOADS_REL', 'img/uploads');

define('ARCHIVO_CONTENIDO', DATA_DIR . '/contenido.json');
define('ARCHIVO_USUARIOS', DATA_DIR . '/usuarios.json');

// ---------------------------------------------------------------------------
// SEGURIDAD / LÍMITES
// ---------------------------------------------------------------------------

// Intentos de login fallidos antes de bloquear temporalmente.
define('MAX_INTENTOS', 5);

// Duración del bloqueo por fuerza bruta, en segundos (15 minutos).
define('BLOQUEO_SEGUNDOS', 900);

// Cierre de sesión automático por inactividad, en segundos (1 hora).
define('SESION_INACTIVIDAD', 3600);

// Longitud mínima de contraseña al cambiarla.
define('MIN_LARGO_PASSWORD', 10);

// Tamaño máximo de archivos subidos.
define('MAX_BYTES_IMAGEN', 5 * 1024 * 1024);   // 5 MB
define('MAX_BYTES_VIDEO', 25 * 1024 * 1024);  // 25 MB

// Tipos MIME reales permitidos (se verifican con finfo, no con la extensión).
$GLOBALS['MIME_IMAGEN'] = array(
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
);

$GLOBALS['MIME_VIDEO'] = array(
    'video/mp4' => 'mp4',
    'video/quicktime' => 'mp4',
);

// ---------------------------------------------------------------------------
// SESIÓN
// ---------------------------------------------------------------------------

define('NOMBRE_SESION', 'uruaplan_panel');

/**
 * Arranca la sesión del panel con cookies endurecidas.
 * Detecta HTTPS para marcar la cookie como "secure" solo cuando aplica
 * (en GoDaddy con SSL activo será siempre true).
 */
function iniciar_sesion_panel()
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
        || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);

    session_name(NOMBRE_SESION);

    // La cookie se limita a la carpeta del panel: no viaja al sitio público.
    $ruta_cookie = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/';
    if ($ruta_cookie === '/') {
        $ruta_cookie = '/cheve/';
    }

    session_set_cookie_params(array(
        'lifetime' => 0,
        'path' => $ruta_cookie,
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ));

    session_start();
}
