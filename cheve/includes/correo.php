<?php
/**
 * ===========================================================================
 *  ENVÍO DE CORREO POR SMTP — PHP PURO
 * ===========================================================================
 *  Cliente SMTP mínimo pero completo, sin Composer y sin PHPMailer, para
 *  mantener la regla del proyecto: nada de dependencias externas.
 *
 *  Hace lo que necesita el formulario de eventos y nada más:
 *    - Conexión por STARTTLS (puerto 587) o TLS directo (puerto 465).
 *    - Autenticación AUTH LOGIN (la que acepta Gmail con contraseña
 *      de aplicación).
 *    - Mensaje MIME multipart con texto UTF-8 y un adjunto binario.
 *
 *  Por qué no se usa mail() de PHP: en hosting compartido el correo sale
 *  desde el servidor del hosting con un remitente que no coincide con el
 *  dominio, y Gmail lo manda a spam o lo rechaza. Autenticándose contra
 *  Gmail el correo llega a la bandeja de entrada.
 *
 *  Todas las funciones devuelven el error como texto en lugar de lanzar
 *  excepciones, igual que el resto del proyecto.
 * ===========================================================================
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config-correo.php';

// Cortamos si el servidor tarda demasiado, para no dejar al visitante
// mirando una página en blanco.
define('SMTP_TIMEOUT', 20);

// ---------------------------------------------------------------------------
// UTILIDADES DE CABECERAS
// ---------------------------------------------------------------------------

/**
 * Limpia un valor que va a ir en una cabecera de correo.
 *
 * Los saltos de línea son el vector clásico de inyección de cabeceras:
 * si alguien escribe "Pepe\r\nBcc: victima@ejemplo.com" en un campo del
 * formulario, sin esto acabaría enviando copias a donde quisiera.
 */
function correo_valor_seguro($texto)
{
    return trim(str_replace(array("\r", "\n", "\0"), ' ', (string) $texto));
}

/**
 * Codifica una cabecera con acentos según RFC 2047.
 * Si el texto es ASCII puro se deja tal cual, que se lee mejor en los logs.
 */
function correo_cabecera_codificada($texto)
{
    $texto = correo_valor_seguro($texto);

    if ($texto === '' || preg_match('/^[\x20-\x7E]*$/', $texto)) {
        return $texto;
    }

    return '=?UTF-8?B?' . base64_encode($texto) . '?=';
}

/**
 * Arma un "Nombre <correo>" con el nombre ya codificado y entrecomillado.
 */
function correo_buzon($direccion, $nombre = '')
{
    $direccion = correo_valor_seguro($direccion);
    $nombre    = correo_cabecera_codificada($nombre);

    if ($nombre === '') {
        return $direccion;
    }

    return '"' . str_replace('"', '', $nombre) . '" <' . $direccion . '>';
}

/**
 * Deja un nombre de archivo en ASCII para el Content-Disposition.
 * Los nombres con acentos o emojis rompen clientes de correo viejos.
 */
function correo_nombre_adjunto($nombre)
{
    $nombre = (string) $nombre;
    $nombre = preg_replace('~[/\\\\]~', '', $nombre);          // sin rutas
    $nombre = preg_replace('/[^A-Za-z0-9._-]+/', '-', $nombre); // sin rarezas
    $nombre = trim($nombre, '-.');

    if ($nombre === '') {
        $nombre = 'adjunto';
    }

    return substr($nombre, 0, 80);
}

// ---------------------------------------------------------------------------
// CONSTRUCCIÓN DEL MENSAJE MIME
// ---------------------------------------------------------------------------

/**
 * Monta el mensaje completo (cabeceras + cuerpo) listo para el comando DATA.
 *
 * @param array  $m         Datos del mensaje (ver enviar_correo()).
 * @param string $frontera  Separador entre las partes MIME.
 */
