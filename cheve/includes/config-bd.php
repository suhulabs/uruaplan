<?php
/**
 * ===========================================================================
 *  CREDENCIALES DE LA BASE DE DATOS  —  MySQL / MariaDB
 * ===========================================================================
 *  Junto con config-correo.php, este es uno de los dos únicos archivos que
 *  hay que editar a mano en toda la instalación.
 *
 *  CÓMO CONSEGUIR ESTOS DATOS EN GODADDY / cPANEL
 *  ----------------------------------------------
 *    1. cPanel → Bases de datos → "Bases de datos MySQL".
 *    2. "Crear base de datos nueva": ponle  uruaplan  y pulsa Crear.
 *       cPanel le añade delante tu prefijo de cuenta, así que el nombre
 *       real quedará algo como  cpanelusr_uruaplan . Cópialo TAL CUAL.
 *    3. Más abajo, "Usuarios de MySQL → Añadir usuario nuevo": crea uno
 *       (por ejemplo  uruaplan ) y usa el generador de contraseñas.
 *       GUARDA LA CONTRASEÑA ANTES DE DARLE A CREAR: no se vuelve a ver.
 *    4. "Añadir un usuario a una base de datos": elige el usuario y la base
 *       que acabas de crear, y en la pantalla de permisos marca
 *       "TODOS LOS PRIVILEGIOS".
 *    5. Pega aquí abajo el nombre completo de la base, el usuario completo
 *       (también lleva prefijo) y la contraseña.
 *
 *  Después abre  uruaplan.com/cheve/instalar.php  una sola vez: esa página
 *  crea todas las tablas y siembra el contenido inicial.
 *
 *  ⚠ Este archivo tiene una contraseña dentro. La carpeta includes/ está
 *    bloqueada por su propio .htaccess, así que no se puede abrir desde el
 *    navegador. Aun así: no lo mandes por WhatsApp ni lo subas a ningún
 *    repositorio público.
 * ===========================================================================
 */

// Intentar cargar credenciales externas (fuera de public_html)
$rutaCredenciales = dirname(__DIR__, 4) . '/credenciales_uruaplan.php';
$credencialesExt = file_exists($rutaCredenciales) ? require $rutaCredenciales : [];

$GLOBALS['BD'] = array(
    'host' => isset($credencialesExt['BD_HOST']) ? $credencialesExt['BD_HOST'] : 'localhost',
    'puerto' => isset($credencialesExt['BD_PUERTO']) ? $credencialesExt['BD_PUERTO'] : 3306,
    'base' => isset($credencialesExt['BD_NOMBRE']) ? $credencialesExt['BD_NOMBRE'] : 'cpanelusr_uruaplan',
    'usuario' => isset($credencialesExt['BD_USUARIO']) ? $credencialesExt['BD_USUARIO'] : 'cpanelusr_uruaplan',
    'password' => isset($credencialesExt['BD_PASSWORD']) ? $credencialesExt['BD_PASSWORD'] : '',
    'charset' => 'utf8mb4',
);


/*
 * ---------------------------------------------------------------------------
 *  ENTORNO LOCAL (respaldo)
 * ---------------------------------------------------------------------------
 *  Si existe credenciales_uruaplan.php fuera de public_html (producción),
 *  se usan esas credenciales y NUNCA se sobreescriben por cabeceras HTTP.
 *  Si no existe dicho archivo, se usan las credenciales locales de desarrollo.
 * ---------------------------------------------------------------------------
 */

$tieneCredencialesExt = file_exists($rutaCredenciales);

if (!$tieneCredencialesExt) {
    $GLOBALS['BD'] = array(
        'host' => '127.0.0.1',
        'puerto' => 3306,
        'base' => 'uruaplan_dev',
        'usuario' => 'uruaplan',
        'password' => 'uruaplan_dev',
        'charset' => 'utf8mb4',
    );
}

unset($tieneCredencialesExt);
