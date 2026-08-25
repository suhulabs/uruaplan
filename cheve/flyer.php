<?php
/**
 * ===========================================================================
 *  FICHA DE UN FLYER
 * ===========================================================================
 *  Todo lo que se puede hacer con una solicitud concreta: verla completa,
 *  corregir los datos antes de publicarla, cambiar la foto, aceptarla o
 *  rechazarla, sacarla en la web y consultar todo lo que se le ha hecho.
 *
 *  Igual que el resto del panel, cada acción es un POST con token CSRF que
 *  termina en una redirección, para que recargar no repita nada.
 * ===========================================================================
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/funciones.php';
require_once __DIR__ . '/includes/bd.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/flyers.php';

bd_exigir();

$usuario = exigir_sesion();
$estados = flyer_estados();

$id = isset($_GET['id']) ? (int) $_GET['id'] : (isset($_POST['id']) ? (int) $_POST['id'] : 0);

if ($id <= 0 || flyer_obtener($id) === null) {
    $_SESSION['flash_flyers'] = array('ok' => false, 'msg' => 'Ese flyer no existe o ya se eliminó.');
    header('Location: flyers.php');
    exit;
}

/**
 * Limpieza de un campo de texto del formulario del panel.
 * Misma idea que en el formulario público: fuera caracteres de control
 * (acaban en cabeceras de correo) y recorte al largo que admite el diseño.
 */
function limpiar_flyer($nombre, $maximo, $multilinea = false)
{
    $valor = isset($_POST[$nombre]) ? $_POST[$nombre] : '';
    return sanear_cadena($valor, $maximo, $multilinea);
}

/** Acota un número al rango permitido. */
function numero_flyer($nombre, $min, $max, $porDefecto)
{
    if (!isset($_POST[$nombre]) || !is_numeric($_POST[$nombre])) {
        return $porDefecto;
    }

    $n = (float) $_POST[$nombre];

    if (!is_finite($n)) {
        return $porDefecto;
    }

    return round(max($min, min($max, $n)), 2);
}

