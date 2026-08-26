# GUÍA DE DESPLIEGUE EN PRODUCCIÓN — GODADDY / cPANEL (URUAPLAN)

Esta guía detalla los pasos exactos, qué archivos subir, cuáles proteger y cómo actualizar el sitio **Uruaplan** en **GoDaddy / cPanel** de forma segura y sin riesgo de sobrescribir datos de producción.

---

## 1. RESUMEN DEL PAQUETE DE DESPLIEGUE

Para evitar subir archivos de desarrollo, respaldos locales o claves de pruebas por error, el proyecto cuenta con el script automatizado `desplegar.sh`.

### Generar el paquete listo para producción:
Ejecuta en tu terminal local:
```bash
bash desplegar.sh
```

El script ejecutará las 6 pruebas automatizadas de seguridad y generará un paquete comprimido en la carpeta `dist/`:
```text
dist/uruaplan_release_20260825_105151.zip
```

---

## 2. MATRIZ DE ARCHIVOS: QUÉ SUBIR Y QUÉ PROTEGER

### ✅ QUÉ SÍ SE DEBE SUBIR / REEMPLAZAR
*(Incluidos automáticamente en el archivo `.zip` generado por `desplegar.sh`)*

| Archivo / Carpeta | Descripción del Cambio / Propósito |
|---|---|
| `index.php` | Landing page principal + Leyenda y Modal de Aviso de Privacidad LFPDPPP. |
| `enviar-evento.php` | Procesamiento del formulario de eventos con antispam y tasa por IP. |
| `css/styles.css` | Estilos visuales del sitio, modal de privacidad y lightbox. |
| `js/main.js` | Lógica interactiva (fondos dinámicos, contadores, modal con Escape). |
| `.htaccess` | Reglas de seguridad (`Permissions-Policy`, bloqueo de `.gz`, tipos MIME). |
| `respaldo-bd.sh` | Script de respaldo MySQL seguro (`MYSQL_PWD` + directorio fuera de `public_html`). |
| `credenciales_uruaplan.example.php` | Plantilla de configuración de credenciales externas. |
| `cheve/admin.php` | Editor de contenido del panel. |
| `cheve/flyers.php` | Bandeja de moderación de solicitudes de flyers. |
| `cheve/flyer.php` | Ficha detallada de solicitud de flyer. |
| `cheve/flyer-imagen.php` | Visor de imágenes de solicitudes privadas. |
| `cheve/index.php`, `logout.php`, `cambiar-password.php` | Login, cierre de sesión y cambio de contraseña. |
| `cheve/assets/` | Estilos (`panel.css`) y scripts (`panel.js`) del administrador. |
| `cheve/includes/` | Lógica refactorizada (`guardar.php`, `correo.php`, `antispam.php`, `auth.php`, `funciones.php`, `bd.php`, `esquema.php`, `flyers.php`, etc.). |

---

### ❌ QUÉ NUNCA SE DEBE SOBREESCRIBIR EN PRODUCCIÓN
*(Archivos que contienen datos de producción reales o archivos generados por usuarios)*

| Archivo / Carpeta | Por qué NO sobreescribir / conservar en el servidor |
|---|---|
| `../credenciales_uruaplan.php` | Contiene el usuario, host y contraseña REALES de la base de datos de producción y del SMTP. Vive preferentemente fuera de `public_html`. |
| `cheve/includes/config-bd.php` | Mantiene la configuración activa en producción si no usas credenciales externas. |
| `cheve/includes/config-correo.php` | Mantiene las credenciales del servidor SMTP en producción. |
| `cheve/data/secreto.json` | Clave secreta HMAC generada en producción para la firma de tokens antispam. |
| `img/uploads/` | Fotos e imágenes subidas por los usuarios en la web pública. |
| `cheve/data/flyers/` | Fotografías adjuntas a solicitudes de flyers en moderación. |

---

### 🚫 QUÉ NO DEBE EXISTIR EN PRODUCCIÓN
*(Excluidos automáticamente en el paquete `.zip` por `desplegar.sh`)*

- `cheve/instalar.php` *(Herramienta de instalación inicial, debe eliminarse tras instalar)*
- `cheve/probar-correo.php` *(Herramienta de diagnóstico de SMTP)*
- `cheve/tests/` *(Suite de pruebas automatizadas locales)*
- `.git/` y `.gitignore` *(Control de versiones local)*
- `ANALISIS*.md` y `INSTRUCCIONES.md` *(Documentación técnica interna)*
- Archivos `.sql.gz` de respaldos antiguos.

---

## 3. PASO A PASO PARA ACTUALIZAR EN GODADDY / cPANEL

### Paso 1: Generar el paquete zip
En tu máquina local, ejecuta:
```bash
bash desplegar.sh
```

### Paso 2: Crear respaldo previo en GoDaddy (Recomendado)
1. Entra a **cPanel** en GoDaddy.
2. Abre **Administrador de Archivos** (`File Manager`).
3. Ve a `public_html/`.
4. Selecciona todo el contenido y crea un archivo comprimido de respaldo: `respaldo_previo.zip`.

### Paso 3: Subir el nuevo paquete
1. Dentro de `public_html/` en el Administrador de Archivos de cPanel, haz clic en **Cargar** (`Upload`).
2. Sube el archivo `.zip` generado (ejemplo: `dist/uruaplan_release_20260825_105151.zip`).

### Paso 4: Extraer y Reemplazar
1. Selecciona el archivo `.zip` subido en `public_html/` y haz clic en **Extraer** (`Extract`).
2. Confirma reemplazar los archivos existentes.
3. Elimina el archivo `.zip` subido para mantener limpio el servidor.

### Paso 5: Verificar Permisos de Carpetas
Asegúrate en cPanel de que las siguientes carpetas tengan permisos `755`:
- `img/uploads/` → `755`
- `cheve/data/` → `755`
- `cheve/data/flyers/` → `755`

---

## 4. CHECKLIST DE VERIFICACIÓN TRAS EL DESPLIEGUE

Una vez extraído el paquete en el servidor, realiza estas comprobaciones sencillas:

1. **Carga Pública (`https://uruaplan.com/`)**:
   - Comprueba que la página cargue correctamente con sus fondos y tipografías.
   - Haz clic en el enlace **Aviso de Privacidad** en el footer o en el formulario: verifica que abra la ventana modal informativa.

2. **Prueba del Formulario**:
   - Envía un evento de prueba desde el formulario «Promociona tu Plan».
   - Verifica que aparezca el aviso de confirmación verde y recibas la notificación por correo.

3. **Acceso al Panel (`https://uruaplan.com/cheve/`)**:
   - Inicia sesión en el panel administrativo.
   - Entra a **Moderación de Flyers** (`/cheve/flyers.php`) y confirma que el evento enviado aparezca en la bandeja.

4. **Verificación de Respaldo de BD**:
   - Ejecuta `bash respaldo-bd.sh` desde la consola del servidor (SSH) o prueba la tarea programada Cron.
   - Confirma que el respaldo comprimido `.sql.gz` se genere correctamente en la carpeta `/home/usuario/respaldos_uruaplan/` (fuera del webroot public_html).

---

*Documento generado para el proyecto Uruaplan — Versión 1.0.0 (Agosto 2026)*
