<?php
/**
 * Procesamiento del formulario del panel.
 *
 * Principios:
 *  - LISTA BLANCA: solo se guardan claves que existan en el esquema.
 *    Cualquier campo inventado que llegue por POST se ignora.
 *  - El texto se guarda tal cual lo escribió el editor (limpio de caracteres
 *    de control y recortado al máximo permitido). El escapado contra XSS
 *    se hace SIEMPRE al renderizar, con htmlspecialchars().
 *  - Las URL se validan aparte: un enlace "javascript:..." sería un XSS
 *    que htmlspecialchars() no detiene, porque va dentro de un href.
 */

require_once __DIR__ . '/funciones.php';
require_once __DIR__ . '/esquema.php';
require_once __DIR__ . '/subidas.php';
require_once __DIR__ . '/contenido.php';

// ---------------------------------------------------------------------------
// LIMPIEZA DE VALORES
// ---------------------------------------------------------------------------

/**
 * Normaliza un texto que viene del formulario.
 */
function limpiar_texto($valor, $max = 0, $multilinea = false)
{
    return sanear_cadena($valor, $max, $multilinea);
}

/**
 * Valida un enlace. Devuelve '' si el enlace es peligroso o inválido.
 *
 * Se aceptan: cadena vacía, "#", anclas (#seccion), rutas relativas y
 * URLs http/https. Se rechazan javascript:, data:, vbscript: y similares.
 */
function limpiar_url($valor, $max = 200)
{
    $url = limpiar_texto($valor, $max);

    if ($url === '' || $url === '#') {
        return $url;
    }

    // Cualquier esquema que no sea http/https/mailto/tel se descarta.
    if (preg_match('~^\s*([a-z][a-z0-9+.\-]*)\s*:~i', $url, $m)) {
        $esquema = strtolower($m[1]);
        if (!in_array($esquema, array('http', 'https', 'mailto', 'tel'), true)) {
            return '';
        }
        return $url;
    }

    // Ancla interna o ruta relativa: se dejan pasar.
    if ($url[0] === '#' || $url[0] === '/') {
        return $url;
    }

    // "instagram.com/uruaplan" -> "https://instagram.com/uruaplan"
    return 'https://' . $url;
}

/**
 * Valida un correo. Si no es válido devuelve '' para que se conserve el previo.
 */
function limpiar_email($valor, $max = 120)
{
    $correo = limpiar_texto($valor, $max);

    if ($correo === '') {
        return '';
    }

    return filter_var($correo, FILTER_VALIDATE_EMAIL) ? $correo : '';
}

/**
 * Recorta un número al rango permitido. Si no es un número válido
 * devuelve el valor por defecto.
 */
function limpiar_numero($valor, $min, $max, $porDefecto, $decimales = 2)
{
    if (!is_scalar($valor) || !is_numeric($valor)) {
        return $porDefecto;
    }

    $n = (float) $valor;

    if (!is_finite($n)) {
        return $porDefecto;
    }

    if ($n < $min) {
        $n = $min;
    }
    if ($n > $max) {
        $n = $max;
    }

    return round($n, $decimales);
}

/**
 * Aplica la limpieza que corresponda según el tipo declarado en el esquema.
 *
 * @param string $error  Se rellena si el valor se rechazó.
 */
function limpiar_segun_tipo($tipo, $valor, $max, $etiqueta, &$error)
{
    $error = '';

    if ($tipo === 'url') {
        $limpio = limpiar_url($valor, $max > 0 ? $max : 200);
        if ($limpio === '' && limpiar_texto($valor) !== '') {
            $error = '«' . $etiqueta . '»: el enlace no es válido y se dejó como estaba.';
            return null;
        }
        return $limpio;
    }

    if ($tipo === 'email') {
        $limpio = limpiar_email($valor, $max > 0 ? $max : 120);
        if ($limpio === '' && limpiar_texto($valor) !== '') {
            $error = '«' . $etiqueta . '»: el correo no tiene un formato válido y se dejó como estaba.';
            return null;
        }
        return $limpio;
    }

    if ($tipo === 'tel') {
        // Los teléfonos para wa.me solo llevan dígitos.
        return solo_digitos(limpiar_texto($valor, $max));
    }

    if ($tipo === 'icono') {
        // Solo letras, números, guiones y espacios: es una clase CSS.
        $limpio = limpiar_texto($valor, $max > 0 ? $max : 60);
        $limpio = preg_replace('/[^A-Za-z0-9 \-]/', '', $limpio);
        return trim(preg_replace('/\s+/', ' ', $limpio));
    }

    return limpiar_texto($valor, $max);
}

// ---------------------------------------------------------------------------
// GUARDADO
// ---------------------------------------------------------------------------

