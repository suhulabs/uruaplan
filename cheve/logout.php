<?php
/**
 * Cierre de sesión seguro mediante POST y token CSRF.
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/funciones.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

iniciar_sesion_panel();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_exigir();
    cerrar_sesion();
}

header('Location: index.php');
exit;
