<?php
/**
 * URUAPLAN — SUITE DE PRUEBAS DE SEGURIDAD Y DE HUMO
 *
 * No utiliza assert() para garantizar su ejecución independiente de zend.assertions en php.ini.
 * Si alguna prueba falla, imprime el motivo y finaliza con código de salida 1 (exit(1)).
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/subidas.php';
require_once __DIR__ . '/../includes/funciones.php';
require_once __DIR__ . '/../includes/antispam.php';
require_once __DIR__ . '/../includes/guardar.php';
require_once __DIR__ . '/../includes/flyers.php';

/**
 * Helper de pruebas que no depende de assert().
 */
function probar($condicion, $mensajeError)
{
    if (!$condicion) {
        echo "❌ FALLÓ: {$mensajeError}\n";
        exit(1);
    }
}

echo "Ejecutando pruebas de seguridad y de humo de Uruaplan...\n\n";

// ---------------------------------------------------------------------------
// 1. Bloqueo de rutas peligrosas (Path Traversal)
// ---------------------------------------------------------------------------
probar(ruta_existente_segura('../../config.php') === '', 'Debería bloquear rutas relativas con ../');
probar(ruta_existente_segura('http://evil.com/foto.jpg') === '', 'Debería bloquear URLs externas');
probar(ruta_existente_segura('img/fondos/centro.webp') === 'img/fondos/centro.webp', 'Debería aceptar rutas locales válidas');
echo "✔ Prueba 1: Bloqueo de rutas peligrosas (Path Traversal): PASÓ\n";

// ---------------------------------------------------------------------------
// 2. Sanitización HTML, Vectores XSS y Sanear Cadena
// ---------------------------------------------------------------------------
probar(e('<script>alert(1)</script>') === '&lt;script&gt;alert(1)&lt;/script&gt;', 'Debería neutralizar etiquetas <script>');
probar(e('" onmouseover="alert(1)') === '&quot; onmouseover=&quot;alert(1)', 'Debería escapar comillas dobles para atributos HTML');
probar(sanear_cadena("Hola\r\nMundo\x07", 10, false) === 'Hola Mundo', 'sanear_cadena debe limpiar saltos, caracteres de control y acotar longitud');
echo "✔ Prueba 2: Sanitización de salida XSS (e) y sanear_cadena: PASÓ\n";

// ---------------------------------------------------------------------------
// 3. Sanitización y Validación de URLs (guardar.php)
// ---------------------------------------------------------------------------
probar(limpiar_url('javascript:alert(1)') === '', 'Debería bloquear esquemas peligrosos javascript:');
probar(limpiar_url('data:text/html,<script>alert(1)</script>') === '', 'Debería bloquear esquemas data:');
probar(limpiar_url('https://instagram.com/uruaplan') === 'https://instagram.com/uruaplan', 'Debería permitir URLs https válidas');
probar(limpiar_url('facebook.com/uruaplan') === 'https://facebook.com/uruaplan', 'Debería añadir prefijo https:// a URLs incompletas');
echo "✔ Prueba 3: Sanitización y validación de URLs (limpiar_url): PASÓ\n";

// ---------------------------------------------------------------------------
// 4. Verificación de Rutas de Imagen Privada (flyer_ruta_imagen)
// ---------------------------------------------------------------------------
probar(flyer_ruta_imagen('../../../etc/passwd') === '', 'Debería rechazar traversal en nombres de imagen');
probar(flyer_ruta_imagen('flyer-malicioso.php') === '', 'Debería rechazar extensiones no permitidas');

// Probar con un archivo temporal existente en la carpeta privada de flyers
$dirFlyers = flyers_dir();
if (!is_dir($dirFlyers)) {
    @mkdir($dirFlyers, 0755, true);
}
$nombreFotoTest = 'flyer-20260824-a1b2c3d4e5f6.jpg';
$rutaFotoTest = $dirFlyers . '/' . $nombreFotoTest;
file_put_contents($rutaFotoTest, 'dummy');

probar(flyer_ruta_imagen($nombreFotoTest) === $rutaFotoTest, 'Debería retornar la ruta absoluta para un archivo de flyer existente');
@unlink($rutaFotoTest);
echo "✔ Prueba 4: Validación y seguridad de rutas de imágenes privadas: PASÓ\n";

// ---------------------------------------------------------------------------
// 5. Antispam del Formulario Público (token_formulario)
// ---------------------------------------------------------------------------
$tokenValido = token_formulario();
probar(strpos($tokenValido, '.') !== false, 'El token antispam debe contener firma HMAC separada por punto');
probar(token_formulario_error('token_sin_punto') !== '', 'Debería rechazar tokens sin formato de punto');
probar(token_formulario_error($tokenValido, 4) !== '', 'Debería rechazar envíos inmediatos (<4 segundos)');
echo "✔ Prueba 5: Verificación de firma y tiempo del token antispam: PASÓ\n";

// ---------------------------------------------------------------------------
// 6. Purga de Datos Personales (retención LFPDPPP)
// ---------------------------------------------------------------------------
probar(function_exists('flyers_purgar_datos_personales'), 'La función flyers_purgar_datos_personales debe existir');
try {
    probar(is_int(flyers_purgar_datos_personales(6)), 'La función de purga debe retornar el conteo entero (int) de filas modificadas');
    echo "✔ Prueba 6: Purga de datos personales (LFPDPPP): PASÓ\n";
} catch (BdError $e) {
    echo "⚠ Prueba 6: Purga de datos personales (LFPDPPP): OMITIDA (sin base de datos activa)\n";
}

echo "\n¡Todas las pruebas automatizadas pasaron correctamente! 🚀\n";
