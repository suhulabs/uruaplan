<?php
/**
 * ===========================================================================
 * URUAPLAN — PLANTILLA DE CREDENCIALES DE PRODUCCIÓN
 * ===========================================================================
 *
 * Para configurar tu servidor de producción (cPanel / GoDaddy / VPS):
 *
 * 1. Copia este archivo una carpeta arriba de public_html (fuera del servidor web):
 *    cp credenciales_uruaplan.example.php /home/tu_usuario/credenciales_uruaplan.php
 *
 * 2. Reemplaza los valores de abajo por las credenciales reales de tu base de
 *    datos MySQL y servidor SMTP.
 *
 * 3. Uruaplan detectará este archivo automáticamente y mantendrá tus credenciales
 *    completamente protegidas e inaccesibles desde la web.
 * ===========================================================================
 */

return array(
    // Base de Datos MySQL / MariaDB
    'BD_HOST'     => '127.0.0.1',
    'BD_PUERTO'   => 3306,
    'BD_NOMBRE'   => 'cpanelusr_uruaplan',
    'BD_USUARIO'  => 'cpanelusr_uruaplan',
    'BD_PASSWORD' => 'TuContraseñaSeguraBD123!',

    // Envío de Correo SMTP
    'SMTP_HOST'    => 'smtp.tuservidor.com',
    'SMTP_PUERTO'  => 587,
    'SMTP_USUARIO' => 'contacto@uruaplan.com',
    'SMTP_PASSWORD'=> 'TuContraseñaSeguraSMTP123!',

    // Código de Seguridad para Instalación Inicial (Recomendado/Obligatorio al instalar)
    'CODIGO_INSTALACION' => 'CAMBIA_ESTE_CODIGO_DE_SEGURIDAD',
);