function correo_construir_mensaje(array $m, $frontera)
{
    $adjuntos = isset($m['adjuntos']) && is_array($m['adjuntos']) ? $m['adjuntos'] : array();
    $hayPartes = !empty($adjuntos);

    // El dominio del Message-ID: Gmail lo mira, y uno inventado resta puntos.
    $dominio = 'uruaplan.com';
    if (strpos($m['de_correo'], '@') !== false) {
        $partes  = explode('@', $m['de_correo']);
        $dominio = end($partes);
    }

    $cabeceras = array(
        'Date'         => date('r'),
        'From'         => correo_buzon($m['de_correo'], isset($m['de_nombre']) ? $m['de_nombre'] : ''),
        'To'           => correo_valor_seguro($m['para']),
        'Subject'      => correo_cabecera_codificada($m['asunto']),
        'Message-ID'   => '<' . bin2hex(random_bytes(12)) . '@' . $dominio . '>',
        'MIME-Version' => '1.0',
    );

    if (!empty($m['responder_a'])) {
        $cabeceras['Reply-To'] = correo_valor_seguro($m['responder_a']);
    }

    // El cuerpo va siempre en base64: así da igual que lleve acentos,
    // emojis o líneas larguísimas, nunca se pasa del límite de 998
    // caracteres por línea que impone SMTP.
    $cuerpoTexto = chunk_split(base64_encode((string) $m['cuerpo']), 76, "\r\n");

    if (!$hayPartes) {
        $cabeceras['Content-Type']              = 'text/plain; charset=UTF-8';
        $cabeceras['Content-Transfer-Encoding'] = 'base64';
        $cuerpo = $cuerpoTexto;
    } else {
        $cabeceras['Content-Type'] = 'multipart/mixed; boundary="' . $frontera . '"';

        $cuerpo = "Este mensaje va en formato MIME.\r\n\r\n"
            . '--' . $frontera . "\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: base64\r\n\r\n"
            . $cuerpoTexto . "\r\n";

        foreach ($adjuntos as $adjunto) {
            $nombre = correo_nombre_adjunto(isset($adjunto['nombre']) ? $adjunto['nombre'] : 'adjunto');
            $tipo   = correo_valor_seguro(isset($adjunto['tipo']) ? $adjunto['tipo'] : 'application/octet-stream');
            $datos  = isset($adjunto['datos']) ? $adjunto['datos'] : '';

            $cuerpo .= '--' . $frontera . "\r\n"
                . 'Content-Type: ' . $tipo . '; name="' . $nombre . '"' . "\r\n"
                . "Content-Transfer-Encoding: base64\r\n"
                . 'Content-Disposition: attachment; filename="' . $nombre . '"' . "\r\n\r\n"
                . chunk_split(base64_encode($datos), 76, "\r\n") . "\r\n";
        }

        $cuerpo .= '--' . $frontera . "--\r\n";
    }

    $texto = '';
    foreach ($cabeceras as $clave => $valor) {
        $texto .= $clave . ': ' . $valor . "\r\n";
    }

    return $texto . "\r\n" . $cuerpo;
}

// ---------------------------------------------------------------------------
// DIÁLOGO SMTP
// ---------------------------------------------------------------------------

/**
 * Lee una respuesta del servidor.
 *
 * Pueden ser varias líneas: las intermedias llevan guion tras el código
 * ("250-STARTTLS") y la última lleva un espacio ("250 HELP").
 */
function smtp_leer($socket)
{
    $respuesta = '';

    while (($linea = fgets($socket, 1024)) !== false) {
        $respuesta .= $linea;

        // Una línea de continuación tiene guion en la 4ª posición.
        if (strlen($linea) < 4 || $linea[3] !== '-') {
            break;
        }
    }

    return $respuesta;
}

/**
 * Manda un comando y comprueba que el código de respuesta sea el esperado.
 *
 * @param array|null $codigos  Códigos aceptables. null = no esperar respuesta.
 * @param string     $etapa    Nombre legible para el mensaje de error.
 * @param string     $error    Se rellena por referencia si algo falla.
 */
function smtp_comando($socket, $comando, $codigos, $etapa, &$error, $secreto = false)
{
    if ($comando !== null && fwrite($socket, $comando . "\r\n") === false) {
        $error = 'Se cortó la conexión al enviar el comando de ' . $etapa . '.';
        return false;
    }

    if ($codigos === null) {
        return true;
    }

    $respuesta = smtp_leer($socket);

    if ($respuesta === '') {
        $error = 'El servidor de correo cerró la conexión durante: ' . $etapa . '.';
        return false;
    }

    $codigo = (int) substr($respuesta, 0, 3);

    if (!in_array($codigo, $codigos, true)) {
        // El comando nunca se incluye en el error: llevaría la contraseña.
        $error = 'El servidor de correo respondió "' . trim($respuesta)
            . '" durante: ' . $etapa . '.';
        return false;
    }

    return true;
}

