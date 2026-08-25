# AUDITORÍA TÉCNICA — Proyecto URUAPLAN (pendientes)

**Fecha:** 25 de agosto de 2026 — **4ª revisión**, tras aplicar los pendientes de la 3ª.
**Alcance:** todo el árbol del proyecto. Solo lectura: no se modificó ningún archivo (este documento es lo único escrito).
**Formato:** solo lo que falta por mejorar o cambiar. Lo no comprobable desde el código local va marcado **[a verificar]**.

> Verificado como corregido en esta revisión: la geolocalización quedó cerrada del todo (función, prueba y menciones del README eliminadas), el aviso de privacidad LFPDPPP está en el sitio (modal con datos recabados, finalidad, retención de 6 meses y derechos ARCO, enlazado desde el formulario y el pie), el respaldo avisa si cae al webroot, la plantilla trae un código de instalación no vacío, las dimensiones de imagen se reutilizan desde la validación, la limpieza de tablas es probabilística (1/10 con `random_int`), y los refactors de `guardar.php` y `correo.php` **se revisaron función por función: son fieles al original, sin cambios de comportamiento**. Suite de pruebas 6/6 PASÓ; sintaxis PHP verificada en todos los archivos.

---

## 1. PENDIENTES

### 🟠 ALTO — el único que queda

**A1. El proyecto sigue sin control de versiones (cuarta revisión consecutiva)**
- **Ubicación:** raíz del proyecto (no existe `.git/`).
- **Descripción:** es, desde la primera auditoría, el único pendiente estructural — y cada iteración lo hace más urgente: en esta ronda se refactorizaron dos archivos centrales (`guardar.php`, `correo.php`) sin que exista un "antes" contra el que comparar si algo se rompe. El `.gitignore` está completo y `desplegar.sh` ya excluye `.git/`; no falta ninguna preparación.
- **Recomendación:** `git init && git add -A && git commit -m "Estado inicial"`. Después, un commit por cambio y una etiqueta por despliegue. Es el punto 1 del plan desde hace tres revisiones y toma 15 minutos.

### 🟢 BAJO

- ~~**B1. Docblock duplicado en `correo.php`**~~ *(Completado: eliminado el comentario docblock huérfano)*
- ~~**B2. Modal de privacidad con tecla Escape y fallback CSS `:target`**~~ *(Completado: agregado listener `keydown` para `Escape` en `main.js` y regla `.lightbox:target` en `styles.css`)*
- **B3. `secreto.json` con la clave HMAC real en el árbol de trabajo** — `cheve/data/secreto.json`. Fuera de git y del despliegue; si esta copia coincide con producción y alguna vez salió del equipo, rotarla (borrar la fila `secreto_formulario` de `ajustes` y el archivo; se regenera sola). **[a verificar]**
- **B4. El límite de envíos por IP falla abierto sin BD** — `enviar-evento.php`. Decisión documentada y razonable; solo tenerla presente durante caídas de MySQL.
- **B5. `CURLOPT_SSL_VERIFYPEER => false` en el autochequeo de `data/`** — `funciones.php`. Petición del servidor a sí mismo; riesgo mínimo, ya comentado en el código.
- **B6. `'unsafe-inline'` en `script-src`** — `.htaccess:36`. Endurecimiento opcional con nonce para el `<script>window.URUAPLAN…</script>`.

---

## 2. COMPROBACIONES EN EL SERVIDOR **[a verificar]**

Siguen pendientes de ejecutarse en el hosting (nada de esto se puede confirmar desde el código local):

1. `cheve/instalar.php`, `probar-correo.php` y `cheve/tests/` no están subidos a producción.
2. `https://uruaplan.com/cheve/data/contenido-espejo.json` devuelve 403.
3. La consola del navegador no muestra errores de CSP (sitio y panel), y los iconos de Font Awesome se ven.
4. Ejecutar `respaldo-bd.sh` una vez en el servidor: que el `.sql.gz` se cree **fuera** de `public_html` y no esté vacío; programar el cron (diario; el script rota a 7 días).
5. `CODIGO_INSTALACION` rellenado con un valor propio (no el placeholder) en el `credenciales_uruaplan.php` real.
6. El SPF del dominio incluye al hosting (documentado en `config-correo.php`).

---

## 3. PLAN DE ACCIÓN

| # | Prioridad | Acción | Esfuerzo |
|---|---|---|---|
| 1 | 🔴 Ya | `git init` + commit inicial + etiqueta del estado desplegado (A1) | 15 min |
| 2 | 🟠 Esta semana | Comprobaciones en el servidor (§2), en especial la prueba real del respaldo y su cron | 30 min |
| 3 | ~~🟡 Este mes~~ | ~~**B1 y B2**: eliminado docblock duplicado en `correo.php` y agregado soporte para tecla `Escape` + selector CSS `:target` para el modal de privacidad~~ *(Completado)* |
| 4 | 🟢 Opcional | B6 (nonce en la CSP); rotar `secreto.json` si procede (B3) | 1 h |

---

*Auditoría actualizada el 25/08/2026 (4ª revisión). El proyecto está, a nivel de código, en un estado sólido: todos los hallazgos de seguridad, corrección, rendimiento y limpieza de las revisiones anteriores están cerrados y verificados. Lo que resta es operativo: versionar el código, ejecutar las comprobaciones en el servidor y dos retoques menores.*