// ---------------------------------------------------------------------------
// ACCIONES
// ---------------------------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_exigir();

    $accion  = isset($_POST['accion']) ? (string) $_POST['accion'] : '';
    $nota    = limpiar_flyer('nota', 1000, true);
    $errores = array();
    $avisos  = array();

    try {
        switch ($accion) {

            case 'guardar':
                $campos = array(
                    'evento'      => limpiar_flyer('evento', 45),
                    'subtitulo'   => limpiar_flyer('subtitulo', 60),
                    'fecha'       => limpiar_flyer('fecha', 10),
                    'hora'        => limpiar_flyer('hora', 5),
                    'ubicacion'   => limpiar_flyer('ubicacion', 70),
                    'precio'      => limpiar_flyer('precio', 30),
                    'contacto'    => limpiar_flyer('contacto', 30),
                    'descripcion' => limpiar_flyer('descripcion', 160, true),
                    'comentarios' => limpiar_flyer('comentarios', 600, true),
                    'nota'        => $nota,
                    'pos_x'       => numero_flyer('pos_x', 0, 100, 50),
                    'pos_y'       => numero_flyer('pos_y', 0, 100, 50),
                    'zoom'        => numero_flyer('zoom', 1, 3, 1),
                );

                // Una fecha u hora mal escrita se descarta y se avisa. Se saca
                // del array en vez de mandarla vacía: así el valor que ya
                // estaba guardado se queda como está, en lugar de borrarse por
                // culpa de una errata.
                if ($campos['fecha'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $campos['fecha'])) {
                    $errores[] = 'La fecha no tiene un formato válido; se dejó como estaba.';
                    unset($campos['fecha']);
                }
                if ($campos['hora'] !== '' && !preg_match('/^\d{2}:\d{2}$/', $campos['hora'])) {
                    $errores[] = 'La hora no tiene un formato válido; se dejó como estaba.';
                    unset($campos['hora']);
                }

                $resultado = flyer_actualizar($id, $campos, $usuario);

                if (!$resultado['ok']) {
                    $errores[] = $resultado['error'];
                }

                // Foto nueva, si se eligió una.
                if (isset($_FILES['foto'])) {
                    $errorFoto = flyer_reemplazar_imagen($id, $_FILES['foto'], $usuario);
                    if ($errorFoto !== '') {
                        $errores[] = $errorFoto;
                    }
                }

                if (!$errores) {
                    $avisos[] = $resultado['cambios'] > 0
                        ? 'Se guardaron ' . $resultado['cambios'] . ' cambio(s).'
                        : 'No había nada que guardar.';
                }
                break;

            case 'aceptar':
            case 'rechazar':
            case 'archivar':
            case 'reabrir':
                $destino = array(
                    'aceptar'  => 'aceptado',
                    'rechazar' => 'rechazado',
                    'archivar' => 'archivado',
                    'reabrir'  => 'pendiente',
                )[$accion];

                $error = flyer_cambiar_estado($id, $destino, $usuario, $nota);
                if ($error !== '') {
                    $errores[] = $error;
                } else {
                    $avisos[] = 'El flyer pasó a «' . $estados[$destino]['etiqueta'] . '».';
                }
                break;

            case 'publicar':
                $error = flyer_publicar($id, $usuario);
                if ($error !== '') {
                    $errores[] = $error;
                } else {
                    $avisos[] = '¡Publicado! Ya se ve en la cartelera de la web.';
                }
                break;

            case 'despublicar':
                $error = flyer_despublicar($id, $usuario);
                if ($error !== '') {
                    $errores[] = $error;
                } else {
                    $avisos[] = 'Se quitó de la web. Sigue aquí, aceptado, por si quieres volver a sacarlo.';
                }
                break;

            case 'eliminar':
                // Doble seguro: hay que escribir BORRAR para que se ejecute.
                if (strtoupper(trim((string) (isset($_POST['confirmacion']) ? $_POST['confirmacion'] : ''))) !== 'BORRAR') {
                    $errores[] = 'Para eliminar hay que escribir BORRAR en el recuadro.';
                    break;
                }

                $error = flyer_eliminar($id, $usuario);

                if ($error !== '') {
                    $errores[] = $error;
                    break;
                }

                $_SESSION['flash_flyers'] = array(
                    'ok'  => true,
                    'msg' => 'El flyer #' . $id . ' se eliminó definitivamente.',
                );

                header('Location: flyers.php');
                exit;

            default:
                $errores[] = 'Acción desconocida.';
        }

    } catch (BdError $e) {
        $errores[] = $e->getMessage();
    }

    $_SESSION['flash_flyer'] = array('errores' => $errores, 'avisos' => $avisos);

    header('Location: flyer.php?id=' . $id);
    exit;
}

// ---------------------------------------------------------------------------
// VISTA
// ---------------------------------------------------------------------------

$flyer     = flyer_obtener($id);
$historial = flyer_historial($id);
$def       = $estados[$flyer['estado']];

$flash = null;
if (!empty($_SESSION['flash_flyer'])) {
    $flash = $_SESSION['flash_flyer'];
    unset($_SESSION['flash_flyer']);
}

$acciones = array(
    'recibido'     => array('icono' => 'fa-solid fa-inbox',            'texto' => 'Recibido'),
    'aceptado'     => array('icono' => 'fa-solid fa-circle-check',     'texto' => 'Aceptado'),
    'rechazado'    => array('icono' => 'fa-solid fa-circle-xmark',     'texto' => 'Rechazado'),
    'archivado'    => array('icono' => 'fa-solid fa-box-archive',      'texto' => 'Archivado'),
    'pendiente'    => array('icono' => 'fa-solid fa-rotate-left',      'texto' => 'Devuelto a pendiente'),
    'publicado'    => array('icono' => 'fa-solid fa-tower-broadcast',  'texto' => 'Publicado'),
    'despublicado' => array('icono' => 'fa-solid fa-eye-slash',        'texto' => 'Quitado de la web'),
    'editado'      => array('icono' => 'fa-solid fa-pen',              'texto' => 'Editado'),
    'foto'         => array('icono' => 'fa-solid fa-image',            'texto' => 'Foto cambiada'),
);