// ---------------------------------------------------------------------------
// FUNCIÓN PRINCIPAL
// ---------------------------------------------------------------------------

/**
 * Inicia la conexión de socket con el servidor SMTP.
 */
function smtp_iniciar_conexion($host, $puerto, $modo, &$socket, &$error)
{
    $esquema  = ($modo === 'ssl') ? 'ssl://' : 'tcp://';
    $errNo    = 0;
    $errStr   = '';
    $contexto = stream_context_create(array(
        'ssl' => array(
            'verify_peer'      => true,
            'verify_peer_name' => true,
            'SNI_enabled'      => true,
        ),
    ));

    $socket = @stream_socket_client(
        $esquema . $host . ':' . $puerto,
        $errNo,
        $errStr,
        SMTP_TIMEOUT,
        STREAM_CLIENT_CONNECT,
        $contexto
    );

    if (!$socket) {
        $error = 'No se pudo conectar con ' . $host . ':' . $puerto . '. '
            . 'Puede que el hosting bloquee la salida SMTP: pídele a soporte que abra el puerto '
            . $puerto . ' (' . trim($errStr) . ').';
        return false;
    }

    stream_set_timeout($socket, SMTP_TIMEOUT);
    return true;
}

/**
 * Negocia el cifrado STARTTLS en la conexión SMTP.
 */
function smtp_negociar_tls($socket, $modo, $nombreCliente, &$error)
{
    if ($modo !== 'tls') {
        return true;
    }

    if (!smtp_comando($socket, 'STARTTLS', array(220), 'inicio del cifrado (STARTTLS)', $error)) {
        return false;
    }

    $cripto = STREAM_CRYPTO_METHOD_TLS_CLIENT;
    if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
        $cripto |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
    }
    if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
        $cripto |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
    }

    if (!@stream_socket_enable_crypto($socket, true, $cripto)) {
        $error = 'No se pudo cifrar la conexión con el servidor de correo (STARTTLS falló). '
            . 'Suele indicar que el PHP del hosting no tiene los certificados al día.';
        return false;
    }

    return smtp_comando($socket, 'EHLO ' . $nombreCliente, array(250), 'presentación tras el cifrado', $error);
}

/**
 * Realiza la autenticación SMTP por AUTH LOGIN.
 */
function smtp_autenticar($socket, $usuario, $password, $host, &$error)
{
    $ok = smtp_comando($socket, 'AUTH LOGIN', array(334), 'inicio de sesión', $error)
        && smtp_comando($socket, base64_encode($usuario), array(334), 'envío del usuario', $error)
        && smtp_comando($socket, base64_encode($password), array(235), 'comprobación de la contraseña', $error, true);

    if (!$ok && strpos($error, '535') !== false) {
        if (stripos($host, 'zoho') !== false) {
            $error .= ' Con Zoho esto suele ser una de tres: (1) el servidor debe ser'
                . ' smtppro.zoho.com para direcciones de dominio propio, no smtp.zoho.com;'
                . ' (2) si la cuenta tiene verificación en dos pasos hace falta una'
                . ' contraseña de aplicación, no la normal; (3) el plan gratuito de Zoho'
                . ' no permite enviar por SMTP desde programas externos.';
        } elseif (stripos($host, 'gmail') !== false) {
            $error .= ' Revisa que sea una CONTRASEÑA DE APLICACIÓN de 16 letras'
                . ' (no la contraseña normal de Gmail) y que la verificación en dos pasos'
                . ' esté activada.';
        }
    }

    return $ok;
}

/**
 * Envía la declaración del sobre (MAIL FROM / RCPT TO) y el contenido MIME.
 */
function smtp_enviar_datos_mensaje($socket, array $m, &$error)
{
    $frontera = 'uruaplan_' . bin2hex(random_bytes(12));
    $mensaje  = correo_construir_mensaje($m, $frontera);
    $mensaje  = str_replace("\r\n.", "\r\n..", $mensaje);

    return smtp_comando($socket, 'MAIL FROM:<' . correo_valor_seguro($m['de_correo']) . '>', array(250), 'declaración del remitente', $error)
        && smtp_comando($socket, 'RCPT TO:<' . correo_valor_seguro($m['para']) . '>', array(250, 251), 'declaración del destinatario', $error)
        && smtp_comando($socket, 'DATA', array(354), 'apertura del mensaje', $error)
        && smtp_comando($socket, $mensaje . "\r\n.", array(250), 'entrega del mensaje', $error);
}

