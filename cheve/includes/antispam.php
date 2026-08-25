<?php
/**
 * ===========================================================================
 *  ANTISPAM DEL FORMULARIO PÚBLICO
 * ===========================================================================
 *  El formulario de eventos lo usa gente anónima, así que no hay sesión ni
 *  token CSRF como en el panel. En su lugar se apilan tres defensas baratas:
 *
 *    1. TOKEN FIRMADO. Cada carga de la página genera un token con la hora
 *       dentro, firmado con HMAC. Un robot que haga POST directo contra
 *       enviar-evento.php sin pasar por la página no puede fabricarlo.
 *       Además caduca a las 2 horas y no vale si se envió demasiado rápido.
 *
 *    2. HONEYPOT. Un campo invisible que las personas nunca ven. Los robots
 *       rellenan todo lo que encuentran; si viene con algo, es un robot.
 *
 *    3. LÍMITE POR IP. Máximo N envíos por hora desde la misma conexión,
 *       para que nadie use el formulario como máquina de mandar spam a
 *       través de nuestra cuenta de Gmail.
 * ===========================================================================
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/funciones.php';
require_once __DIR__ . '/bd.php';

// Respaldo de la clave de firma para cuando MySQL no responda. Es la única
// cosa del antispam que sigue teniendo copia en disco, y por un motivo
// concreto: sin ella la página pública no podría ni pintar el formulario.
define('ARCHIVO_SECRETO', DATA_DIR . '/secreto.json');

// Nombre del campo trampa. Suena a algo que un robot querría rellenar.
define('CAMPO_TRAMPA', 'sitio_web');

/**
 * Clave secreta con la que se firman los tokens del formulario.
 *
 * Se genera sola la primera vez y se guarda en la tabla `ajustes`, así el
 * editor no tiene que configurar nada. Se mantiene además una copia en
 * data/secreto.json: si la base se cae, la página pública sigue pudiendo
 * firmar y verificar tokens en lugar de rechazar todos los envíos.
 */
function secreto_formulario()
{
    static $cache = null;

    if ($cache !== null) {
        return $cache;
    }

    try {
        $guardada = bd_valor('SELECT valor FROM ajustes WHERE clave = ?', array('secreto_formulario'));

        if (is_string($guardada) && strlen($guardada) >= 32) {
            $cache = $guardada;
            secreto_respaldar($cache);
            return $cache;
        }

        $cache = bin2hex(random_bytes(32));

        // INSERT IGNORE por si dos visitas simultáneas la generan a la vez:
        // gana la primera y la segunda se queda con la suya en memoria solo
        // durante esta petición, que no rompe nada.
        bd_ejecutar(
            'INSERT IGNORE INTO ajustes (clave, valor) VALUES (?, ?)',
            array('secreto_formulario', $cache)
        );

        $cache = (string) bd_valor(
            'SELECT valor FROM ajustes WHERE clave = ?',
            array('secreto_formulario'),
            $cache
        );

        secreto_respaldar($cache);

        return $cache;

    } catch (BdError $e) {
        // Sin base: se tira del respaldo en disco.
        $datos = leer_json(ARCHIVO_SECRETO, array());

        if (!empty($datos['clave']) && is_string($datos['clave']) && strlen($datos['clave']) >= 32) {
            $cache = $datos['clave'];
            return $cache;
        }

        // Ni base ni respaldo: una clave de emergencia para esta petición.
        // El formulario no va a poder guardar nada de todos modos.
        $cache = bin2hex(random_bytes(32));
        return $cache;
    }
}

/**
 * Mantiene al día la copia en disco de la clave de firma.
 */
function secreto_respaldar($clave)
{
    $datos = leer_json(ARCHIVO_SECRETO, array());

    if (isset($datos['clave']) && $datos['clave'] === $clave) {
        return;
    }

    guardar_json(ARCHIVO_SECRETO, array('clave' => $clave, 'creado' => date('c')));
}

