<?php
/**
 * Generación del formulario del panel a partir del esquema.
 *
 * Convención de nombres de los campos (importante para entender guardar.php):
 *
 *   Texto simple .......  c[seccion][campo]
 *   Archivo simple .....  f~seccion~campo                (nombre plano: $_FILES es más fácil de leer así)
 *   Texto de lista .....  l[seccion][lista][INDICE][campo]
 *   Archivo de lista ...  fl~seccion~lista~INDICE~campo
 *   Archivo actual .....  l[seccion][lista][INDICE][__actual][campo]   (oculto)
 *
 * El orden de las filas de una lista es el orden en que el navegador las
 * envía, que es el orden del DOM. Por eso reordenar en pantalla basta.
 */

require_once __DIR__ . '/funciones.php';
require_once __DIR__ . '/esquema.php';

/** Prefijo para ver las imágenes del sitio desde dentro de /cheve/. */
define('PREFIJO_VISTA', '../');

/**
 * Bloque de previsualización de un archivo (imagen o video) ya guardado.
 */
function render_vista_previa($ruta, $id = '')
{
    $url = url_activo($ruta, PREFIJO_VISTA);
    $esVideo = (bool) preg_match('~\.(mp4|mov)$~i', (string) $ruta);

    echo '<div class="vista-previa">';

    if ($ruta === '') {
        echo '<div class="vista-vacia"><i class="fa-solid fa-image"></i><span>Sin archivo</span></div>';
    } elseif ($esVideo) {
        echo '<video src="' . e($url) . '" muted loop playsinline'
            . ($id !== '' ? ' id="' . e($id) . '"' : '') . '></video>';
    } else {
        echo '<img src="' . e($url) . '" alt=""' . ($id !== '' ? ' id="' . e($id) . '"' : '')
            . ' loading="lazy">';
    }

    echo '<code class="ruta-actual">' . ($ruta === '' ? '—' : e($ruta)) . '</code>';
    echo '</div>';
}

/**
 * Dibuja un campo simple de una sección.
 */
function render_campo($seccion, $clave, array $def, $valor)
{
    $tipo   = isset($def['tipo']) ? $def['tipo'] : 'texto';
    $label  = isset($def['label']) ? $def['label'] : $clave;
    $ayuda  = isset($def['ayuda']) ? $def['ayuda'] : '';
    $max    = isset($def['max']) ? (int) $def['max'] : 0;
    $idHtml = 'campo-' . $seccion . '-' . $clave;

    $nombreTexto   = 'c[' . $seccion . '][' . $clave . ']';
    $nombreArchivo = 'f~' . $seccion . '~' . $clave;

    echo '<div class="campo campo-' . e($tipo) . '">';
    echo '<label class="campo-label" for="' . e($idHtml) . '">' . e($label) . '</label>';

    if (es_campo_archivo($tipo)) {
        // --- Campo de archivo -------------------------------------------
        $acepta = $tipo === 'media'
            ? 'image/jpeg,image/png,image/webp,video/mp4'
            : 'image/jpeg,image/png,image/webp';

        echo '<div class="campo-archivo">';
        render_vista_previa((string) $valor, $idHtml . '-preview');
        echo '<div class="campo-archivo-control">';
        echo '<input type="file" id="' . e($idHtml) . '" name="' . e($nombreArchivo) . '"'
            . ' accept="' . e($acepta) . '" class="input-archivo"'
            . ' data-preview="' . e($idHtml) . '-preview">';
        echo '<p class="campo-ayuda">Deja este campo vacío para conservar el archivo actual.'
            . ($ayuda !== '' ? ' ' . e($ayuda) : '') . '</p>';
        echo '</div>';
        echo '</div>';

    } elseif ($tipo === 'area') {
        // --- Textarea ----------------------------------------------------
        echo '<textarea id="' . e($idHtml) . '" name="' . e($nombreTexto) . '" rows="3"'
            . ' class="input-texto"'
            . ($max > 0 ? ' maxlength="' . $max . '" data-contador="1"' : '')
            . '>' . e($valor) . '</textarea>';
        if ($max > 0) {
            echo '<small class="contador"></small>';
        }
        if ($ayuda !== '') {
            echo '<p class="campo-ayuda">' . e($ayuda) . '</p>';
        }

    } elseif ($tipo === 'icono') {
        // --- Clase de Font Awesome con vista previa en vivo --------------
        echo '<div class="campo-icono">';
        echo '<span class="icono-preview"><i class="' . e($valor) . '"></i></span>';
        echo '<input type="text" id="' . e($idHtml) . '" name="' . e($nombreTexto) . '"'
            . ' value="' . e($valor) . '" class="input-texto" maxlength="60" data-icono="1"'
            . ' spellcheck="false">';
        echo '</div>';
        echo '<p class="campo-ayuda">Clase de Font Awesome. Ejemplos: '
            . '<code>fa-solid fa-star</code>, <code>fa-solid fa-music</code>, '
            . '<code>fa-brands fa-instagram</code>. Búscalas en fontawesome.com/icons'
            . ($ayuda !== '' ? ' — ' . e($ayuda) : '') . '</p>';

    } else {
        // --- Input de una línea -------------------------------------------
        $tiposHtml = array('email' => 'email', 'tel' => 'tel', 'url' => 'url');
        $tipoHtml  = isset($tiposHtml[$tipo]) ? $tiposHtml[$tipo] : 'text';

        echo '<input type="' . e($tipoHtml) . '" id="' . e($idHtml) . '" name="' . e($nombreTexto) . '"'
            . ' value="' . e($valor) . '" class="input-texto"'
            . ($max > 0 ? ' maxlength="' . $max . '" data-contador="1"' : '') . '>';
        if ($max > 0) {
            echo '<small class="contador"></small>';
        }
        if ($ayuda !== '') {
            echo '<p class="campo-ayuda">' . e($ayuda) . '</p>';
        }
    }

    echo '</div>';
}

