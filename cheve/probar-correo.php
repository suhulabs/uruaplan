<?php
/**
 * PRUEBA DEL ENVÍO DE CORREO
 *
 * Página de diagnóstico para comprobar que la contraseña de la cuenta que
 * envía está bien puesta y que el hosting deja salir al puerto SMTP.
 * Manda un correo de prueba y enseña el error EXACTO si algo falla.
 *
 * Requiere sesión: los errores de SMTP pueden revelar detalles del
 * servidor, no debe verlos cualquiera.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/funciones.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/correo.php';

$usuario = exigir_sesion();

$tituloPagina = 'Probar correo';
$paginaActual = 'correo';

$resultado = null;
$destino   = correo_destino_formulario();
$cfg       = isset($GLOBALS['CORREO']) ? $GLOBALS['CORREO'] : array();
$password  = str_replace(array(' ', "\t"), '', (string) valor_cfg($cfg, 'smtp_password'));
$host      = (string) valor_cfg($cfg, 'smtp_host', '');
$esZoho    = stripos($host, 'zoho') !== false;
$esGmail   = stripos($host, 'gmail') !== false;
// Lo que no es ni Zoho ni Gmail es el montaje normal: la cuenta de cPanel
// del propio hosting. Las pistas de error son distintas en cada caso.
$esCpanel  = !$esZoho && !$esGmail;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_exigir();

    $resultado = enviar_correo(array(
        'para'   => $destino,
        'asunto' => 'Prueba de correo del sitio Uruaplan',
        'cuerpo' => "Si estás leyendo esto, el formulario de eventos ya funciona.\n\n"
            . 'Prueba lanzada por: ' . (isset($usuario['nombre']) ? $usuario['nombre'] : $usuario['id']) . "\n"
            . 'Fecha: ' . date('d/m/Y H:i') . "\n",
    ));
}

require __DIR__ . '/includes/cabecera.php';
?>

<div class="encabezado-pagina">
  <div>
    <h1>Probar el envío de correo</h1>
    <p>Comprueba que las solicitudes del formulario de la web llegan de verdad al buzón.</p>
  </div>
</div>

<?php if ($resultado !== null && $resultado['ok']): ?>
  <div class="alerta alerta-ok">
    <i class="fa-solid fa-circle-check"></i>
    <span>
      <strong>¡Funcionó!</strong> El correo de prueba salió sin errores.
      Revisa la bandeja de <strong><?= e($destino) ?></strong>; la primera vez
      mira también en la carpeta de Spam. El formulario de la web ya está operativo.
    </span>
  </div>

  <?php
  // "Salió sin errores" solo quiere decir que el servidor lo aceptó. Si el
  // destino es del mismo dominio que envía, cPanel puede haberlo entregado en
  // un buzón local suyo en vez de mandarlo al proveedor de fuera.
  $dominioDestino = strtolower(substr(strrchr($destino, '@'), 1));
  $dominioEmisor  = strtolower(substr(strrchr((string) valor_cfg($cfg, 'smtp_usuario', ''), '@'), 1));
  ?>
  <?php if ($esCpanel && $dominioDestino !== '' && $dominioDestino === $dominioEmisor): ?>
    <div class="alerta alerta-info">
      <i class="fa-solid fa-lightbulb"></i>
      <span>
        <strong>¿Salió bien pero no aparece nada en la bandeja?</strong>
        Como <code><?= e($destino) ?></code> es del mismo dominio que envía, puede que
        cPanel lo haya dejado en un buzón local suyo en lugar de mandarlo fuera.
        Se arregla en <strong>cPanel → Enrutamiento de correo</strong>, poniendo
        <code><?= e($dominioDestino) ?></code> como
        <strong>«Intercambiador de correo remoto»</strong>.
      </span>
    </div>
  <?php endif; ?>
<?php elseif ($resultado !== null): ?>
  <div class="alerta alerta-error">
    <i class="fa-solid fa-triangle-exclamation"></i>
    <span>
      <strong>No se pudo enviar.</strong><br>
      <?= e($resultado['error']) ?>
    </span>
  </div>

  <div class="alerta alerta-info">
    <i class="fa-solid fa-lightbulb"></i>
    <span>
      <strong>Las causas habituales:</strong>
      <ul>
        <?php if ($esZoho): ?>
          <li>El servidor no es el correcto: para direcciones de dominio propio
            (<code>@uruaplan.com</code>) Zoho usa <strong>smtppro</strong>.zoho.com,
            no smtp.zoho.com.</li>
          <li>La cuenta tiene verificación en dos pasos y hace falta una
            <em>contraseña de aplicación</em>, no la normal.</li>
          <li><strong>El plan de Zoho es el gratuito.</strong> Zoho quitó el envío por
            SMTP del plan gratuito; hace falta uno de pago (Mail Lite o superior).
            Si todo lo demás está bien escrito, es casi seguro esto.</li>
          <li>La cuenta se creó en otro centro de datos: prueba
            <code>smtppro.zoho.eu</code> (o <code>.in</code>, <code>.com.au</code>).</li>
        <?php elseif ($esGmail): ?>
          <li>La contraseña puesta es la normal de Gmail y no una <em>de aplicación</em>.</li>
          <li>La cuenta no tiene activada la verificación en dos pasos (sin ella Google no
            deja crear contraseñas de aplicación).</li>
        <?php else: ?>
          <li>La cuenta <code><?= e(valor_cfg($cfg, 'smtp_usuario', '')) ?></code> no existe
            en <strong>cPanel → Cuentas de correo</strong>, o el nombre no está escrito
            igual. Tiene que coincidir letra por letra.</li>
          <li>La contraseña no es la de esa cuenta. Se puede cambiar sin perder nada en
            <strong>cPanel → Cuentas de correo → Administrar</strong>.</li>
          <li>El certificado del servidor no coincide con <code><?= e($host) ?></code>.
            Mira el nombre exacto que da cPanel en <strong>Conectar dispositivos</strong>
            y ponlo en <code>smtp_host</code>.</li>
        <?php endif; ?>
        <li>El hosting bloquea la salida SMTP. Pídele a soporte de GoDaddy que abra el
          puerto <?= (int) valor_cfg($cfg, 'smtp_puerto', 465) ?>, o prueba el otro:
          <code>465</code> va con <code>'ssl'</code> y <code>587</code> con
          <code>'tls'</code>, en <code>config-correo.php</code>.</li>
      </ul>
    </span>
  </div>
<?php endif; ?>

<div class="tarjeta">
  <div class="panel-cabecera">
    <h2><i class="fa-solid fa-gear"></i> Configuración actual</h2>
  </div>

  <p class="campo-ayuda">
    <strong>Servidor:</strong>
    <?= e(valor_cfg($cfg, 'smtp_host', 'smtp.gmail.com')) ?>:<?= e(valor_cfg($cfg, 'smtp_puerto', 587)) ?>
    (<?= e(valor_cfg($cfg, 'smtp_seguridad', 'tls')) ?>)
  </p>

  <p class="campo-ayuda">
    <strong>Cuenta que envía:</strong>
    <?= e(valor_cfg($cfg, 'smtp_usuario', '(sin configurar)')) ?>
  </p>

  <p class="campo-ayuda">
    <strong>Contraseña:</strong>
    <?php if ($password === ''): ?>
      <span class="aviso-coincide mal">
        Sin rellenar. Edita <code>cheve/includes/config-correo.php</code> y pégala ahí.
      </span>
    <?php elseif ($esGmail && strlen($password) !== 16): ?>
      <span class="aviso-coincide mal">
        Puesta, pero tiene <?= strlen($password) ?> caracteres y las de Google son de 16.
        Revisa que no falte ni sobre ninguna letra.
      </span>
    <?php else: ?>
      <span class="aviso-coincide bien">Puesta (<?= strlen($password) ?> caracteres).</span>
    <?php endif; ?>
  </p>

  <?php
  // Dos errores de configuración que se pueden detectar sin llegar a conectar.
  $remitente = (string) valor_cfg($cfg, 'remitente_correo', '');
  $emisor    = (string) valor_cfg($cfg, 'smtp_usuario', '');
  ?>

  <?php if ($esZoho && stripos($host, 'smtppro') === false): ?>
    <div class="alerta alerta-aviso">
      <i class="fa-solid fa-triangle-exclamation"></i>
      <span>
        Estás usando <code><?= e($host) ?></code>. Si la dirección es de dominio propio
        (<code>@uruaplan.com</code>), Zoho exige <code>smtppro.zoho.com</code>.
        Con <code>smtp.zoho.com</code> el acceso se rechaza aunque la contraseña sea correcta.
      </span>
    </div>
  <?php endif; ?>

  <?php if ($remitente !== '' && $emisor !== '' && strcasecmp($remitente, $emisor) !== 0): ?>
    <div class="alerta alerta-aviso">
      <i class="fa-solid fa-triangle-exclamation"></i>
      <span>
        El remitente (<code><?= e($remitente) ?></code>) no coincide con la cuenta que
        inicia sesión (<code><?= e($emisor) ?></code>). Casi todos los proveedores
        rechazan el envío salvo que esa dirección esté dada de alta como alias.
      </span>
    </div>
  <?php endif; ?>

  <p class="campo-ayuda">
    <strong>Las solicitudes llegan a:</strong> <?= e($destino) ?>
  </p>

  <hr class="separador">

  <form method="post" action="probar-correo.php">
    <?php csrf_campo(); ?>
    <button type="submit" class="btn btn-primario">
      <i class="fa-solid fa-paper-plane"></i> Enviar un correo de prueba
    </button>
  </form>
</div>

<?php require __DIR__ . '/includes/pie.php'; ?>
