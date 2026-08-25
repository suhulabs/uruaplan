<?php
/**
 * ===========================================================================
 *  BANDEJA DE FLYERS
 * ===========================================================================
 *  El listado de todo lo que ha mandado la gente por el formulario, con
 *  filtros por estado y buscador. Desde aquí se acepta y se rechaza en un
 *  clic; para corregir datos o publicar se entra al detalle (flyer.php).
 *
 *  Las acciones se hacen por POST y responden con una redirección
 *  (POST → Redirect → GET), para que recargar la página no vuelva a
 *  ejecutar la última acción.
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

// ---------------------------------------------------------------------------
// ACCIONES RÁPIDAS (POST → Redirect → GET)
// ---------------------------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_exigir();

    $accion = isset($_POST['accion']) ? (string) $_POST['accion'] : '';
    $id     = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    $error  = '';

    $msgSuccess = '';
    try {
        if ($accion === 'aceptar') {
            $error = flyer_cambiar_estado($id, 'aceptado', $usuario);
        } elseif ($accion === 'rechazar') {
            $error = flyer_cambiar_estado($id, 'rechazado', $usuario);
        } elseif ($accion === 'archivar') {
            $error = flyer_cambiar_estado($id, 'archivado', $usuario);
        } elseif ($accion === 'reabrir') {
            $error = flyer_cambiar_estado($id, 'pendiente', $usuario);
        } elseif ($accion === 'purgar_datos') {
            $purgados = flyers_purgar_datos_personales(6, $usuario);
            $msgSuccess = $purgados > 0
                ? 'Se purgaron los datos personales (IP/User-Agent) de ' . $purgados . ' flyer(s) antiguos (>6 meses).'
                : 'No había flyers antiguos con IP o User-Agent pendientes de purgar.';
        } else {
            $error = 'Acción desconocida.';
        }
    } catch (BdError $e) {
        $error = $e->getMessage();
    }

    if ($accion === 'purgar_datos') {
        $_SESSION['flash_flyers'] = $error === ''
            ? array('ok' => true, 'msg' => $msgSuccess)
            : array('ok' => false, 'msg' => $error);
    } else {
        $_SESSION['flash_flyers'] = $error === ''
            ? array('ok' => true,  'msg' => 'Flyer #' . $id . ' actualizado.')
            : array('ok' => false, 'msg' => $error);
    }

    // Se vuelve exactamente a la vista que estaba abierta.
    $vuelta = 'flyers.php';
    $query  = array();
    if (!empty($_POST['volver_estado'])) {
        $query['estado'] = (string) $_POST['volver_estado'];
    }
    if (!empty($_POST['volver_q'])) {
        $query['q'] = (string) $_POST['volver_q'];
    }
    if (!empty($_POST['volver_pagina'])) {
        $query['pagina'] = (int) $_POST['volver_pagina'];
    }
    if ($query) {
        $vuelta .= '?' . http_build_query($query);
    }

    header('Location: ' . $vuelta);
    exit;
}

// ---------------------------------------------------------------------------
// VISTA
// ---------------------------------------------------------------------------

$flash = null;
if (!empty($_SESSION['flash_flyers'])) {
    $flash = $_SESSION['flash_flyers'];
    unset($_SESSION['flash_flyers']);
}

$filtroEstado = isset($_GET['estado']) && flyer_estado_valido($_GET['estado'])
    ? (string) $_GET['estado']
    : '';

$busqueda = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
if (function_exists('mb_substr')) {
    $busqueda = mb_substr($busqueda, 0, 80, 'UTF-8');
}

$pagina = isset($_GET['pagina']) ? (int) $_GET['pagina'] : 1;

$conteo   = flyers_conteo();
$listado  = flyers_listar(array(
    'estado'     => $filtroEstado,
    'busqueda'   => $busqueda,
    'pagina'     => $pagina,
    'por_pagina' => 20,
));

/** Construye una URL de la bandeja conservando los filtros actuales. */
function url_bandeja(array $cambios = array())
{
    global $filtroEstado, $busqueda, $listado;

    $query = array_merge(array(
        'estado' => $filtroEstado,
        'q'      => $busqueda,
        'pagina' => $listado['pagina'],
    ), $cambios);

    $query = array_filter($query, function ($v) {
        return $v !== '' && $v !== null && $v !== 0 && $v !== '0';
    });

    return 'flyers.php' . ($query ? '?' . http_build_query($query) : '');
}