/**
 * Envía un correo por SMTP autenticado.
 *
 * @param array $m  array(
 *     'para'        => destinatario,
 *     'asunto'      => string,
 *     'cuerpo'      => texto plano UTF-8,
 *     'responder_a' => (opcional) correo para el botón Responder,
 *     'adjuntos'    => (opcional) array de array('nombre','tipo','datos'),
 * )
 *
 * @return array('ok' => bool, 'error' => string)
 */
function enviar_correo(array $m)
{
    $cfg      = isset($GLOBALS['CORREO']) ? $GLOBALS['CORREO'] : array();
    $password = str_replace(array(' ', "\t"), '', (string) valor_cfg($cfg, 'smtp_password'));
    $usuario  = (string) valor_cfg($cfg, 'smtp_usuario');
    $host     = (string) valor_cfg($cfg, 'smtp_host', 'smtp.gmail.com');
    $puerto   = (int) valor_cfg($cfg, 'smtp_puerto', 587);
    $modo     = strtolower((string) valor_cfg($cfg, 'smtp_seguridad', 'tls'));

    if ($usuario === '' || $password === '') {
        return array(
            'ok'    => false,
            'error' => 'Falta la contraseña de aplicación. Rellena "smtp_password" en cheve/includes/config-correo.php.',
        );
    }

    if (!function_exists('stream_socket_client')) {
        return array(
            'ok'    => false,
            'error' => 'El hosting tiene desactivada la función stream_socket_client, sin ella no se puede enviar correo por SMTP.',
        );
    }

    $m['de_correo'] = (string) valor_cfg($cfg, 'remitente_correo', $usuario);
    $m['de_nombre'] = (string) valor_cfg($cfg, 'remitente_nombre', '');

    $socket = null;
    $error  = '';

    if (!smtp_iniciar_conexion($host, $puerto, $modo, $socket, $error)) {
        return array('ok' => false, 'error' => $error);
    }

    $nombreCliente = isset($_SERVER['SERVER_NAME']) ? preg_replace('/[^A-Za-z0-9.\-]/', '', $_SERVER['SERVER_NAME']) : '';
    if ($nombreCliente === '') {
        $nombreCliente = 'uruaplan.com';
    }

    $ok = smtp_comando($socket, null, array(220), 'saludo inicial', $error)
        && smtp_comando($socket, 'EHLO ' . $nombreCliente, array(250), 'presentación (EHLO)', $error)
        && smtp_negociar_tls($socket, $modo, $nombreCliente, $error)
        && smtp_autenticar($socket, $usuario, $password, $host, $error)
        && smtp_enviar_datos_mensaje($socket, $m, $error);

    $errorCierre = '';
    @smtp_comando($socket, 'QUIT', null, 'cierre', $errorCierre);
    @fclose($socket);

    return array('ok' => $ok, 'error' => $ok ? '' : $error);
}

/**
 * Lee una clave del array de configuración con valor por defecto.
 * (Existe para no repetir isset() quince veces.)
 */
function valor_cfg(array $cfg, $clave, $porDefecto = '')
{
    if (!isset($cfg[$clave]) || $cfg[$clave] === '') {
        return $porDefecto;
    }

    return $cfg[$clave];
}

/**
 * Devuelve el correo al que deben llegar las solicitudes del formulario.
 * Prioridad: lo que diga config-correo.php; si está vacío, lo que haya
 * puesto el editor en el panel.
 */
function correo_destino_formulario()
{
    $cfg     = isset($GLOBALS['CORREO']) ? $GLOBALS['CORREO'] : array();
    $destino = trim((string) valor_cfg($cfg, 'destino'));

    if ($destino === '') {
        require_once __DIR__ . '/funciones.php';
        $destino = trim(c('contacto', 'correo_formulario', c('global', 'correo')));
    }

    // Último recurso: si el panel no tiene nada válido, el correo se manda a
    // la propia cuenta que envía. Así una solicitud nunca se queda sin
    // destinatario por un campo mal borrado.
    if ($destino === '' || !filter_var($destino, FILTER_VALIDATE_EMAIL)) {
        $destino = (string) valor_cfg($cfg, 'smtp_usuario', 'contacto@uruaplan.com');
    }

    return $destino;
}