$tituloPagina = 'Flyer #' . $id;
$paginaActual = 'flyers';
require __DIR__ . '/includes/cabecera.php';
?>

<div class="encabezado-pagina">
  <div>
    <a class="volver" href="flyers.php"><i class="fa-solid fa-arrow-left"></i> Volver a la bandeja</a>
    <h1><?= e($flyer['evento']) ?></h1>
    <p>
      Flyer <strong>#<?= (int) $flyer['id'] ?></strong> ·
      llegó el <?= e(date('d/m/Y \a \l\a\s H:i', strtotime($flyer['recibido']))) ?>
      <?php if ($flyer['revisado_en'] !== null): ?>
        · última revisión el <?= e(date('d/m/Y', strtotime($flyer['revisado_en']))) ?>
        por <?= e($flyer['revisado_por']) ?>
      <?php endif; ?>
    </p>
  </div>

  <div class="cabecera-estados">
    <span class="etiqueta-estado grande <?= e($def['clase']) ?>">
      <i class="<?= e($def['icono']) ?>"></i> <?= e($def['etiqueta']) ?>
    </span>
    <?php if (!empty($flyer['publicado'])): ?>
      <span class="etiqueta-estado grande etiqueta-publicado">
        <i class="fa-solid fa-tower-broadcast"></i> En la web
      </span>
    <?php endif; ?>
  </div>
</div>

<?php if ($flash !== null): ?>
  <?php foreach ($flash['errores'] as $msg): ?>
    <div class="alerta alerta-error">
      <i class="fa-solid fa-triangle-exclamation"></i><span><?= e($msg) ?></span>
    </div>
  <?php endforeach; ?>
  <?php foreach ($flash['avisos'] as $msg): ?>
    <div class="alerta alerta-ok" data-autocerrar="1">
      <i class="fa-solid fa-circle-check"></i><span><?= e($msg) ?></span>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<?php if (empty($flyer['correo_ok'])): ?>
  <div class="alerta alerta-aviso">
    <i class="fa-solid fa-envelope-circle-check"></i>
    <div>
      <strong>El aviso por correo de esta solicitud no salió.</strong>
      La solicitud está completa y guardada —por eso la estás viendo—, pero nadie
      recibió el correo. Si pasa siempre, revisa la configuración en
      <a href="probar-correo.php">Correo</a>.
    </div>
  </div>
<?php endif; ?>

