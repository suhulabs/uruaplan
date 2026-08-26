<?php
/**
 * ===========================================================================
 *  ESQUEMA DE LA BASE DE DATOS
 * ===========================================================================
 *  Aquí vive el CREATE TABLE de todo el panel. Es la única fuente de verdad
 *  sobre la forma de los datos, igual que esquema.php lo es sobre qué campos
 *  se pueden editar.
 *
 *  Lo ejecuta cheve/instalar.php. Todas las sentencias llevan
 *  "IF NOT EXISTS", así que volver a pasar el instalador no destruye nada:
 *  crea lo que falte y deja intacto lo que ya está.
 *
 *  Por qué InnoDB y utf8mb4:
 *    - InnoDB da transacciones (guardar el contenido son decenas de
 *      escrituras: o entran todas o no entra ninguna) y claves foráneas
 *      (borrar un flyer se lleva su historial solo).
 *    - utf8mb4 es el único juego de caracteres que guarda bien acentos,
 *      la ñ y los emojis. Con el viejo "utf8" de MySQL un emoji corta
 *      el texto por la mitad.
 * ===========================================================================
 */

require_once __DIR__ . '/bd.php';

/**
 * Todas las tablas, en orden de creación (las que tienen clave foránea
 * van después de aquella a la que apuntan).
 *
 * @return array  nombre => sentencia CREATE TABLE
 */
