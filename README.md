# URUAPLAN — Cartelera Cultural y Comercial de Uruapan

Plataforma web artesanal desarrollada en PHP vanilla para la difusión de eventos, cultura y cartelera comercial en Uruapan, Michoacán. Incluye un panel privado de moderación y administración sin dependencias externas pesadas.

---

## 🚀 Características Principales

- **Cartelera Pública (`/`)**: Visualización dinámica de eventos, galería, promociones y formulario de envío de flyers.
- **Panel de Administración (`/cheve/`)**:
  - Moderación de flyers recibidos (Aceptar, Rechazar, Archivar, Reabrir, Publicar).
  - Ajustes de encuadre y recortes de imagen interactivosen pantalla.
  - Editor visual de contenidos con respaldo en MySQL y espejo JSON.
- **Seguridad Integrada**:
  - Consultas preparadas en PDO contra Inyección SQL (`bd_ejecutar()`).
  - Escape automático HTML contra Cross-Site Scripting (XSS) (`e()`).
  - Tokens contra falsificación de peticiones CSRF (`csrf_campo()`, `csrf_exigir()`).
  - Autenticación con `password_hash()` (BCRYPT / Argon2id).
- **Antispam Multinivel**:
  - Tokens firmados con HMAC de caducidad temporal.
  - Campo trampa *honeypot* invisible.
  - Limitador de tasa por IP.
- **Registro de Ubicación**:
  - Registro de coordenadas GPS si el usuario las comparte voluntariamente desde el formulario.
  - Alertas visuales de procedencia en el panel cuando se incluyen coordenadas.
- **Privacidad y Retención de Datos (LFPDPPP)**:
  - Función y botón de purga para limpiar la IP y User-Agent de flyers de más de 6 meses de antigüedad.

---

## 📁 Estructura del Proyecto

```text
uruaplan/
├── index.php                 # Página pública principal (cartelera, eventos, contacto)
├── enviar-evento.php         # Handler del formulario público de solicitudes
├── desplegar.sh              # Script de despliegue al servidor
├── css/                      # Hojas de estilo CSS vanilla
├── js/                       # Scripts interactivos JavaScript
├── img/                      # Imágenes públicas y subidas de cartelera
│
└── cheve/                    # PANEL DE ADMINISTRACIÓN
    ├── index.php             # Login del panel
    ├── admin.php             # Editor de contenidos del sitio
    ├── flyers.php            # Bandeja principal de moderación de flyers
    ├── flyer.php             # Vista y edición a detalle de un flyer
    ├── flyer-imagen.php      # Proxy seguro para servir imágenes privadas
    ├── cambiar-password.php  # Cambio de contraseña del administrador
    ├── probar-correo.php     # Diagnóstico de envío SMTP
    ├── instalar.php          # Instalador e inicializador de tablas MySQL
    │
    ├── includes/             # LÓGICA Y MODELO DE DATOS
    │   ├── config.php        # Configuración global y rutas
    │   ├── bd.php            # Abstracción PDO y seguridad SQL
    │   ├── auth.php          # Control de sesiones y autenticación
    │   ├── csrf.php          # Protección CSRF
    │   ├── antispam.php      # Antispam del formulario público
    │   ├── flyers.php        # Modelo de datos de los flyers
    │   ├── funciones.php     # Utilidades y sanitización de datos
    │   ├── correo.php        # Envío SMTP y avisos
    │   ├── subidas.php       # Validación estricta de imágenes
    │   └── esquema-bd.php    # Esquema DDL y migraciones de tablas
    │
    ├── data/                 # Almacenamiento privado de flyers y respaldos JSON
    └── tests/
        └── pruebas.php       # Suite de pruebas de humo automatizadas
```

---

## 🛠️ Requisitos de Instalación

- **PHP**: 7.4 o superior (compatible con PHP 8.x).
- **Extensiones de PHP**: `pdo_mysql`, `gd`, `fileinfo`, `openssl`, `mbstring`.
- **Base de Datos**: MySQL 5.7+ o MariaDB 10.3+.
- **Servidor Web**: Apache con `mod_rewrite` habilitado o Nginx.

---

## ⚙️ Configuración e Inicialización

### 1. Clonar / Descargar el repositorio
```bash
git clone https://github.com/tu-usuario/uruaplan.git
cd uruaplan
```

### 2. Configurar las Credenciales de Producción
En un servidor o cPanel, copia la plantilla `credenciales_uruaplan.example.php` una carpeta arriba de `public_html` (fuera del servidor web):
```bash
cp credenciales_uruaplan.example.php ../credenciales_uruaplan.php
```
Edita sus valores con tus credenciales reales de MySQL y SMTP:
```php
return array(
    'BD_HOST'     => '127.0.0.1',
    'BD_NOMBRE'   => 'cpanelusr_uruaplan',
    'BD_USUARIO'  => 'cpanelusr_uruaplan',
    'BD_PASSWORD' => 'TuContraseñaSegura123!',
    // ...
);
```

### 3. Ejecutar el Instalador
Abre en tu navegador:
`http://localhost/cheve/instalar.php`

El instalador creará automáticamente todas las tablas MySQL e insertará el usuario administrador inicial.

---

## 🧪 Pruebas Automatizadas (Pruebas de Humo)

El proyecto cuenta con un ejecutor de pruebas de humo sin dependencias externas (sin Composer ni PHPUnit):

```bash
php cheve/tests/pruebas.php
```

Verifica:
- Bloqueo de ataques Path Traversal.
- Sanitización de vectores XSS.
- Purga de datos personales (LFPDPPP).
- Firma HMAC y tiempo del token antispam.

---

## 💻 Desarrollo Local

Para iniciar el servidor de desarrollo integrado de PHP:

```bash
php -S 127.0.0.1:8000
```
Visita `http://127.0.0.1:8000` para el sitio público y `http://127.0.0.1:8000/cheve/` para el panel.

---

## 📊 Monitoreo y Error Log

Los registros de actividad y errores del servidor se escriben con el prefijo **`[uruaplan]`** en el registro de errores de PHP del servidor:
- **Local / CLI**: Salida estándar de errores (`stderr`).
- **cPanel / Servidor de Producción**: Archivo `error_log` dentro del directorio del dominio o `/var/log/php_errors.log`.

Para filtrar únicamente los registros de Uruaplan en Linux:
```bash
tail -f /path/to/error_log | grep '\[uruaplan\]'
```

---

## 📜 Licencia y Derechos

Desarrollado para **Uruaplan.com**. Todos los derechos reservados.