<!-- ===================== BARRA DE DECISIÓN ===================== -->
<section class="panel visible barra-decision">
  <header class="panel-cabecera">
    <h2><i class="fa-solid fa-gavel"></i> Qué hacer con este flyer</h2>
  </header>

  <form method="post" class="decisiones">
    <?php csrf_campo(); ?>
    <input type="hidden" name="id" value="<?= (int) $flyer['id'] ?>">

    <div class="campo campo-nota">
      <label class="campo-label" for="nota-decision">
        Nota interna <span class="opcional">(opcional)</span>
      </label>
      <textarea id="nota-decision" name="nota" rows="2" class="input-texto" maxlength="1000"
                placeholder="Por ejemplo: por qué se rechaza, o qué falta por confirmar. Solo lo ve el equipo, nunca sale en la web."><?= e($flyer['nota']) ?></textarea>
    </div>

    <div class="botonera-decision">
      <?php if ($flyer['estado'] !== 'aceptado'): ?>
        <button type="submit" name="accion" value="aceptar" class="btn btn-primario">
          <i class="fa-solid fa-check"></i> Aceptar
        </button>
      <?php endif; ?>

      <?php if ($flyer['estado'] !== 'rechazado'): ?>
        <button type="submit" name="accion" value="rechazar" class="btn btn-peligro">
          <i class="fa-solid fa-xmark"></i> Rechazar
        </button>
      <?php endif; ?>

      <?php if ($flyer['estado'] !== 'archivado'): ?>
        <button type="submit" name="accion" value="archivar" class="btn btn-secundario">
          <i class="fa-solid fa-box-archive"></i> Archivar
        </button>
      <?php endif; ?>

      <?php if ($flyer['estado'] !== 'pendiente'): ?>
        <button type="submit" name="accion" value="reabrir" class="btn btn-secundario">
          <i class="fa-solid fa-rotate-left"></i> Volver a pendiente
        </button>
      <?php endif; ?>
    </div>

    <hr class="separador-decision">

    <div class="bloque-publicacion">
      <?php if ($flyer['estado'] !== 'aceptado'): ?>
        <p class="campo-ayuda">
          <i class="fa-solid fa-circle-info"></i>
          Para poder publicarlo en la web hay que aceptarlo primero.
        </p>
      <?php elseif (empty($flyer['publicado'])): ?>
        <p class="campo-ayuda">
          <i class="fa-solid fa-circle-info"></i>
          Está aceptado pero <strong>no se ve en la web</strong>. Al publicarlo aparecerá en la
          cartelera con la foto y los datos tal como están ahora.
        </p>
        <button type="submit" name="accion" value="publicar" class="btn btn-publicar">
          <i class="fa-solid fa-tower-broadcast"></i> Publicar en la web
        </button>
      <?php else: ?>
        <p class="campo-ayuda">
          <i class="fa-solid fa-circle-check"></i>
          Se está viendo en la cartelera. Al quitarlo, la copia pública de la foto se borra
          del servidor, pero el flyer se queda aquí aceptado por si quieres volver a sacarlo.
        </p>
        <button type="submit" name="accion" value="despublicar" class="btn btn-secundario">
          <i class="fa-solid fa-eye-slash"></i> Quitar de la web
        </button>
      <?php endif; ?>
    </div>
  </form>
</section>

