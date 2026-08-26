<?php
/**
 * ===========================================================================
 *  URUAPLAN — RECEPCIÓN DEL FORMULARIO DE EVENTOS
 * ===========================================================================
 *  Sustituye al antiguo "mailto:", que solo funcionaba si el visitante
 *  tenía un programa de correo instalado y configurado en su computadora
 *  (Outlook, Thunderbird...). En la mayoría de las máquinas no pasaba nada
 *  al darle a enviar, y la foto nunca podía viajar adjunta.
 *
 *  Ahora el correo lo manda el servidor, con la imagen adjunta de verdad.
 *
 *  Responde de dos formas, según quién pregunte:
 *    - JSON, si lo llama el JavaScript de la página (caso normal).
 *    - Redirección a index.php#contacto con un aviso, si el visitante
 *      tiene el JavaScript desactivado.
 *
 *  DESDE LA MIGRACIÓN A BASE DE DATOS
 *  ----------------------------------
 *  La solicitud se GUARDA antes de mandar nada. El correo pasa a ser un
 *  aviso ("ha llegado algo nuevo"), no el registro: aunque el servidor de
 *  correo esté caído o el mensaje acabe en spam, la solicitud está en el
 *  panel esperando a que alguien la revise. Antes, si el correo fallaba,
 *  el evento se perdía y nadie se enteraba.
 * ===========================================================================
 */

require_once __DIR__ . '/cheve/includes/config.php';
require_once __DIR__ . '/cheve/includes/funciones.php';
require_once __DIR__ . '/cheve/includes/bd.php';
require_once __DIR__ . '/cheve/includes/antispam.php';
require_once __DIR__ . '/cheve/includes/flyers.php';
require_once __DIR__ . '/cheve/includes/correo.php';

// ---------------------------------------------------------------------------
// RESPUESTA
// ---------------------------------------------------------------------------

/**
 * ¿La petición viene del fetch() de main.js o de un formulario normal?
 */
function pide_json()
{
    if (
        isset($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'fetch'
    ) {
        return true;
    }

    return isset($_SERVER['HTTP_ACCEPT'])
        && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;
}

/**
 * Contesta y termina. Nunca se devuelve el detalle técnico del fallo SMTP
 * al visitante: eso va al log del servidor, no a la pantalla.
 */
function responder($ok, $mensaje, $codigo = 200)
{
    if (pide_json()) {
        http_response_code($codigo);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(
            array('ok' => $ok, 'mensaje' => $mensaje),
            JSON_UNESCAPED_UNICODE
        );
        exit;
    }

    $destino = 'index.php?envio=' . ($ok ? 'ok' : 'error') . '#contacto';

    header('Location: ' . $destino, true, 303);
    exit;
}

// ---------------------------------------------------------------------------
// LECTURA Y VALIDACIÓN DE CAMPOS
// ---------------------------------------------------------------------------

/**
 * Saca un campo de texto del POST, ya limpio y recortado.
 * Se quitan los caracteres de control (incluidos los saltos de línea en los
 * campos de una sola línea) porque acaban dentro de cabeceras de correo.
 */
function campo($nombre, $maximo, $multilinea = false)
{
    $valor = isset($_POST[$nombre]) ? $_POST[$nombre] : '';
    return sanear_cadena($valor, $maximo, $multilinea);
}

// ---------------------------------------------------------------------------
// FLUJO PRINCIPAL
// ---------------------------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(false, 'Esta dirección solo acepta el envío del formulario.', 405);
}

$cfg = isset($GLOBALS['CORREO']) ? $GLOBALS['CORREO'] : array();
$maxPorHora = (int) valor_cfg($cfg, 'max_por_hora_ip', 5);
$segundosMinimos = (int) valor_cfg($cfg, 'segundos_minimos', 4);

