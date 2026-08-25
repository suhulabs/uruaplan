<?php
/**
 * ===========================================================================
 *  INSTALADOR
 * ===========================================================================
 *  Se abre UNA vez, después de crear la base de datos en cPanel y de escribir
 *  las credenciales en includes/config-bd.php. Hace tres cosas:
 *
 *    1. Comprueba que se puede hablar con MySQL y lo dice claro si no.
 *    2. Crea las tablas que falten (nunca borra las que ya están).
 *    3. Siembra el contenido inicial: si todavía existen los JSON de la
 *       versión anterior los importa, para que la web quede exactamente
 *       igual que antes de la migración.
 *
 *  QUIÉN PUEDE ABRIRLA
 *  -------------------
 *  Esta página enseña detalles del servidor, así que no puede quedar
 *  abierta al mundo. La regla es simple:
 *
 *    - Mientras NO haya ningún usuario en la base, es una instalación
 *      recién estrenada y cualquiera que llegue puede terminarla (es la
 *      única forma de crear al primer usuario).
 *    - En cuanto hay usuarios, exige sesión iniciada como cualquier otra
 *      página del panel.
 *
 *  Una vez instalado se puede borrar del servidor sin problema; también
 *  sirve de página de diagnóstico si algo se rompe más adelante.
 * ===========================================================================
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/funciones.php';
require_once __DIR__ . '/includes/bd.php';
require_once __DIR__ . '/includes/esquema-bd.php';
require_once __DIR__ . '/includes/esquema.php';
require_once __DIR__ . '/includes/contenido.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/flyers.php';

iniciar_sesion_panel();

// ---------------------------------------------------------------------------
// ESTADO ACTUAL
// ---------------------------------------------------------------------------

$errorConexion = '';
$conectada     = bd_disponible($errorConexion);

$tablasFaltan = array();
$hayUsuarios  = false;

if ($conectada) {
    foreach (array_keys(bd_tablas()) as $nombre) {
        if (!bd_tabla_existe($nombre)) {
            $tablasFaltan[] = $nombre;
        }
    }

    if (bd_tabla_existe('usuarios')) {
        $hayUsuarios = (int) bd_valor('SELECT COUNT(*) FROM usuarios', array(), 0) > 0;
    }
}

// --- Control de acceso -----------------------------------------------------
// En cuanto existe el primer usuario, esta página deja de ser pública.
$sesion = $conectada && $hayUsuarios ? sesion_activa() : true;

if (!$sesion) {
    header('Location: index.php?e=sesion');
    exit;
}

// ---------------------------------------------------------------------------
// ACCIONES
// ---------------------------------------------------------------------------

$hechos  = array();
$fallos  = array();
$avisos  = array();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $conectada) {
    csrf_exigir();

    $accion = isset($_POST['accion']) ? (string) $_POST['accion'] : '';

    try {
        if ($accion === 'instalar') {

            // --- 1. Tablas -------------------------------------------------
            $tablas = bd_crear_tablas();

            if ($tablas['creadas']) {
                $hechos[] = 'Se crearon ' . count($tablas['creadas']) . ' tabla(s): '
                    . implode(', ', $tablas['creadas']) . '.';
            } else {
                $hechos[] = 'Las tablas ya estaban creadas; no hubo que tocar nada.';
            }

            // --- 2. Carpeta privada de los flyers --------------------------
            $errorCarpeta = flyers_preparar_carpeta();
            if ($errorCarpeta !== '') {
                $avisos[] = $errorCarpeta;
            } else {
                $hechos[] = 'La carpeta privada de las fotos de flyers está lista.';
            }

            // --- 3. Contenido ----------------------------------------------
            $origen = leer_json(ARCHIVO_CONTENIDO, array());

            if ($origen) {
                $sembrado = contenido_sembrar($origen);
                $hechos[] = 'Se importaron ' . $sembrado['campos'] . ' campo(s) de texto y '
                    . $sembrado['filas'] . ' elemento(s) de galería y portada desde el '
                    . 'contenido.json anterior.';
            } else {
                $avisos[] = 'No se encontró cheve/data/contenido.json, así que el contenido '
                    . 'empieza vacío. Rellénalo desde el panel.';
            }

            // --- 4. Usuarios ------------------------------------------------
            $usuariosJson = leer_json(ARCHIVO_USUARIOS, array());

            if (!empty($usuariosJson['usuarios']) && is_array($usuariosJson['usuarios'])) {
                $importados = 0;

                foreach ($usuariosJson['usuarios'] as $u) {
                    if (empty($u['id']) || empty($u['hash'])) {
                        continue;
                    }

                    // Si ya está en la base no se pisa: su contraseña puede
                    // haber cambiado desde el panel después de migrar.
                    if (buscar_usuario_por_id($u['id']) !== null) {
                        continue;
                    }

                    guardar_usuario(
                        $u['id'],
                        isset($u['nombre']) ? $u['nombre'] : $u['id'],
                        $u['hash'],
                        isset($u['alias']) && is_array($u['alias']) ? $u['alias'] : array($u['id']),
                        !empty($u['debe_cambiar'])
                    );

                    $importados++;
                }

                if ($importados > 0) {
                    $hechos[] = 'Se importaron ' . $importados . ' usuario(s) con su contraseña '
                        . 'actual intacta: se entra igual que antes.';
                } else {
                    $hechos[] = 'Los usuarios ya estaban en la base de datos.';
                }
            }

            // Estado nuevo tras instalar.
            $hayUsuarios = (int) bd_valor('SELECT COUNT(*) FROM usuarios', array(), 0) > 0;

            $tablasFaltan = array();
            foreach (array_keys(bd_tablas()) as $nombre) {
                if (!bd_tabla_existe($nombre)) {
                    $tablasFaltan[] = $nombre;
                }
            }

        } elseif ($accion === 'primer_usuario') {

            $id     = mb_strtolower(trim((string) (isset($_POST['id']) ? $_POST['id'] : '')), 'UTF-8');
            $nombre = trim((string) (isset($_POST['nombre']) ? $_POST['nombre'] : ''));
            $alias2 = mb_strtolower(trim((string) (isset($_POST['alias2']) ? $_POST['alias2'] : '')), 'UTF-8');
            $pass   = (string) (isset($_POST['password']) ? $_POST['password'] : '');
            $pass2  = (string) (isset($_POST['password2']) ? $_POST['password2'] : '');
            $codigoIngresado = (string) (isset($_POST['codigo_instalacion']) ? $_POST['codigo_instalacion'] : '');

            // Verificar si hay un CODIGO_INSTALACION configurado en credenciales_uruaplan.php
            $rutaCred = dirname(__DIR__, 4) . '/credenciales_uruaplan.php';
            $credExt  = file_exists($rutaCred) ? require $rutaCred : array();
            $codigoRequerido = isset($credExt['CODIGO_INSTALACION']) ? trim((string) $credExt['CODIGO_INSTALACION']) : '';

            if ($codigoRequerido !== '' && !hash_equals($codigoRequerido, $codigoIngresado)) {
                $fallos[] = 'El código de instalación es incorrecto. Verifícalo en credenciales_uruaplan.php.';
            } elseif (!preg_match('/^[a-z0-9_]{3,20}$/', $id)) {
                $fallos[] = 'El usuario debe tener entre 3 y 20 caracteres y solo letras '
                    . 'minúsculas sin acentos, números o guion bajo.';
            } elseif ($alias2 !== '' && !preg_match('/^[a-z0-9_]{3,20}$/', $alias2)) {
                $fallos[] = 'El segundo alias tiene el mismo formato que el usuario: '
                    . 'minúsculas sin acentos, números o guion bajo.';
            } elseif ($nombre === '') {
                $fallos[] = 'Escribe el nombre completo de la persona.';
            } else {
                $errorPass = validar_password_nueva($pass, $pass2);

                if ($errorPass !== '') {
                    $fallos[] = $errorPass;
                } else {
                    $alias = array($id);
                    if ($alias2 !== '' && $alias2 !== $id) {
                        $alias[] = $alias2;
                    }

                    guardar_usuario(
                        $id,
                        $nombre,
                        password_hash($pass, PASSWORD_DEFAULT),
                        $alias,
                        false          // la eligió la propia persona: no hay que cambiarla
                    );

                    $hayUsuarios = true;
                    $hechos[] = 'Usuario «' . $id . '» creado. Ya puedes entrar al panel.';
                }
            }
        }

    } catch (BdError $e) {
        $fallos[] = $e->getMessage();
    }
}

$listo = $conectada && !$tablasFaltan && $hayUsuarios;

/** Atajo de escapado para esta página. */
function h($t)
{
    return htmlspecialchars((string) $t, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>Instalación · Panel Uruaplan</title>
  <link rel="icon" href="../img/logos/logo.png">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="<?= versionado('assets/panel.css', CHEVE_DIR . '/assets/panel.css') ?>">
</head>

<body class="pagina-panel">

  <header class="barra-superior">
    <div class="barra-marca">
      <img src="../img/logos/logo.png" alt="" onerror="this.style.display='none'">
      <span>Instalación del panel</span>
    </div>
    <nav class="barra-nav">
      <?php if ($listo): ?>
        <a href="index.php"><i class="fa-solid fa-right-to-bracket"></i><span>Ir al panel</span></a>
      <?php endif; ?>
    </nav>
  </header>

  <main class="contenido-panel">

    <div class="encabezado-pagina">
      <div>
        <h1>Instalación</h1>
        <p>Crea las tablas de la base de datos y trae el contenido que ya tenías.
           Puedes volver a abrir esta página cuando quieras: no borra nada.</p>
      </div>
    </div>

    <?php foreach ($fallos as $msg): ?>
      <div class="alerta alerta-error">
        <i class="fa-solid fa-triangle-exclamation"></i><div><?= h($msg) ?></div>
      </div>
    <?php endforeach; ?>

    <?php if ($hechos): ?>
      <div class="alerta alerta-ok">
        <i class="fa-solid fa-circle-check"></i>
        <div><strong>Listo:</strong>
          <ul><?php foreach ($hechos as $msg): ?><li><?= h($msg) ?></li><?php endforeach; ?></ul>
        </div>
      </div>
    <?php endif; ?>

    <?php foreach ($avisos as $msg): ?>
      <div class="alerta alerta-aviso">
        <i class="fa-solid fa-circle-exclamation"></i><div><?= h($msg) ?></div>
      </div>
    <?php endforeach; ?>

    <!-- ================= PASO 1: CONEXIÓN ================= -->
    <section class="panel visible">
      <header class="panel-cabecera">
        <h2><i class="fa-solid fa-plug"></i> 1. Conexión con MySQL</h2>
      </header>

      <?php if ($conectada): ?>
        <div class="alerta alerta-ok">
          <i class="fa-solid fa-circle-check"></i>
          <span>Conectado correctamente a la base de datos
            <code><?= h(isset($GLOBALS['BD']['base']) ? $GLOBALS['BD']['base'] : '') ?></code>.</span>
        </div>
      <?php else: ?>
        <div class="alerta alerta-error">
          <i class="fa-solid fa-triangle-exclamation"></i>
          <div>
            <strong>No se pudo conectar.</strong>
            <p><?= h($errorConexion) ?></p>
            <p>Edita <code>cheve/includes/config-bd.php</code> y recarga esta página.
               Los datos salen de cPanel → <em>Bases de datos MySQL</em>; acuérdate de que
               tanto el nombre de la base como el del usuario llevan delante el prefijo
               de tu cuenta de hosting.</p>
          </div>
        </div>
      <?php endif; ?>
    </section>

    <?php if ($conectada): ?>

      <!-- ================= PASO 2: TABLAS ================= -->
      <section class="panel visible">
        <header class="panel-cabecera">
          <h2><i class="fa-solid fa-table"></i> 2. Tablas y contenido</h2>
        </header>

        <?php if ($tablasFaltan): ?>
          <p>Faltan por crear <strong><?= count($tablasFaltan) ?></strong> tabla(s):
             <code><?= h(implode(', ', $tablasFaltan)) ?></code>.</p>
        <?php else: ?>
          <div class="alerta alerta-ok">
            <i class="fa-solid fa-circle-check"></i>
            <span>Las <?= count(bd_tablas()) ?> tablas están creadas.</span>
          </div>
        <?php endif; ?>

        <p class="campo-ayuda">
          Al pulsar el botón se crean las tablas que falten y, si todavía existen los
          archivos <code>data/contenido.json</code> y <code>data/usuarios.json</code> de la
          versión anterior, se importan: los textos, las imágenes y las contraseñas
          quedan exactamente como estaban. Lo que ya esté en la base no se toca.
        </p>

        <form method="post" style="margin-top:1rem">
          <?php csrf_campo(); ?>
          <input type="hidden" name="accion" value="instalar">
          <button type="submit" class="btn btn-primario">
            <i class="fa-solid fa-wand-magic-sparkles"></i>
            <?= $tablasFaltan ? 'Instalar ahora' : 'Volver a comprobar e importar' ?>
          </button>
        </form>
      </section>

      <!-- ================= PASO 3: PRIMER USUARIO ================= -->
      <?php if (!$hayUsuarios && !$tablasFaltan): ?>
        <section class="panel visible">
          <header class="panel-cabecera">
            <h2><i class="fa-solid fa-user-plus"></i> 3. Crear el primer usuario</h2>
            <p class="panel-ayuda">
              <i class="fa-solid fa-lightbulb"></i>
              No había ningún <code>usuarios.json</code> que importar, así que hay que crear
              a mano la primera cuenta. Después podrás entrar con ella y las demás se
              gestionan desde el panel.
            </p>
          </header>

          <form method="post">
            <?php csrf_campo(); ?>
            <input type="hidden" name="accion" value="primer_usuario">

            <div class="rejilla-campos">
              <?php
                $rutaCredCheck = dirname(__DIR__, 4) . '/credenciales_uruaplan.php';
                $credExtCheck  = file_exists($rutaCredCheck) ? require $rutaCredCheck : array();
                $codigoRequeridoCheck = isset($credExtCheck['CODIGO_INSTALACION']) ? trim((string) $credExtCheck['CODIGO_INSTALACION']) : '';
              ?>
              <?php if ($codigoRequeridoCheck !== ''): ?>
                <div class="campo" style="grid-column: 1 / -1;">
                  <label class="campo-label" for="i-codigo">Código de instalación</label>
                  <input type="password" id="i-codigo" name="codigo_instalacion" class="input-texto" required>
                  <p class="campo-ayuda">Se requiere el código de seguridad definido en <code>credenciales_uruaplan.php</code>.</p>
                </div>
              <?php endif; ?>

              <div class="campo">
                <label class="campo-label" for="i-id">Usuario</label>
                <input type="text" id="i-id" name="id" class="input-texto" maxlength="20"
                       required autocomplete="username" spellcheck="false">
                <p class="campo-ayuda">Minúsculas sin acentos. Por ejemplo: <code>david</code>.</p>
              </div>

              <div class="campo">
                <label class="campo-label" for="i-alias2">Segundo alias (opcional)</label>
                <input type="text" id="i-alias2" name="alias2" class="input-texto" maxlength="20"
                       spellcheck="false">
                <p class="campo-ayuda">Otro nombre con el que también podrá entrar,
                   por ejemplo su profesión: <code>ingeniero</code>.</p>
              </div>

              <div class="campo">
                <label class="campo-label" for="i-nombre">Nombre completo</label>
                <input type="text" id="i-nombre" name="nombre" class="input-texto" maxlength="120" required>
              </div>

              <div class="campo">
                <label class="campo-label" for="i-pass">Contraseña</label>
                <input type="password" id="i-pass" name="password" class="input-texto"
                       required autocomplete="new-password">
                <p class="campo-ayuda">Mínimo <?= MIN_LARGO_PASSWORD ?> caracteres, con al menos
                   una letra y un número.</p>
              </div>

              <div class="campo">
                <label class="campo-label" for="i-pass2">Repite la contraseña</label>
                <input type="password" id="i-pass2" name="password2" class="input-texto"
                       required autocomplete="new-password">
              </div>
            </div>

            <button type="submit" class="btn btn-primario" style="margin-top:1rem">
              <i class="fa-solid fa-user-plus"></i> Crear usuario
            </button>
          </form>
        </section>
      <?php endif; ?>

      <!-- ================= FINAL ================= -->
      <?php if ($listo): ?>
        <section class="panel visible">
          <header class="panel-cabecera">
            <h2><i class="fa-solid fa-flag-checkered"></i> Todo listo</h2>
          </header>

          <p>El panel ya funciona sobre MySQL. Dos cosas que conviene hacer ahora:</p>
          <ul>
            <li>Entra al panel y comprueba que el contenido se ve como antes.</li>
            <li>Cuando lo confirmes, <strong>borra del servidor</strong>
                <code>cheve/instalar.php</code> y los archivos
                <code>cheve/data/contenido.json</code> y <code>cheve/data/usuarios.json</code>,
                que ya no se usan (el segundo tiene dentro los hashes de las contraseñas).</li>
          </ul>

          <a class="btn btn-primario" href="index.php" style="margin-top:1rem">
            <i class="fa-solid fa-right-to-bracket"></i> Ir al panel
          </a>
        </section>
      <?php endif; ?>

    <?php endif; ?>

  </main>
</body>
</html>