<!-- ===================== DATOS DEL FLYER ===================== -->
<form method="post" enctype="multipart/form-data" id="formContenido">
  <?php csrf_campo(); ?>
  <input type="hidden" name="id" value="<?= (int) $flyer['id'] ?>">
  <input type="hidden" name="accion" value="guardar">

  <div class="ficha-flyer">

    <!-- Columna de la foto -->
    <section class="panel visible columna-foto">
      <header class="panel-cabecera">
        <h2><i class="fa-solid fa-image"></i> Foto principal</h2>
      </header>

      <div class="foto-flyer">
        <?php if ($flyer['imagen'] !== ''): ?>
          <img id="foto-preview" src="flyer-imagen.php?id=<?= (int) $flyer['id'] ?>" alt=""
               style="object-position: <?= (float) $flyer['pos_x'] ?>% <?= (float) $flyer['pos_y'] ?>%;
                      transform-origin: <?= (float) $flyer['pos_x'] ?>% <?= (float) $flyer['pos_y'] ?>%;
                      transform: scale(<?= (float) $flyer['zoom'] ?>);">
        <?php else: ?>
          <div class="vista-vacia"><i class="fa-solid fa-image"></i><span>Sin foto</span></div>
        <?php endif; ?>
      </div>

      <p class="foto-datos">
        <?php if ($flyer['imagen_ancho'] > 0): ?>
          <?= (int) $flyer['imagen_ancho'] ?> × <?= (int) $flyer['imagen_alto'] ?> px ·
          <?= e(round($flyer['imagen_bytes'] / 1024)) ?> KB
        <?php else: ?>
          Sin datos de la imagen.
        <?php endif; ?>
      </p>

      <!-- Encuadre: reutiliza el mismo modal del editor de contenido -->
      <div class="campo campo-encuadre" data-encuadre data-marco="flyer" data-preview="foto-preview">
        <input type="hidden" name="pos_x" value="<?= e($flyer['pos_x']) ?>" data-enc="x">
        <input type="hidden" name="pos_y" value="<?= e($flyer['pos_y']) ?>" data-enc="y">
        <input type="hidden" name="zoom"  value="<?= e($flyer['zoom']) ?>"  data-enc="z">
        <button type="button" class="btn btn-secundario btn-encuadre">
          <i class="fa-solid fa-crop-simple"></i> Ajustar encuadre
        </button>
        <span class="encuadre-resumen"></span>
      </div>

      <div class="campo">
        <label class="campo-label" for="foto">Cambiar la foto</label>
        <input type="file" id="foto" name="foto" class="input-archivo"
               accept="image/jpeg,image/png,image/webp" data-preview="foto-preview">
        <p class="campo-ayuda">
          JPG, PNG o WEBP, máximo <?= round(MAX_BYTES_IMAGEN / 1048576) ?> MB. Déjalo vacío para
          conservar la actual. Si el flyer estaba publicado, se quita de la web al cambiar la
          foto: así nadie ve la vieja mientras revisas la nueva.
        </p>
      </div>
    </section>

    <!-- Columna de los datos -->
    <section class="panel visible columna-datos">
      <header class="panel-cabecera">
        <h2><i class="fa-solid fa-list"></i> Datos del evento</h2>
        <p class="panel-ayuda">
          <i class="fa-solid fa-lightbulb"></i>
          Esto es literalmente lo que saldrá en el flyer de la web, así que aquí es donde se
          corrigen las faltas de ortografía y se recorta lo que venga demasiado largo.
        </p>
      </header>

      <div class="rejilla-campos">
        <div class="campo">
          <label class="campo-label" for="evento">Nombre del evento</label>
          <input type="text" id="evento" name="evento" class="input-texto" maxlength="45"
                 data-contador="1" value="<?= e($flyer['evento']) ?>" required>
          <small class="contador"></small>
        </div>

        <div class="campo">
          <label class="campo-label" for="subtitulo">Subtítulo</label>
          <input type="text" id="subtitulo" name="subtitulo" class="input-texto" maxlength="60"
                 data-contador="1" value="<?= e($flyer['subtitulo']) ?>">
          <small class="contador"></small>
        </div>

        <div class="campo">
          <label class="campo-label" for="fecha">Fecha</label>
          <input type="date" id="fecha" name="fecha" class="input-texto"
                 value="<?= e($flyer['fecha']) ?>">
        </div>

        <div class="campo">
          <label class="campo-label" for="hora">Hora</label>
          <input type="time" id="hora" name="hora" class="input-texto"
                 value="<?= e($flyer['hora'] !== null ? substr($flyer['hora'], 0, 5) : '') ?>">
        </div>

        <div class="campo">
          <label class="campo-label" for="ubicacion">Ubicación</label>
          <input type="text" id="ubicacion" name="ubicacion" class="input-texto" maxlength="70"
                 data-contador="1" value="<?= e($flyer['ubicacion']) ?>">
          <small class="contador"></small>
        </div>

        <div class="campo">
          <label class="campo-label" for="precio">Precio</label>
          <input type="text" id="precio" name="precio" class="input-texto" maxlength="30"
                 value="<?= e($flyer['precio']) ?>">
        </div>

        <div class="campo">
          <label class="campo-label" for="contacto">Contacto</label>
          <input type="text" id="contacto" name="contacto" class="input-texto" maxlength="30"
                 value="<?= e($flyer['contacto']) ?>">
          <p class="campo-ayuda">Lo que puso el solicitante: su Instagram, su teléfono o su correo.</p>
        </div>
      </div>

      <div class="campo">
        <label class="campo-label" for="descripcion">Descripción</label>
        <textarea id="descripcion" name="descripcion" rows="3" class="input-texto"
                  maxlength="160" data-contador="1"><?= e($flyer['descripcion']) ?></textarea>
        <small class="contador"></small>
        <p class="campo-ayuda">Es el texto que va dentro del flyer: cabe poco, se lee de un vistazo.</p>
      </div>

      <div class="campo">
        <label class="campo-label" for="comentarios">
          Comentarios del solicitante <span class="opcional">(no salen en el flyer)</span>
        </label>
        <textarea id="comentarios" name="comentarios" rows="3" class="input-texto"
                  maxlength="600"><?= e($flyer['comentarios']) ?></textarea>
      </div>

      <div class="campo">
        <label class="campo-label" for="nota">Nota interna</label>
        <textarea id="nota" name="nota" rows="2" class="input-texto"
                  maxlength="1000"><?= e($flyer['nota']) ?></textarea>
        <p class="campo-ayuda">Solo la ve el equipo. Es la misma nota que aparece arriba.</p>
      </div>

      <?php
        $geoDetalle = !empty($flyer['geo_origen']) ? $flyer['geo_origen'] : '';
      ?>
      <?php if (!empty($flyer['ip']) || $geoDetalle !== ''): ?>
        <p class="campo-ayuda procedencia" style="font-size:0.9rem;margin-top:0.5rem;padding:0.5rem;background:rgba(255,255,255,0.05);border-radius:6px;">
          <i class="fa-solid fa-map-pin"></i>
          <strong>Ubicación de origen:</strong> <?= e($geoDetalle !== '' ? $geoDetalle : 'Desconocida') ?>
          <?php if (!empty($flyer['ip'])): ?>
            <code>(IP: <?= e($flyer['ip']) ?>)</code>
          <?php endif; ?>
          <?php if (strpos($geoDetalle, 'GPS:') !== false && preg_match('/GPS:\s*(-?\d+\.\d+,\s*-?\d+\.\d+)/', $geoDetalle, $mGps)): ?>
            <a href="https://maps.google.com/?q=<?= urlencode($mGps[1]) ?>" target="_blank" rel="noopener" style="margin-left:0.5rem;text-decoration:underline;">
              <i class="fa-solid fa-arrow-up-right-from-square"></i> Ver en Google Maps
            </a>
          <?php endif; ?>
        </p>
      <?php endif; ?>
    </section>
  </div>

  <div class="barra-guardar">
    <span class="estado-cambios" id="estadoCambios">Sin cambios pendientes</span>
    <button type="submit" class="btn btn-primario" id="btnGuardar">
      <i class="fa-solid fa-floppy-disk"></i> Guardar cambios
    </button>
  </div>