// --- 1. Trampa para robots -------------------------------------------------
// Se contesta "ok" a propósito: si el robot supiera que lo detectamos,
// probaría otra cosa. Simplemente el correo no se manda.
if (isset($_POST[CAMPO_TRAMPA]) && trim((string) $_POST[CAMPO_TRAMPA]) !== '') {
    responder(true, '¡Solicitud recibida! Te contactaremos pronto.');
}

// --- 2. Token de la página -------------------------------------------------
$errorToken = token_formulario_error(
    isset($_POST['token']) ? $_POST['token'] : '',
    $segundosMinimos
);

if ($errorToken !== '') {
    responder(false, $errorToken, 400);
}

// --- 3. Campos obligatorios ------------------------------------------------
$datos = array(
    'evento' => campo('evento', 80),
    'subtitulo' => campo('subtitulo', 90),
    'fecha' => campo('fecha', 10),
    'hora' => campo('hora', 5),
    'contacto' => campo('contacto', 60),
    'precio' => campo('precio', 60),
    'ubicacion' => campo('ubicacion', 120),
    'descripcion' => campo('descripcion', 250, true),
    'comentarios' => campo('comentarios', 600, true),
);

$etiquetas = array(
    'evento' => 'Nombre del evento',
    'subtitulo' => 'Subtítulo',
    'fecha' => 'Fecha',
    'hora' => 'Hora',
    'contacto' => 'Contacto',
    'precio' => 'Precio',
    'ubicacion' => 'Ubicación',
    'descripcion' => 'Descripción',
);

$faltan = array();
foreach ($etiquetas as $clave => $etiqueta) {
    if ($datos[$clave] === '') {
        $faltan[] = $etiqueta;
    }
}

if ($faltan) {
    responder(false, 'Faltan campos por llenar: ' . implode(', ', $faltan) . '.', 422);
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $datos['fecha'])) {
    responder(false, 'La fecha no tiene un formato válido.', 422);
}

if (!preg_match('/^\d{2}:\d{2}$/', $datos['hora'])) {
    responder(false, 'La hora no tiene un formato válido.', 422);
}

$foto = isset($_FILES['foto']) ? $_FILES['foto'] : array();
$val = validar_imagen_subida($foto, false, MAX_BYTES_IMAGEN);

if (!$val['ok']) {
    responder(false, $val['vacio'] ? 'Falta la foto principal del evento.' : $val['error'], 422);
}

// Extraemos $mime y $dimensiones para que las use el código de abajo
$mime = $val['mime'];
$dimensiones = $val['dimensiones'];


$contenidoFoto = @file_get_contents($foto['tmp_name']);
if ($contenidoFoto === false || $contenidoFoto === '') {
    responder(false, 'No se pudo leer la foto en el servidor. Inténtalo de nuevo.', 500);
}

// Nombre del adjunto a partir del título del evento. Los acentos se cambian
// por su letra sin tilde en vez de borrarse: si no, "Exposición de Óleo"
// acabaría llamándose "exposici-n-de-leo.jpg".
$sinAcentos = strtr(
    $datos['evento'],
    array(
        'á' => 'a',
        'é' => 'e',
        'í' => 'i',
        'ó' => 'o',
        'ú' => 'u',
        'ü' => 'u',
        'ñ' => 'n',
        'Á' => 'A',
        'É' => 'E',
        'Í' => 'I',
        'Ó' => 'O',
        'Ú' => 'U',
        'Ü' => 'U',
        'Ñ' => 'N',
        'à' => 'a',
        'è' => 'e',
        'ì' => 'i',
        'ò' => 'o',
        'ù' => 'u',
        'ç' => 'c',
    )
);

$nombreFoto = 'evento-' . preg_replace('/[^a-z0-9]+/', '-', strtolower($sinAcentos));
$nombreFoto = trim($nombreFoto, '-');

if ($nombreFoto === 'evento' || $nombreFoto === '') {
    $nombreFoto = 'evento';
}

$nombreFoto .= '.' . $GLOBALS['MIME_IMAGEN'][$mime];

