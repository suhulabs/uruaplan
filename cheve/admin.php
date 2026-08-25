<?php
/**
 * PANEL DE EDICIÓN DE CONTENIDO
 *
 * Una sola página con pestañas. Todo el formulario se envía junto, así que
 * un único "Guardar cambios" actualiza contenido.json completo.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/funciones.php';
require_once __DIR__ . '/includes/bd.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/esquema.php';
require_once __DIR__ . '/includes/render.php';
require_once __DIR__ . '/includes/guardar.php';

// Sin base de datos no hay panel: se corta aquí con una pantalla que
// explica qué falta, en vez de reventar más adelante.
bd_exigir();

// Sesión obligatoria. Si el usuario aún tiene la contraseña temporal,
// exigir_sesion() lo manda a cambiarla antes de llegar aquí.
$usuario = exigir_sesion();

$secciones = esquema_secciones();

// ---------------------------------------------------------------------------
// GUARDADO (POST → Redirect → GET)
// ---------------------------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_exigir();

    $resultado = procesar_guardado($usuario['id']);
    $pestana   = isset($_POST['tab']) ? preg_replace('/[^a-z_]/', '', (string) $_POST['tab']) : '';

    $_SESSION['flash'] = array(
        'ok'      => $resultado['ok'],
        'cambios' => $resultado['cambios'],
        'errores' => $resultado['errores'],
        'quien'   => isset($usuario['nombre']) ? $usuario['nombre'] : $usuario['id'],
    );

    header('Location: admin.php' . ($pestana !== '' ? '?s=' . urlencode($pestana) : ''));
    exit;
}

// ---------------------------------------------------------------------------
// PREPARACIÓN DE LA VISTA
// ---------------------------------------------------------------------------

$flash = null;
if (!empty($_SESSION['flash'])) {
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
}

$contenido = leer_contenido(true);

// Pestaña activa: la que venga por ?s= o la primera.
$clavesSecciones = array_keys($secciones);
$activa = isset($_GET['s']) && isset($secciones[$_GET['s']]) ? (string) $_GET['s'] : $clavesSecciones[0];

// --- Diagnóstico de permisos: es el fallo #1 al subir a un hosting nuevo ---
$problemas = array();

if (!is_writable(DATA_DIR)) {
    $problemas[] = 'La carpeta <code>cheve/data/</code> no tiene permiso de escritura (ponla en 755). '
        . 'El contenido se guarda en MySQL, así que el panel funciona igual, pero ahí vive la copia '
        . 'de respaldo que mantiene la web en pie si la base de datos se cae.';
}
$errorSubidas = verificar_carpeta_subidas();
if ($errorSubidas !== '') {
    $problemas[] = e($errorSubidas);
}

// Comprobación de que el .htaccess de data/ está bloqueando de verdad.
// Solo se hace una vez por hora y por sesión: implica una petición HTTP.
$ahora = time();
if (!isset($_SESSION['chequeo_data']) || $ahora - $_SESSION['chequeo_data']['cuando'] > 3600) {
    $_SESSION['chequeo_data'] = array(
        'cuando'   => $ahora,
        'expuesta' => data_expuesta_publicamente(),
    );
}

if ($_SESSION['chequeo_data']['expuesta'] === true) {
    $problemas[] = '<strong>¡ATENCIÓN!</strong> El contenido de la carpeta <code>cheve/data/</code> '
        . 'se puede descargar desde internet, y ahí están las fotos de los flyers pendientes y '
        . 'rechazados. Sube el archivo <code>.htaccess</code> a esa carpeta (asegúrate de que el '
        . 'Administrador de archivos de cPanel muestre los archivos ocultos) o activa la '
        . 'Protección de Directorios sobre ella.';
}

$tituloPagina = 'Editar contenido';
$paginaActual = 'admin';
require __DIR__ . '/includes/cabecera.php';
?>

<div class="encabezado-pagina">
  <div>
    <h1>Contenido del sitio</h1>
    <p>
      Cambia los textos y las imágenes. Al guardar, la página pública se actualiza al instante.
      En los párrafos largos puedes usar <code>**negrita**</code> para resaltar palabras.
    </p>
  </div>
  <a class="btn btn-secundario" href="../index.php" target="_blank" rel="noopener">
    <i class="fa-solid fa-eye"></i> Ver la página
  </a>
</div>

<?php if ($problemas): ?>
  <div class="alerta alerta-error">
    <i class="fa-solid fa-triangle-exclamation"></i>
    <div>
      <strong>Revisa los permisos del hosting:</strong>
      <ul>
        <?php foreach ($problemas as $p): ?>
          <li><?= $p /* ya viene escapado o es HTML fijo de esta misma página */ ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
