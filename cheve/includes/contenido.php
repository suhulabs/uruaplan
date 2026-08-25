<?php
/**
 * ===========================================================================
 *  CONTENIDO EDITABLE — LECTURA Y ESCRITURA EN LA BASE DE DATOS
 * ===========================================================================
 *  Sustituye a contenido.json. Hacia fuera se comporta igual que antes: el
 *  resto del proyecto sigue pidiendo el contenido como un array anidado
 *
 *      $contenido['hero']['titulo']
 *      $contenido['galeria']['items'][0]['archivo']
 *
 *  y por eso index.php, render.php y admin.php no notan el cambio. Lo único
 *  que cambia es de dónde salen esos datos.
 *
 *  EL ESPEJO
 *  ---------
 *  En un hosting compartido, MySQL se cae o se queda sin conexiones libres
 *  bastante más a menudo que el disco. Si la página pública dependiera solo
 *  de la base, un hipo de dos minutos dejaría el sitio en blanco.
 *
 *  Por eso, cada vez que se guarda, se escribe además una copia de solo
 *  lectura en data/contenido-espejo.json. La web SIEMPRE lee de MySQL; el
 *  espejo únicamente entra en juego si la conexión falla, y así el visitante
 *  ve la página completa en lugar de un error.
 *
 *  El espejo NO se edita a mano: se regenera entero en cada guardado y
 *  cualquier cambio que le hagas se pierde al siguiente.
 * ===========================================================================
 */

require_once __DIR__ . '/bd.php';
require_once __DIR__ . '/esquema.php';

define('ARCHIVO_ESPEJO', DATA_DIR . '/contenido-espejo.json');

// ---------------------------------------------------------------------------
// LECTURA
// ---------------------------------------------------------------------------

/**
 * Carga TODO el contenido en un array anidado.
 *
 * Son dos consultas para la página entera, y el resultado se cachea en
 * memoria durante la petición: leer cien campos no son cien viajes a MySQL.
 *
 * @param bool $forzar  true para saltarse la caché de la petición.
 */
function contenido_cargar($forzar = false)
{
    static $cache = null;

    if ($cache !== null && !$forzar) {
        return $cache;
    }

    try {
        $cache = contenido_cargar_de_bd();

        // El espejo se regenera al guardar, no en cada visita: escribir un
        // archivo en cada carga de la página pública sería un desperdicio.
        // Aquí solo se crea la primera vez, para que exista desde ya y no
        // haya una ventana en la que no habría respaldo al que recurrir.
        if (!is_file(ARCHIVO_ESPEJO)) {
            contenido_espejo_escribir($cache);
        }
    } catch (BdError $e) {
        // La base no responde. Antes de rendirse, el espejo.
        error_log('[uruaplan] Contenido servido desde el espejo: ' . $e->getMessage());
        $cache = contenido_espejo_leer();
    }

    return $cache;
}

/**
 * Arma el array de contenido a partir de las tres tablas.
 *
 * @throws BdError
 */
function contenido_cargar_de_bd()
{
    $contenido = array();

    // --- Campos simples ---
    foreach (bd_filas('SELECT seccion, clave, valor FROM contenido') as $fila) {
        $contenido[$fila['seccion']][$fila['clave']] = $fila['valor'];
    }

    // --- Listas ---
    // Un solo JOIN trae todas las filas de todas las listas con sus campos.
    // El ORDER BY por (orden, id) es lo que conserva el orden que el editor
    // dejó en el panel con las flechas ↑ ↓.
    $filas = bd_filas(
        'SELECT l.id, l.seccion, l.lista, l.orden, c.campo, c.valor
           FROM contenido_listas l
           LEFT JOIN contenido_lista_campos c ON c.fila_id = l.id
          ORDER BY l.seccion, l.lista, l.orden, l.id'
    );

    // Se agrupan por fila conservando el orden de llegada.
    $porFila = array();
    foreach ($filas as $f) {
        $id = (int) $f['id'];

        if (!isset($porFila[$id])) {
            $porFila[$id] = array(
                'seccion' => $f['seccion'],
                'lista'   => $f['lista'],
                'campos'  => array(),
            );
        }

        // El LEFT JOIN devuelve campo = NULL si la fila no tiene ninguno.
        if ($f['campo'] !== null) {
            $porFila[$id]['campos'][$f['campo']] = $f['valor'];
        }
    }

    foreach ($porFila as $fila) {
        $contenido[$fila['seccion']][$fila['lista']][] = $fila['campos'];
    }

    return $contenido;
}

// ---------------------------------------------------------------------------
// ESCRITURA
// ---------------------------------------------------------------------------