// --- 4. Subida opcional del logo del organizador --------------------------
$logoOrgGuardado = '';
if (isset($_FILES['logo_organizador']) && $_FILES['logo_organizador']['error'] !== UPLOAD_ERR_NO_FILE) {
    $valLogo = validar_imagen_subida($_FILES['logo_organizador'], false, MAX_BYTES_IMAGEN);
    if ($valLogo['ok']) {
        $contenidoLogo = @file_get_contents($_FILES['logo_organizador']['tmp_name']);
        if ($contenidoLogo !== false && $contenidoLogo !== '') {
            $logoOrgGuardado = flyer_guardar_imagen($contenidoLogo, $valLogo['mime']);
        }
    }
}

// --- 5. Límite por conexión ------------------------------------------------
// Se comprueba al final, cuando ya sabemos que la solicitud es legítima:
// así un formulario mal llenado no gasta el cupo de la persona.
try {
    $errorLimite = limite_envios_error($maxPorHora);
    if ($errorLimite !== '') {
        responder(false, $errorLimite, 429);
    }
} catch (BdError $e) {
    // Sin base de datos no se puede contar, pero tampoco se va a castigar a
    // quien está enviando de buena fe: se sigue adelante y queda anotado.
    error_log('[uruaplan] No se pudo comprobar el límite por IP: ' . $e->getMessage());
}

// --- 6. Se guarda la solicitud ---------------------------------------------
// Esto va ANTES del correo a propósito: es lo que garantiza que ninguna
// solicitud se pierda aunque el envío falle después.
$idFlyer = 0;
$errorGuardar = '';
$nombreGuardado = '';

try {
    $nombreGuardado = flyer_guardar_imagen($contenidoFoto, $mime);

    if ($nombreGuardado === '') {
        $errorGuardar = 'No se pudo guardar la foto en el servidor.';
    } else {
        $idFlyer = flyer_crear(array(
            'evento' => $datos['evento'],
            'subtitulo' => $datos['subtitulo'],
            'fecha' => $datos['fecha'],
            'hora' => $datos['hora'],
            'ubicacion' => $datos['ubicacion'],
            'precio' => $datos['precio'],
            'contacto' => $datos['contacto'],
            'descripcion' => $datos['descripcion'],
            'comentarios' => $datos['comentarios'],
            'geo_coords' => campo('geo_coords', 60),
            'imagen' => $nombreGuardado,
            'imagen_mime' => $mime,
            'imagen_ancho' => $dimensiones[0],
            'imagen_alto' => $dimensiones[1],
            'logo_organizador' => $logoOrgGuardado,
            'imagen_bytes' => $foto['size'],
        ));
    }
} catch (BdError $e) {
    $errorGuardar = $e->getMessage();
}

if ($errorGuardar !== '') {
    // La foto se escribe antes de insertar la fila, así que si el insert
    // falla hay que retirarla: si no, cada error dejaría un archivo suelto
    // en data/flyers/ al que no apunta ningún flyer.
    if ($nombreGuardado !== '') {
        flyer_borrar_imagen($nombreGuardado);
    }

    // No se corta: todavía queda el correo, que lleva la foto adjunta y
    // todos los datos. Peor sería devolverle un error a alguien que llenó
    // el formulario bien.
    error_log('[uruaplan] La solicitud no se pudo guardar en la base de datos: ' . $errorGuardar);
}

// ---------------------------------------------------------------------------
// ARMADO Y ENVÍO DEL CORREO
// ---------------------------------------------------------------------------

$separador = str_repeat('=', 54);

$cuerpo = "SOLICITUD DE PUBLICACIÓN DE EVENTO — URUAPLAN\n"
    . $separador . "\n\n"
    . "Nombre del evento : " . $datos['evento'] . "\n"
    . "Subtítulo         : " . $datos['subtitulo'] . "\n"
    . "Fecha             : " . $datos['fecha'] . "\n"
    . "Hora              : " . $datos['hora'] . "\n"
    . "Ubicación         : " . $datos['ubicacion'] . "\n"
    . "Precio            : " . $datos['precio'] . "\n"
    . "Contacto          : " . $datos['contacto'] . "\n\n"
    . "DESCRIPCIÓN\n"
    . $datos['descripcion'] . "\n\n";

