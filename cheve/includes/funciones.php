<?php
/**
 * Funciones compartidas: lectura/escritura de JSON, escapado y rutas.
 *
 * Estas funciones las usan TANTO el panel como la página pública,
 * por eso no dependen de que haya sesión iniciada.
 */

require_once __DIR__ . '/config.php';

// ---------------------------------------------------------------------------
// ESCAPADO / SALIDA
// ---------------------------------------------------------------------------

/**
 * Escapa texto para imprimirlo en HTML. Se usa en CADA valor que sale del JSON.
 * Es la defensa principal contra XSS: el contenido se guarda tal cual lo
 * escribió el editor y se neutraliza al momento de renderizar.
 */
function e($texto)
{
    return htmlspecialchars((string) $texto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Sanitiza y normaliza una cadena de texto (elimina caracteres de control,
 * normaliza saltos de línea y acota su longitud máxima).
 *
 * @param mixed $valor       El texto a sanitizar.
 * @param int   $maximo      Longitud máxima permitida (0 para ilimitado).
 * @param bool  $multilinea  Si es false, convierte los saltos de línea en espacios.
 *
 * @return string
 */
function sanear_cadena($valor, $maximo = 0, $multilinea = false)
{
    if (!is_scalar($valor)) {
        return '';
    }

    $texto = (string) $valor;
    $texto = str_replace(array("\r\n", "\r"), "\n", $texto);

    if (!$multilinea) {
        $texto = str_replace("\n", ' ', $texto);
    }

    $limpio = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $texto);
    if ($limpio !== null) {
        $texto = $limpio;
    }

    $limpio = preg_replace('/[ \t]+/', ' ', $texto);
    $texto = trim($limpio !== null ? $limpio : $texto);

    if ($maximo > 0) {
        if (function_exists('mb_substr')) {
            return mb_substr($texto, 0, $maximo, 'UTF-8');
        }
        return substr($texto, 0, $maximo);
    }

    return $texto;
}

/**
 * Escapa texto para incrustarlo dentro de un atributo href/src ya construido
 * (por ejemplo dentro de una URL de wa.me o de un mailto).
 */
function eu($texto)
{
    return rawurlencode((string) $texto);
}

/**
 * Convierte una ruta de archivo guardada en el JSON ("img/eventos/Foto 1.jpg")
 * en una URL válida, codificando espacios y paréntesis segmento por segmento.
 *
 * @param string $ruta    Ruta relativa a la raíz del sitio.
 * @param string $prefijo "" desde la raíz, "../" desde dentro de /cheve/.
 */
function url_activo($ruta, $prefijo = '')
{
    $ruta = ltrim((string) $ruta, '/');
    if ($ruta === '') {
        return '';
    }
    $partes = array_map('rawurlencode', explode('/', $ruta));
    return $prefijo . implode('/', $partes);
}

/**
 * Añade la fecha del archivo a la URL de un CSS o un JS.
 *
 *   <link href="<?= versionado('css/styles.css', SITIO_DIR . '/css/styles.css') ?>">
 *   ->  css/styles.css?v=1786071234
 *
 * Así, cuando se toca la hoja de estilos, el navegador descarga la nueva en
 * lugar de seguir con la que tenía en caché. Sin esto un cambio de diseño
 * puede tardar días en verse.
 *
 * @param string $url       Ruta tal cual va en el HTML (relativa).
 * @param string $enDisco   Ruta absoluta del archivo, para leer su fecha.
 */
function versionado($url, $enDisco)
{
    $version = is_file($enDisco) ? filemtime($enDisco) : time();

    return e($url . '?v=' . $version);
}

/**
 * Solo dígitos: normaliza un teléfono para usarlo en enlaces wa.me.
 */
function solo_digitos($texto)
{
    return preg_replace('/\D+/', '', (string) $texto);
}

/**
 * Dirección desde la que llega la petición.
 *
 * Vive aquí, y no en auth.php, porque la usan los dos lados: el panel para
 * contar intentos de login y la página pública para el límite antispam y
 * para anotar de dónde vino cada flyer. Ponerla en auth.php dejaba a la
 * parte pública sin ella.
 *
 * No se hace caso a cabeceras tipo X-Forwarded-For: las manda el cliente y
 * se pueden inventar, así que un robot podría saltarse el límite por IP
 * cambiándolas en cada envío.
 */
function ip_cliente()
{
    return isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : 'desconocida';
}




// ---------------------------------------------------------------------------
// LECTURA / ESCRITURA DE JSON
// ---------------------------------------------------------------------------

/**
 * Lee un archivo JSON y lo devuelve como array asociativo.
 * Si el archivo no existe o está corrupto devuelve $porDefecto,
 * de modo que la página pública nunca se rompe por un JSON malo.
 */
function leer_json($ruta, $porDefecto = array())
{
    if (!is_file($ruta) || !is_readable($ruta)) {
        return $porDefecto;
    }

    $crudo = file_get_contents($ruta);
    if ($crudo === false || trim($crudo) === '') {
        return $porDefecto;
    }

    $datos = json_decode($crudo, true);
    if (!is_array($datos)) {
        return $porDefecto;
    }

    return $datos;
}

/**
 * Guarda un array como JSON de forma atómica.
 *
 * Escribe primero en un archivo temporal y luego lo renombra: así, si el
 * proceso muere a mitad de la escritura, el JSON original queda intacto
 * en lugar de quedar truncado.
 *
 * @return bool true si se guardó correctamente.
 */
function guardar_json($ruta, array $datos)
{
    $dir = dirname($ruta);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    $banderas = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    if (defined('JSON_PRETTY_PRINT')) {
        $banderas |= JSON_PRETTY_PRINT;
    }

    $json = json_encode($datos, $banderas);
    if ($json === false) {
        return false;
    }

    $temporal = $ruta . '.tmp' . bin2hex(random_bytes(4));
    if (@file_put_contents($temporal, $json, LOCK_EX) === false) {
        return false;
    }

    @chmod($temporal, 0644);

    if (!@rename($temporal, $ruta)) {
        @unlink($temporal);
        return false;
    }

    // Limpia la caché de opcode/stat por si el hosting la tiene agresiva.
    clearstatcache(true, $ruta);

    return true;
}

// ---------------------------------------------------------------------------
// ACCESO AL CONTENIDO EDITABLE
// ---------------------------------------------------------------------------

/**
 * Carga el contenido editable completo. Se cachea en memoria dentro de la
 * misma petición, así que llamarla cien veces cuesta lo mismo que una.
 *
 * Los datos viven en MySQL desde la migración; contenido.php se encarga de
 * armarlos con la misma forma que tenían en el viejo contenido.json, para
 * que ni index.php ni el panel noten la diferencia.
 *
 * El require va aquí dentro y no arriba a propósito: contenido.php necesita
 * leer_json()/guardar_json() de este mismo archivo, y cargarlo en la cabecera
 * dejaría a los dos esperándose el uno al otro.
 */
function leer_contenido($forzar = false)
{
    require_once __DIR__ . '/contenido.php';

    return contenido_cargar($forzar);
}

/**
 * Devuelve un campo de texto del contenido, con valor de respaldo.
 *
 *   c('hero', 'titulo')  =>  $contenido['hero']['titulo']
 */
function c($seccion, $campo, $porDefecto = '')
{
    $contenido = leer_contenido();

    if (isset($contenido[$seccion][$campo]) && is_scalar($contenido[$seccion][$campo])) {
        $valor = (string) $contenido[$seccion][$campo];
        if ($valor !== '') {
            return $valor;
        }
    }

    return $porDefecto;
}

/**
 * Igual que c() pero ya escapado para HTML. Es el atajo que más se usa
 * en la página pública:  <h1><?= ce('hero','titulo') ?></h1>
 */
function ce($seccion, $campo, $porDefecto = '')
{
    return e(c($seccion, $campo, $porDefecto));
}

/**
 * Devuelve una lista (galería, fondos del hero...) como array de filas.
 * Siempre devuelve un array, aunque el JSON no tenga la clave.
 */
function lista($seccion, $clave)
{
    $contenido = leer_contenido();

    if (!isset($contenido[$seccion][$clave]) || !is_array($contenido[$seccion][$clave])) {
        return array();
    }

    // array_values garantiza índices 0..n aunque el JSON traiga huecos.
    return array_values(array_filter($contenido[$seccion][$clave], 'is_array'));
}

/**
 * Convierte un texto del panel en HTML seguro con formato mínimo.
 *
 * El orden importa y es lo que hace esto seguro:
 *   1. Se escapa TODO el texto con htmlspecialchars(). A partir de aquí
 *      es imposible que el editor inyecte una etiqueta o un script.
 *   2. Solo después se sustituyen marcadores conocidos ({handle},
 *      {corazon}...) por fragmentos de HTML que escribimos nosotros.
 *   3. Se convierte **negrita** en <strong>. Como el contenido ya está
 *      escapado, dentro de la negrita no puede colarse nada peligroso.
 *
 * @param array $tokens  array('handle' => '<strong>@X</strong>')
 */
function con_tokens($texto, array $tokens = array())
{
    $salida = e($texto);

    foreach ($tokens as $nombre => $html) {
        $salida = str_replace('{' . $nombre . '}', $html, $salida);
    }

    // **texto** -> <strong>texto</strong>
    $salida = preg_replace('/\*\*(.+?)\*\*/us', '<strong>$1</strong>', $salida);

    return $salida === null ? e($texto) : $salida;
}

/**
 * URL lista para usar en un src/href, a partir de un campo de imagen del JSON.
 */
function cimg($seccion, $campo, $porDefecto = '', $prefijo = '')
{
    return e(url_activo(c($seccion, $campo, $porDefecto), $prefijo));
}

// ---------------------------------------------------------------------------
// AUTOCOMPROBACIÓN DE SEGURIDAD
// ---------------------------------------------------------------------------

/**
 * URL pública de la carpeta del panel, deducida de la petición actual.
 */
function url_base_panel()
{
    $host = isset($_SERVER['HTTP_HOST']) ? (string) $_SERVER['HTTP_HOST'] : '';

    // El Host lo manda el cliente: solo se acepta si parece un host normal.
    if ($host === '' || !preg_match('~^[A-Za-z0-9.\-]+(:\d+)?$~', $host)) {
        return '';
    }

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');

    return ($https ? 'https' : 'http') . '://' . $host . $dir;
}

/**
 * Comprueba, pidiéndolo por HTTP como lo haría cualquiera desde fuera,
 * si el contenido de cheve/data/ es descargable públicamente.
 *
 * Es la verificación de que el .htaccess de esa carpeta está haciendo su
 * trabajo. Desde la migración a MySQL las contraseñas ya no viven ahí, pero
 * la carpeta sigue guardando cosas que no deben verse: la clave con la que
 * se firman los tokens del formulario y, sobre todo, las fotos de los flyers
 * que aún están pendientes o que se rechazaron.
 *
 * Se prueba con el espejo del contenido porque es el único archivo que
 * siempre está ahí; si ese se descarga, sus vecinos también.
 *
 * @return bool|null  true = EXPUESTO, false = protegido, null = no se pudo comprobar.
 */
function data_expuesta_publicamente()
{
    // El servidor de pruebas de PHP (php -S) no lee archivos .htaccess, así
    // que la comprobación siempre daría positivo. Además suele ser monohilo:
    // pedirse una página a sí mismo lo bloquearía. En local no se comprueba.
    if (php_sapi_name() === 'cli-server') {
        return null;
    }

    $base = url_base_panel();
    if ($base === '') {
        return null;
    }

    $url  = $base . '/data/contenido-espejo.json';
    $body = null;
    $codigo = 0;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 4,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => false,  // certificados internos del hosting
            CURLOPT_RANGE          => '0-512',
        ));
        $body   = curl_exec($ch);
        $codigo = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    } elseif (ini_get('allow_url_fopen')) {
        $ctx = stream_context_create(array(
            'http' => array('timeout' => 4, 'ignore_errors' => true),
            'ssl'  => array('verify_peer' => false, 'verify_peer_name' => false),
        ));
        $body = @file_get_contents($url, false, $ctx, 0, 512);

        if (isset($http_response_header[0]) && preg_match('~\s(\d{3})\s~', $http_response_header[0], $m)) {
            $codigo = (int) $m[1];
        }
    } else {
        return null;
    }

    if ($body === false || $body === null || $codigo === 0) {
        return null;
    }

    // Está expuesto solo si respondió 200 Y devolvió el contenido real.
    return ($codigo >= 200 && $codigo < 300) && strpos($body, '{') !== false;
}