/**
 * Dibuja una fila de una lista dinámica (galería, fondos del hero...).
 *
 * @param int   $indice  Índice único dentro del formulario.
 * @param array $fila    Valores actuales, o vacío si es una fila nueva.
 */
function render_fila_lista($seccion, $claveLista, array $defLista, $indice, array $fila)
{
    $base   = 'l[' . $seccion . '][' . $claveLista . '][' . $indice . ']';
    $baseId = 'fila-' . $seccion . '-' . $claveLista . '-' . $indice;

    echo '<div class="fila-lista" data-indice="' . e($indice) . '">';

    echo '<div class="fila-cabecera">';
    echo '<span class="fila-numero"></span>';
    echo '<div class="fila-acciones">';
    echo '<button type="button" class="btn-mini" data-mover="-1" title="Subir"><i class="fa-solid fa-arrow-up"></i></button>';
    echo '<button type="button" class="btn-mini" data-mover="1" title="Bajar"><i class="fa-solid fa-arrow-down"></i></button>';
    echo '<button type="button" class="btn-mini btn-mini-rojo" data-eliminar="1" title="Quitar"><i class="fa-solid fa-trash"></i></button>';
    echo '</div>';
    echo '</div>';

    echo '<div class="fila-cuerpo">';

    foreach ($defLista['campos'] as $clave => $def) {
        $tipo   = isset($def['tipo']) ? $def['tipo'] : 'texto';
        $label  = isset($def['label']) ? $def['label'] : $clave;
        $ayuda  = isset($def['ayuda']) ? $def['ayuda'] : '';
        $max    = isset($def['max']) ? (int) $def['max'] : 0;
        $valor  = isset($fila[$clave]) ? (string) $fila[$clave] : '';
        $idHtml = $baseId . '-' . $clave;

        echo '<div class="campo campo-' . e($tipo) . '">';

        // El interruptor trae su propia etiqueta al lado; los demás no.
        if ($tipo !== 'check') {
            echo '<label class="campo-label" for="' . e($idHtml) . '">' . e($label) . '</label>';
        }

        if ($tipo === 'check') {
            // --- Interruptor mostrar / ocultar -------------------------------
            // El campo oculto con valor 0 va ANTES a propósito: un checkbox sin
            // marcar no se envía, así que sin él no habría forma de distinguir
            // "desmarcado" de "no venía en el formulario".
            $marcado = array_key_exists($clave, $fila)
                ? !empty($fila[$clave])
                : !empty($def['defecto']);

            echo '<input type="hidden" name="' . e($base . '[' . $clave . ']') . '" value="0">';
            echo '<label class="interruptor">';
            echo '<input type="checkbox" id="' . e($idHtml) . '"'
                . ' name="' . e($base . '[' . $clave . ']') . '" value="1"'
                . ($marcado ? ' checked' : '') . '>';
            echo '<span class="interruptor-pista"><span class="interruptor-bolita"></span></span>';
            echo '<span class="interruptor-texto">' . e($label) . '</span>';
            echo '</label>';

            if ($ayuda !== '') {
                echo '<p class="campo-ayuda">' . e($ayuda) . '</p>';
            }

            echo '</div>';
            continue;
        }

        if ($tipo === 'encuadre') {
            // --- Encuadre: foco y zoom dentro de la casilla del collage ------
            // Se guardan tres claves sueltas en la fila (pos_x, pos_y, zoom)
            // para que el JSON siga siendo legible. Los valores los escribe
            // el modal del panel; aquí solo viajan en campos ocultos.
            $px   = isset($fila['pos_x']) ? (float) $fila['pos_x'] : 50.0;
            $py   = isset($fila['pos_y']) ? (float) $fila['pos_y'] : 50.0;
            $zoom = isset($fila['zoom'])  ? (float) $fila['zoom']  : 1.0;

            // "marco" le dice al modal qué proporción tiene el hueco donde
            // acabará la foto: una casilla del collage o el fondo a pantalla
            // completa de la portada. Cada uno recorta de forma muy distinta.
            $marco = isset($def['marco']) ? $def['marco'] : 'collage';

            // El campo de archivo de esta misma fila es el que da la vista
            // previa; su clave depende de la lista (archivo, imagen…).
            $claveArchivo = 'archivo';
            foreach ($defLista['campos'] as $k => $d) {
                if (es_campo_archivo(isset($d['tipo']) ? $d['tipo'] : '')) {
                    $claveArchivo = $k;
                    break;
                }
            }

            echo '<div class="campo-encuadre" data-encuadre'
                . ' data-marco="' . e($marco) . '"'
                . ' data-preview="' . e($baseId . '-' . $claveArchivo . '-preview') . '">';
            echo '<input type="hidden" name="' . e($base . '[pos_x]') . '" value="' . e($px) . '" data-enc="x">';
            echo '<input type="hidden" name="' . e($base . '[pos_y]') . '" value="' . e($py) . '" data-enc="y">';
            echo '<input type="hidden" name="' . e($base . '[zoom]') . '" value="' . e($zoom) . '" data-enc="z">';
            echo '<button type="button" class="btn btn-secundario btn-encuadre">'
                . '<i class="fa-solid fa-crop-simple"></i> Ajustar encuadre</button>';
            echo '<span class="encuadre-resumen"></span>';
            echo '</div>';
            echo '</div>';
            continue;
        }

        if (es_campo_archivo($tipo)) {
            $acepta = $tipo === 'media'
                ? 'image/jpeg,image/png,image/webp,video/mp4'
                : 'image/jpeg,image/png,image/webp';

            echo '<div class="campo-archivo">';
            render_vista_previa($valor, $idHtml . '-preview');
            echo '<div class="campo-archivo-control">';
            // El campo oculto arrastra el archivo actual para que sobreviva
            // a los cambios de orden y a las filas insertadas en medio.
            echo '<input type="hidden" name="' . e($base . '[__actual][' . $clave . ']') . '"'
                . ' value="' . e($valor) . '">';
            echo '<input type="file" id="' . e($idHtml) . '"'
                . ' name="fl~' . e($seccion) . '~' . e($claveLista) . '~' . e($indice) . '~' . e($clave) . '"'
                . ' accept="' . e($acepta) . '" class="input-archivo"'
                . ' data-preview="' . e($idHtml) . '-preview">';
            echo '<p class="campo-ayuda">Vacío = conservar el archivo actual.</p>';
            echo '</div>';
            echo '</div>';

        } elseif ($tipo === 'area') {
            echo '<textarea id="' . e($idHtml) . '" name="' . e($base . '[' . $clave . ']') . '"'
                . ' rows="2" class="input-texto"'
                . ($max > 0 ? ' maxlength="' . $max . '"' : '') . '>' . e($valor) . '</textarea>';
            if ($ayuda !== '') {
                echo '<p class="campo-ayuda">' . e($ayuda) . '</p>';
            }

        } else {
            echo '<input type="text" id="' . e($idHtml) . '" name="' . e($base . '[' . $clave . ']') . '"'
                . ' value="' . e($valor) . '" class="input-texto"'
                . ($max > 0 ? ' maxlength="' . $max . '"' : '') . '>';
            if ($ayuda !== '') {
                echo '<p class="campo-ayuda">' . e($ayuda) . '</p>';
            }
        }

        echo '</div>';
    }

    echo '</div>';  // .fila-cuerpo
    echo '</div>';  // .fila-lista
}

