<?php
/**
 * LOGIN DEL PANEL — uruaplan.com/cheve/
 *
 * Es la puerta de entrada. Acepta como usuario cualquiera de los dos alias
 * de cada persona (nombre corto o profesión).
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/funciones.php';
require_once __DIR__ . '/includes/bd.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

// Sin base de datos no hay usuarios que comprobar. Si todavía no está
// instalada, esto redirige solo a instalar.php.
bd_exigir();

iniciar_sesion_panel();

// Si ya hay sesión válida, no tiene sentido mostrar el login.
if (sesion_activa()) {
    header('Location: admin.php');
    exit;
}

$error   = '';
$aviso   = '';
$alias   = '';

if (isset($_GET['e']) && $_GET['e'] === 'sesion') {
    $aviso = 'Tu sesión se cerró por inactividad. Vuelve a entrar.';
}
if (isset($_GET['e']) && $_GET['e'] === 'salir') {
    $aviso = 'Sesión cerrada correctamente. ¡Hasta la próxima!';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_exigir();

    $alias      = isset($_POST['alias']) ? trim((string) $_POST['alias']) : '';
    $contrasena = isset($_POST['password']) ? (string) $_POST['password'] : '';

    $resultado = intentar_login($alias, $contrasena);

    if ($resultado['ok']) {
        // Patrón POST/Redirect/GET: evita reenviar el formulario al recargar.
        header('Location: admin.php');
        exit;
    }

    $error = $resultado['error'];
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>Acceso · Panel Uruaplan</title>
  <link rel="icon" href="../img/logos/logo.png">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="<?= versionado('assets/panel.css', CHEVE_DIR . '/assets/panel.css') ?>">
</head>

<body class="pagina-login">

  <main class="caja-login">
    <div class="login-marca">
      <img src="../img/logos/logo.png" alt="Uruaplan" onerror="this.style.display='none'">
      <h1>Panel de contenido</h1>
      <p>Edita los textos e imágenes de uruaplan.com</p>
    </div>

    <?php if ($aviso !== ''): ?>
      <div class="alerta alerta-info">
        <i class="fa-solid fa-circle-info"></i>
        <span><?= e($aviso) ?></span>
      </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
      <div class="alerta alerta-error">
        <i class="fa-solid fa-triangle-exclamation"></i>
        <span><?= e($error) ?></span>
      </div>
    <?php endif; ?>

    <form method="post" action="index.php" autocomplete="on" novalidate>
      <?php csrf_campo(); ?>

      <div class="campo">
        <label class="campo-label" for="alias">Usuario</label>
        <input type="text" id="alias" name="alias" class="input-texto"
               value="<?= e($alias) ?>" required autofocus
               autocapitalize="none" autocorrect="off" spellcheck="false"
               autocomplete="username" maxlength="60">
        <p class="campo-ayuda">Puedes entrar con tu nombre o con tu profesión.</p>
      </div>

      <div class="campo">
        <label class="campo-label" for="password">Contraseña</label>
        <div class="campo-password">
          <input type="password" id="password" name="password" class="input-texto"
                 required autocomplete="current-password" maxlength="200">
          <button type="button" class="btn-ojo" data-ver="password" aria-label="Mostrar contraseña">
            <i class="fa-solid fa-eye"></i>
          </button>
        </div>
      </div>

      <button type="submit" class="btn btn-primario btn-ancho">
        <i class="fa-solid fa-right-to-bracket"></i> Entrar
      </button>
    </form>

    <p class="login-pie">
      <i class="fa-solid fa-lock"></i>
      Tras <?= (int) MAX_INTENTOS ?> intentos fallidos la cuenta se bloquea
      <?= (int) round(BLOQUEO_SEGUNDOS / 60) ?> minutos.
    </p>
  </main>

  <script src="<?= versionado('assets/panel.js', CHEVE_DIR . '/assets/panel.js') ?>"></script>
</body>

</html>
