<?php
/**
 * ESQUEMA DE CONTENIDO EDITABLE
 *
 * Esta es la única fuente de verdad sobre qué se puede editar.
 * El formulario del panel se genera a partir de aquí, y el guardado
 * solo acepta claves que existan en este esquema (lista blanca):
 * cualquier campo extra que llegue por POST se descarta.
 *
 * Para agregar un campo nuevo basta con añadirlo aquí y luego usarlo
 * en la plantilla pública con  ce('seccion','campo').
 *
 * Tipos disponibles:
 *   texto   - input de una línea
 *   area    - textarea de varias líneas
 *   email   - input de correo
 *   tel     - input de teléfono
 *   url     - input de enlace
 *   imagen  - subida de archivo (jpg / png / webp)
 *   media   - subida de archivo (jpg / png / webp / mp4)
 *   icono   - clase de Font Awesome, p. ej. "fa-solid fa-store"
 */

function esquema_secciones()
{
    return array(

        // -------------------------------------------------------------------
        'global' => array(
            'titulo' => 'Datos generales',
            'icono'  => 'fa-solid fa-sliders',
            'ayuda'  => 'Estos datos se reutilizan en TODA la página: cambiarlos aquí los cambia en todos lados a la vez (botón flotante, tarjetas de contacto, pie de página, etc.).',
            'campos' => array(
                'whatsapp_numero'  => array('label' => 'WhatsApp — número para enlaces', 'tipo' => 'tel', 'max' => 20, 'ayuda' => 'SOLO dígitos, con lada de país y sin espacios ni signos. Ejemplo: 524521234567'),
                'whatsapp_visible' => array('label' => 'WhatsApp — como se muestra', 'tipo' => 'texto', 'max' => 40, 'ayuda' => 'El texto bonito que ve el visitante. Ejemplo: +52 (452) 123 4567'),
                'wa_mensaje_flotante' => array('label' => 'Mensaje del botón flotante de WhatsApp', 'tipo' => 'area', 'max' => 300, 'ayuda' => 'Texto que aparece ya escrito cuando alguien toca la burbuja verde.'),
                'correo'           => array('label' => 'Correo electrónico', 'tipo' => 'email', 'max' => 120),
                'ubicacion_corta'  => array('label' => 'Ubicación (corta)', 'tipo' => 'texto', 'max' => 80, 'ayuda' => 'Se usa en la tarjeta de contacto. Ejemplo: Uruapan, Michoacán'),
                'ubicacion_larga'  => array('label' => 'Ubicación (larga)', 'tipo' => 'texto', 'max' => 120, 'ayuda' => 'Se usa en el pie de página. Ejemplo: Uruapan, Michoacán, México'),
                'instagram_handle' => array('label' => 'Usuario de Instagram', 'tipo' => 'texto', 'max' => 40, 'ayuda' => 'Con arroba. Ejemplo: @URUAPLAN'),
                'url_instagram'    => array('label' => 'Enlace a Instagram', 'tipo' => 'url', 'max' => 200),
                'url_facebook'     => array('label' => 'Enlace a Facebook', 'tipo' => 'url', 'max' => 200),
                'url_tiktok'       => array('label' => 'Enlace a TikTok', 'tipo' => 'url', 'max' => 200),
                'logo_header'      => array('label' => 'Logo de la barra superior', 'tipo' => 'imagen'),
                'logo_emblema'     => array('label' => 'Logo emblema (sección Nosotros)', 'tipo' => 'imagen'),
                'logo_promo'       => array('label' => 'Logo de promociones', 'tipo' => 'imagen'),
            ),
        ),

        // -------------------------------------------------------------------
        'seo' => array(
            'titulo' => 'SEO y redes',
            'icono'  => 'fa-solid fa-magnifying-glass',
            'ayuda'  => 'Lo que ve Google en los resultados de búsqueda y lo que aparece cuando alguien comparte el enlace en WhatsApp o Facebook.',
            'campos' => array(
                'title'            => array('label' => 'Título de la pestaña / Google', 'tipo' => 'texto', 'max' => 70, 'ayuda' => 'Ideal: menos de 60 caracteres.'),
                'meta_description' => array('label' => 'Descripción para Google', 'tipo' => 'area', 'max' => 165, 'ayuda' => 'Ideal: entre 120 y 155 caracteres.'),
                'meta_keywords'    => array('label' => 'Palabras clave', 'tipo' => 'area', 'max' => 300, 'ayuda' => 'Separadas por comas.'),
                'og_title'         => array('label' => 'Título al compartir', 'tipo' => 'texto', 'max' => 90),
                'og_description'   => array('label' => 'Descripción al compartir', 'tipo' => 'area', 'max' => 200),
                'og_image'         => array('label' => 'Imagen al compartir', 'tipo' => 'imagen', 'ayuda' => 'Recomendado 1200 × 630 px.'),
            ),
        ),

        // -------------------------------------------------------------------
        'nav' => array(
            'titulo' => 'Menú de navegación',
            'icono'  => 'fa-solid fa-bars',
            'ayuda'  => 'Solo el texto visible de cada enlace. Los destinos (las secciones a las que salta) no cambian.',
            'campos' => array(
                'inicio'     => array('label' => 'Enlace 1', 'tipo' => 'texto', 'max' => 30),
                'nosotros'   => array('label' => 'Enlace 2', 'tipo' => 'texto', 'max' => 30),
                'cartelera'  => array('label' => 'Enlace 3 — cartelera de eventos', 'tipo' => 'texto', 'max' => 30, 'ayuda' => 'Este enlace solo sale en el menú cuando la cartelera se está mostrando. Si lo dejas vacío pone «Eventos».'),
                'promociona' => array('label' => 'Enlace 4', 'tipo' => 'texto', 'max' => 30),
                'galeria'    => array('label' => 'Enlace 5', 'tipo' => 'texto', 'max' => 30),
                'contacto'   => array('label' => 'Botón de contacto', 'tipo' => 'texto', 'max' => 30),
            ),
        ),

        // -------------------------------------------------------------------
        'hero' => array(
            'titulo' => 'Portada (Hero)',
            'icono'  => 'fa-solid fa-mountain-sun',
            'ayuda'  => 'La primera pantalla que ve el visitante.',
            'campos' => array(
                'titulo'           => array('label' => 'Título principal', 'tipo' => 'texto', 'max' => 80, 'ayuda' => 'La primera parte, en blanco.'),
                'titulo_resaltado' => array('label' => 'Título — parte resaltada', 'tipo' => 'texto', 'max' => 40, 'ayuda' => 'Se pinta en verde lima al final del título.'),
                'subtitulo'        => array('label' => 'Subtítulo', 'tipo' => 'area', 'max' => 300),
                'btn1_texto'       => array('label' => 'Botón 1 — texto', 'tipo' => 'texto', 'max' => 40),
                'btn1_icono'       => array('label' => 'Botón 1 — icono', 'tipo' => 'icono'),
                'btn2_texto'       => array('label' => 'Botón 2 — texto', 'tipo' => 'texto', 'max' => 40),
                'btn2_icono'       => array('label' => 'Botón 2 — icono', 'tipo' => 'icono'),
            ),
            'listas' => array(
                'fondos' => array(
                    'titulo'   => 'Fondos que rotan en la portada',
                    'ayuda'    => 'Se muestran en carrusel, uno cada 5 segundos, en el orden de esta lista. Recomendado: fotos horizontales de al menos 1600 px de ancho. Como el fondo ocupa toda la pantalla, casi siempre se recorta: usa «Ajustar encuadre» para decidir qué parte se ve, y comprueba la vista de celular, que es la que más recorta.',
                    'singular' => 'fondo',
                    'campos'   => array(
                        'imagen'   => array('label' => 'Imagen', 'tipo' => 'imagen'),
                        'encuadre' => array('label' => 'Encuadre', 'tipo' => 'encuadre', 'marco' => 'hero'),
                        'visible'  => array('label' => 'Usar este fondo', 'tipo' => 'check', 'defecto' => true, 'ayuda' => 'Si lo apagas, este fondo deja de salir en el carrusel pero no se borra.'),
                    ),
                ),
            ),
        ),

        // -------------------------------------------------------------------
        'nosotros' => array(
            'titulo' => 'Nosotros',
            'icono'  => 'fa-solid fa-users',
            'campos' => array(
                'subtitulo'     => array('label' => 'Etiqueta superior', 'tipo' => 'texto', 'max' => 60),
                'titulo'        => array('label' => 'Título', 'tipo' => 'texto', 'max' => 120),
                'parrafo_1'     => array('label' => 'Párrafo 1', 'tipo' => 'area', 'max' => 600),
                'parrafo_2'     => array('label' => 'Párrafo 2', 'tipo' => 'area', 'max' => 600),
                'imagen'        => array('label' => 'Imagen / logo de la sección', 'tipo' => 'imagen'),
                'pilar1_icono'  => array('label' => 'Pilar 1 — icono', 'tipo' => 'icono'),
                'pilar1_titulo' => array('label' => 'Pilar 1 — título', 'tipo' => 'texto', 'max' => 40),
                'pilar1_texto'  => array('label' => 'Pilar 1 — texto', 'tipo' => 'area', 'max' => 200),
                'pilar2_icono'  => array('label' => 'Pilar 2 — icono', 'tipo' => 'icono'),
                'pilar2_titulo' => array('label' => 'Pilar 2 — título', 'tipo' => 'texto', 'max' => 40),
                'pilar2_texto'  => array('label' => 'Pilar 2 — texto', 'tipo' => 'area', 'max' => 200),
            ),
        ),

        // -------------------------------------------------------------------
        'promociona' => array(
            'titulo' => 'Promociona tu Plan',
            'icono'  => 'fa-solid fa-bullhorn',
            'campos' => array(
                'subtitulo'        => array('label' => 'Etiqueta superior', 'tipo' => 'texto', 'max' => 80),
                'titulo'           => array('label' => 'Título', 'tipo' => 'texto', 'max' => 80),
                'titulo_resaltado' => array('label' => 'Título — parte resaltada', 'tipo' => 'texto', 'max' => 40),
                'parrafo'          => array('label' => 'Párrafo', 'tipo' => 'area', 'max' => 400),
                'vineta_1'         => array('label' => 'Viñeta 1', 'tipo' => 'texto', 'max' => 120),
                'vineta_2'         => array('label' => 'Viñeta 2', 'tipo' => 'texto', 'max' => 120),
                'vineta_3'         => array('label' => 'Viñeta 3', 'tipo' => 'texto', 'max' => 120),
                'btn_texto'        => array('label' => 'Texto del botón', 'tipo' => 'texto', 'max' => 60),
                'imagen'           => array('label' => 'Imagen / logo del bloque', 'tipo' => 'imagen'),
            ),
        ),

        // -------------------------------------------------------------------
        'cartelera' => array(
            'titulo' => 'Cartelera de eventos',
            'icono'  => 'fa-solid fa-calendar-star',
            'ayuda'  => 'Los eventos NO se escriben aquí: salen solos de los flyers que apruebas y publicas en la pestaña «Flyers» de la barra de arriba. En esta pantalla solo se editan los textos que los acompañan.',
            'campos' => array(
                'subtitulo'   => array('label' => 'Etiqueta superior', 'tipo' => 'texto', 'max' => 60),
                'titulo'      => array('label' => 'Título', 'tipo' => 'texto', 'max' => 80),
                'descripcion' => array('label' => 'Descripción', 'tipo' => 'area', 'max' => 300),
                'vacio_texto' => array('label' => 'Texto cuando no hay eventos', 'tipo' => 'area', 'max' => 300, 'ayuda' => 'Lo que se lee si no hay ningún flyer publicado. Si lo dejas vacío, la sección entera desaparece de la web hasta que publiques uno.'),
                'btn_texto'   => array('label' => 'Texto del botón final', 'tipo' => 'texto', 'max' => 60, 'ayuda' => 'Lleva al formulario para mandar un evento. Vacío = sin botón.'),
            ),
        ),

        // -------------------------------------------------------------------
        'como_funciona' => array(
            'titulo' => '¿Cómo Funciona?',
            'icono'  => 'fa-solid fa-list-ol',
            'campos' => array(
                'subtitulo'    => array('label' => 'Etiqueta superior', 'tipo' => 'texto', 'max' => 60),
                'titulo'       => array('label' => 'Título', 'tipo' => 'texto', 'max' => 80),
                'descripcion'  => array('label' => 'Descripción', 'tipo' => 'area', 'max' => 300),
                'paso1_icono'  => array('label' => 'Paso 1 — icono', 'tipo' => 'icono'),
                'paso1_titulo' => array('label' => 'Paso 1 — título', 'tipo' => 'texto', 'max' => 40),
                'paso1_texto'  => array('label' => 'Paso 1 — texto', 'tipo' => 'area', 'max' => 250),
                'paso2_icono'  => array('label' => 'Paso 2 — icono', 'tipo' => 'icono'),
                'paso2_titulo' => array('label' => 'Paso 2 — título', 'tipo' => 'texto', 'max' => 40),
                'paso2_texto'  => array('label' => 'Paso 2 — texto', 'tipo' => 'area', 'max' => 250),
                'paso3_icono'  => array('label' => 'Paso 3 — icono', 'tipo' => 'icono'),
                'paso3_titulo' => array('label' => 'Paso 3 — título', 'tipo' => 'texto', 'max' => 40),
                'paso3_texto'  => array('label' => 'Paso 3 — texto', 'tipo' => 'area', 'max' => 250),
                'paso4_icono'  => array('label' => 'Paso 4 — icono', 'tipo' => 'icono'),
                'paso4_titulo' => array('label' => 'Paso 4 — título', 'tipo' => 'texto', 'max' => 40),
                'paso4_texto'  => array('label' => 'Paso 4 — texto', 'tipo' => 'area', 'max' => 250),
            ),
        ),

        // -------------------------------------------------------------------
        'instagram' => array(
            'titulo' => 'Instagram',
            'icono'  => 'fa-brands fa-instagram',
            'ayuda'  => 'En la descripción puedes escribir {handle} y se sustituirá automáticamente por el usuario de Instagram, resaltado en color.',
            'campos' => array(
                'badge'         => array('label' => 'Etiqueta superior', 'tipo' => 'texto', 'max' => 40),
                'titulo'        => array('label' => 'Título', 'tipo' => 'texto', 'max' => 80),
                'descripcion'   => array('label' => 'Descripción', 'tipo' => 'area', 'max' => 400, 'ayuda' => 'Usa {handle} donde quieras que aparezca el usuario resaltado.'),
                'btn_texto'     => array('label' => 'Texto del botón', 'tipo' => 'texto', 'max' => 60),
                'badge_overlay' => array('label' => 'Etiqueta sobre la imagen', 'tipo' => 'texto', 'max' => 30),
                'imagen_1'      => array('label' => 'Imagen 1 del carrusel', 'tipo' => 'imagen', 'ayuda' => 'Formato vertical tipo historia (9:16).'),
                'imagen_2'      => array('label' => 'Imagen 2 del carrusel', 'tipo' => 'imagen', 'ayuda' => 'Formato vertical tipo historia (9:16).'),
            ),
        ),

        // -------------------------------------------------------------------
        'galeria' => array(
            'titulo' => 'Galería',
            'icono'  => 'fa-solid fa-images',
            'campos' => array(
                'subtitulo'   => array('label' => 'Etiqueta superior', 'tipo' => 'texto', 'max' => 60),
                'titulo'      => array('label' => 'Título', 'tipo' => 'texto', 'max' => 80),
                'descripcion' => array('label' => 'Descripción', 'tipo' => 'area', 'max' => 300),
            ),
            'listas' => array(
                'items' => array(
                    'titulo'   => 'Fotos y videos del collage',
                    'ayuda'    => 'Reordena con las flechas ↑ ↓. Se aceptan imágenes (JPG, PNG, WEBP) y videos MP4 cortos. Como el collage recorta las fotos para encajarlas, usa «Ajustar encuadre» para decidir qué parte se ve. Ojo: al cambiar el orden cambia también el tamaño de la casilla, así que conviene reencuadrar después de mover.',
                    'singular' => 'elemento',
                    'campos'   => array(
                        'archivo'  => array('label' => 'Foto o video', 'tipo' => 'media'),
                        'encuadre' => array('label' => 'Encuadre', 'tipo' => 'encuadre', 'marco' => 'collage'),
                        'etiqueta' => array('label' => 'Etiqueta visible', 'tipo' => 'texto', 'max' => 40),
                        'alt'      => array('label' => 'Texto alternativo', 'tipo' => 'texto', 'max' => 120, 'ayuda' => 'Descripción para buscadores y lectores de pantalla.'),
                        'visible'  => array('label' => 'Mostrar en la web', 'tipo' => 'check', 'defecto' => true, 'ayuda' => 'Si lo desmarcas, el elemento desaparece de la galería pero no se borra: puedes volver a mostrarlo cuando quieras.'),
                    ),
                ),
            ),
        ),

        // -------------------------------------------------------------------
        'contacto' => array(
            'titulo' => 'Contacto y formulario',
            'icono'  => 'fa-solid fa-envelope-open-text',
            'ayuda'  => 'Los valores de las tres tarjetas (correo, WhatsApp, ubicación) salen de "Datos generales". Aquí solo se editan sus etiquetas.',
            'campos' => array(
                'subtitulo'         => array('label' => 'Etiqueta superior', 'tipo' => 'texto', 'max' => 60),
                'titulo'            => array('label' => 'Título', 'tipo' => 'texto', 'max' => 80),
                'descripcion'       => array('label' => 'Descripción', 'tipo' => 'area', 'max' => 300),
                'tarjeta1_label'    => array('label' => 'Tarjeta 1 — etiqueta', 'tipo' => 'texto', 'max' => 40),
                'tarjeta2_label'    => array('label' => 'Tarjeta 2 — etiqueta', 'tipo' => 'texto', 'max' => 40),
                'tarjeta3_label'    => array('label' => 'Tarjeta 3 — etiqueta', 'tipo' => 'texto', 'max' => 40),
                'free_badge'        => array('label' => 'Insignia de gratuidad', 'tipo' => 'texto', 'max' => 30),
                'free_texto'        => array('label' => 'Texto de gratuidad', 'tipo' => 'area', 'max' => 400, 'ayuda' => 'Los iconos de TikTok, Facebook e Instagram se añaden solos al final.'),
                'nota_titulo'       => array('label' => 'Nota de moderación — título', 'tipo' => 'texto', 'max' => 40),
                'nota_texto'        => array('label' => 'Nota de moderación — texto', 'tipo' => 'area', 'max' => 400),
                'correo_formulario' => array('label' => 'Correo que recibe los eventos', 'tipo' => 'email', 'max' => 120, 'ayuda' => 'A esta dirección se envía el formulario de publicación.'),
                'btn_enviar'        => array('label' => 'Texto del botón de envío', 'tipo' => 'texto', 'max' => 60, 'ayuda' => 'El correo se añade solo al final del botón.'),
                'nota_flyer'        => array('label' => 'Nota bajo la vista previa', 'tipo' => 'area', 'max' => 300),
                'flyer_badge'       => array('label' => 'Insignia de la vista previa', 'tipo' => 'texto', 'max' => 50),
                'flyer_titulo'      => array('label' => 'Flyer de ejemplo — título', 'tipo' => 'texto', 'max' => 45),
                'flyer_subtitulo'   => array('label' => 'Flyer de ejemplo — subtítulo', 'tipo' => 'texto', 'max' => 60),
                'flyer_fecha'       => array('label' => 'Flyer de ejemplo — fecha', 'tipo' => 'texto', 'max' => 30),
                'flyer_hora'        => array('label' => 'Flyer de ejemplo — hora', 'tipo' => 'texto', 'max' => 20),
                'flyer_contacto'    => array('label' => 'Flyer de ejemplo — contacto', 'tipo' => 'texto', 'max' => 30),
                'flyer_precio'      => array('label' => 'Flyer de ejemplo — precio', 'tipo' => 'texto', 'max' => 30),
                'flyer_ubicacion'   => array('label' => 'Flyer de ejemplo — ubicación', 'tipo' => 'texto', 'max' => 70),
                'flyer_descripcion' => array('label' => 'Flyer de ejemplo — descripción', 'tipo' => 'area', 'max' => 160),
                'flyer_imagen'      => array('label' => 'Flyer de ejemplo — foto', 'tipo' => 'imagen'),
                'flyer_footer'      => array('label' => 'Flyer — franja inferior', 'tipo' => 'texto', 'max' => 30),
            ),
        ),

        // -------------------------------------------------------------------
        'especializada' => array(
            'titulo' => 'Promoción Especializada',
            'icono'  => 'fa-solid fa-crown',
            'ayuda'  => 'El bloque dorado del final. Los números y el correo salen de "Datos generales".',
            'campos' => array(
                'badge'         => array('label' => 'Insignia', 'tipo' => 'texto', 'max' => 40),
                'titulo'        => array('label' => 'Título', 'tipo' => 'texto', 'max' => 80),
                'descripcion'   => array('label' => 'Descripción', 'tipo' => 'area', 'max' => 400),
                'wa_label'      => array('label' => 'Caja WhatsApp — etiqueta', 'tipo' => 'texto', 'max' => 60),
                'wa_btn'        => array('label' => 'Caja WhatsApp — texto del botón', 'tipo' => 'texto', 'max' => 60),
                'wa_mensaje'    => array('label' => 'Caja WhatsApp — mensaje prellenado', 'tipo' => 'area', 'max' => 300),
                'mail_label'    => array('label' => 'Caja Correo — etiqueta', 'tipo' => 'texto', 'max' => 60),
                'mail_btn'      => array('label' => 'Caja Correo — texto del botón', 'tipo' => 'texto', 'max' => 60),
                'mail_asunto'   => array('label' => 'Caja Correo — asunto', 'tipo' => 'texto', 'max' => 120),
                'mail_cuerpo'   => array('label' => 'Caja Correo — cuerpo del mensaje', 'tipo' => 'area', 'max' => 400),
            ),
        ),

        // -------------------------------------------------------------------
        'footer' => array(
            'titulo' => 'Pie de página',
            'icono'  => 'fa-solid fa-shoe-prints',
            'ayuda'  => 'En "Hecho con..." puedes escribir {corazon} y se sustituirá por el icono de corazón. La firma del final solo aparece si el nombre del estudio no está vacío.',
            'campos' => array(
                'parrafo'         => array('label' => 'Párrafo de la marca', 'tipo' => 'area', 'max' => 400),
                'nav_titulo'      => array('label' => 'Columna 1 — título', 'tipo' => 'texto', 'max' => 30),
                'nav_1'           => array('label' => 'Columna 1 — enlace 1', 'tipo' => 'texto', 'max' => 30),
                'nav_2'           => array('label' => 'Columna 1 — enlace 2', 'tipo' => 'texto', 'max' => 30),
                'nav_3'           => array('label' => 'Columna 1 — enlace 3', 'tipo' => 'texto', 'max' => 30),
                'nav_4'           => array('label' => 'Columna 1 — enlace 4', 'tipo' => 'texto', 'max' => 30),
                'nav_5'           => array('label' => 'Columna 1 — enlace 5', 'tipo' => 'texto', 'max' => 30),
                'cat_titulo'      => array('label' => 'Columna 2 — título', 'tipo' => 'texto', 'max' => 30),
                'cat_1'           => array('label' => 'Columna 2 — categoría 1', 'tipo' => 'texto', 'max' => 30),
                'cat_2'           => array('label' => 'Columna 2 — categoría 2', 'tipo' => 'texto', 'max' => 30),
                'cat_3'           => array('label' => 'Columna 2 — categoría 3', 'tipo' => 'texto', 'max' => 30),
                'cat_4'           => array('label' => 'Columna 2 — categoría 4', 'tipo' => 'texto', 'max' => 30),
                'cat_5'           => array('label' => 'Columna 2 — categoría 5', 'tipo' => 'texto', 'max' => 30),
                'contacto_titulo' => array('label' => 'Columna 3 — título', 'tipo' => 'texto', 'max' => 30),
                'btn_cta'         => array('label' => 'Botón final', 'tipo' => 'texto', 'max' => 40),
                'copyright'       => array('label' => 'Aviso de copyright', 'tipo' => 'texto', 'max' => 120, 'ayuda' => 'El año se pone solo delante.'),
                'hecho_con'       => array('label' => 'Frase final', 'tipo' => 'texto', 'max' => 80, 'ayuda' => 'Usa {corazon} para el icono del corazón.'),
                'firma_texto'     => array('label' => 'Firma — texto previo', 'tipo' => 'texto', 'max' => 40, 'ayuda' => 'Lo que va antes del nombre. Ejemplo: Desarrollado por'),
                'firma_marca'     => array('label' => 'Firma — nombre del estudio', 'tipo' => 'texto', 'max' => 40, 'ayuda' => 'Déjalo vacío para ocultar la firma por completo.'),
                'firma_url'       => array('label' => 'Firma — enlace de Instagram', 'tipo' => 'url', 'max' => 200),
            ),
        ),
    );
}

/**
 * ¿El tipo de campo guarda una ruta de archivo?
 */
function es_campo_archivo($tipo)
{
    return $tipo === 'imagen' || $tipo === 'media';
}

/**
 * Devuelve la definición de una lista, o null si no existe.
 */
function esquema_lista($seccion, $clave)
{
    $secciones = esquema_secciones();

    if (isset($secciones[$seccion]['listas'][$clave])) {
        return $secciones[$seccion]['listas'][$clave];
    }

    return null;
}