/** Fecha del evento en formato legible. */
function fecha_bonita($fecha)
{
    if (empty($fecha) || $fecha === '0000-00-00') {
        return 'Sin fecha';
    }

    $meses = array(1 => 'ene', 'feb', 'mar', 'abr', 'may', 'jun',
                        'jul', 'ago', 'sep', 'oct', 'nov', 'dic');

    $partes = explode('-', (string) $fecha);
    if (count($partes) !== 3) {
        return (string) $fecha;
    }

    $mes = (int) $partes[1];

    return (int) $partes[2] . ' ' . (isset($meses[$mes]) ? $meses[$mes] : $partes[1]) . ' ' . $partes[0];
}

/** "hace 3 horas", "hace 2 días"... */
function hace_cuanto($fechaHora)
{
    $marca = strtotime((string) $fechaHora);

    if ($marca === false) {
        return '';
    }

    $s = time() - $marca;

    if ($s < 60)    { return 'hace un momento'; }
    if ($s < 3600)  { return 'hace ' . (int) ($s / 60) . ' min'; }
    if ($s < 86400) { return 'hace ' . (int) ($s / 3600) . ' h'; }
    if ($s < 2592000) { return 'hace ' . (int) ($s / 86400) . ' día(s)'; }

    return 'el ' . date('d/m/Y', $marca);
}

$tituloPagina = 'Flyers';
$paginaActual = 'flyers';
require __DIR__ . '/includes/cabecera.php';
?>

<div class="encabezado-pagina">
  <div>
    <h1>Flyers recibidos</h1>
    <p>
      Todo lo que manda la gente por el formulario llega aquí. Revísalo, corrige lo que
      haga falta y decide si se publica en la web. Nada se borra: lo rechazado y lo
      archivado se queda en el histórico.
    </p>
  </div>
  <div style="display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap;">
    <form method="post" action="flyers.php" onsubmit="return confirm('¿Purgar IP y User-Agent de flyers recibidos hace más de 6 meses?');" style="display:inline;">
      <?php csrf_campo(); ?>
      <input type="hidden" name="accion" value="purgar_datos">
      <button type="submit" class="btn btn-secundario" title="Cumplimiento LFPDPPP: borra IP y User-Agent de flyers antiguos (>6 meses)">
        <i class="fa-solid fa-user-shield"></i> Purga de datos (>6m)
      </button>
    </form>
    <a class="btn btn-secundario" href="../index.php#cartelera" target="_blank" rel="noopener">
      <i class="fa-solid fa-eye"></i> Ver la cartelera
    </a>
  </div>
</div>

<?php if ($flash !== null): ?>
  <div class="alerta <?= $flash['ok'] ? 'alerta-ok' : 'alerta-error' ?>" <?= $flash['ok'] ? 'data-autocerrar="1"' : '' ?>>
    <i class="fa-solid <?= $flash['ok'] ? 'fa-circle-check' : 'fa-triangle-exclamation' ?>"></i>
    <span><?= e($flash['msg']) ?></span>
  </div>
<?php endif; ?>

<!-- ===================== FILTROS ===================== -->
<div class="bandeja-filtros">
  <nav class="pestanas-estado">
    <a href="<?= e(url_bandeja(array('estado' => '', 'pagina' => 1))) ?>"
       class="pastilla-filtro <?= $filtroEstado === '' ? 'activa' : '' ?>">
      <i class="fa-solid fa-layer-group"></i> Todos
      <span class="pastilla"><?= (int) $conteo['total'] ?></span>
    </a>

    <?php foreach ($estados as $clave => $def): ?>
      <a href="<?= e(url_bandeja(array('estado' => $clave, 'pagina' => 1))) ?>"
         class="pastilla-filtro <?= $def['clase'] ?> <?= $filtroEstado === $clave ? 'activa' : '' ?>">
        <i class="<?= e($def['icono']) ?>"></i> <?= e($def['etiqueta']) ?>
        <span class="pastilla"><?= (int) $conteo[$clave] ?></span>
      </a>
    <?php endforeach; ?>
  </nav>

  <form method="get" action="flyers.php" class="buscador">
    <?php if ($filtroEstado !== ''): ?>
      <input type="hidden" name="estado" value="<?= e($filtroEstado) ?>">
    <?php endif; ?>
    <i class="fa-solid fa-magnifying-glass"></i>
    <input type="search" name="q" value="<?= e($busqueda) ?>" class="input-texto"
           placeholder="Buscar por evento, lugar o contacto…" maxlength="80">
    <?php if ($busqueda !== ''): ?>
      <a class="btn-mini" href="<?= e(url_bandeja(array('q' => '', 'pagina' => 1))) ?>" title="Limpiar búsqueda">
        <i class="fa-solid fa-xmark"></i>
      </a>
    <?php endif; ?>
  </form>