<?php endif; ?>

<?php if ($flash !== null): ?>
  <?php if ($flash['ok'] && empty($flash['errores'])): ?>
    <div class="alerta alerta-ok" data-autocerrar="1">
      <i class="fa-solid fa-circle-check"></i>
      <span>
        <strong>¡Guardado!</strong>
        <?= (int) $flash['cambios'] > 0
              ? 'Se actualizaron ' . (int) $flash['cambios'] . ' campo(s) y ya se ven en la página.'
              : 'No hubo cambios que guardar.' ?>
      </span>
    </div>
  <?php elseif ($flash['ok']): ?>
    <div class="alerta alerta-aviso">
      <i class="fa-solid fa-circle-exclamation"></i>
      <div>
        <strong>Se guardó, pero con avisos:</strong>
        <ul>
          <?php foreach ($flash['errores'] as $msg): ?>
            <li><?= e($msg) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  <?php else: ?>
    <div class="alerta alerta-error">
      <i class="fa-solid fa-triangle-exclamation"></i>
      <div>
        <strong>No se pudo guardar:</strong>
        <ul>
          <?php foreach ($flash['errores'] as $msg): ?>
            <li><?= e($msg) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  <?php endif; ?>
<?php endif; ?>

<form method="post" action="admin.php" enctype="multipart/form-data" id="formContenido">
  <?php csrf_campo(); ?>
  <input type="hidden" name="tab" id="tabActiva" value="<?= e($activa) ?>">

  <div class="disposicion">

    <!-- Pestañas laterales -->
    <nav class="pestanas" aria-label="Secciones editables">
      <?php foreach ($secciones as $id => $def): ?>
        <button type="button" class="pestana <?= $id === $activa ? 'activa' : '' ?>" data-panel="panel-<?= e($id) ?>">
          <i class="<?= e($def['icono']) ?>"></i>
          <span><?= e($def['titulo']) ?></span>
        </button>
      <?php endforeach; ?>
    </nav>

    <!-- Paneles -->
    <div class="paneles">
      <?php foreach ($secciones as $id => $def): ?>
        <section class="panel <?= $id === $activa ? 'visible' : '' ?>" id="panel-<?= e($id) ?>">

          <header class="panel-cabecera">
            <h2><i class="<?= e($def['icono']) ?>"></i> <?= e($def['titulo']) ?></h2>
            <?php if (!empty($def['ayuda'])): ?>
              <p class="panel-ayuda"><i class="fa-solid fa-lightbulb"></i> <?= e($def['ayuda']) ?></p>
            <?php endif; ?>
          </header>

          <div class="rejilla-campos">
            <?php foreach ($def['campos'] as $clave => $defCampo): ?>
              <?php
                $valor = isset($contenido[$id][$clave]) && is_scalar($contenido[$id][$clave])
                    ? (string) $contenido[$id][$clave]
                    : '';
                render_campo($id, $clave, $defCampo, $valor);
              ?>
            <?php endforeach; ?>
          </div>

          <?php if (!empty($def['listas'])): ?>
            <?php foreach ($def['listas'] as $claveLista => $defLista): ?>
              <?php
                $filas = isset($contenido[$id][$claveLista]) && is_array($contenido[$id][$claveLista])
                    ? array_values(array_filter($contenido[$id][$claveLista], 'is_array'))
                    : array();
                render_lista($id, $claveLista, $defLista, $filas);
              ?>
            <?php endforeach; ?>
          <?php endif; ?>

        </section>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Barra de guardado fija -->
  <div class="barra-guardar">
    <span class="estado-cambios" id="estadoCambios">Sin cambios pendientes</span>
    <button type="submit" class="btn btn-primario" id="btnGuardar">
      <i class="fa-solid fa-floppy-disk"></i> Guardar cambios
    </button>
  </div>
</form>

<!-- ==========================================================================
     MODAL DE ENCUADRE
     Va FUERA del <form> a propósito: sus controles no deben enviarse. Lo que
     se guarda son los campos ocultos de cada fila, que este modal actualiza.
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
