<?php
/**
 * Cabecera común de las páginas privadas del panel.
 *
 * Antes de incluirla hay que definir:
 *   $tituloPagina  (string)
 *   $usuario       (array, el que devuelve exigir_sesion())
 *   $paginaActual  (string: 'admin' | 'password')
 */

if (!isset($usuario) || !is_array($usuario)) {
  exit('Cabecera incluida sin sesión.');
}

$tituloPagina = isset($tituloPagina) ? $tituloPagina : 'Panel';
$paginaActual = isset($paginaActual) ? $paginaActual : '';
$nombre = isset($usuario['nombre']) ? $usuario['nombre'] : $usuario['id'];

// Cuántos flyers esperan revisión. Va en la barra de arriba para que se vea
// desde cualquier página del panel sin tener que entrar a mirar.
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/flyers.php';
$pendientesFlyers = flyers_pendientes();

// Primer nombre, para el saludo.
$partesNombre = preg_split('/\s+/', trim($nombre));
$primerNombre = $partesNombre[0];
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title><?= e($tituloPagina) ?> · Panel Uruaplan</title>
  <link rel="icon" href="../img/logos/logo.png">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="<?= versionado('assets/panel.css', CHEVE_DIR . '/assets/panel.css') ?>">
</head>

<body class="pagina-panel">

  <header class="barra-superior">
    <div class="barra-marca">
      <img src="../img/logos/logo.png" alt="" onerror="this.style.display='none'">
      <span>Panel Uruaplan</span>
    </div>

    <nav class="barra-nav">
      <a href="admin.php" class="<?= $paginaActual === 'admin' ? 'activo' : '' ?>">
        <i class="fa-solid fa-pen-to-square"></i><span>Contenido</span>
      </a>
      <a href="flyers.php" class="<?= $paginaActual === 'flyers' ? 'activo' : '' ?>">
        <i class="fa-solid fa-inbox"></i><span>Flyers</span>
        <?php if ($pendientesFlyers > 0): ?>
          <span class="globo-pendientes"
            title="<?= (int) $pendientesFlyers ?> flyer(s) esperando revisión"><?= (int) $pendientesFlyers ?></span>
        <?php endif; ?>
      </a>
      <a href="cambiar-password.php" class="<?= $paginaActual === 'password' ? 'activo' : '' ?>">
        <i class="fa-solid fa-key"></i><span>Contraseña</span>
      </a>
      <a href="probar-correo.php" class="<?= $paginaActual === 'correo' ? 'activo' : '' ?>">
        <i class="fa-solid fa-envelope"></i><span>Correo</span>
      </a>
      <a href="../index.php" target="_blank" rel="noopener">
        <i class="fa-solid fa-arrow-up-right-from-square"></i><span>Ver sitio</span>
      </a>
    </nav>

    <div class="barra-usuario">
      <span class="saludo">Hola, <strong><?= e($primerNombre) ?></strong></span>
      <form action="logout.php" method="POST" style="display:inline;">
        <?php csrf_campo(); ?>
        <button type="submit" class="btn btn-salir" title="Cerrar sesión">
          <i class="fa-solid fa-right-from-bracket"></i><span>Salir</span>
        </button>
      </form>

    </div>
  </header>

  <main class="contenido-panel">