</div>

<?php if ($conteo['publicados'] > 0): ?>
  <p class="nota-cartelera">
    <i class="fa-solid fa-tower-broadcast"></i>
    Ahora mismo hay <strong><?= (int) $conteo['publicados'] ?></strong> flyer(s) visibles en la web.
  </p>
<?php endif; ?>

<!-- ===================== LISTADO ===================== -->
<?php if (!$listado['filas']): ?>

  <div class="vacio-bandeja">
    <i class="fa-regular fa-folder-open"></i>
    <h2><?= $busqueda !== '' || $filtroEstado !== ''
            ? 'No hay flyers que coincidan'
            : 'Todavía no ha llegado ningún flyer' ?></h2>
    <p>
      <?php if ($busqueda !== '' || $filtroEstado !== ''): ?>
        Prueba a quitar el filtro o a buscar otra cosa.
      <?php else: ?>
        Cuando alguien llene el formulario de «Promociona tu Plan» en la web,
        su solicitud aparecerá en esta lista.
      <?php endif; ?>
    </p>
  </div>

<?php else: ?>

  <div class="lista-flyers">
    <?php foreach ($listado['filas'] as $f): ?>
      <?php
        $def = $estados[$f['estado']];
        $esPendiente = $f['estado'] === 'pendiente';
      ?>
      <article class="tarjeta-flyer <?= e($def['clase']) ?>">

        <a class="flyer-miniatura" href="flyer.php?id=<?= (int) $f['id'] ?>">
          <?php if ($f['imagen'] !== ''): ?>
            <img src="flyer-imagen.php?id=<?= (int) $f['id'] ?>" alt="" loading="lazy">
          <?php else: ?>
            <span class="sin-foto"><i class="fa-solid fa-image-slash"></i></span>
          <?php endif; ?>
        </a>

        <div class="flyer-datos">
          <div class="flyer-cabecera">
            <span class="etiqueta-estado <?= e($def['clase']) ?>">
              <i class="<?= e($def['icono']) ?>"></i> <?= e($def['etiqueta']) ?>
            </span>

            <?php if (!empty($f['publicado'])): ?>
              <span class="etiqueta-estado etiqueta-publicado">
                <i class="fa-solid fa-tower-broadcast"></i> En la web
              </span>
            <?php endif; ?>

            <?php if (empty($f['correo_ok'])): ?>
              <span class="etiqueta-estado etiqueta-aviso" title="El aviso por correo no se pudo enviar. La solicitud sí quedó guardada.">
                <i class="fa-solid fa-envelope-circle-check"></i> Sin aviso
              </span>
            <?php endif; ?>

            <span class="flyer-id">#<?= (int) $f['id'] ?></span>
          </div>

          <h2 class="flyer-titulo">
            <a href="flyer.php?id=<?= (int) $f['id'] ?>"><?= e($f['evento']) ?></a>
          </h2>

          <?php if ($f['subtitulo'] !== ''): ?>
            <p class="flyer-subtitulo-lista"><?= e($f['subtitulo']) ?></p>
          <?php endif; ?>

          <ul class="flyer-meta">
            <li><i class="fa-solid fa-calendar-days"></i> <?= e(fecha_bonita($f['fecha'])) ?>
                <?= $f['hora'] !== null ? e(' · ' . substr($f['hora'], 0, 5)) : '' ?></li>
            <li><i class="fa-solid fa-location-dot"></i> <?= e($f['ubicacion'] !== '' ? $f['ubicacion'] : '—') ?></li>
            <li><i class="fa-solid fa-at"></i> <?= e($f['contacto'] !== '' ? $f['contacto'] : '—') ?></li>
            <li><i class="fa-solid fa-clock-rotate-left"></i> Llegó <?= e(hace_cuanto($f['recibido'])) ?></li>
            <?php
              $geoMostrar = !empty($f['geo_origen']) ? $f['geo_origen'] : '';
              $esUruapan = $geoMostrar === '' || stripos($geoMostrar, 'Uruapan') !== false;
            ?>
            <?php if ($geoMostrar !== ''): ?>
              <li style="margin-top:0.3rem;">
                <span class="etiqueta-estado <?= $esUruapan ? 'etiqueta-aceptado' : 'etiqueta-rechazado' ?>" style="font-size:0.75rem;padding:0.25rem 0.5rem;" title="Procedencia por IP / Geolocalización">
                  <i class="fa-solid fa-map-pin"></i> <?= e($geoMostrar) ?>
                </span>
              </li>
            <?php endif; ?>
          </ul>
        </div>

        <div class="flyer-acciones">
          <a class="btn btn-secundario btn-bloque" href="flyer.php?id=<?= (int) $f['id'] ?>">
            <i class="fa-solid fa-pen-to-square"></i> Abrir
          </a>

          <?php if ($esPendiente): ?>
            <form method="post" class="accion-rapida">
              <?php csrf_campo(); ?>
              <input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
              <input type="hidden" name="volver_estado" value="<?= e($filtroEstado) ?>">
              <input type="hidden" name="volver_q" value="<?= e($busqueda) ?>">
              <input type="hidden" name="volver_pagina" value="<?= (int) $listado['pagina'] ?>">

              <button type="submit" name="accion" value="aceptar" class="btn btn-primario btn-bloque">
                <i class="fa-solid fa-check"></i> Aceptar
              </button>
              <button type="submit" name="accion" value="rechazar" class="btn btn-peligro btn-bloque">
                <i class="fa-solid fa-xmark"></i> Rechazar
              </button>
            </form>
          <?php else: ?>
            <form method="post" class="accion-rapida">
              <?php csrf_campo(); ?>
              <input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
              <input type="hidden" name="volver_estado" value="<?= e($filtroEstado) ?>">
              <input type="hidden" name="volver_q" value="<?= e($busqueda) ?>">
              <input type="hidden" name="volver_pagina" value="<?= (int) $listado['pagina'] ?>">

              <?php if ($f['estado'] !== 'archivado'): ?>
                <button type="submit" name="accion" value="archivar" class="btn btn-secundario btn-bloque">
                  <i class="fa-solid fa-box-archive"></i> Archivar
                </button>
              <?php else: ?>
                <button type="submit" name="accion" value="reabrir" class="btn btn-secundario btn-bloque">
                  <i class="fa-solid fa-rotate-left"></i> Reabrir
                </button>
              <?php endif; ?>
            </form>
          <?php endif; ?>
        </div>
      </article>
    <?php endforeach; ?>
  </div>

  <?php if ($listado['paginas'] > 1): ?>
    <nav class="paginacion">
      <?php if ($listado['pagina'] > 1): ?>
        <a class="btn btn-secundario" href="<?= e(url_bandeja(array('pagina' => $listado['pagina'] - 1))) ?>">
          <i class="fa-solid fa-arrow-left"></i> Anteriores
        </a>
      <?php endif; ?>

      <span class="paginacion-info">
        Página <?= (int) $listado['pagina'] ?> de <?= (int) $listado['paginas'] ?>
        · <?= (int) $listado['total'] ?> flyer(s)
      </span>

      <?php if ($listado['pagina'] < $listado['paginas']): ?>
        <a class="btn btn-secundario" href="<?= e(url_bandeja(array('pagina' => $listado['pagina'] + 1))) ?>">
          Siguientes <i class="fa-solid fa-arrow-right"></i>
        </a>
      <?php endif; ?>
    </nav>
  <?php endif; ?>

<?php endif; ?>

<?php require __DIR__ . '/includes/pie.php'; ?>
