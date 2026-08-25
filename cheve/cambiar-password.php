<?php
/**
 * CAMBIO DE CONTRASEÑA
 *
 * Sirve para dos casos:
 *  1. Cambio OBLIGATORIO en el primer acceso (mientras el usuario tenga
 *     la contraseña temporal, exigir_sesion() lo redirige aquí y no lo
 *     deja entrar al panel).
 *  2. Cambio voluntario en cualquier momento.
 *
 * En ambos casos se pide la contraseña actual: así, si alguien encuentra
 * una sesión abierta y desatendida, no puede secuestrar la cuenta.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/funciones.php';
require_once __DIR__ . '/includes/bd.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

bd_exigir();

// El "true" indica que ESTA es la página de cambio: evita el bucle de redirección.
$usuario = exigir_sesion(true);

$obligatorio = !empty($usuario['debe_cambiar']);
$error = '';
$exito = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_exigir();

  $actual = isset($_POST['actual']) ? (string) $_POST['actual'] : '';
  $nueva = isset($_POST['nueva']) ? (string) $_POST['nueva'] : '';
  $repetida = isset($_POST['repetida']) ? (string) $_POST['repetida'] : '';

  if (!password_verify($actual, $usuario['hash'])) {
    $error = 'La contraseña actual no es correcta.';
  } elseif ($actual === $nueva) {
    $error = 'La contraseña nueva debe ser distinta de la actual.';
  } else {
    $error = validar_password_nueva($nueva, $repetida);

    if ($error === '') {
      if (cambiar_password($usuario['id'], $nueva)) {
        // Sesión nueva tras cambiar credenciales.
        session_regenerate_id(true);

        $exito = true;

        if ($obligatorio) {
          $_SESSION['flash'] = array(
            'ok' => true,
            'cambios' => 0,
            'errores' => array(),
            'quien' => $usuario['nombre'],
          );
          header('Location: admin.php?bienvenida=1');
          exit;
        }

        // Refresca los datos para que desaparezca el aviso de obligatorio.
        $usuario = usuario_actual();
        $obligatorio = false;
      } else {
        $error = 'No se pudo actualizar la contraseña. Ocurrió un error en la base de datos. Inténtalo de nuevo.';
      }
    }
  }
}

$tituloPagina = 'Cambiar contraseña';
$paginaActual = 'password';
require __DIR__ . '/includes/cabecera.php';
?>

<div class="encabezado-pagina">
  <div>
    <h1><?= $obligatorio ? 'Crea tu contraseña' : 'Cambiar contraseña' ?></h1>
    <p>
      <?= $obligatorio
        ? 'Estás usando la contraseña temporal que te dieron. Elige una propia para continuar.'
        : 'Elige una contraseña nueva. Solo afecta a tu cuenta.' ?>
    </p>
  </div>
</div>

<?php if ($obligatorio): ?>
  <div class="alerta alerta-aviso">
    <i class="fa-solid fa-key"></i>
    <span>
      <strong>Paso obligatorio.</strong>
      No podrás entrar al panel hasta que cambies la contraseña temporal.
    </span>
  </div>
<?php endif; ?>

<?php if ($exito): ?>
  <div class="alerta alerta-ok">
    <i class="fa-solid fa-circle-check"></i>
    <span><strong>¡Listo!</strong> Tu contraseña quedó actualizada. Úsala la próxima vez que entres.</span>
  </div>
<?php endif; ?>

<?php if ($error !== ''): ?>
  <div class="alerta alerta-error">
    <i class="fa-solid fa-triangle-exclamation"></i>
    <span><?= e($error) ?></span>
  </div>
<?php endif; ?>

<div class="tarjeta tarjeta-estrecha">
  <form method="post" action="cambiar-password.php" autocomplete="on" novalidate>
    <?php csrf_campo(); ?>

    <div class="campo">
      <label class="campo-label" for="actual">
        <?= $obligatorio ? 'Contraseña temporal (la que te dieron)' : 'Contraseña actual' ?>
      </label>
      <div class="campo-password">
        <input type="password" id="actual" name="actual" class="input-texto" required autocomplete="current-password"
          maxlength="200" autofocus>
        <button type="button" class="btn-ojo" data-ver="actual" aria-label="Mostrar contraseña">
          <i class="fa-solid fa-eye"></i>
        </button>
      </div>
    </div>

    <hr class="separador">

    <div class="campo">
      <label class="campo-label" for="nueva">Contraseña nueva</label>
      <div class="campo-password">
        <input type="password" id="nueva" name="nueva" class="input-texto" required autocomplete="new-password"
          maxlength="200" minlength="<?= (int) MIN_LARGO_PASSWORD ?>" data-fuerza="1">
        <button type="button" class="btn-ojo" data-ver="nueva" aria-label="Mostrar contraseña">
          <i class="fa-solid fa-eye"></i>
        </button>
      </div>
      <div class="medidor-fuerza" id="medidorFuerza" hidden>
        <div class="medidor-barra"><span></span></div>
        <small class="medidor-texto"></small>
      </div>
      <p class="campo-ayuda">
        Mínimo <?= (int) MIN_LARGO_PASSWORD ?> caracteres, con al menos una letra y un número.
        No la reutilices de otro sitio.
      </p>
    </div>

    <div class="campo">
      <label class="campo-label" for="repetida">Repite la contraseña nueva</label>
      <div class="campo-password">
        <input type="password" id="repetida" name="repetida" class="input-texto" required autocomplete="new-password"
          maxlength="200">
        <button type="button" class="btn-ojo" data-ver="repetida" aria-label="Mostrar contraseña">
          <i class="fa-solid fa-eye"></i>
        </button>
      </div>
      <small class="aviso-coincide" id="avisoCoincide"></small>
    </div>

    <button type="submit" class="btn btn-primario btn-ancho">
      <i class="fa-solid fa-floppy-disk"></i> Guardar contraseña
    </button>
  </form>
</div>

<?php require __DIR__ . '/includes/pie.php'; ?>