/**
 * Procesa la actualización de campos simples dentro de una sección.
 */
function guardar_procesar_campos_simples($idSeccion, array $defSeccion, array &$contenido, array $postTexto, array &$errores, array &$huerfanos, array &$nuevosArchivos)
{
    $cambios = 0;
    if (empty($defSeccion['campos']) || !is_array($defSeccion['campos'])) {
        return $cambios;
    }

    foreach ($defSeccion['campos'] as $clave => $def) {
        $tipo     = isset($def['tipo']) ? $def['tipo'] : 'texto';
        $max      = isset($def['max']) ? (int) $def['max'] : 0;
        $etiqueta = isset($def['label']) ? $def['label'] : $clave;
        $actual   = isset($contenido[$idSeccion][$clave]) ? (string) $contenido[$idSeccion][$clave] : '';

        if (es_campo_archivo($tipo)) {
            $resultado = procesar_subida('f~' . $idSeccion . '~' . $clave, $tipo, $idSeccion . '-' . $clave);

            if ($resultado['vacio']) {
                continue;
            }

            if (!$resultado['ok']) {
                $errores[] = '«' . $etiqueta . '»: ' . $resultado['error'];
                continue;
            }

            if ($actual !== '' && $actual !== $resultado['ruta']) {
                $huerfanos[] = $actual;
            }

            $contenido[$idSeccion][$clave] = $resultado['ruta'];
            $nuevosArchivos[] = $resultado['ruta'];
            $cambios++;
            continue;
        }

        if (!isset($postTexto[$idSeccion][$clave])) {
            continue;
        }

        $error = '';
        $nuevo = limpiar_segun_tipo($tipo, $postTexto[$idSeccion][$clave], $max, $etiqueta, $error);

        if ($error !== '') {
            $errores[] = $error;
            continue;
        }

        if ($nuevo !== $actual) {
            $contenido[$idSeccion][$clave] = $nuevo;
            $cambios++;
        }
    }

    return $cambios;
}

/**
 * Procesa la actualización de listas dinámicas dentro de una sección.
 */
function guardar_procesar_listas_dinamicas($idSeccion, array $defSeccion, array &$contenido, array $postListas, array &$errores, array &$huerfanos, array &$nuevosArchivos)
{
    $cambios = 0;
    if (empty($defSeccion['listas']) || !is_array($defSeccion['listas'])) {
        return $cambios;
    }

    foreach ($defSeccion['listas'] as $claveLista => $defLista) {
        $anteriores = isset($contenido[$idSeccion][$claveLista]) && is_array($contenido[$idSeccion][$claveLista])
            ? $contenido[$idSeccion][$claveLista]
            : array();

        if (!isset($postListas[$idSeccion][$claveLista]) || !is_array($postListas[$idSeccion][$claveLista])) {
            continue;
        }

        $enviadas = $postListas[$idSeccion][$claveLista];
        $nuevas   = array();

        $rutasPrevias = array();
        foreach ($anteriores as $filaVieja) {
            if (!is_array($filaVieja)) {
                continue;
            }
            foreach ($defLista['campos'] as $cl => $df) {
                if (es_campo_archivo(isset($df['tipo']) ? $df['tipo'] : 'texto') && !empty($filaVieja[$cl])) {
                    $rutasPrevias[] = (string) $filaVieja[$cl];
                }
            }
        }

        foreach ($enviadas as $indice => $filaEnviada) {
            if (!is_array($filaEnviada)) {
                continue;
            }

            $fila       = array();
            $tieneAlgo  = false;
            $faltaMedia = false;

            foreach ($defLista['campos'] as $clave => $def) {
                $tipo = isset($def['tipo']) ? $def['tipo'] : 'texto';
                $max  = isset($def['max']) ? (int) $def['max'] : 0;

                if ($tipo === 'check') {
                    $fila[$clave] = isset($filaEnviada[$clave])
                        && $filaEnviada[$clave] !== '0'
                        && $filaEnviada[$clave] !== '';
                    continue;
                }

                if ($tipo === 'encuadre') {
                    $fila['pos_x'] = limpiar_numero(
                        isset($filaEnviada['pos_x']) ? $filaEnviada['pos_x'] : 50, 0, 100, 50
                    );
                    $fila['pos_y'] = limpiar_numero(
                        isset($filaEnviada['pos_y']) ? $filaEnviada['pos_y'] : 50, 0, 100, 50
                    );
                    $fila['zoom'] = limpiar_numero(
                        isset($filaEnviada['zoom']) ? $filaEnviada['zoom'] : 1, 1, 3, 1
                    );
                    continue;
                }

                if (es_campo_archivo($tipo)) {
                    $nombreArchivo = 'fl~' . $idSeccion . '~' . $claveLista . '~' . $indice . '~' . $clave;
                    $resultado = procesar_subida($nombreArchivo, $tipo, $idSeccion . '-' . $claveLista);

                    if ($resultado['ok']) {
                        $fila[$clave]     = $resultado['ruta'];
                        $nuevosArchivos[] = $resultado['ruta'];
                        $tieneAlgo        = true;
                        $cambios++;
                    } elseif (!$resultado['vacio']) {
                        $errores[] = 'Fila ' . ((int) $indice + 1) . ' de «' . $defLista['titulo'] . '»: ' . $resultado['error'];
                        $previo = isset($filaEnviada['__actual'][$clave])
                            ? ruta_existente_segura($filaEnviada['__actual'][$clave]) : '';
                        $fila[$clave] = $previo;
                        if ($previo !== '') {
                            $tieneAlgo = true;
                        } else {
                            $faltaMedia = true;
                        }
                    } else {
                        $previo = isset($filaEnviada['__actual'][$clave])
                            ? ruta_existente_segura($filaEnviada['__actual'][$clave]) : '';
                        $fila[$clave] = $previo;
                        if ($previo !== '') {
                            $tieneAlgo = true;
                        } else {
                            $faltaMedia = true;
                        }
                    }
                    continue;
                }

                $error = '';
                $valor = limpiar_segun_tipo(
                    $tipo,
                    isset($filaEnviada[$clave]) ? $filaEnviada[$clave] : '',
                    $max,
                    isset($def['label']) ? $def['label'] : $clave,
                    $error
                );

                $fila[$clave] = $error === '' ? $valor : '';
                if ($fila[$clave] !== '') {
                    $tieneAlgo = true;
                }
            }

            if ($faltaMedia || !$tieneAlgo) {
                continue;
            }

            $nuevas[] = $fila;
        }

        if (json_encode($nuevas) !== json_encode(array_values($anteriores))) {
            $cambios++;
        }

        $contenido[$idSeccion][$claveLista] = $nuevas;

        $rutasNuevas = array();
        foreach ($nuevas as $fila) {
            foreach ($defLista['campos'] as $cl => $df) {
                if (es_campo_archivo(isset($df['tipo']) ? $df['tipo'] : 'texto') && !empty($fila[$cl])) {
                    $rutasNuevas[] = (string) $fila[$cl];
                }
            }
        }

        foreach (array_diff($rutasPrevias, $rutasNuevas) as $ruta) {
            $huerfanos[] = $ruta;
        }
    }

    return $cambios;
}

