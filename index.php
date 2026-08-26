<?php
/**
 * ===========================================================================
 *  URUAPLAN — PÁGINA PÚBLICA
 * ===========================================================================
 *  Este archivo sustituye al antiguo index.html. Es exactamente la misma
 *  página, pero los textos y las imágenes salen de cheve/data/contenido.json
 *  en lugar de estar escritos aquí dentro.
 *
 *  Atajos que se usan a lo largo del archivo:
 *    ce('seccion','campo')   -> texto ya escapado, listo para imprimir
 *    c('seccion','campo')    -> texto en crudo (para meterlo en una URL)
 *    cimg('seccion','campo') -> ruta de imagen escapada y codificada
 *    lista('seccion','clave')-> array de filas (galería, fondos del hero)
 *
 *  TODO valor que sale del JSON pasa por htmlspecialchars(): es lo que
 *  impide que un texto del panel pueda inyectar HTML o scripts.
 * ===========================================================================
 */

require_once __DIR__ . '/cheve/includes/funciones.php';
require_once __DIR__ . '/cheve/includes/antispam.php';
require_once __DIR__ . '/cheve/includes/flyers.php';

header('Content-Type: text/html; charset=UTF-8');

// --- Resultado del formulario de eventos ----------------------------------
// Solo se usa cuando el visitante tiene el JavaScript desactivado: en ese
// caso enviar-evento.php redirige aquí con el aviso en la URL. Con JS el
// mensaje lo pinta main.js sin recargar la página.
$avisoEnvio = '';
$envioOk = false;

$mensajesRespuesta = array(
  'ok' => '¡Solicitud enviada! Te contactaremos pronto.',
  'error_captcha' => 'No se superó la prueba de seguridad antispam.',
  'error_correo' => 'El correo electrónico proporcionado no es válido.',
  'error_servidor' => 'No se pudo enviar la solicitud. Inténtalo de nuevo más tarde.',
);

$codigoEnvio = isset($_GET['envio']) ? (string) $_GET['envio'] : '';
$envioOk = ($codigoEnvio === 'ok');
$avisoEnvio = isset($mensajesRespuesta[$codigoEnvio])
  ? $mensajesRespuesta[$codigoEnvio]
  : ($codigoEnvio !== '' ? 'Ocurrió un error con el envío.' : '');

// --- Datos que se reutilizan muchas veces ---------------------------------

$wa = solo_digitos(c('global', 'whatsapp_numero'));
$correo = c('global', 'correo');
$correoForm = c('contacto', 'correo_formulario', $correo);
$handle = c('global', 'instagram_handle', '@URUAPLAN');
$fondos = lista('hero', 'fondos');

// --- Cartelera: los flyers que el equipo aprobó Y publicó desde el panel ---
// Esta lista no se edita a mano en ningún sitio: es exactamente lo que hay
// marcado como publicado en la bandeja de flyers.
$cartelera = flyers_publicados(12);

// La sección solo se dibuja si hay algo que enseñar o si el panel tiene
// escrito un texto para el caso de "todavía no hay eventos".
$textoSinEventos = c('cartelera', 'vacio_texto');
$hayCartelera = !empty($cartelera) || $textoSinEventos !== '';

// --- Galería: se quedan fuera los elementos sin archivo y los ocultos ------
// Se filtra ANTES de pintar para que las casillas del collage (gi-1 … gi-13)
// se repartan entre los elementos que de verdad se ven. Si la clave "visible"
// no existe (contenido guardado antes de que hubiera interruptor), el
// elemento se muestra.
$itemsGaleria = lista('galeria', 'items');
$galeriaVisible = array();

foreach ($itemsGaleria as $item) {
  if (empty($item['archivo'])) {
    continue;
  }
  if (array_key_exists('visible', $item) && !$item['visible']) {
    continue;
  }
  $rutaFisica = SITIO_DIR . '/' . ltrim($item['archivo'], '/');
  if (!is_file($rutaFisica)) {
    continue;
  }
  $galeriaVisible[] = $item;
}

// Si la base de datos tenía referencias a fotos antiguas inexistentes,
// recurrir a las imágenes predeterminadas de contenido.json
if (empty($galeriaVisible)) {
  $jsonPredeterminado = leer_json(DATA_DIR . '/contenido.json', array());
  $itemsBackup = isset($jsonPredeterminado['galeria']['items']) ? $jsonPredeterminado['galeria']['items'] : array();
  foreach ($itemsBackup as $item) {
    if (!empty($item['archivo']) && is_file(SITIO_DIR . '/' . ltrim($item['archivo'], '/'))) {
      if (!array_key_exists('visible', $item) || $item['visible']) {
        $galeriaVisible[] = $item;
      }
    }
  }
}

/**
 * Formatea un número para meterlo en una regla CSS, sin depender del
 * separador decimal del idioma del servidor y sin ceros de sobra.
 */
function css_num($valor, $decimales = 2)
{
  return rtrim(rtrim(number_format((float) $valor, $decimales, '.', ''), '0'), '.');
}

/**
 * Construye un enlace de WhatsApp con el mensaje ya escrito.
 */
function enlace_wa($numero, $mensaje = '')
{
  $url = 'https://wa.me/' . $numero;

  if ($mensaje !== '') {
    $url .= '?text=' . rawurlencode($mensaje);
  }

  return e($url);
}

/**
 * Construye un enlace mailto con asunto y cuerpo.
 */
function enlace_mailto($correo, $asunto = '', $cuerpo = '')
{
  $url = 'mailto:' . $correo;
  $partes = array();

  if ($asunto !== '') {
    $partes[] = 'subject=' . rawurlencode($asunto);
  }
  if ($cuerpo !== '') {
    $partes[] = 'body=' . rawurlencode($cuerpo);
  }
  if ($partes) {
    $url .= '?' . implode('&', $partes);
  }

  return e($url);
}

// --- Datos que necesita el JavaScript -------------------------------------
// Se pasan como un único objeto para no repetir valores en el código.

$paraJs = array(
  'whatsapp' => $wa,
  'correoForm' => $correoForm,
  'fondos' => array(),
  'flyer' => array(
    'titulo' => c('contacto', 'flyer_titulo'),
    'subtitulo' => c('contacto', 'flyer_subtitulo'),
    'fecha' => c('contacto', 'flyer_fecha'),
    'hora' => c('contacto', 'flyer_hora'),
    'contacto' => c('contacto', 'flyer_contacto'),
    'precio' => c('contacto', 'flyer_precio'),
    'ubicacion' => c('contacto', 'flyer_ubicacion'),
    'descripcion' => c('contacto', 'flyer_descripcion'),
  ),
);