/**
 * Vuelca el contenido completo a la base de datos.
 *
 * Va todo dentro de una transacción: guardar son decenas de escrituras y
 * no puede quedar a medias (media galería nueva y media vieja sería peor
 * que no haber guardado).
 *
 * Solo se escriben las secciones y claves declaradas en esquema.php: si
 * llegara un campo inventado, aquí se queda fuera. Es la misma lista blanca
 * que ya aplicaba guardar.php, repetida a propósito en la capa de datos.
 *
 * @param array  $contenido   Array anidado completo.
 * @param string $usuarioId   Quién guarda (para la columna actualizado_por).
 *
 * @return int  Cuántos campos cambiaron de valor de verdad.
 * @throws BdError
 */
function contenido_guardar(array $contenido, $usuarioId = null)
{
    $secciones = esquema_secciones();

    $cambios = bd_transaccion(function () use ($contenido, $usuarioId, $secciones) {

        $ahora   = date('Y-m-d H:i:s');
        $previo  = contenido_cargar_de_bd();
        $cambios = 0;

        foreach ($secciones as $idSeccion => $defSeccion) {

            // ---------------- Campos simples ----------------
            foreach ($defSeccion['campos'] as $clave => $def) {
                if (!isset($contenido[$idSeccion]) || !array_key_exists($clave, $contenido[$idSeccion])) {
                    continue;
                }

                $nuevo  = (string) $contenido[$idSeccion][$clave];
                $actual = isset($previo[$idSeccion][$clave]) ? (string) $previo[$idSeccion][$clave] : null;

                if ($actual !== null && $actual === $nuevo) {
                    continue;   // Sin cambio: no se toca la fila.
                }

                bd_ejecutar(
                    'INSERT INTO contenido (seccion, clave, valor, actualizado, actualizado_por)
                          VALUES (?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE
                          valor = VALUES(valor),
                          actualizado = VALUES(actualizado),
                          actualizado_por = VALUES(actualizado_por)',
                    array($idSeccion, $clave, $nuevo, $ahora, $usuarioId)
                );

                $cambios++;
            }

            // ---------------- Listas ----------------
            if (empty($defSeccion['listas'])) {
                continue;
            }

            foreach ($defSeccion['listas'] as $claveLista => $defLista) {
                if (!isset($contenido[$idSeccion][$claveLista])
                    || !is_array($contenido[$idSeccion][$claveLista])) {
                    continue;
                }

                $nuevas    = array_values($contenido[$idSeccion][$claveLista]);
                $anteriores = isset($previo[$idSeccion][$claveLista])
                    ? array_values($previo[$idSeccion][$claveLista])
                    : array();

                if (contenido_listas_iguales($anteriores, $nuevas)) {
                    continue;
                }

                // Se reescribe la lista entera. Es lo más simple y lo más
                // seguro: reordenar, insertar en medio y borrar filas a la
                // vez es un rompecabezas de índices que aquí desaparece.
                // El ON DELETE CASCADE se lleva los campos de cada fila.
                bd_ejecutar(
                    'DELETE FROM contenido_listas WHERE seccion = ? AND lista = ?',
                    array($idSeccion, $claveLista)
                );

                foreach ($nuevas as $orden => $fila) {
                    if (!is_array($fila)) {
                        continue;
                    }

                    bd_ejecutar(
                        'INSERT INTO contenido_listas (seccion, lista, orden) VALUES (?, ?, ?)',
                        array($idSeccion, $claveLista, $orden)
                    );

                    $filaId = bd_ultimo_id();

                    foreach ($fila as $campo => $valor) {
                        // Los booleanos se guardan como "1"/"0" para que al
                        // releerlos sigan siendo falsos donde toca.
                        if (is_bool($valor)) {
                            $valor = $valor ? '1' : '0';
                        }

                        bd_ejecutar(
                            'INSERT INTO contenido_lista_campos (fila_id, campo, valor) VALUES (?, ?, ?)',
                            array($filaId, (string) $campo, (string) $valor)
                        );
                    }
                }

                $cambios++;
            }
        }

        return $cambios;
    });

    // La caché en memoria y el espejo quedan al día tras guardar.
    contenido_espejo_escribir(contenido_cargar(true));

    return $cambios;
}

/**
 * ¿Dos versiones de una lista son la misma cosa?
 *
 * Se compara el contenido, no el tipo: la fila que sale del formulario trae
 * el interruptor como booleano y la que sale de MySQL como "1", y eso no es
 * un cambio que merezca reescribir la lista.
 */
function contenido_listas_iguales(array $a, array $b)
{
    if (count($a) !== count($b)) {
        return false;
    }

    foreach ($a as $i => $filaA) {
        $filaB = isset($b[$i]) ? $b[$i] : null;

        if (!is_array($filaA) || !is_array($filaB)) {
            return false;
        }

        $normal = function ($fila) {
            $salida = array();
            foreach ($fila as $k => $v) {
                if (is_bool($v)) {
                    $v = $v ? '1' : '0';
                }
                $salida[$k] = (string) $v;
            }
            ksort($salida);
            return $salida;
        };

        if ($normal($filaA) !== $normal($filaB)) {
            return false;
        }
    }

    return true;
}

// ---------------------------------------------------------------------------
// ESPEJO DE RESPALDO
// ---------------------------------------------------------------------------

/**
 * Guarda la copia de solo lectura. Un fallo aquí no es motivo para romper
 * nada: el espejo es una red, no la fuente de verdad.
 */
function contenido_espejo_escribir(array $contenido)
{
    if (!is_dir(DATA_DIR) || !is_writable(DATA_DIR)) {
        return false;
    }

    return guardar_json(ARCHIVO_ESPEJO, $contenido);
}

/**
 * Lee la copia de respaldo. Si tampoco existe, devuelve un array vacío:
 * la página saldrá con los textos por defecto en lugar de con un error 500.
 */
function contenido_espejo_leer()
{
    $espejo = leer_json(ARCHIVO_ESPEJO, array());

    if (!$espejo) {
        // Último recurso: el contenido.json original, si sigue ahí de antes
        // de la migración. Mejor una página con textos viejos que ninguna.
        $espejo = leer_json(ARCHIVO_CONTENIDO, array());
    }

    return $espejo;
}

// ---------------------------------------------------------------------------
// SIEMBRA INICIAL (la usa instalar.php)
// ---------------------------------------------------------------------------

/**
 * Mete en la base el contenido de un array (normalmente el que sale del
 * viejo contenido.json). Solo escribe lo que aún no exista, así que pasar
 * el instalador dos veces no pisa lo que ya editaste desde el panel.
 *
 * @return array ('campos' => int, 'filas' => int)
 */
function contenido_sembrar(array $origen)
{
    $secciones = esquema_secciones();

    return bd_transaccion(function () use ($origen, $secciones) {
        $ahora   = date('Y-m-d H:i:s');
        $campos  = 0;
        $filas   = 0;

        foreach ($secciones as $idSeccion => $defSeccion) {

            foreach ($defSeccion['campos'] as $clave => $def) {
                if (!isset($origen[$idSeccion]) || !array_key_exists($clave, $origen[$idSeccion])) {
                    continue;
                }
                if (!is_scalar($origen[$idSeccion][$clave])) {
                    continue;
                }

                // INSERT IGNORE: si la clave ya está, se respeta lo que hay.
                $n = bd_afectadas(
                    'INSERT IGNORE INTO contenido (seccion, clave, valor, actualizado)
                     VALUES (?, ?, ?, ?)',
                    array($idSeccion, $clave, (string) $origen[$idSeccion][$clave], $ahora)
                );

                $campos += $n;
            }

            if (empty($defSeccion['listas'])) {
                continue;
            }

            foreach ($defSeccion['listas'] as $claveLista => $defLista) {
                if (!isset($origen[$idSeccion][$claveLista])
                    || !is_array($origen[$idSeccion][$claveLista])) {
                    continue;
                }

                // Si la lista ya tiene filas en la base, no se toca.
                $yaHay = (int) bd_valor(
                    'SELECT COUNT(*) FROM contenido_listas WHERE seccion = ? AND lista = ?',
                    array($idSeccion, $claveLista),
                    0
                );

                if ($yaHay > 0) {
                    continue;
                }

                $orden = 0;
                foreach ($origen[$idSeccion][$claveLista] as $fila) {
                    if (!is_array($fila)) {
                        continue;
                    }

                    bd_ejecutar(
                        'INSERT INTO contenido_listas (seccion, lista, orden) VALUES (?, ?, ?)',
                        array($idSeccion, $claveLista, $orden)
                    );

                    $filaId = bd_ultimo_id();

                    foreach ($fila as $campo => $valor) {
                        if (!is_scalar($valor) && !is_bool($valor)) {
                            continue;
                        }
                        if (is_bool($valor)) {
                            $valor = $valor ? '1' : '0';
                        }

                        bd_ejecutar(
                            'INSERT INTO contenido_lista_campos (fila_id, campo, valor) VALUES (?, ?, ?)',
                            array($filaId, (string) $campo, (string) $valor)
                        );
                    }

                    $orden++;
                    $filas++;
                }
            }
        }

        return array('campos' => $campos, 'filas' => $filas);
    });
}