/**
 * Lee el formulario completo, actualiza el contenido en la base de datos
 * y devuelve el resultado.
 *
 * @param string $usuarioId  Quién guarda, para dejarlo anotado en cada campo.
 *
 * @return array (
 *     'ok'       => bool,
 *     'errores'  => string[],  // problemas que impidieron guardar algo
 *     'cambios'  => int        // cuántos campos cambiaron de valor
 * )
 */
function procesar_guardado($usuarioId = null)
{
    $errores        = array();
    $cambios        = 0;
    $secciones      = esquema_secciones();
    $contenido      = leer_contenido(true);
    $huerfanos      = array();
    $nuevosArchivos = array();

    $postTexto  = isset($_POST['c']) && is_array($_POST['c']) ? $_POST['c'] : array();
    $postListas = isset($_POST['l']) && is_array($_POST['l']) ? $_POST['l'] : array();

    foreach ($secciones as $idSeccion => $defSeccion) {
        if (!isset($contenido[$idSeccion]) || !is_array($contenido[$idSeccion])) {
            $contenido[$idSeccion] = array();
        }

        $cambios += guardar_procesar_campos_simples($idSeccion, $defSeccion, $contenido, $postTexto, $errores, $huerfanos, $nuevosArchivos);
        $cambios += guardar_procesar_listas_dinamicas($idSeccion, $defSeccion, $contenido, $postListas, $errores, $huerfanos, $nuevosArchivos);
    }

    // ---------------- Escritura en Transacción ----------------
    try {
        contenido_guardar($contenido, $usuarioId);
    } catch (BdError $e) {
        $errores[] = $e->getMessage();

        foreach ($nuevosArchivos as $ruta) {
            borrar_subida_si_procede($ruta);
        }

        return array('ok' => false, 'errores' => $errores, 'cambios' => 0);
    }

    // ---------------- Limpieza de archivos huérfanos ----------------
    foreach (array_unique($huerfanos) as $ruta) {
        borrar_subida_si_procede($ruta);
    }

    return array('ok' => true, 'errores' => $errores, 'cambios' => $cambios);
}