// Cada fondo viaja con su encuadre; js/main.js los aplica al crear los
// slides. Los apagados desde el panel ni se envían.
foreach ($fondos as $fondo) {
  if (empty($fondo['imagen'])) {
    continue;
  }
  if (array_key_exists('visible', $fondo) && !$fondo['visible']) {
    continue;
  }
  $rutaFisica = SITIO_DIR . '/' . ltrim($fondo['imagen'], '/');
  if (!is_file($rutaFisica)) {
    continue;
  }

  $paraJs['fondos'][] = array(
    'url' => url_activo($fondo['imagen']),
    'x' => isset($fondo['pos_x']) ? (float) $fondo['pos_x'] : 50.0,
    'y' => isset($fondo['pos_y']) ? (float) $fondo['pos_y'] : 50.0,
    'zoom' => isset($fondo['zoom']) ? (float) $fondo['zoom'] : 1.0,
  );
}

// Respaldos predeterminados si la base de datos tenía referencias a archivos antiguos borrados
if (empty($paraJs['fondos'])) {
  $jsonPredeterminado = leer_json(DATA_DIR . '/contenido.json', array());
  $fondosBackup = isset($jsonPredeterminado['hero']['fondos']) ? $jsonPredeterminado['hero']['fondos'] : array();
  foreach ($fondosBackup as $fondo) {
    if (!empty($fondo['imagen']) && is_file(SITIO_DIR . '/' . ltrim($fondo['imagen'], '/'))) {
      $paraJs['fondos'][] = array(
        'url' => url_activo($fondo['imagen']),
        'x' => isset($fondo['pos_x']) ? (float) $fondo['pos_x'] : 50.0,
        'y' => isset($fondo['pos_y']) ? (float) $fondo['pos_y'] : 50.0,
        'zoom' => isset($fondo['zoom']) ? (float) $fondo['zoom'] : 1.0,
      );
    }
  }
}