function bd_tablas()
{
    $motor = ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

    return array(

        // -------------------------------------------------------------------
        // CONTENIDO EDITABLE
        // -------------------------------------------------------------------

        // Un renglón por cada campo del panel. La pareja (seccion, clave) es
        // exactamente la misma que usa esquema.php, así que agregar un campo
        // nuevo al panel NO exige tocar la base de datos: aparece solo.
        'contenido' => "CREATE TABLE IF NOT EXISTS contenido (
            seccion         VARCHAR(40)  NOT NULL,
            clave           VARCHAR(60)  NOT NULL,
            valor           MEDIUMTEXT   NOT NULL,
            actualizado     DATETIME     NOT NULL,
            actualizado_por VARCHAR(20)  DEFAULT NULL,
            PRIMARY KEY (seccion, clave)
        {$motor}",

        // Las listas (fondos de portada, elementos de la galería) necesitan
        // orden y un número variable de filas, así que van aparte. Cada fila
        // es un renglón aquí y sus campos cuelgan de la tabla siguiente.
        'contenido_listas' => "CREATE TABLE IF NOT EXISTS contenido_listas (
            id      INT UNSIGNED NOT NULL AUTO_INCREMENT,
            seccion VARCHAR(40)  NOT NULL,
            lista   VARCHAR(40)  NOT NULL,
            orden   INT          NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY idx_lista_orden (seccion, lista, orden)
        {$motor}",

        // Los campos de cada fila. Se guardan como pares clave/valor por la
        // misma razón que en 'contenido': las listas no tienen todas los
        // mismos campos, y el panel puede ganar campos nuevos sin migración.
        'contenido_lista_campos' => "CREATE TABLE IF NOT EXISTS contenido_lista_campos (
            fila_id INT UNSIGNED NOT NULL,
            campo   VARCHAR(40)  NOT NULL,
            valor   MEDIUMTEXT   NOT NULL,
            PRIMARY KEY (fila_id, campo),
            CONSTRAINT fk_campo_fila FOREIGN KEY (fila_id)
                REFERENCES contenido_listas (id) ON DELETE CASCADE
        {$motor}",

        // -------------------------------------------------------------------
        // ACCESO AL PANEL
        // -------------------------------------------------------------------

        'usuarios' => "CREATE TABLE IF NOT EXISTS usuarios (
            id                   VARCHAR(20)  NOT NULL,
            nombre               VARCHAR(120) NOT NULL,
            hash                 VARCHAR(255) NOT NULL,
            debe_cambiar         TINYINT(1)   NOT NULL DEFAULT 1,
            creado               DATETIME     NOT NULL,
            ultimo_acceso        DATETIME     DEFAULT NULL,
            password_actualizada DATETIME     DEFAULT NULL,
            PRIMARY KEY (id)
        {$motor}",

        // Cada persona entra con dos alias (su nombre o su profesión).
        // Tabla aparte porque es una relación uno-a-muchos de verdad;
        // meterlos en una columna separada por comas obligaría a buscar
        // con LIKE, que ni usa índice ni distingue "luis" de "luisa".
        'usuario_alias' => "CREATE TABLE IF NOT EXISTS usuario_alias (
            alias      VARCHAR(40) NOT NULL,
            usuario_id VARCHAR(20) NOT NULL,
            PRIMARY KEY (alias),
            KEY idx_alias_usuario (usuario_id),
            CONSTRAINT fk_alias_usuario FOREIGN KEY (usuario_id)
                REFERENCES usuarios (id) ON DELETE CASCADE ON UPDATE CASCADE
        {$motor}",

        // Contador de intentos fallidos de login (por cuenta+IP y por IP).
        'intentos' => "CREATE TABLE IF NOT EXISTS intentos (
            clave  VARCHAR(180)  NOT NULL,
            fallos INT UNSIGNED  NOT NULL DEFAULT 0,
            ultimo INT UNSIGNED  NOT NULL DEFAULT 0,
            PRIMARY KEY (clave),
            KEY idx_intentos_ultimo (ultimo)
        {$motor}",

        // -------------------------------------------------------------------
        // AJUSTES INTERNOS Y ANTISPAM
        // -------------------------------------------------------------------

        // Valores que el sistema se guarda solo y que nadie edita a mano:
        // hoy, la clave con la que se firman los tokens del formulario.
        'ajustes' => "CREATE TABLE IF NOT EXISTS ajustes (
            clave VARCHAR(60) NOT NULL,
            valor TEXT        NOT NULL,
            PRIMARY KEY (clave)
        {$motor}",

        // Marca de tiempo de cada envío aceptado, para el límite por hora.
        // Se guarda el HASH de la IP, no la IP: sirve igual para contar y
        // no deja un registro de quién visitó la página.
        'envios_ip' => "CREATE TABLE IF NOT EXISTS envios_ip (
            id      INT UNSIGNED NOT NULL AUTO_INCREMENT,
            ip_hash CHAR(64)     NOT NULL,
            cuando  INT UNSIGNED NOT NULL,
            PRIMARY KEY (id),
            KEY idx_envios (ip_hash, cuando)
        {$motor}",

        // -------------------------------------------------------------------
        // FLYERS
        // -------------------------------------------------------------------

        // El corazón del cambio: cada solicitud que llega por el formulario
        // deja de ser solo un correo y pasa a ser un renglón con estado.
        //
        //   estado     dónde está en el proceso de revisión
        //   publicado  si además se está enseñando en la web (solo tiene
        //              sentido con estado = 'aceptado')
        //
        // Los dos son independientes a propósito: aceptar un flyer es decir
        // "está bien", publicarlo es decir "sáquenlo ya". Un evento aceptado
        // puede esperar sin estar visible, y despublicarse sin rechazarlo.
        'flyers' => "CREATE TABLE IF NOT EXISTS flyers (
            id             INT UNSIGNED NOT NULL AUTO_INCREMENT,

            estado         ENUM('pendiente','aceptado','rechazado','archivado')
                           NOT NULL DEFAULT 'pendiente',
            publicado      TINYINT(1)   NOT NULL DEFAULT 0,

            evento         VARCHAR(80)  NOT NULL,
            subtitulo      VARCHAR(90)  NOT NULL DEFAULT '',
            fecha          DATE         DEFAULT NULL,
            hora           TIME         DEFAULT NULL,
            ubicacion      VARCHAR(120) NOT NULL DEFAULT '',
            precio         VARCHAR(60)  NOT NULL DEFAULT '',
            contacto       VARCHAR(60)  NOT NULL DEFAULT '',
            descripcion    VARCHAR(255) NOT NULL DEFAULT '',
            comentarios    TEXT         NOT NULL,

            -- La foto vive en cheve/data/flyers/ (carpeta cerrada al público)
            -- mientras se revisa. Solo al publicar se copia a img/uploads/ y
            -- se anota aquí su ruta pública.
            imagen         VARCHAR(190) NOT NULL DEFAULT '',
            imagen_publica VARCHAR(190) NOT NULL DEFAULT '',
            imagen_mime    VARCHAR(40)  NOT NULL DEFAULT '',
            imagen_ancho   INT UNSIGNED NOT NULL DEFAULT 0,
            imagen_alto    INT UNSIGNED NOT NULL DEFAULT 0,
            imagen_bytes   INT UNSIGNED NOT NULL DEFAULT 0,

            -- Logo opcional de la marca / organizador
            logo_organizador         VARCHAR(190) NOT NULL DEFAULT '',
            logo_organizador_publico VARCHAR(190) NOT NULL DEFAULT '',

            -- Encuadre de la foto dentro de la tarjeta, igual que en galería.
            pos_x          DECIMAL(5,2) NOT NULL DEFAULT 50.00,
            pos_y          DECIMAL(5,2) NOT NULL DEFAULT 50.00,
            zoom           DECIMAL(4,2) NOT NULL DEFAULT 1.00,

            -- Nota interna del equipo: motivo del rechazo, recordatorios...
            -- Nunca sale en la web.
            nota           TEXT         NOT NULL,

            -- De dónde vino y si el aviso por correo llegó a salir.
            ip             VARCHAR(45)  NOT NULL DEFAULT '',
            agente         VARCHAR(255) NOT NULL DEFAULT '',
            geo_origen     VARCHAR(150) NOT NULL DEFAULT '',
            correo_ok      TINYINT(1)   NOT NULL DEFAULT 0,

            recibido       DATETIME     NOT NULL,
            actualizado    DATETIME     NOT NULL,
            revisado_por   VARCHAR(20)  DEFAULT NULL,
            revisado_en    DATETIME     DEFAULT NULL,

            PRIMARY KEY (id),
            KEY idx_bandeja  (estado, recibido),
            KEY idx_cartelera (publicado, estado, fecha)
        {$motor}",

        // Quién hizo qué y cuándo. Es lo que convierte la bandeja en un
        // histórico de verdad: aunque un flyer se edite diez veces, queda
        // el rastro de cada cambio y de quién lo firmó.
        'flyer_historial' => "CREATE TABLE IF NOT EXISTS flyer_historial (
            id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
            flyer_id       INT UNSIGNED NOT NULL,
            accion         VARCHAR(30)  NOT NULL,
            usuario_id     VARCHAR(20)  DEFAULT NULL,
            usuario_nombre VARCHAR(120) NOT NULL DEFAULT '',
            detalle        TEXT         NOT NULL,
            cuando         DATETIME     NOT NULL,
            PRIMARY KEY (id),
            KEY idx_historial_flyer (flyer_id, cuando),
            CONSTRAINT fk_historial_flyer FOREIGN KEY (flyer_id)
                REFERENCES flyers (id) ON DELETE CASCADE
        {$motor}",
    );
}