/**
 * Genera el token que se incrusta en el formulario.
 * Formato: "<marca de tiempo>.<firma>"
 */
function token_formulario()
{
    $ahora = time();
    $ip    = ip_cliente();
    $firma = hash_hmac('sha256', $ahora . '|' . $ip, secreto_formulario());

    return $ahora . '.' . $firma;
}

/**
 * Comprueba el token recibido.
 *
 * @return string  '' si es válido, o el motivo del rechazo.
 */
function token_formulario_error($token, $segundosMinimos = 4)
{
    $token = (string) $token;

    if (strpos($token, '.') === false) {
        return 'La página lleva demasiado tiempo abierta o se recargó de forma rara. Actualiza e inténtalo otra vez.';
    }

    list($marca, $firma) = explode('.', $token, 2);

    if (!ctype_digit($marca)) {
        return 'La solicitud llegó con un identificador inválido. Recarga la página e inténtalo de nuevo.';
    }

    $ip       = ip_cliente();
    $esperada = hash_hmac('sha256', $marca . '|' . $ip, secreto_formulario());

    if (!hash_equals($esperada, $firma)) {
        return 'La solicitud no se pudo verificar. Recarga la página e inténtalo de nuevo.';
    }

    $transcurrido = time() - (int) $marca;

    if ($transcurrido < $segundosMinimos) {
        return 'El formulario se envió demasiado rápido. Si no eres un robot, espera un momento y vuelve a darle a enviar.';
    }

    if ($transcurrido > 7200) {
        return 'La página llevaba más de dos horas abierta. Recárgala y vuelve a enviar el formulario.';
    }

    return '';
}

/**
 * Imprime los dos campos ocultos que debe llevar el formulario:
 * el token y la trampa para robots.
 */
function campos_antispam()
{
    echo '<input type="hidden" name="token" value="'
        . htmlspecialchars(token_formulario(), ENT_QUOTES, 'UTF-8') . '">';

    // aria-hidden + tabindex="-1" para que los lectores de pantalla y el
    // tabulador lo ignoren: es invisible para las personas, no un obstáculo.
    echo '<div class="campo-trampa" aria-hidden="true">'
        . '<label for="' . CAMPO_TRAMPA . '">No rellenar este campo</label>'
        . '<input type="text" id="' . CAMPO_TRAMPA . '" name="' . CAMPO_TRAMPA
        . '" tabindex="-1" autocomplete="off">'
        . '</div>';
}

/**
 * Comprueba y anota el límite de envíos por IP.
 *
 * A diferencia de intentos.json (que cuenta fallos), aquí se cuentan los
 * envíos ACEPTADOS dentro de la última hora.
 *
 * @return string  '' si puede enviar, o el mensaje de rechazo.
 */
function limite_envios_error($maxPorHora = 5)
{
    $ip = ip_cliente();

    // Se guarda el hash de la IP y no la IP: para contar sirve igual y no
    // deja en la base un registro de quién entró a la página.
    $clave  = hash('sha256', $ip);
    $ahora  = time();
    $limite = $ahora - 3600;

    // De paso se tiran las marcas viejas de todas las IP (probabilidad 1 en 10),
    // para que la tabla no crezca sin control.
    if (random_int(1, 10) === 1) {
        bd_ejecutar('DELETE FROM envios_ip WHERE cuando <= ?', array($limite));
    }

    $recientes = (int) bd_valor(
        'SELECT COUNT(*) FROM envios_ip WHERE ip_hash = ? AND cuando > ?',
        array($clave, $limite),
        0
    );

    if ($recientes >= $maxPorHora) {
        return 'Ya se enviaron ' . $maxPorHora . ' solicitudes desde esta conexión en la última hora. '
            . 'Espera un rato o escríbenos por WhatsApp.';
    }

    bd_ejecutar(
        'INSERT INTO envios_ip (ip_hash, cuando) VALUES (?, ?)',
        array($clave, $ahora)
    );

    return '';
}
