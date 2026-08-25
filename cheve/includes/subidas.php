<?php
/**
 * Validación y almacenamiento de archivos subidos.
 *
 * Defensas aplicadas:
 *  1. Se comprueba el MIME REAL con finfo, no la extensión ni el
 *     Content-Type que envía el navegador (ambos son falsificables).
 *  2. Para imágenes se exige además que getimagesize() las reconozca.
 *  3. El archivo se renombra a un nombre aleatorio con extensión derivada
 *     del MIME detectado, así nunca se guarda un ".php" ni se colisiona.
 *  4. La carpeta img/uploads/ lleva su propio .htaccess que desactiva
 *     la ejecución de PHP por si algo se colara.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/funciones.php';

/**
 * Traduce los códigos de error de PHP a algo que un editor entienda.
 */
function mensaje_error_subida($codigo)
{
    switch ($codigo) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'El archivo supera el tamaño máximo que acepta el servidor ('
                . ini_get('upload_max_filesize') . ').';
        case UPLOAD_ERR_PARTIAL:
            return 'La subida se interrumpió. Inténtalo de nuevo.';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'El servidor no tiene carpeta temporal configurada.';
        case UPLOAD_ERR_CANT_WRITE:
            return 'El servidor no pudo escribir el archivo en disco.';
        case UPLOAD_ERR_EXTENSION:
            return 'Una extensión de PHP bloqueó la subida.';
        default:
            return 'Error desconocido al subir el archivo (código ' . $codigo . ').';
    }
}

/**
 * Comprueba que la carpeta de subidas exista y se pueda escribir.
 *
 * @return string  '' si todo bien, o el mensaje de error.
 */
function verificar_carpeta_subidas()
{
    if (!is_dir(UPLOADS_DIR)) {
        if (!@mkdir(UPLOADS_DIR, 0755, true)) {
            return 'No existe la carpeta ' . UPLOADS_REL . '/ y no se pudo crear. Créala en cPanel con permisos 755.';
        }
    }

    if (!is_writable(UPLOADS_DIR)) {
        return 'La carpeta ' . UPLOADS_REL . '/ no tiene permiso de escritura. Ponla en 755 desde el Administrador de archivos.';
    }

    return '';
}

/**
 * Valida de forma estricta una imagen o archivo multimedia subido.
 * 
 * @param array $archivo        Elemento de $_FILES.
 * @param bool  $permitirVideo  Si es true, permite también formatos de video MP4.
 * @param int   $maxBytes       Límite máximo en bytes (por defecto MAX_BYTES_IMAGEN).
 * 
 * @return array ('ok' => bool, 'vacio' => bool, 'mime' => string, 'extension' => string, 'error' => string)
 */
function validar_imagen_subida(array $archivo, $permitirVideo = false, $maxBytes = MAX_BYTES_IMAGEN)
{
    if (!isset($archivo['error']) || is_array($archivo['error'])) {
        return ['ok' => false, 'vacio' => false, 'error' => 'Subida malformada o incompleta.'];
    }

    if ($archivo['error'] === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'vacio' => true, 'error' => 'No se seleccionó ningún archivo.'];
    }

    if ($archivo['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($archivo['tmp_name'])) {
        return ['ok' => false, 'vacio' => false, 'error' => mensaje_error_subida($archivo['error'])];
    }

    if ($archivo['size'] <= 0) {
        return ['ok' => false, 'vacio' => false, 'error' => 'El archivo está vacío.'];
    }

    if ($archivo['size'] > $maxBytes) {
        $mbSubido = round($archivo['size'] / 1048576, 1);
        $mbMax = round($maxBytes / 1048576);
        return ['ok' => false, 'vacio' => false, 'error' => "El archivo pesa {$mbSubido} MB y el máximo permitido es {$mbMax} MB."];
    }

    // Inspección de MIME real
    $permitidos = $GLOBALS['MIME_IMAGEN'];
    if ($permitirVideo) {
        $permitidos = array_merge($permitidos, $GLOBALS['MIME_VIDEO']);
    }

    $mime = '';
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = (string) finfo_file($finfo, $archivo['tmp_name']);
            finfo_close($finfo);
        }
    }
    if ($mime === '' && function_exists('mime_content_type')) {
        $mime = (string) mime_content_type($archivo['tmp_name']);
    }

    if ($mime === '' || !isset($permitidos[$mime])) {
        $formatos = $permitirVideo ? 'JPG, PNG, WEBP o MP4' : 'JPG, PNG o WEBP';
        return ['ok' => false, 'vacio' => false, 'error' => "El archivo debe ser un formato válido ({$formatos})."];
    }

    // Para imágenes, verificar integridad de cabecera con getimagesize
    $ext = $permitidos[$mime];
    if (in_array($ext, ['jpg', 'png', 'webp'])) {
        $dims = @getimagesize($archivo['tmp_name']);
        if ($dims === false || empty($dims[0])) {
            return ['ok' => false, 'vacio' => false, 'error' => 'El archivo no es una imagen válida o está dañado.'];
        }
    }

    return [
        'ok' => true,
        'vacio' => false,
        'mime' => $mime,
        'extension' => $ext,
        'dimensiones' => isset($dims) && is_array($dims) ? $dims : null,
        'error' => ''
    ];
}