// JSON_HEX_* evita que un texto que contenga "</script>" rompa la página.
$jsonJs = json_encode(
  $paraJs,
  JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
  | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="format-detection" content="telephone=no">
  <title><?= ce('seo', 'title') ?></title>

  <!-- SEO Meta Tags -->
  <meta name="description" content="<?= ce('seo', 'meta_description') ?>">
  <meta name="keywords" content="<?= ce('seo', 'meta_keywords') ?>">
  <meta name="author" content="Uruaplan">
  <meta property="og:title" content="<?= ce('seo', 'og_title') ?>">
  <meta property="og:description" content="<?= ce('seo', 'og_description') ?>">
  <meta property="og:image" content="<?= cimg('seo', 'og_image') ?>">
  <meta property="og:type" content="website">

  <!-- Google Fonts & Font Awesome Icons -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link
    href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Outfit:wght@500;700;900&display=swap"
    rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

  <!-- Custom Stylesheet -->
  <link rel="stylesheet" href="<?= versionado('css/styles.css', SITIO_DIR . '/css/styles.css') ?>">
</head>

<body>

  <!-- Datos del panel que necesita js/main.js -->
  <script>window.URUAPLAN = <?= $jsonJs ?>;</script>

  <!-- Floating WhatsApp Button -->
  <a href="<?= enlace_wa($wa, c('global', 'wa_mensaje_flotante')) ?>" class="whatsapp-float" target="_blank"
    rel="noopener noreferrer" aria-label="Contactar por WhatsApp">
    <i class="fa-brands fa-whatsapp"></i>
  </a>

  <!-- NAVIGATION BAR -->
  <header class="navbar" id="navbar">
    <div class="container nav-container">
      <a href="#" class="logo-wrapper">
        <img decoding="async" src="<?= cimg('global', 'logo_header') ?>" alt="Uruaplan" class="logo-img">
      </a>

      <ul class="nav-links" id="navLinks">
        <li><a href="#inicio" class="nav-link active"><?= ce('nav', 'inicio') ?></a></li>
        <li><a href="#nosotros" class="nav-link"><?= ce('nav', 'nosotros') ?></a></li>
        <?php if ($hayCartelera): ?>
          <li><a href="#cartelera" class="nav-link"><?= ce('nav', 'cartelera', 'Eventos') ?></a></li>
        <?php endif; ?>
        <li><a href="#promociona" class="nav-link"><?= ce('nav', 'promociona') ?></a></li>
        <li><a href="#galeria" class="nav-link"><?= ce('nav', 'galeria') ?></a></li>
        <li><a href="#contacto" class="btn btn-primary"
            style="padding: 0.5rem 1.25rem;"><?= ce('nav', 'contacto') ?></a></li>
      </ul>

      <button class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Abrir menú de navegación">
        <i class="fa-solid fa-bars"></i>
      </button>
    </div>
  </header>

  <!-- HERO SECTION -->
  <section class="hero" id="inicio">
    <div class="hero-slideshow" id="heroSlideshow"></div>
    <div class="hero-bg-overlay"></div>
    <div class="container">
      <div class="hero-content">
        <h1 class="hero-title">
          <?= ce('hero', 'titulo') ?> <span class="text-lime"><?= ce('hero', 'titulo_resaltado') ?></span>
        </h1>

        <p class="hero-subtitle">
          <?= con_tokens(c('hero', 'subtitulo')) ?>
        </p>

        <div class="hero-ctas">
          <!-- Este botón apuntaba a #eventos, una sección que no existía.
               Ahora lleva a la cartelera —que es justo lo que prometía— y
               solo cae en la galería si todavía no hay ningún evento
               publicado y por tanto la cartelera no se dibuja. -->
          <a href="<?= $hayCartelera ? '#cartelera' : '#galeria' ?>" class="btn btn-primary">
            <i class="<?= ce('hero', 'btn1_icono') ?>"></i> <?= ce('hero', 'btn1_texto') ?>
          </a>
          <a href="#contacto" class="btn btn-outline">
            <i class="<?= ce('hero', 'btn2_icono') ?>"></i> <?= ce('hero', 'btn2_texto') ?>
          </a>
        </div>
      </div>
    </div>
    <div class="hero-controls" id="heroControls"></div>
  </section>

  <!-- ABOUT US SECTION (QUIÉNES SOMOS) -->
  <section class="about-section" id="nosotros">
    <div class="container">
      <div class="about-grid">
        <div class="about-logo-wrapper">
          <img loading="lazy" decoding="async" src="<?= cimg('nosotros', 'imagen') ?>" alt="Uruaplan Emblem Logo"
            class="about-logo-img">
        </div>

        <div class="about-content">
          <span class="section-subtitle"><?= ce('nosotros', 'subtitulo') ?></span>
          <h2 class="section-title"><?= ce('nosotros', 'titulo') ?></h2>
          <p class="section-description" style="text-align: left; margin-bottom: 1.25rem;">
            <?= con_tokens(c('nosotros', 'parrafo_1')) ?>
          </p>
          <?php if (c('nosotros', 'parrafo_2') !== ''): ?>
            <p style="color: var(--text-muted); font-size: 0.95rem; line-height: 1.6;">
              <?= con_tokens(c('nosotros', 'parrafo_2')) ?>
            </p>
          <?php endif; ?>

          <div class="about-pillars">
            <?php for ($p = 1; $p <= 2; $p++): ?>
              <div class="about-pillar-item">
                <div class="about-pillar-icon">
                  <i class="<?= ce('nosotros', 'pilar' . $p . '_icono') ?>"></i>
                </div>
                <div class="about-pillar-text">
                  <h4><?= ce('nosotros', 'pilar' . $p . '_titulo') ?></h4>
                  <p><?= ce('nosotros', 'pilar' . $p . '_texto') ?></p>
                </div>
              </div>
            <?php endfor; ?>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- PROMOTION FOR ORGANIZERS & BUSINESSES -->
  <section id="promociona">
    <div class="container">
      <div class="promo-banner">
        <div class="promo-grid">
          <div class="promo-content">
            <span class="section-subtitle"><?= ce('promociona', 'subtitulo') ?></span>
            <h2><?= ce('promociona', 'titulo') ?> <span
                class="text-lime"><?= ce('promociona', 'titulo_resaltado') ?></span></h2>
            <p>
              <?= con_tokens(c('promociona', 'parrafo')) ?>
            </p>

            <ul class="promo-features">
              <?php for ($v = 1; $v <= 3; $v++): ?>
                <?php $vineta = c('promociona', 'vineta_' . $v); ?>
                <?php if ($vineta !== ''): ?>
                  <li><i class="fa-solid fa-circle-check"></i> <?= e($vineta) ?></li>
                <?php endif; ?>
              <?php endfor; ?>
            </ul>

            <a href="#contacto" class="btn btn-primary">
              <i class="fa-solid fa-rocket"></i> <?= ce('promociona', 'btn_texto') ?>
            </a>
          </div>

          <div class="promo-logo-standalone">
            <img loading="lazy" decoding="async" src="<?= cimg('promociona', 'imagen') ?>"
              alt="Uruaplan Promociones Logo" class="promo-logo-img">
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ========================================================================
       CARTELERA DE EVENTOS
       Todo lo de aquí sale de la tabla de flyers: son las solicitudes que el
       equipo aceptó Y marcó como publicadas desde el panel. No hay nada que
       escribir a mano; para quitar un evento de la web se despublica en
       cheve/flyer.php y desaparece de esta lista.
       ======================================================================== -->
  <?php if ($hayCartelera): ?>
    <section class="cartelera-section" id="cartelera">
      <div class="container">
        <div class="section-header">
          <!-- Los textos por defecto están aquí para que la sección se vea
               bien desde el primer evento publicado, aunque nadie haya
               pasado todavía por la pestaña «Cartelera» del panel. -->
          <span class="section-subtitle"><?= ce('cartelera', 'subtitulo', 'Qué hay esta semana') ?></span>
          <h2 class="section-title"><?= ce('cartelera', 'titulo', 'Cartelera de eventos') ?></h2>
          <?php if (c('cartelera', 'descripcion') !== ''): ?>
            <p class="section-description"><?= con_tokens(c('cartelera', 'descripcion')) ?></p>
          <?php endif; ?>
        </div>

        <?php if ($cartelera): ?>
          <div class="cartelera-grid">
            <?php foreach ($cartelera as $ev): ?>
              <?php
              // El evento ya pasó si tiene fecha y es anterior a hoy. Se
              // sigue enseñando (hay planes de varios días) pero atenuado.
              $pasado = !empty($ev['fecha']) && $ev['fecha'] < date('Y-m-d');

              $posX = isset($ev['pos_x']) ? (float) $ev['pos_x'] : 50.0;
              $posY = isset($ev['pos_y']) ? (float) $ev['pos_y'] : 50.0;
              $zoom = isset($ev['zoom']) ? (float) $ev['zoom'] : 1.0;

              // La fecha se parte para pintarla como una hoja de calendario.
              $dia = $mes = '';
              if (!empty($ev['fecha'])) {
                $marca = strtotime($ev['fecha']);
                if ($marca !== false) {
                  $nombresMes = array(
                    1 => 'ENE',
                    'FEB',
                    'MAR',
                    'ABR',
                    'MAY',
                    'JUN',
                    'JUL',
                    'AGO',
                    'SEP',
                    'OCT',
                    'NOV',
                    'DIC'
                  );
                  $dia = date('j', $marca);
                  $mes = $nombresMes[(int) date('n', $marca)];
                }
              }
              ?>
              <article class="evento-card<?= $pasado ? ' evento-pasado' : '' ?>">

                <div class="evento-foto">
                  <img loading="lazy" decoding="async" src="<?= e(url_activo($ev['imagen_publica'])) ?>"
                    alt="Flyer de <?= e($ev['evento']) ?>" style="object-position: <?= css_num($posX) ?>% <?= css_num($posY) ?>%;
                              transform-origin: <?= css_num($posX) ?>% <?= css_num($posY) ?>%;
                              transform: scale(<?= css_num($zoom) ?>);">

                  <?php if ($dia !== ''): ?>
                    <div class="evento-fecha" aria-hidden="true">
                      <span class="evento-dia"><?= e($dia) ?></span>
                      <span class="evento-mes"><?= e($mes) ?></span>
                    </div>
                  <?php endif; ?>
                </div>

                <div class="evento-cuerpo">
                  <h3 class="evento-titulo"><?= e($ev['evento']) ?></h3>

                  <?php if ($ev['subtitulo'] !== ''): ?>
                    <p class="evento-subtitulo"><?= e($ev['subtitulo']) ?></p>
                  <?php endif; ?>

                  <ul class="evento-datos">
                    <?php if (!empty($ev['fecha'])): ?>
                      <li>
                        <i class="fa-solid fa-calendar-days"></i>
                        <time datetime="<?= e($ev['fecha']) ?>"><?= e(date('d/m/Y', strtotime($ev['fecha']))) ?></time>
                        <?php if ($ev['hora'] !== null): ?>
                          <span class="evento-hora"><?= e(substr($ev['hora'], 0, 5)) ?> h</span>
                        <?php endif; ?>
                      </li>
                    <?php endif; ?>

                    <?php if ($ev['ubicacion'] !== ''): ?>
                      <li><i class="fa-solid fa-location-dot"></i> <?= e($ev['ubicacion']) ?></li>
                    <?php endif; ?>

                    <?php if ($ev['precio'] !== ''): ?>
                      <li><i class="fa-solid fa-ticket"></i> <?= e($ev['precio']) ?></li>
                    <?php endif; ?>

                    <?php if ($ev['contacto'] !== ''): ?>
                      <li><i class="fa-solid fa-at"></i> <?= e($ev['contacto']) ?></li>
                    <?php endif; ?>
                  </ul>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <p class="cartelera-vacia">
            <i class="fa-solid fa-calendar-plus"></i>
            <?= con_tokens($textoSinEventos) ?>
          </p>
        <?php endif; ?>

        <?php if (c('cartelera', 'btn_texto') !== ''): ?>
          <div class="cartelera-cta">
            <a href="#contacto" class="btn btn-primary">
              <i class="fa-solid fa-bullhorn"></i> <?= ce('cartelera', 'btn_texto') ?>
            </a>
          </div>
        <?php endif; ?>
      </div>
    </section>
  <?php endif; ?>

  <!-- HOW IT WORKS SECTION -->
  <section class="how-it-works-section" id="como-funciona">
    <div class="container">
      <div class="section-header">
        <span class="section-subtitle"><?= ce('como_funciona', 'subtitulo') ?></span>
        <h2 class="section-title"><?= ce('como_funciona', 'titulo') ?></h2>
        <p class="section-description">
          <?= ce('como_funciona', 'descripcion') ?>
        </p>
      </div>

      <div class="steps-grid">
        <?php for ($n = 1; $n <= 4; $n++): ?>
          <div class="step-card">
            <div class="step-icon-header">
              <div class="step-number"><?= $n ?></div>
              <i class="<?= ce('como_funciona', 'paso' . $n . '_icono') ?> step-icon"></i>
            </div>
            <h3 class="step-title"><?= ce('como_funciona', 'paso' . $n . '_titulo') ?></h3>
            <p class="step-desc">
              <?= ce('como_funciona', 'paso' . $n . '_texto') ?>
            </p>
          </div>
        <?php endfor; ?>
      </div>
    </div>
  </section>

  <!-- INSTAGRAM REPOST SECTION -->
  <section class="instagram-section" id="instagram">
    <div class="container">
      <div class="instagram-card">
        <div class="instagram-grid">
          <div class="instagram-content">
            <span class="instagram-badge">
              <i class="fa-brands fa-instagram"></i> <?= ce('instagram', 'badge') ?>
            </span>
            <h2 class="instagram-title"><?= ce('instagram', 'titulo') ?></h2>
            <p class="instagram-desc">
              <?= con_tokens(
                c('instagram', 'descripcion'),
                array('handle' => '<strong class="highlight-tag">' . e($handle) . '</strong>')
              ) ?>
            </p>
            <div class="instagram-actions">
              <a href="<?= e(c('global', 'url_instagram', '#')) ?>" target="_blank" rel="noopener noreferrer"
                class="btn btn-instagram">
                <i class="fa-brands fa-instagram"></i> <?= ce('instagram', 'btn_texto') ?>
              </a>
            </div>
          </div>

          <div class="instagram-image-wrapper">
            <div class="instagram-frame" id="igCarouselFrame">
              <!-- Carousel Images -->
              <div class="ig-carousel-slides">
                <img loading="lazy" decoding="async" src="<?= cimg('instagram', 'imagen_1') ?>"
                  alt="Historia de Instagram en evento de Uruaplan" class="ig-slide active" id="igSlide1">
                <img loading="lazy" decoding="async" src="<?= cimg('instagram', 'imagen_2') ?>"
                  alt="Selfie de fiesta en evento de Uruaplan" class="ig-slide" id="igSlide2">
              </div>

              <!-- Instagram Story Progress Indicators -->
              <div class="ig-carousel-indicators" id="igCarouselIndicators">
                <span class="ig-dot active" data-slide="0"></span>
                <span class="ig-dot" data-slide="1"></span>
              </div>

              <div class="instagram-overlay-badge">
                <i class="fa-brands fa-instagram"></i> <?= ce('instagram', 'badge_overlay') ?>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- GALLERY SECTION -->
  <section class="gallery-section" id="galeria">
    <div class="gallery-section-header">
      <span class="section-subtitle"><?= ce('galeria', 'subtitulo') ?></span>
      <h2 class="section-title"><?= ce('galeria', 'titulo') ?></h2>
      <p class="section-description"><?= ce('galeria', 'descripcion') ?></p>
    </div>

    <div class="gallery-collage" id="galleryCollage">
      <?php foreach ($galeriaVisible as $i => $item): ?>
        <?php
        $archivo = (string) $item['archivo'];
        $etiqueta = isset($item['etiqueta']) ? (string) $item['etiqueta'] : '';
        $alt = isset($item['alt']) && $item['alt'] !== '' ? (string) $item['alt'] : $etiqueta;
        $url = url_activo($archivo);

        // Las clases gi-1 … gi-13 son las que definen el mosaico en el CSS.
        // Si hay más elementos que clases, se reinicia el ciclo para que
        // el collage siga cuadrando visualmente.
        $clasePosicion = 'gi-' . (($i % 13) + 1);
        $esVideo = (bool) preg_match('~\.(mp4|mov)$~i', $archivo);

        // Encuadre elegido en el panel. Solo se emite el atributo style
        // cuando algo se movió de su valor por defecto, para no ensuciar
        // el HTML con reglas que no hacen nada.
        $px = isset($item['pos_x']) ? (float) $item['pos_x'] : 50.0;
        $py = isset($item['pos_y']) ? (float) $item['pos_y'] : 50.0;
        $zoom = isset($item['zoom']) ? (float) $item['zoom'] : 1.0;

        $estilo = '';
        if (abs($px - 50) > 0.01 || abs($py - 50) > 0.01 || abs($zoom - 1) > 0.001) {
          $estilo = '--enc-pos:' . css_num($px) . '% ' . css_num($py) . '%;'
            . '--enc-zoom:' . css_num($zoom, 3) . ';';
        }
        ?>
        <div class="gallery-item <?= e($clasePosicion) ?>" data-label="<?= e($etiqueta) ?>" data-src="<?= e($url) ?>"
          data-tipo="<?= $esVideo ? 'video' : 'imagen' ?>" data-alt="<?= e($alt) ?>" <?= $estilo !== '' ? 'style="' . e($estilo) . '"' : '' ?>>
          <?php if ($esVideo): ?>
            <video src="<?= e($url) ?>" autoplay loop muted playsinline preload="metadata"></video>
            <div class="video-badge"><i class="fa-solid fa-play"></i> Video</div>
          <?php else: ?>
            <img src="<?= e($url) ?>" alt="<?= e($alt) ?>" loading="lazy">
          <?php endif; ?>
          <?php if ($etiqueta !== ''): ?>
            <div class="gallery-label"><?= e($etiqueta) ?></div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- LIGHTBOX DE LA GALERÍA -->
  <!-- Lo rellena js/main.js al tocar cualquier foto del collage. -->
  <div class="lightbox" id="lightbox" role="dialog" aria-modal="true" aria-label="Imagen ampliada">
    <button class="lightbox-close" id="lightboxClose" aria-label="Cerrar (Esc)">&times;</button>

    <button class="lightbox-nav lightbox-prev" id="lightboxPrev" aria-label="Anterior">
      <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
    </button>

    <div class="lightbox-stage" id="lightboxStage"></div>

    <button class="lightbox-nav lightbox-next" id="lightboxNext" aria-label="Siguiente">
      <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
    </button>

    <p class="lightbox-hint" aria-hidden="true">Usa ← → para navegar · Esc para cerrar</p>

    <div class="lightbox-caption">
      <span class="lightbox-label" id="lightboxLabel"></span>
      <span class="lightbox-counter" id="lightboxCounter"></span>
    </div>
  </div>

  <!-- CONTACT & FORM SECTION WITH LIVE FLYER PREVIEW -->
  <section class="contact-section" id="contacto">
    <div class="container">
      <div class="section-header">
        <span class="section-subtitle"><?= ce('contacto', 'subtitulo') ?></span>
        <h2 class="section-title"><?= ce('contacto', 'titulo') ?></h2>
        <p class="section-description"><?= ce('contacto', 'descripcion') ?></p>
      </div>

      <!-- Contact Info Cards Bar -->
      <div class="contact-info-bar">
        <div class="contact-card-mini">
          <i class="fa-solid fa-envelope"></i>
          <div>
            <span><?= ce('contacto', 'tarjeta1_label') ?></span>
            <strong><?= e($correo) ?></strong>
          </div>
        </div>

        <div class="contact-card-mini">
          <i class="fa-brands fa-whatsapp"></i>
          <div>
            <span><?= ce('contacto', 'tarjeta2_label') ?></span>
            <strong><?= ce('global', 'whatsapp_visible') ?></strong>
          </div>
        </div>

        <div class="contact-card-mini">
          <i class="fa-solid fa-location-dot"></i>
          <div>
            <span><?= ce('contacto', 'tarjeta3_label') ?></span>
            <strong><?= ce('global', 'ubicacion_corta') ?></strong>
          </div>
        </div>
      </div>

      <!-- Free Publication & Social Media Banner -->
      <div class="free-promo-banner">
        <div class="free-badge">
          <i class="fa-solid fa-bolt"></i> <?= ce('contacto', 'free_badge') ?>
        </div>
        <div class="free-promo-text">
          <?= con_tokens(c('contacto', 'free_texto')) ?>
          <strong class="social-highlight">
            <i class="fa-brands fa-tiktok"></i> TikTok,
            <i class="fa-brands fa-facebook-f"></i> Facebook e
            <i class="fa-brands fa-instagram"></i> Instagram
          </strong>.
        </div>
      </div>

      <!-- Moderation / Approval Policy Note -->
      <div class="approval-policy-note">
        <i class="fa-solid fa-shield-halved policy-icon"></i>
        <span><strong><?= ce('contacto', 'nota_titulo') ?></strong>
          <?= con_tokens(c('contacto', 'nota_texto')) ?></span>
      </div>

      <div class="contact-grid">
        <!-- Contact Form -->
        <div class="contact-form-container">
          <form id="contactForm" action="enviar-evento.php" method="POST" enctype="multipart/form-data">
            <?php campos_antispam(); ?>

            <div class="form-header-badge">
              <i class="fa-solid fa-paper-plane"></i>
              <span>Envío directo a <strong><?= e($correoForm) ?></strong></span>
            </div>

            <!-- Resultado del envío. Con JavaScript lo rellena main.js; sin él
                 llega ya escrito desde enviar-evento.php. -->
            <div id="formMensaje" class="form-mensaje <?= $avisoEnvio !== '' ? ($envioOk ? 'ok' : 'error') : '' ?>"
              role="status" aria-live="polite" <?= $avisoEnvio === '' ? 'hidden' : '' ?>><?= e($avisoEnvio) ?></div>

            <div class="form-row">
              <div class="form-group">
                <label for="formEventName" class="form-label"><i class="fa-solid fa-heading"></i> Nombre del Evento
                  *</label>
                <input type="text" id="formEventName" name="evento" class="form-control"
                  placeholder="Ej. Artes Visuales &amp; Literatura" maxlength="80" required>
                <small class="char-counter" data-for="formEventName"></small>
              </div>

              <div class="form-group">
                <label for="formSubtitle" class="form-label"><i class="fa-solid fa-font"></i> Subtítulo *</label>
                <input type="text" id="formSubtitle" name="subtitulo" class="form-control"
                  placeholder="Ej. Exposición Colectiva y Libro" maxlength="90" required>
                <small class="char-counter" data-for="formSubtitle"></small>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label for="formDate" class="form-label"><i class="fa-solid fa-calendar-days"></i> Fecha *</label>
                <input type="date" id="formDate" name="fecha" class="form-control" required>
              </div>

              <div class="form-group">
                <label for="formTime" class="form-label"><i class="fa-solid fa-clock"></i> Hora *</label>
                <input type="time" id="formTime" name="hora" class="form-control" required>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label for="formContact" class="form-label"><i class="fa-solid fa-address-book"></i> Contacto *</label>
                <input type="text" id="formContact" name="contacto" class="form-control"
                  placeholder="Ej. @cero_talleraleria / 452 123 4567" maxlength="60" required>
                <small class="char-counter" data-for="formContact"></small>
              </div>

              <div class="form-group">
                <label for="formPrice" class="form-label"><i class="fa-solid fa-ticket"></i> Precio *</label>
                <input type="text" id="formPrice" name="precio" class="form-control"
                  placeholder="Ej. Entrada libre / $150 MXN" maxlength="60" required>
                <small class="char-counter" data-for="formPrice"></small>
              </div>
            </div>

            <div class="form-group">
              <label for="formLocation" class="form-label"><i class="fa-solid fa-location-dot"></i> Ubicación *</label>
              <input type="text" id="formLocation" name="ubicacion" class="form-control"
                placeholder="Ej. 2calle 1ra Privada de Juárez #31, Uruapan" maxlength="120" required>
              <small class="char-counter" data-for="formLocation"></small>
            </div>

            <div class="form-group">
              <label for="formMainPhoto" class="form-label"><i class="fa-solid fa-image"></i> Foto Principal *</label>
              <input type="file" id="formMainPhoto" name="foto" class="form-control file-input" accept="image/*"
                required>
              <small class="file-hint">Imagen central de la obra / flyer</small>
            </div>

            <div class="form-group">
              <label for="formOrgLogo" class="form-label">
                <i class="fa-solid fa-copyright"></i> Logo de tu Marca / Organizador <span style="font-weight: normal; opacity: 0.75; font-size: 0.85em;">(Opcional)</span>
              </label>
              <input type="file" id="formOrgLogo" name="logo_organizador" class="form-control file-input" accept="image/png,image/jpeg,image/webp">
              <small class="file-hint">*Opcional: PNG transparente o JPG. Aparecerá en la esquina superior derecha de tu flyer.*</small>
            </div>

            <div class="form-group">
              <label for="formDescription" class="form-label"><i class="fa-solid fa-align-left"></i> Descripción
                *</label>
              <textarea id="formDescription" name="descripcion" class="form-control" rows="4"
                placeholder="Detalles de la exposición, conversatorio o espectáculo..." maxlength="250"
                required></textarea>
              <small class="char-counter" data-for="formDescription"></small>
            </div>

            <div class="form-group">
              <label for="formComments" class="form-label"><i class="fa-solid fa-comment-dots"></i> Comentarios
                Adicionales (Opcional)</label>
              <textarea id="formComments" name="comentarios" class="form-control" rows="3" maxlength="600"
                placeholder="Escribe aquí cualquier indicación especial, duda o nota privada para nuestro equipo..."></textarea>
              <small class="file-hint"><i class="fa-solid fa-lock"></i> *Este campo se enviará únicamente por correo a
                nuestro equipo y <strong>NO aparecerá en el flyer</strong>.*</small>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%;">
              <i class="fa-solid fa-paper-plane"></i> <?= ce('contacto', 'btn_enviar') ?> <?= e($correoForm) ?>
            </button>

            <p class="form-privacy-hint" style="font-size:0.8rem;opacity:0.85;margin-top:0.75rem;text-align:center;">
              <i class="fa-solid fa-user-shield"></i> Al enviar la solicitud aceptas nuestro
              <a href="#modalPrivacidad" class="abrir-privacidad" style="text-decoration:underline;color:inherit;font-weight:600;">Aviso de Privacidad (LFPDPPP)</a>.
            </p>
          </form>
        </div>

        <!-- Live Flyer Preview -->
        <div class="flyer-preview-wrapper">
          <div class="flyer-badge">
            <i class="fa-solid fa-wand-magic-sparkles"></i> <?= ce('contacto', 'flyer_badge') ?>
          </div>

          <div class="flyer-card" id="flyerCard">
            <!-- Imagen principal del evento como fondo a sangre -->
            <img loading="lazy" decoding="async" id="flyerPreviewMainImg" class="flyer-bg-img"
              src="<?= cimg('contacto', 'flyer_imagen') ?>" alt="Foto Principal del Evento">

            <!-- Capa de oscurecimiento translúcida para alto contraste -->
            <div class="flyer-overlay"></div>

            <!-- Contenido interno del flyer -->
            <div class="flyer-inner-content">

              <!-- Encabezado: Logo Uruaplan (Izquierda) y Logo Organizador (Derecha) -->
              <div class="flyer-header">
                <img loading="lazy" decoding="async"
                  src="<?= cimg('global', 'logo_header') ?: 'img/logos/logo_header.png' ?>" alt="Uruaplan - Qué Plan?"
                  class="flyer-logo">
                <img loading="lazy" decoding="async" id="flyerPreviewOrgLogo" src="" alt="Logo Organizador" class="flyer-logo-org" style="display: none;">
              </div>

              <!-- Título y Subtítulo/Organizadores centrados -->
              <div class="flyer-titles-wrapper">
                <h3 class="flyer-title" id="flyerPreviewTitle"><?= ce('contacto', 'flyer_titulo') ?></h3>
                <p class="flyer-subtitle" id="flyerPreviewSubtitle"><?= ce('contacto', 'flyer_subtitulo') ?></p>
              </div>

              <!-- Separador 1 -->
              <div class="flyer-divider"></div>

              <!-- Bloque de Fecha y Hora -->
              <div class="flyer-datetime-block">
                <div class="flyer-datetime-header">
                  <span class="flyer-datetime-text" id="flyerPreviewDate"><?= ce('contacto', 'flyer_fecha') ?></span>
                  <span class="flyer-datetime-slash">/</span>
                  <span class="flyer-datetime-text" id="flyerPreviewTime"><?= ce('contacto', 'flyer_hora') ?></span>
                </div>
                <div class="flyer-datetime-icons">
                  <div class="flyer-icon-outline"><i class="fa-regular fa-calendar-days"></i></div>
                  <div class="flyer-icon-outline"><i class="fa-regular fa-clock"></i></div>
                </div>
              </div>

              <!-- Descripción con separador superior pegado (solo visible si se especifica texto) -->
              <?php $descInicial = c('contacto', 'flyer_descripcion'); ?>
              <div class="flyer-desc-wrapper" id="flyerPreviewDescWrapper" style="<?= $descInicial !== '' ? '' : 'display: none;' ?>">
                <div class="flyer-divider"></div>
                <p id="flyerPreviewDesc"><?= e($descInicial) ?></p>
              </div>

              <!-- Rejilla Inferior: Precio (izq) y Ubicación (der) -->
              <div class="flyer-bottom-grid">
                <div class="flyer-bottom-item">
                  <div class="flyer-icon-circle"><i class="fa-solid fa-dollar-sign"></i></div>
                  <span id="flyerPreviewPrice"><?= ce('contacto', 'flyer_precio') ?></span>
                </div>

                <div class="flyer-bottom-item">
                  <div class="flyer-icon-circle"><i class="fa-solid fa-location-dot"></i></div>
                  <span id="flyerPreviewLocation"><?= ce('contacto', 'flyer_ubicacion') ?></span>
                </div>
              </div>

              <!-- Contacto / Redes del organizador -->
              <div class="flyer-contact-wrapper">
                <div class="flyer-icon-circle"><i class="fa-regular fa-user"></i></div>
                <span id="flyerPreviewContact"><?= ce('contacto', 'flyer_contacto') ?></span>
              </div>

              <!-- Footer tag @URUAPLAN -->
              <div class="flyer-footer">
                <span class="flyer-footer-tag"><?= ce('contacto', 'flyer_footer') ?></span>
              </div>
            </div>
          </div>

          <p class="flyer-representation-note">
            <i class="fa-solid fa-circle-info"></i> <?= ce('contacto', 'nota_flyer') ?>
          </p>
        </div>
      </div>
    </div>
  </section>

  <!-- SPECIALIZED PROMOTION SECTION AT THE BOTTOM -->
  <section class="specialized-promo-section" id="promocion-especializada">
    <div class="container">
      <div class="specialized-promo-card">
        <div class="specialized-promo-header">
          <span class="specialized-badge">
            <i class="fa-solid fa-crown"></i> <?= ce('especializada', 'badge') ?>
          </span>
          <h3 class="specialized-title"><?= ce('especializada', 'titulo') ?></h3>
          <p class="specialized-desc">
            <?= con_tokens(c('especializada', 'descripcion')) ?>
          </p>
        </div>

        <div class="specialized-contact-grid">
          <div class="specialized-contact-box">
            <div class="specialized-icon-wrapper whatsapp-icon">
              <i class="fa-brands fa-whatsapp"></i>
            </div>
            <div class="specialized-contact-info">
              <span class="specialized-label"><?= ce('especializada', 'wa_label') ?></span>
              <strong class="specialized-value"><?= ce('global', 'whatsapp_visible') ?></strong>
            </div>
            <a href="<?= enlace_wa($wa, c('especializada', 'wa_mensaje')) ?>" target="_blank" rel="noopener noreferrer"
              class="btn btn-whatsapp-sm">
              <i class="fa-brands fa-whatsapp"></i> <?= ce('especializada', 'wa_btn') ?>
            </a>
          </div>

          <div class="specialized-contact-box">
            <div class="specialized-icon-wrapper email-icon">
              <i class="fa-solid fa-envelope"></i>
            </div>
            <div class="specialized-contact-info">
              <span class="specialized-label"><?= ce('especializada', 'mail_label') ?></span>
              <strong class="specialized-value"><?= e($correo) ?></strong>
            </div>
            <a href="<?= enlace_mailto($correo, c('especializada', 'mail_asunto'), c('especializada', 'mail_cuerpo')) ?>"
              class="btn btn-outline-lime-sm">
              <i class="fa-solid fa-paper-plane"></i> <?= ce('especializada', 'mail_btn') ?>
            </a>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- FOOTER -->
  <footer class="footer">
    <div class="container">
      <div class="footer-grid">
        <div class="footer-brand">
          <a href="#inicio" class="logo-wrapper">
            <img loading="lazy" decoding="async" src="<?= cimg('global', 'logo_header') ?>" alt="Uruaplan"
              class="logo-img">
          </a>
          <p>
            <?= con_tokens(c('footer', 'parrafo')) ?>
          </p>
          <div class="social-links">
            <a href="<?= e(c('global', 'url_instagram', '#')) ?>" class="social-icon" target="_blank"
              rel="noopener noreferrer" aria-label="Instagram"><i class="fa-brands fa-instagram"
                aria-hidden="true"></i></a>
            <a href="<?= e(c('global', 'url_facebook', '#')) ?>" class="social-icon" target="_blank"
              rel="noopener noreferrer" aria-label="Facebook"><i class="fa-brands fa-facebook-f"
                aria-hidden="true"></i></a>
            <a href="<?= e(c('global', 'url_tiktok', '#')) ?>" class="social-icon" target="_blank"
              rel="noopener noreferrer" aria-label="TikTok"><i class="fa-brands fa-tiktok" aria-hidden="true"></i></a>
            <a href="<?= enlace_wa($wa, 'Hola Uruaplan!') ?>" class="social-icon" target="_blank"
              rel="noopener noreferrer" aria-label="WhatsApp"><i class="fa-brands fa-whatsapp"
                aria-hidden="true"></i></a>
          </div>
        </div>

        <?php
        // Los destinos de los enlaces son fijos (son anclas de esta página);
        // desde el panel solo se edita el texto que se ve.
        $anclasNav = array('#inicio', '#nosotros', '#como-funciona', '#galeria', '#promociona');
        ?>
        <nav class="footer-col" aria-label="Navegación del sitio">
          <h4 class="footer-title"><?= ce('footer', 'nav_titulo') ?></h4>
          <ul class="footer-links">
            <?php foreach ($anclasNav as $idx => $ancla): ?>
              <?php $texto = c('footer', 'nav_' . ($idx + 1)); ?>
              <?php if ($texto !== ''): ?>
                <li><a href="<?= e($ancla) ?>"><?= e($texto) ?></a></li>
              <?php endif; ?>
            <?php endforeach; ?>
          </ul>
        </nav>

        <div class="footer-col">
          <h4 class="footer-title"><?= ce('footer', 'contacto_titulo') ?></h4>
          <ul class="footer-contact">
            <li>
              <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
              <span><?= ce('global', 'ubicacion_larga') ?></span>
            </li>
            <li>
              <a href="<?= enlace_wa($wa, 'Hola Uruaplan!') ?>" target="_blank" rel="noopener noreferrer">
                <i class="fa-brands fa-whatsapp" aria-hidden="true"></i>
                <span><?= ce('global', 'whatsapp_visible') ?></span>
              </a>
            </li>
            <li>
              <a href="<?= enlace_mailto($correo) ?>">
                <i class="fa-solid fa-envelope" aria-hidden="true"></i>
                <span><?= e($correo) ?></span>
              </a>
            </li>
          </ul>
          <a href="#contacto" class="btn btn-outline footer-cta">
            <i class="fa-solid fa-bullhorn" aria-hidden="true"></i> <?= ce('footer', 'btn_cta') ?>
          </a>
        </div>
      </div>

      <div class="footer-bottom">
        <p>&copy; <span id="footerYear"><?= date('Y') ?></span> <?= ce('footer', 'copyright') ?></p>
        <p class="footer-made">
          <?= con_tokens(
            c('footer', 'hecho_con'),
            array('corazon' => '<i class="fa-solid fa-heart" aria-hidden="true"></i>')
          ) ?>
        </p>

        <?php
        // Firma del estudio. Si el nombre está vacío en el panel, la línea
        // entera desaparece en lugar de quedar un enlace suelto sin texto.
        $firmaMarca = c('footer', 'firma_marca');
        $firmaUrl = c('footer', 'firma_url');
        ?>
        <?php if ($firmaMarca !== ''): ?>
          <p class="footer-signature">
            <?php $firmaTexto = c('footer', 'firma_texto'); ?>
            <?php if ($firmaTexto !== ''): ?><span><?= e($firmaTexto) ?></span><?php endif; ?>
            <?php if ($firmaUrl !== ''): ?>
              <a href="<?= e($firmaUrl) ?>" target="_blank" rel="noopener noreferrer"
                aria-label="<?= e($firmaMarca . ' en Instagram') ?>">
                <i class="fa-brands fa-instagram" aria-hidden="true"></i><?= e($firmaMarca) ?>
              </a>
            <?php else: ?>
              <strong><?= e($firmaMarca) ?></strong>
            <?php endif; ?>
          </p>
        <?php endif; ?>

        <p class="footer-privacy" style="margin-top:0.75rem;">
          <a href="#modalPrivacidad" class="btn-privacy-pill abrir-privacidad">
            <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
            <span>Aviso de Privacidad</span>
          </a>
        </p>
      </div>
    </div>
  </footer>

  <!-- MODAL AVISO DE PRIVACIDAD (LFPDPPP) -->
  <div class="lightbox" id="modalPrivacidad" role="dialog" aria-modal="true" aria-label="Aviso de Privacidad">
    <div class="privacy-modal-card">
      <button class="lightbox-close" id="cerrarModalPrivacidad" aria-label="Cerrar aviso de privacidad">&times;</button>
      
      <div class="privacy-modal-header">
        <div class="privacy-modal-icon">
          <i class="fa-solid fa-shield-halved"></i>
        </div>
        <div>
          <span class="privacy-modal-badge">Protección de Datos &amp; LFPDPPP</span>
          <h3 class="privacy-modal-title">Aviso de Privacidad</h3>
        </div>
      </div>

      <p class="privacy-intro-text">
        Conforme a la <strong>Ley Federal de Protección de Datos Personales en Posesión de los Particulares (LFPDPPP)</strong>, Uruaplan le informa sobre el tratamiento transparente de la información recabada al utilizar el formulario «Promociona tu Plan»:
      </p>

      <div class="privacy-grid">
        <div class="privacy-grid-item">
          <i class="fa-solid fa-user-check privacy-item-icon"></i>
          <div class="privacy-item-content">
            <h4>Datos Recabados</h4>
            <p>Información del evento/obra, datos de contacto de quien publica, fotografía representativa, dirección IP y agente de usuario. Opcionalmente coordenadas GPS si decide compartirlas.</p>
          </div>
        </div>

        <div class="privacy-grid-item">
          <i class="fa-solid fa-bullhorn privacy-item-icon"></i>
          <div class="privacy-item-content">
            <h4>Finalidad del Tratamiento</h4>
            <p>Moderación de contenido, verificación antispam para protección del sitio, publicación en la cartelera digital y redes sociales autorizadas, y contacto para aclaraciones.</p>
          </div>
        </div>

        <div class="privacy-grid-item">
          <i class="fa-solid fa-clock-rotate-left privacy-item-icon"></i>
          <div class="privacy-item-content">
            <h4>Retención de Datos</h4>
            <p>Los registros técnicos de seguridad (IP y User-Agent) se eliminan automáticamente tras <strong>6 meses</strong> mediante nuestro sistema de purga automatizada.</p>
          </div>
        </div>

        <div class="privacy-grid-item">
          <i class="fa-solid fa-lock privacy-item-icon"></i>
          <div class="privacy-item-content">
            <h4>Transferencia a Terceros</h4>
            <p>Sus datos personales nunca son vendidos, arrendados ni compartidos con terceros ajenos a la plataforma Uruaplan.</p>
          </div>
        </div>
      </div>

      <div class="privacy-modal-footer">
        <div class="privacy-arco-note">
          <i class="fa-solid fa-envelope"></i> Derechos ARCO: Escríbenos a <code><?= e($correoForm) ?></code>
        </div>
        <button type="button" class="btn-close-privacy" onclick="document.getElementById('modalPrivacidad').classList.remove('active');">
          Entendido
        </button>
      </div>
    </div>
  </div>

  <!-- Custom Script -->
  <script src="<?= versionado('js/main.js', SITIO_DIR . '/js/main.js') ?>"></script>
</body>

</html>