/**
 * Crea las tablas que falten.
 *
 * @return array  ('creadas' => string[], 'existentes' => string[])
 * @throws BdError
 */
function bd_crear_tablas()
{
    $creadas    = array();
    $existentes = array();

    foreach (bd_tablas() as $nombre => $ddl) {
        if (bd_tabla_existe($nombre)) {
            $existentes[] = $nombre;
            continue;
        }

        bd_ejecutar($ddl);
        $creadas[] = $nombre;
    }

    // Migración automática de columnas para instalaciones existentes
    if (bd_tabla_existe('flyers')) {
        try {
            $columnas = bd_filas('SHOW COLUMNS FROM flyers LIKE "geo_origen"');
            if (empty($columnas)) {
                bd_ejecutar("ALTER TABLE flyers ADD COLUMN geo_origen VARCHAR(150) NOT NULL DEFAULT '' AFTER agente");
            }
        } catch (BdError $e) {
            // Ignorar si el motor no lo permite en entornos aislados
        }
    }

    return array('creadas' => $creadas, 'existentes' => $existentes);
}

/**
 * Borra TODAS las tablas del panel. Solo la usa el instalador cuando se
 * pide expresamente reinstalar desde cero, y con confirmación escrita.
 */
function bd_borrar_tablas()
{
    $nombres = array_reverse(array_keys(bd_tablas()));
    $borradas = array();

    // Sin esto, el orden de borrado tendría que respetar las claves foráneas
    // aunque las tablas se vayan todas de todos modos.
    bd_ejecutar('SET FOREIGN_KEY_CHECKS = 0');

    foreach ($nombres as $nombre) {
        if (!bd_tabla_existe($nombre)) {
            continue;
        }
        // El nombre sale de bd_tablas(), no del usuario: no hay inyección
        // posible. DROP TABLE no admite marcadores para el identificador.
        bd_ejecutar('DROP TABLE `' . $nombre . '`');
        $borradas[] = $nombre;
    }

    bd_ejecutar('SET FOREIGN_KEY_CHECKS = 1');

    return $borradas;
}