/**
 * Dibuja una lista completa con su botón de "agregar".
 */
function render_lista($seccion, $claveLista, array $defLista, array $filas)
{
    $singular = isset($defLista['singular']) ? $defLista['singular'] : 'elemento';

    echo '<div class="bloque-lista" data-seccion="' . e($seccion) . '"'
        . ' data-lista="' . e($claveLista) . '"'
        . ' data-siguiente="' . count($filas) . '">';

    echo '<div class="bloque-lista-cabecera">';
    echo '<h3><i class="fa-solid fa-layer-group"></i> ' . e($defLista['titulo'])
        . ' <span class="pastilla contador-filas">' . count($filas) . '</span></h3>';
    if (!empty($defLista['ayuda'])) {
        echo '<p class="campo-ayuda">' . e($defLista['ayuda']) . '</p>';
    }
    echo '</div>';

    echo '<div class="filas">';
    foreach ($filas as $i => $fila) {
        render_fila_lista($seccion, $claveLista, $defLista, $i, is_array($fila) ? $fila : array());
    }
    echo '</div>';

    echo '<button type="button" class="btn btn-secundario btn-agregar">'
        . '<i class="fa-solid fa-plus"></i> Agregar ' . e($singular) . '</button>';

    // Plantilla que clona el JS al pulsar "Agregar". __IDX__ se sustituye
    // por el índice real en el navegador.
    echo '<template class="plantilla-fila">';
    render_fila_lista($seccion, $claveLista, $defLista, '__IDX__', array());
    echo '</template>';

    echo '</div>';
}
