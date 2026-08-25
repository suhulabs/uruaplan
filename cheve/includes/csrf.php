<?php
/**
 * Protección CSRF.
 *
 * Un único token por sesión (no se rota en cada formulario) para que el
 * usuario pueda tener el panel abierto en varias pestañas sin invalidarse.
 * Se compara siempre con hash_equals() para evitar timing attacks.
 */

require_once __DIR__ . '/config.php';

/**
 * Devuelve el token CSRF de la sesión, creándolo la primera vez.
 */
function csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Imprime el campo oculto que debe llevar TODO formulario POST del panel.
 */
function csrf_campo()
{
    echo '<input type="hidden" name="csrf_token" value="'
        . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Verifica el token recibido por POST.
 */
function csrf_valido()
{
    if (empty($_SESSION['csrf_token']) || empty($_POST['csrf_token'])) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], (string) $_POST['csrf_token']);
}

/**
 * Corta la ejecución si el token no es válido.
 * Se llama al principio de cada handler de POST.
 */
function csrf_exigir()
{
    if (!csrf_valido()) {
        http_response_code(400);
        exit('Token de seguridad inválido o expirado. Vuelve a cargar la página e inténtalo de nuevo.');
    }
}