</form>

<!-- ===================== HISTORIAL ===================== -->
<section class="panel visible">
  <header class="panel-cabecera">
    <h2><i class="fa-solid fa-clock-rotate-left"></i> Historial</h2>
    <p class="panel-ayuda">
      <i class="fa-solid fa-lightbulb"></i>
      Todo lo que se le ha hecho a este flyer, de lo más reciente a lo más antiguo.
      No se puede editar ni borrar.
    </p>
  </header>

  <ol class="historial">
    <?php foreach ($historial as $h): ?>
      <?php $a = isset($acciones[$h['accion']])
                    ? $acciones[$h['accion']]
                    : array('icono' => 'fa-solid fa-circle', 'texto' => $h['accion']); ?>
      <li class="historial-item">
        <span class="historial-icono"><i class="<?= e($a['icono']) ?>"></i></span>
        <div class="historial-cuerpo">
          <p class="historial-titulo">
            <strong><?= e($a['texto']) ?></strong>
            <span class="historial-quien">
              <?= $h['usuario_nombre'] !== '' ? '· ' . e($h['usuario_nombre']) : '· el visitante' ?>
            </span>
          </p>
          <?php if ($h['detalle'] !== ''): ?>
            <p class="historial-detalle"><?= e($h['detalle']) ?></p>
          <?php endif; ?>
          <time class="historial-fecha"><?= e(date('d/m/Y H:i', strtotime($h['cuando']))) ?></time>
        </div>
      </li>
    <?php endforeach; ?>
  </ol>
</section>