/**
 * Procesa un archivo subido y lo deja en img/uploads/.
 *
 * @param string $nombreCampo  Clave dentro de $_FILES.
 * @param string $tipo         'imagen' (jpg/png/webp) o 'media' (+ mp4).
 * @param string $etiqueta     Texto para el prefijo del nombre del archivo.
 *
 * @return array (
 *     'vacio' => bool,
 *     'ok'    => bool,
 *     'ruta'  => string,
 *     'error' => string
 * )
 */
function procesar_subida($nombreCampo, $tipo = 'imagen', $etiqueta = 'archivo')
{
    $vacio = array('vacio' => true, 'ok' => false, 'ruta' => '', 'error' => '');

    if (!isset($_FILES[$nombreCampo])) {
        return $vacio;
    }

    $esMedia = ($tipo === 'media');
    $maxBytes = $esMedia ? MAX_BYTES_VIDEO : MAX_BYTES_IMAGEN;

    // 1. Usar la nueva función unificada para validar
    $val = validar_imagen_subida($_FILES[$nombreCampo], $esMedia, $maxBytes);

    if ($val['vacio']) {
        return $vacio;
    }

    if (!$val['ok']) {
        return array('vacio' => false, 'ok' => false, 'ruta' => '', 'error' => $val['error']);
    }

    // 2. Carpeta destino
    $errorCarpeta = verificar_carpeta_subidas();
    if ($errorCarpeta !== '') {
        return array('vacio' => false, 'ok' => false, 'ruta' => '', 'error' => $errorCarpeta);
    }

    // 3. Nombre final aleatorio y seguro
    $prefijo = preg_replace('/[^a-z0-9]+/', '-', strtolower($etiqueta));
    $prefijo = trim($prefijo, '-');
    if ($prefijo === '') {
        $prefijo = 'archivo';
    }
    $prefijo = substr($prefijo, 0, 40);

    $nombreFinal = $prefijo . '-' . date('Ymd') . '-' . bin2hex(random_bytes(5)) . '.' . $val['extension'];
    $destino = UPLOADS_DIR . '/' . $nombreFinal;

    if (!move_uploaded_file($_FILES[$nombreCampo]['tmp_name'], $destino)) {
        return array('vacio' => false, 'ok' => false, 'ruta' => '', 'error' => 'No se pudo guardar el archivo en el servidor.');
    }

    @chmod($destino, 0644);

    return array(
        'vacio' => false,
        'ok' => true,
        'ruta' => UPLOADS_REL . '/' . $nombreFinal,
        'error' => '',
    );
}



/**
 * Valida una ruta de archivo que llega desde un campo oculto del formulario
 * (las filas de las listas arrastran su imagen actual).
 *
 * Solo se aceptan rutas relativas dentro de img/. Cualquier intento de
 * salirse con "../" o de apuntar a otra carpeta se descarta.
 *
 * @return string  La ruta si es aceptable, o '' si no lo es.
 */
function ruta_existente_segura($ruta)
{
    $ruta = trim((string) $ruta);

    if ($ruta === '') {
        return '';
    }

    // Sin bytes nulos, sin rutas absolutas, sin escapes hacia arriba.
    if (strpos($ruta, "\0") !== false) {
        return '';
    }
    if ($ruta[0] === '/' || $ruta[0] === '\\' || preg_match('~^[a-zA-Z]:~', $ruta)) {
        return '';
    }
    if (strpos($ruta, '..') !== false) {
        return '';
    }
    if (preg_match('~^https?://~i', $ruta)) {
        return '';
    }

    // Debe vivir dentro de img/
    if (strpos($ruta, 'img/') !== 0) {
        return '';
    }

    // Extensión de la lista blanca.
    if (!preg_match('~\.(jpe?g|png|webp|gif|mp4|mov)$~i', $ruta)) {
        return '';
    }

    return $ruta;
}

/**
 * Borra un archivo de img/uploads/ cuando deja de usarse.
 * Solo toca archivos dentro de esa carpeta: nunca las imágenes originales
 * del diseño que viven en img/ o img/eventos/.
 */
function borrar_subida_si_procede($ruta)
{
    $ruta = ruta_existente_segura($ruta);

    if ($ruta === '' || strpos($ruta, UPLOADS_REL . '/') !== 0) {
        return false;
    }

    $completa = SITIO_DIR . '/' . $ruta;
    $real = realpath($completa);
    $baseReal = realpath(UPLOADS_DIR);

    // Comprobación final: el archivo resuelto debe estar dentro de uploads.
    if ($real === false || $baseReal === false || strpos($real, $baseReal) !== 0) {
        return false;
    }

    return @unlink($real);
}
