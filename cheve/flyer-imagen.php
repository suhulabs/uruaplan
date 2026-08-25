<?php
/**
 * ===========================================================================
 *  FOTO PRIVADA DE UN FLYER
 * ===========================================================================
 *  Las fotos de los flyers viven en cheve/data/flyers/, que está cerrada al
 *  público. Este archivo es la única puerta por la que salen, y solo se abre
 *  con sesión iniciada.
 *
 *  Es lo que permite que el panel enseñe la foto de un evento que todavía
 *  está pendiente (o que se rechazó) sin que esa imagen quede colgada en
 *  internet para quien acierte la dirección.
 * ===========================================================================
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/funciones.php';
require_once __DIR__ . '/includes/bd.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/flyers.php';

bd_exigir();
exigir_sesion();

$id    = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$flyer = $id > 0 ? flyer_obtener($id) : null;

if ($flyer === null) {
    http_response_code(404);
    exit('No existe.');
}

$ruta = flyer_ruta_imagen($flyer['imagen']);

if ($ruta === '') {
    http_response_code(404);
    exit('Este flyer no tiene foto guardada.');
}

$mime = isset($GLOBALS['MIME_IMAGEN'][$flyer['imagen_mime']])
    ? $flyer['imagen_mime']
    : 'application/octet-stream';

$tamano = (int) filesize($ruta);
$fecha  = (int) filemtime($ruta);
$etag   = '"' . md5($flyer['imagen'] . '|' . $fecha . '|' . $tamano) . '"';

// El navegador puede guardarla en su caché privada (es la misma persona con
// la misma sesión), pero nunca un proxy compartido.
header('Content-Type: ' . $mime);
header('Content-Length: ' . $tamano);
header('Cache-Control: private, max-age=600');
header('ETag: ' . $etag);
header('X-Content-Type-Options: nosniff');

// Si el navegador ya la tiene, no se vuelve a mandar.
if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
    http_response_code(304);
    exit;
}

readfile($ruta);
