<?php
/**
 * ===========================================================================
 *  CREDENCIALES DE ENVÍO DE CORREO  —  CUENTA DE cPANEL
 * ===========================================================================
 *  CÓMO FUNCIONA EL CIRCUITO
 *
 *      formulario de la web
 *              │
 *              ▼
 *      noreply@uruaplan.com  ───envía───▶  contacto@uruaplan.com
 *      (cuenta de cPanel,                  (buzón de Zoho, el de siempre)
 *       en el propio hosting)
 *
 *  El envío lo hace la cuenta de cPanel, que vive en el mismo servidor que la
 *  web. El aviso llega al buzón de Zoho de toda la vida.
 *
 *  Son dos cuentas distintas del mismo dominio y es fácil confundirlas:
 *      noreply@  → cPanel  → solo sirve para ENVIAR (nadie le escribe).
 *      contacto@ → Zoho    → es donde se LEEN los eventos que manda la gente.
 *
 *  POR QUÉ SE HACE ASÍ Y NO DESDE ZOHO
 *    El plan gratuito de Zoho Mail dejó de permitir SMTP desde programas
 *    externos: al mandar la contraseña responde "554 5.7.8 Access Restricted".
 *    Pero eso solo afecta a ENVIAR. Recibir sigue funcionando igual. Por eso
 *    se envía desde cPanel (que no cobra por SMTP) y se recibe en Zoho.
 *
 *  ESTE ES EL ÚNICO ARCHIVO QUE HAY QUE EDITAR A MANO. Solo falta rellenar
 *  'smtp_password' con la contraseña de noreply@uruaplan.com.
 *
 *  PASO 1 — Sacar los datos exactos del servidor
 *      cPanel → Cuentas de correo → en noreply@uruaplan.com,
 *      "Conectar dispositivos" (Connect Devices). Ahí salen el servidor de
 *      salida y el puerto; comprueba que coinciden con lo de abajo.
 *
 *  PASO 2 — Pegar la contraseña
 *      Es la de noreply@uruaplan.com, la que le pusiste al crearla en cPanel.
 *      Si no la recuerdas, se cambia sin perder nada en
 *      cPanel → Cuentas de correo → Administrar.
 *
 *  PASO 3 — Comprobar que llega
 *      uruaplan.com/cheve/probar-correo.php  (hay que haber iniciado sesión).
 *
 *  ⚠ DOS COSAS QUE HAY QUE MIRAR EN cPANEL O EL CORREO NO LLEGARÁ NUNCA
 *
 *    a) ENRUTAMIENTO DE CORREO. Esto es lo más importante de todo. Las dos
 *       cuentas son del mismo dominio (admin@ y contacto@, las dos
 *       @uruaplan.com), así que cPanel puede creer que él mismo es el
 *       servidor de correo del dominio y buscar a "contacto" entre SUS
 *       buzones en vez de mandarlo a Zoho. Como ahí no existe, el aviso se
 *       pierde o rebota. Se arregla en:
 *           cPanel → Enrutamiento de correo (Email Routing)
 *           → uruaplan.com → "Intercambiador de correo remoto"
 *       Sin esto el envío dice "OK" y aun así no aparece nada en Zoho: es el
 *       fallo más difícil de diagnosticar de todo este montaje.
 *
 *       Efecto secundario, inofensivo aquí: a partir de ese cambio, el correo
 *       que le llegue a noreply@uruaplan.com desde fuera va a Zoho y no al
 *       buzón de cPanel. Da igual, porque admin@ solo se usa para enviar.
 *
 *    b) SPF. El registro SPF del dominio seguramente solo autoriza a Zoho.
 *       Si el servidor del hosting no está incluido, los avisos caen en spam
 *       o los rechazan. Hay que añadir el hosting al SPF (normalmente
 *       basta con "+a +mx" o el include que indique el proveedor).
 *
 *  ⚠ Este archivo tiene una contraseña dentro. La carpeta includes/ está
 *    bloqueada por su propio .htaccess, así que nadie puede abrirlo desde
 *    el navegador. Aun así: no lo subas a ningún sitio público ni lo
 *    mandes por WhatsApp.
 * ===========================================================================
 */

// Intentar cargar credenciales externas (fuera de public_html)
$rutaCredenciales = dirname(__DIR__, 4) . '/credenciales_uruaplan.php';
$credencialesExt = file_exists($rutaCredenciales) ? require $rutaCredenciales : [];

$GLOBALS['CORREO'] = array(

    'smtp_host' => isset($credencialesExt['SMTP_HOST']) ? $credencialesExt['SMTP_HOST'] : 'uruaplan.com',
    'smtp_puerto' => isset($credencialesExt['SMTP_PUERTO']) ? $credencialesExt['SMTP_PUERTO'] : 465,
    'smtp_seguridad' => 'ssl',

    'smtp_usuario' => isset($credencialesExt['SMTP_USUARIO']) ? $credencialesExt['SMTP_USUARIO'] : 'noreply@uruaplan.com',

    // Se toma la contraseña del archivo externo; si no existe, queda vacía
    'smtp_password' => isset($credencialesExt['SMTP_PASSWORD']) ? $credencialesExt['SMTP_PASSWORD'] : '',

    'remitente_correo' => 'noreply@uruaplan.com',
    'remitente_nombre' => 'Formulario web Uruaplan',
    'destino' => 'contacto@uruaplan.com',

    'max_por_hora_ip' => 5,
    'segundos_minimos' => 4,
);