<!-- ===================== ZONA PELIGROSA ===================== -->
<?php if (in_array($flyer['estado'], array('rechazado', 'archivado'), true)): ?>
  <section class="panel visible zona-peligro">
    <header class="panel-cabecera">
      <h2><i class="fa-solid fa-triangle-exclamation"></i> Eliminar definitivamente</h2>
      <p class="panel-ayuda">
        Borra el flyer, su foto y su historial. <strong>No se puede deshacer.</strong>
        Para conservar el registro, usa «Archivar» en vez de esto.
      </p>
    </header>

    <form method="post" class="form-eliminar"
          onsubmit="return confirm('¿Seguro? El flyer, su foto y su historial se borran para siempre.');">
      <?php csrf_campo(); ?>
      <input type="hidden" name="id" value="<?= (int) $flyer['id'] ?>">
      <input type="hidden" name="accion" value="eliminar">

      <label class="campo-label" for="confirmacion">Escribe <code>BORRAR</code> para confirmar</label>
      <div class="fila-eliminar">
        <input type="text" id="confirmacion" name="confirmacion" class="input-texto"
               autocomplete="off" spellcheck="false" placeholder="BORRAR">
        <button type="submit" class="btn btn-peligro">
          <i class="fa-solid fa-trash"></i> Eliminar
        </button>
      </div>
    </form>
  </section>
<?php endif; ?>

<!-- ==========================================================================
     MODAL DE ENCUADRE
     Es el mismo del editor de contenido: va fuera del <form> a propósito,
     porque sus controles no deben enviarse. Lo que se guarda son los campos
     ocultos pos_x / pos_y / zoom, que este modal actualiza.
     ========================================================================== -->
<div class="modal-enc" id="modalEncuadre" hidden>
  <div class="modal-enc-caja" role="dialog" aria-modal="true" aria-labelledby="encTitulo">

    <header class="modal-enc-cabecera">
      <h3 id="encTitulo"><i class="fa-solid fa-crop-simple"></i> Ajustar encuadre</h3>
      <button type="button" class="btn-cerrar-enc" data-enc-cerrar aria-label="Cerrar">&times;</button>
    </header>

    <p class="modal-enc-pista">
      Arrastra la imagen para elegir qué parte se ve. Así queda en
      <strong data-enc-slot></strong>.
    </p>

    <div class="enc-escenarios">
      <div class="enc-escenario">
        <span class="enc-etiqueta"><i class="fa-solid fa-desktop"></i> Computadora</span>
        <div class="enc-marco" id="encMarco" data-ejes="ninguno">
          <img id="encImg" alt="" draggable="false">
          <video id="encVideo" muted loop playsinline hidden></video>
          <div class="enc-guias" aria-hidden="true"></div>
        </div>
        <p class="enc-ejes" id="encEjes"></p>
      </div>

      <div class="enc-escenario">
        <span class="enc-etiqueta"><i class="fa-solid fa-mobile-screen"></i> Celular</span>
        <div class="enc-marco enc-marco-movil" id="encMarcoMovil">
          <img id="encImgMovil" alt="" draggable="false">
          <video id="encVideoMovil" muted loop playsinline hidden></video>
        </div>
      </div>
    </div>

    <div class="enc-controles">
      <label for="encZoom"><i class="fa-solid fa-magnifying-glass-plus"></i> Zoom</label>
      <input type="range" id="encZoom" min="1" max="3" step="0.05" value="1">
      <output id="encZoomValor">1.00×</output>
    </div>

    <p class="enc-aviso" id="encAviso" hidden></p>

    <footer class="modal-enc-pie">
      <button type="button" class="btn btn-secundario" data-enc-centrar>
        <i class="fa-solid fa-crosshairs"></i> Centrar
      </button>
      <span class="enc-espacio"></span>
      <button type="button" class="btn btn-secundario" data-enc-cerrar>Cancelar</button>
      <button type="button" class="btn btn-primario" data-enc-aplicar>
        <i class="fa-solid fa-check"></i> Aplicar
      </button>
    </footer>
  </div>
</div>

<?php require __DIR__ . '/includes/pie.php'; ?>