if ($datos['comentarios'] !== '') {
    $cuerpo .= "COMENTARIOS ADICIONALES (no salen en el flyer)\n"
        . $datos['comentarios'] . "\n\n";
}

$cuerpo .= $separador . "\n"
    . "La foto principal va adjunta a este correo: " . $nombreFoto . "\n"
    . "(" . $dimensiones[0] . "x" . $dimensiones[1] . " px, "
    . round($foto['size'] / 1024) . " KB)\n\n"
    . "Enviado el " . date('d/m/Y \a \l\a\s H:i') . "\n";

// El correo deja de ser el registro y pasa a ser un aviso: lo importante
// es el enlace al panel, donde el flyer ya está esperando.
if ($idFlyer > 0) {
    $base = url_base_panel();

    $cuerpo .= "\n" . $separador . "\n"
        . "Esta solicitud ya está guardada en el panel como el flyer #" . $idFlyer . ".\n"
        . "Ahí puedes aceptarla, rechazarla, corregir los datos o publicarla en la web:\n"
        . ($base !== '' ? $base . "/cheve/flyer.php?id=" . $idFlyer . "\n" : "uruaplan.com/cheve/flyers.php\n");
} else {
    $cuerpo .= "\n" . $separador . "\n"
        . "⚠ ATENCIÓN: esta solicitud NO se pudo guardar en el panel (la base de\n"
        . "datos no respondió). Este correo es el único registro que queda de ella,\n"
        . "así que no lo borres hasta haberla atendido.\n";
}

// Si en el campo "Contacto" pusieron un correo, el botón Responder de Gmail
// escribe directamente a esa persona en lugar de a nosotros mismos.
$responderA = '';
if (
    preg_match('/[\w.+-]+@[\w-]+\.[\w.-]+/', $datos['contacto'], $coincidencia)
    && filter_var($coincidencia[0], FILTER_VALIDATE_EMAIL)
) {
    $responderA = $coincidencia[0];
}

$resultado = enviar_correo(array(
    'para' => correo_destino_formulario(),
    'asunto' => '[Evento Uruaplan] ' . $datos['evento'],
    'cuerpo' => $cuerpo,
    'responder_a' => $responderA,
    'adjuntos' => array(
        array('nombre' => $nombreFoto, 'tipo' => $mime, 'datos' => $contenidoFoto),
    ),
));

if (!$resultado['ok']) {
    // El detalle técnico va al log de errores de PHP (visible en cPanel),
    // no a la pantalla del visitante.
    error_log('[uruaplan] Fallo al enviar el aviso del formulario de eventos: ' . $resultado['error']);

    // Si la solicitud SÍ quedó guardada, para el visitante todo salió bien:
    // su evento está en la bandeja del panel esperando revisión. Que el aviso
    // por correo no saliera es un problema nuestro, no suyo.
    if ($idFlyer > 0) {
        responder(true, '¡Solicitud enviada! Recibimos tu evento y te contactaremos pronto.');
    }

    responder(
        false,
        'No pudimos registrar tu solicitud por un problema del servidor. '
        . 'Escríbenos por WhatsApp y lo resolvemos.',
        500
    );
}

// El aviso salió. Queda anotado en el flyer, para saber de un vistazo en el
// panel si alguien recibió el correo o si hay que revisar la configuración.
if ($idFlyer > 0) {
    try {
        bd_ejecutar('UPDATE flyers SET correo_ok = 1 WHERE id = ?', array($idFlyer));
    } catch (BdError $e) {
        error_log('[uruaplan] No se pudo marcar el correo del flyer #' . $idFlyer . ': ' . $e->getMessage());
    }
}

responder(true, '¡Solicitud enviada! Recibimos tu evento y te contactaremos pronto.');
