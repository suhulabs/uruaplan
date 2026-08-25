# Panel de contenido Uruaplan — guía de instalación y uso

Panel de administración en PHP puro sobre MySQL, sin Composer y sin
dependencias externas. Compatible con el PHP de cualquier hosting compartido
de GoDaddy/cPanel (PHP 7.4 o superior, compatible con PHP 8.x).

> **Las contraseñas NO están en este archivo a propósito.**
> Este documento se sube al servidor; las contraseñas se entregan aparte.

## Qué hace el panel

1. **Editar el contenido de la web.** Los textos y las imágenes de las 13
   secciones de `uruaplan.com`, sin tocar código.
2. **Moderar los flyers que manda la gente.** Cada solicitud del formulario
   «Promociona tu Plan» queda registrada con su estado: se acepta, se
   rechaza, se corrige, se archiva y —si se quiere— se publica en la
   cartelera de la web. Todo queda en un histórico con quién hizo qué.

> **Si vienes de la versión anterior (la que guardaba en archivos JSON):**
> lee la §3. La instalación es la misma salvo por un paso nuevo —crear la
> base de datos— y el contenido y los usuarios se importan solos, con sus
> contraseñas intactas.

---

## 1. Qué hay que subir y dónde

Todo va dentro de `public_html/` (o de la carpeta raíz del dominio en cPanel).

```
public_html/
├── .htaccess                      ← ¡ojo si ya tienes uno! ver §4
├── index.php                      ← la página real (sustituye a index.html)
├── index.html                     ← el viejo: renombrar a index.html.bak
├── enviar-evento.php              ← recibe el formulario de eventos
├── css/styles.css
├── js/main.js
├── img/
│   ├── ... (todas tus imágenes actuales, sin cambios)
│   └── uploads/                   ← carpeta (permisos 755)
│       └── .htaccess
└── cheve/                         ← el panel
    ├── index.php                  (login)
    ├── admin.php                  (editor de contenido)
    ├── flyers.php                 ← NUEVO  (bandeja de solicitudes)
    ├── flyer.php                  ← NUEVO  (ficha de una solicitud)
    ├── flyer-imagen.php           ← NUEVO  (sirve las fotos privadas)
    ├── instalar.php               ← NUEVO  (crea las tablas; ver §3)
    ├── cambiar-password.php
    ├── probar-correo.php          (diagnóstico del envío de correo)
    ├── logout.php
    ├── assets/
    │   ├── panel.css
    │   └── panel.js
    ├── data/                      (permisos 755)
    │   ├── .htaccess              ← IMPRESCINDIBLE
    │   ├── flyers/                ← se crea sola: fotos sin publicar (755)
    │   ├── contenido-espejo.json  (se crea solo: respaldo de la web)
    │   └── secreto.json           (se crea solo: firma del formulario)
    └── includes/
        ├── .htaccess
        ├── config.php
        ├── config-bd.php          ← NUEVO  (⚠ HAY QUE EDITARLO, ver §3)
        ├── config-correo.php      ← ⚠ HAY QUE EDITARLO, ver §6
        ├── bd.php                 ← NUEVO  (conexión a MySQL)
        ├── esquema-bd.php         ← NUEVO  (las tablas)
        ├── contenido.php          ← NUEVO  (contenido en la base)
        ├── flyers.php             ← NUEVO  (todo lo de los flyers)
        ├── correo.php
        ├── antispam.php
        ├── funciones.php
        ├── auth.php
        ├── csrf.php
        ├── esquema.php
        ├── render.php
        ├── subidas.php
        ├── guardar.php
        ├── cabecera.php
        └── pie.php
```

Los archivos `cheve/data/contenido.json` y `cheve/data/usuarios.json` de la
versión anterior **hay que subirlos también la primera vez**: el instalador
los lee para importar lo que ya tenías. Después se borran (§3, paso 4).

### Pasos en cPanel

1. Entra a **cPanel → Administrador de archivos** y sitúate en `public_html`.
2. Arriba a la derecha, **Configuración → marca "Mostrar archivos ocultos
   (dotfiles)"**. Sin esto los `.htaccess` no se ven y es fácil olvidarlos.
3. Comprime la carpeta del proyecto en un ZIP en tu computadora y súbelo con
   **Cargar**; después **clic derecho → Extraer**. Es mucho más rápido y
   seguro que subir archivo por archivo (así no se pierde ningún `.htaccess`).
4. Verifica que existan los **cuatro** `.htaccess`:
   `public_html/.htaccess`, `cheve/data/.htaccess`,
   `cheve/includes/.htaccess` e `img/uploads/.htaccess`.

---

## 2. Permisos

En el Administrador de archivos, clic derecho → **Permisos**:

| Ruta                        | Permisos |
|-----------------------------|----------|
| `cheve/`                    | 755      |
| `cheve/data/`               | 755      |
| `cheve/data/flyers/`        | 755 (se crea sola) |
| `cheve/data/*.json`         | 644      |
| `img/uploads/`              | 755      |
| Todos los `.php`            | 644      |
| `cheve/includes/config-bd.php`     | 600 si tu hosting lo permite, si no 644 |
| `cheve/includes/config-correo.php` | 600 si tu hosting lo permite, si no 644 |

Esos dos `config-*` llevan contraseñas dentro (la de MySQL y la del correo).
La carpeta `includes/` está bloqueada por su `.htaccess`, así que no se pueden
abrir desde el navegador; poner 600 es un cinturón extra por si algún día se
tocara ese `.htaccess`.

Si `cheve/data/` o `img/uploads/` no se pueden escribir, el panel lo detecta
solo y te lo dice en un recuadro rojo en cuanto entras.

---

## 3. Crear la base de datos e instalar

Este es el único paso realmente nuevo. Se hace **una vez** y no se vuelve a
tocar.

### Paso 1 — Crear la base en cPanel

1. cPanel → **Bases de datos → «Bases de datos MySQL»**.
2. **Crear una base de datos nueva**: escribe `uruaplan` y pulsa *Crear*.
   cPanel le pone delante el prefijo de tu cuenta, así que el nombre real
   queda como `cpanelusr_uruaplan`. **Cópialo tal cual**, con el prefijo.
3. Baja hasta **«Usuarios de MySQL» → Añadir usuario nuevo**. Ponle
   `uruaplan` y usa el **generador de contraseñas**.
   **Copia la contraseña antes de darle a Crear**: no se vuelve a mostrar.
4. Baja un poco más, a **«Añadir un usuario a una base de datos»**. Elige el
   usuario y la base que acabas de crear, dale a *Añadir* y en la pantalla de
   permisos marca **TODOS LOS PRIVILEGIOS**.

### Paso 2 — Pegar los datos en el servidor

Abre `cheve/includes/config-bd.php` (Administrador de archivos → clic derecho
→ **Editar**) y rellena las tres líneas señaladas:

```php
'base'     => 'cpanelusr_uruaplan',   // el nombre COMPLETO, con prefijo
'usuario'  => 'cpanelusr_uruaplan',   // el usuario COMPLETO, con prefijo
'password' => 'la-que-copiaste',
```

`'host' => 'localhost'` ya viene puesto y en cPanel es lo correcto casi
siempre.

> **El fallo más típico.** Olvidar el prefijo. Si en cPanel ves
> `cheve_uruaplan`, eso es lo que va, no `uruaplan` a secas. Pasa igual con
> el usuario.

### Paso 3 — Abrir el instalador

Entra a **`uruaplan.com/cheve/instalar.php`**. La página:

- comprueba la conexión y, si algo falla, dice exactamente qué corregir;
- crea las tablas al pulsar el botón;
- **importa el contenido y los usuarios** de los `contenido.json` y
  `usuarios.json` que subiste, si están ahí. Las contraseñas se conservan:
  cada quien entra con la suya de siempre.

Si no había ningún `usuarios.json`, el instalador te pide crear la primera
cuenta ahí mismo.

Puedes volver a abrir esa página cuando quieras: no borra nada, solo crea lo
que falte. Sirve también de diagnóstico si algo se rompe más adelante.

### Paso 4 — Limpiar

Cuando confirmes que el panel entra y el contenido se ve bien, **borra del
servidor**:

- `cheve/instalar.php`
- `cheve/data/contenido.json`
- `cheve/data/usuarios.json` ← este tiene dentro los hashes de las contraseñas
- `cheve/data/intentos.json` y `cheve/data/envios.json`, si siguen ahí

### ¿Y si se cae MySQL?

El panel no funciona (avisa con una pantalla que lo explica), pero **la web
pública sí sigue en pie**: cada vez que se guarda contenido se escribe una
copia de respaldo en `cheve/data/contenido-espejo.json`, y la página la usa
si la base no responde. Lo único que desaparece mientras tanto es la
cartelera de eventos. Ese archivo se regenera solo; no se edita a mano.

---

## 4. El `index.html` viejo

`index.php` es ahora la página real. Para que se sirva esa y no la antigua:

1. **Renombra** `index.html` a `index.html.bak` (guárdalo como respaldo unos
   días; cuando compruebes que todo va bien, bórralo).
2. El `.htaccess` de la raíz ya incluye `DirectoryIndex index.php index.html`,
   que da prioridad a la versión nueva.

> ⚠️ **Si tu hosting ya tenía un `.htaccess` en `public_html`**, no lo
> sobrescribas. Abre el que ya existe con **Editar** y pega dentro solo las
> líneas que te falten del `.htaccess` que se entrega (como mínimo la de
> `DirectoryIndex`).

---

## 5. Comprobaciones después de subir

Hazlas en este orden:

| # | Qué abrir | Qué debe pasar |
|---|-----------|----------------|
| 1 | `uruaplan.com` | La página se ve **exactamente igual** que antes |
| 2 | `uruaplan.com/cheve/data/contenido-espejo.json` | **403 Prohibido** (si descarga el archivo, el `.htaccess` no se subió) |
| 3 | `uruaplan.com/cheve/includes/config-bd.php` | **403 Prohibido** ← este lleva la contraseña de MySQL |
| 4 | `uruaplan.com/cheve/includes/config.php` | **403 Prohibido** |
| 5 | `uruaplan.com/cheve/` | Pantalla de login |
| 6 | Entrar con un usuario | Entra con la contraseña de siempre |
| 7 | Cambiar un texto y guardar | El cambio se ve en `uruaplan.com` al recargar |
| 8 | `uruaplan.com/cheve/probar-correo.php` | Dice si el correo está listo; el botón manda una prueba (ver §6) |
| 9 | Llenar el formulario de la web y enviarlo | Aviso verde en la página, el correo en la bandeja **y el flyer en `cheve/flyers.php`** |
| 10 | Aceptar ese flyer y darle a *Publicar en la web* | Aparece en la cartelera de `uruaplan.com` |

Los puntos 2 y 3 son los más importantes: en esa carpeta están ahora las fotos
de los flyers que todavía no se han aprobado. Además, **el propio panel lo
comprueba solo**: cada vez que entras hace una petición HTTP a su propia
carpeta `data/` y, si consigue descargar algo, muestra una alerta roja bien
visible.

---

## 6. Configurar el envío de correo del formulario

El formulario de "Promociona tu evento" **manda un correo de verdad desde el
servidor**, con la foto adjunta. El circuito es:

```
formulario de la web
        │
        ▼
noreply@uruaplan.com  ───envía───▶  contacto@uruaplan.com
(cuenta de cPanel,                 (buzón de Zoho, el de siempre)
 en el propio hosting)
```

Es decir: **cPanel envía, Zoho recibe.** Lo único que hay que hacer a mano en
toda la instalación es darle la contraseña de la cuenta de cPanel.

> Antes esto era un enlace `mailto:`: solo abría el programa de correo del
> visitante (Outlook, Thunderbird, la ventana de Gmail...) y en la mayoría de
> las computadoras no pasaba nada al darle a enviar. Además un `mailto` no
> puede llevar adjuntos, así que la foto del evento nunca llegaba.

### Por qué se envía desde cPanel y no desde Zoho

**El plan gratuito de Zoho Mail ya no permite enviar por SMTP** desde
programas externos. Zoho lo dejó solo para los planes de pago (Mail Lite en
adelante) porque el gratuito se estaba usando para envíos masivos.

Probado el 10 de agosto de 2026 con `contacto@uruaplan.com` y su contraseña
de aplicación `uruaplan-php` bien puesta:

```
smtppro.zoho.com:465  →  conecta bien
EHLO                  →  250 OK, ofrece AUTH LOGIN PLAIN
usuario               →  aceptado
contraseña            →  554 5.7.8 Access Restricted
```

Ese `554 5.7.8` **no es «contraseña incorrecta»** (eso sería un `535`). Es
Zoho diciendo que la cuenta no tiene permitido usar SMTP, y remite a
<https://help.zoho.com/portal/en/community/topic/zoho-mail-server-details>,
donde explican que POP/IMAP —y con ellos el SMTP de aplicaciones— quedaron
restringidos en el plan gratuito.

**La clave es que ese límite solo afecta a enviar, no a recibir.** Por eso el
montaje actual envía desde una cuenta de cPanel, que no cobra por SMTP, y deja
que el aviso caiga en el buzón de Zoho. Sale gratis y el remitente sigue
siendo `@uruaplan.com`, que es lo que mejor se ve.

### ⚠ Dos ajustes de cPanel sin los que esto no llega nunca

**1. Enrutamiento de correo.** Este es el importante. Las dos cuentas son del
mismo dominio (`admin@` y `contacto@`, las dos `@uruaplan.com`), así que cPanel
puede creer que él mismo es el servidor de correo del dominio y ponerse a
buscar a `contacto` **entre sus propios buzones** en vez de mandarlo a Zoho.
Como ahí no existe, el aviso se pierde o rebota. Se arregla en **cPanel →
Enrutamiento de correo (Email Routing) → uruaplan.com → "Intercambiador de
correo remoto"**.

Es el fallo más difícil de diagnosticar de todo el montaje, porque el envío
dice **OK** y aun así no aparece nada en Zoho.

> Efecto secundario, inofensivo aquí: a partir de ese cambio, el correo que le
> llegue a `noreply@uruaplan.com` desde fuera va a Zoho y no al buzón de cPanel.
> Da igual, porque `admin@` solo se usa para enviar.

**2. SPF.** El registro SPF del dominio seguramente solo autoriza a Zoho. Si
el servidor del hosting no está incluido, los avisos caen en spam o los
rechazan. Hay que añadir el hosting al SPF (normalmente basta con `+a +mx` o
el `include:` que indique el proveedor).

Mientras esto no esté resuelto, no se pierde nada importante: desde la
migración a base de datos el correo es solo un aviso. **Las solicitudes se
guardan igual** y aparecen en la bandeja de flyers aunque el envío falle (§8).
Se pierde el aviso, no el evento. Los flyers así llegados se marcan con la
etiqueta amarilla «Sin aviso» para que se distingan de un vistazo.

### Paso 1 — La cuenta que envía

Es **`noreply@uruaplan.com`**, la cuenta de **cPanel → Cuentas de correo**. Ya
está creada y ya está puesta en `config-correo.php`, en `'smtp_usuario'` y en
`'remitente_correo'`. Los dos campos tienen que ser la misma cuenta: el
servidor de cPanel rechaza enviar con un remitente que no le pertenece.

> **No confundir las dos cuentas del dominio.** `noreply@uruaplan.com` está en
> cPanel y solo sirve para **enviar** (nadie le escribe).
> `contacto@uruaplan.com` está en Zoho y es donde se **leen** los eventos que
> manda la gente. Son buzones distintos en servidores distintos.

### Paso 2 — Pegar la contraseña en el servidor

Abre `cheve/includes/config-correo.php` (cPanel → Administrador de archivos →
clic derecho → **Editar**) y pégala en la línea que ya está señalada:

```php
'smtp_password'  => 'la-de-noreply@uruaplan.com',
```

Los espacios dan igual, el código los quita solo. **No hay que tocar nada
más**: el servidor, el puerto y el destino ya vienen puestos.

> **Por qué el servidor es `uruaplan.com` y no `mail.uruaplan.com`.** Como el
> correo del dominio está delegado en Zoho, `mail.uruaplan.com` puede apuntar
> a Zoho y no al hosting, que es justo lo contrario de lo que hace falta: aquí
> quien tiene que enviar es el servidor web. El dominio pelado resuelve a ese
> servidor.
>
> Además el código exige que el certificado del servidor case con ese nombre
> (`verify_peer`). Si al probar sale un error de conexión que no se explica
> por el puerto, suele ser el certificado: prueba entonces con el nombre de
> servidor que aparezca en **Conectar dispositivos** de la cuenta de correo.

### Paso 3 — Comprobar que llega

Entra al panel y pulsa **Correo** en la barra de arriba, o abre directamente
`uruaplan.com/cheve/probar-correo.php`. Esa página revisa la configuración,
avisa de los errores que puede detectar sin conectarse, y tiene un botón que
manda un correo de prueba. Si algo falla enseña el error exacto que devolvió
Zoho, no un "no funciona" a secas.

La primera vez, mira también en la carpeta de **Spam**.

### Puertos

Vienen configurados el 465 con SSL, que es lo que da cPanel por defecto. Los
dos funcionan; si el hosting bloqueara uno, cambia al otro en pareja:

| Puerto | `smtp_seguridad` |
|--------|------------------|
| 465    | `'ssl'`          |
| 587    | `'tls'`          |

### A dónde llegan las solicitudes

A **`contacto@uruaplan.com`** (el buzón de Zoho de siempre), fijado en
`'destino'` dentro de `cheve/includes/config-correo.php`.

Mientras esa línea tenga un correo escrito, **manda sobre el panel**: el campo
**Contacto → "Correo que recibe los eventos"** queda ignorado. Es a propósito,
para que el aviso no se pueda desviar sin entrar al servidor. Si algún día
quieres volver a gobernarlo desde el panel, deja `'destino' => ''` y el panel
vuelve a decidir.

La cuenta que **envía** es la de cPanel (`noreply@uruaplan.com`), que no es lo
mismo. Y el correo que se enseña en la web (tarjeta de contacto, pie de página
y enlaces de «escríbenos») es un tercer campo distinto, **Datos generales →
"Correo electrónico"**, que también está en `contacto@uruaplan.com`.

> Son **tres cosas independientes**: desde dónde sale el aviso, a dónde llega,
> y cuál ve el visitante. Separarlos es justo lo que permite recibir las
> solicitudes en una cuenta interna sin publicarla.

Desde la migración, ese correo es un **aviso**, no el registro: la solicitud
queda guardada en la bandeja de flyers pase lo que pase con el envío (§8).

### Si el hosting bloquea el puerto

Algunos hostings compartidos cierran la salida SMTP. Si `probar-correo.php`
dice que no se pudo conectar:

1. Prueba con la otra pareja de valores de la tabla de arriba
   (`'smtp_puerto' => 587` y `'smtp_seguridad' => 'tls'`).
2. Si sigue igual, abre un ticket con soporte de GoDaddy pidiendo que
   habiliten las conexiones SMTP salientes a `uruaplan.com` en los puertos
   465 y 587.

> Aquí la salida es al **propio servidor**, no a un proveedor de fuera, así
> que este bloqueo es bastante menos probable que cuando se enviaba por Zoho.
> Si `probar-correo.php` da un error de conexión, sospecha antes del nombre
> del servidor y su certificado (ver el aviso del Paso 2).

### Protección contra robots

El formulario es público, así que lleva tres defensas para que nadie lo use
como máquina de mandar spam desde nuestra cuenta:

| Defensa | Qué hace |
|---|---|
| Token firmado | Cada carga de la página genera un código con la hora dentro, firmado. Un robot que dispare contra `enviar-evento.php` sin abrir la página no puede fabricarlo. Caduca a las 2 horas. |
| Campo trampa | Un campo invisible para las personas. Si viene relleno, es un robot: se le contesta "ok" y el correo no se manda. |
| Límite por conexión | Máximo 5 solicitudes por hora desde la misma IP. Se ajusta con `max_por_hora_ip`. |

La foto se valida igual que en el panel (tipo real del archivo con `finfo`,
máximo 5 MB, y tiene que ser una imagen que se pueda abrir de verdad), y
**no se guarda en el servidor**: viaja adjunta en el correo y se descarta.
Así nadie puede dejar archivos sueltos en la web.

---

## 7. Usar el panel

- **Dirección:** `uruaplan.com/cheve/`
- **Usuario:** cada persona tiene **dos alias válidos**, el nombre o la profesión:

  | Persona | Puede entrar como |
  |---------|-------------------|
  | David Suchiapa Huante | `david` **o** `ingeniero` |
  | Erasmo Valdés Mora | `erasmo` **o** `arquitecto` |
  | Luis André Cuara Calderón | `luis` **o** `contador` |

- En el **primer acceso** el sistema obliga a cambiar la contraseña temporal.
  No se puede entrar al editor hasta hacerlo.
- Después, cualquiera puede cambiar su contraseña desde **Contraseña** en la
  barra superior.
- Los tres usuarios tienen **los mismos permisos**: editan todo por igual.

La barra de arriba tiene dos zonas de trabajo: **Contenido** (los textos y las
imágenes del sitio) y **Flyers** (las solicitudes que manda la gente). Cuando
hay solicitudes sin revisar, aparece un globo rojo con el número al lado de
«Flyers» desde cualquier pantalla del panel.

### Cómo se edita

El editor está dividido en **13 pestañas** (Datos generales, SEO, Menú,
Portada, Nosotros, Promociona, Cartelera, Cómo Funciona, Instagram, Galería,
Contacto, Promoción Especializada y Pie de página).

- Se rellenan los campos y se pulsa **Guardar cambios** una sola vez: guarda
  todas las pestañas a la vez.
- **Imágenes:** dejar el campo vacío conserva la imagen actual. Para
  cambiarla, elegir un archivo nuevo (JPG, PNG o WEBP, máximo 5 MB).
- **Galería y fondos de portada:** son listas. Se pueden agregar elementos,
  quitarlos y reordenarlos con las flechas ↑ ↓. El orden en pantalla es el
  orden en que salen en la web.

### Encuadre y visibilidad (Galería y Portada)

Tanto el collage de la galería como los fondos de la portada recortan las
fotos para rellenar su hueco (una foto vertical metida en una casilla
apaisada pierde arriba y abajo). Las dos listas llevan los mismos dos
controles:

- **Ajustar encuadre.** Abre un modal con la foto dentro del hueco real que le
  toca: la casilla del collage, o la pantalla completa si es un fondo de
  portada. Se **arrastra la imagen** para elegir qué parte se ve y hay un
  **zoom** de 1× a 3× que acerca tomando ese mismo punto como centro. Se ven a
  la vez la vista de computadora y la de celular, porque el recorte no es el
  mismo — en la portada la diferencia es enorme (apaisado contra vertical).
  El botón **Centrar** vuelve al estado original.

  > **Solo se puede mover donde sobra imagen.** Con este tipo de recorte, si
  > una foto ya encaja justa de ancho, solo se podrá subir y bajar (y al
  > revés). El modal te lo dice debajo del marco y el cursor cambia de forma.
  > Si quieres moverla en el otro sentido, sube primero el zoom.

  > Las casillas del collage miden 3, 4 o 5 columnas **según su posición en la
  > lista**. Si reordenas las fotos, el tamaño de su casilla cambia y conviene
  > volver a revisar el encuadre de las que moviste. Los fondos de portada no
  > tienen este problema: todos ocupan la pantalla entera.

- **Mostrar en la web / Usar este fondo.** Interruptor por elemento. Al
  apagarlo desaparece de la web pero no se borra: el archivo y sus datos
  siguen ahí y se puede volver a encender cuando quieras. Las filas apagadas
  se ven atenuadas en el panel. En la galería las casillas se reparten solas
  entre las que quedan visibles, así que el mosaico nunca queda con huecos.

En la web pública, **tocar cualquier foto del collage la abre a pantalla
completa y sin recortar**, con flechas para pasar a la siguiente, teclas
← → , Esc para cerrar y deslizamiento con el dedo en el celular.
- **Negrita:** en los párrafos largos se puede escribir `**así**` para
  resaltar palabras.
- **Marcadores especiales:** `{handle}` en la descripción de Instagram pone el
  usuario resaltado; `{corazon}` en el pie pone el icono del corazón.
- **Iconos:** son clases de [Font Awesome](https://fontawesome.com/icons),
  por ejemplo `fa-solid fa-star`. Al escribirlas se ve la vista previa al lado.

---

## 8. Los flyers: de la solicitud a la cartelera

Antes, cuando alguien llenaba el formulario de la web, llegaba un correo y ya
está: si ese correo se perdía en spam o se borraba sin querer, el evento
desaparecía y nadie se enteraba. Ahora **cada solicitud queda guardada**. El
correo sigue llegando, pero solo como aviso de que hay algo nuevo que revisar;
lleva un enlace directo a la ficha.

### La bandeja

`uruaplan.com/cheve/flyers.php`, o **Flyers** en la barra de arriba.

Es la lista de todo lo que ha entrado, de lo más reciente a lo más antiguo,
con su foto, sus datos y su estado. Arriba hay pastillas para filtrar por
estado y un buscador que mira en el nombre del evento, el lugar y el contacto.

Sobre las solicitudes nuevas se puede **aceptar** o **rechazar** en un clic
desde la propia lista. Para lo demás, se entra a la ficha con **Abrir**.

### Los cuatro estados

| Estado | Qué significa |
|---|---|
| **Pendiente** | Recién llegado, nadie lo ha mirado |
| **Aceptado** | Revisado y aprobado. Es el único estado desde el que se puede publicar |
| **Rechazado** | No se va a publicar. Se queda en el histórico con el motivo |
| **Archivado** | Ya pasó, o se guarda por si acaso. Fuera de la web y fuera de en medio |

Nada de esto borra nada: se puede ir y volver entre estados cuantas veces
haga falta con **Volver a pendiente**.

### Aceptar no es publicar

Son dos decisiones distintas, y por eso son dos botones distintos:

- **Aceptar** = «este evento está bien». No lo saca en la web.
- **Publicar en la web** = «sáquenlo ya». Solo se puede sobre algo aceptado.

Así se puede tener un evento aprobado esperando a su fecha sin que se vea
todavía, y quitarlo de la web sin rechazarlo. Si un flyer publicado se rechaza
o se archiva, **se despublica solo**: no puede quedarse en la web algo que se
acaba de rechazar.

### Corregir antes de publicar

En la ficha se puede editar **todo**: el nombre, el subtítulo, la fecha, la
hora, el lugar, el precio, el contacto y la descripción. Es donde se arreglan
las faltas de ortografía y se recorta lo que venga demasiado largo, porque lo
que se guarde ahí es literalmente lo que verá la gente.

También se puede **cambiar la foto** y **ajustar el encuadre** con el mismo
modal de arrastrar y hacer zoom que la galería. Ojo: cambiar la foto de un
flyer publicado lo quita de la web automáticamente, para que nadie vea la foto
vieja mientras se revisa la nueva. Basta con volver a darle a publicar.

La **nota interna** es para el equipo: el motivo de un rechazo, un «falta
confirmar el precio»… Nunca sale en la web.

### El histórico

Al final de cada ficha está todo lo que se le ha hecho al flyer, con quién lo
hizo y cuándo: recibido, aceptado, editado, publicado, quitado de la web…
No se puede editar ni borrar.

### Dónde están las fotos

Mientras un flyer no está publicado, su foto vive en `cheve/data/flyers/`, que
está cerrada al público: **la foto de un evento pendiente o rechazado no se
puede ver desde internet**, ni acertando la dirección. El panel la enseña a
través de `flyer-imagen.php`, que exige sesión iniciada.

Al publicar se hace una copia en `img/uploads/`, que sí es pública, y esa es
la que ve el visitante. Al despublicar, esa copia se borra. O sea: el archivo
público existe exactamente mientras el flyer está publicado.

### Eliminar de verdad

Solo aparece en flyers rechazados o archivados, hay que escribir `BORRAR` para
confirmar, y se lleva el flyer, su foto y su histórico. **No hay vuelta
atrás.** Para todo lo demás está *Archivar*.

### La cartelera de la web

Los flyers publicados salen en una sección nueva de `uruaplan.com`, ordenados
por fecha; los que ya pasaron se quedan al final y atenuados en vez de
desaparecer, porque hay planes que duran varios días.

Los textos que rodean la cartelera (el título, la descripción, el mensaje de
«todavía no hay eventos») se editan en **Contenido → Cartelera**. Los eventos
en sí no se tocan ahí: salen de esta bandeja.

Si no hay ningún flyer publicado **y** el texto de «no hay eventos» está
vacío, la sección entera desaparece de la web y el enlace del menú tampoco
aparece. Así la primera semana no se ve un hueco vacío.

---

## 9. Protección de Directorios de cPanel (capa extra, opcional)

Añade una segunda contraseña —la del propio servidor Apache— antes incluso de
que se cargue el login de PHP. Es útil mientras el panel esté nuevo.

1. cPanel → **Archivos → Privacidad del directorio** (o *Directory Privacy*).
2. Navega hasta `public_html` y pulsa **Editar** en la carpeta `cheve`.
3. Marca **"Proteger este directorio con contraseña"**, ponle un nombre
   (por ejemplo `Panel Uruaplan`) y **Guardar**.
4. En la misma pantalla, **Crear usuario**: usuario y contraseña que
   compartirán los tres (esta es distinta de la del panel).

Con esto, al abrir `uruaplan.com/cheve/` el navegador pedirá primero un
usuario/contraseña en una ventanita del sistema, y solo después aparecerá el
login del panel.

> **Cuidado:** esto protege toda la carpeta `cheve/`, incluidos `panel.css` y
> `panel.js`. Los navegadores los cargan con las mismas credenciales, así que
> funciona bien; pero si algo se ve sin estilos, es la causa: desactívalo o
> mueve `assets/` fuera de `cheve/`.

---

## 10. Cómo agregar un campo editable nuevo

Solo se tocan dos archivos:

1. **`cheve/includes/esquema.php`** — añade el campo en la sección que toque:

   ```php
   'mi_campo_nuevo' => array(
       'label' => 'Nombre que verá el editor',
       'tipo'  => 'texto',   // texto | area | email | tel | url | imagen | media | icono
       'max'   => 120,
       'ayuda' => 'Explicación opcional.',
   ),
   ```

2. **`index.php`** — imprímelo donde corresponda:

   ```php
   <?= ce('seccion', 'mi_campo_nuevo') ?>
   ```

No hace falta tocar `contenido.json`: el campo aparecerá vacío en el panel y se
creará al guardar por primera vez.

El guardado funciona con **lista blanca**: solo se acepta lo que está declarado
en `esquema.php`. Cualquier campo que llegue por POST sin estar en el esquema
se descarta.

---

## 11. Seguridad implementada

| Medida | Dónde |
|---|---|
| Contraseñas con `password_hash()` (bcrypt, coste 12) | `auth.php` |
| Verificación con `password_verify()` y comparación en tiempo constante | `auth.php` |
| Cambio obligatorio de contraseña temporal en el primer acceso | `auth.php`, `cambiar-password.php` |
| Bloqueo de 15 min tras 5 fallos (por cuenta, y por IP a los 15) | `auth.php` |
| Contador de fallos unificado entre los dos alias de una persona | `auth.php` |
| Sesión validada en cada página privada, no solo ocultando enlaces | `exigir_sesion()` |
| `session_regenerate_id()` al entrar y al cambiar contraseña | `auth.php` |
| Cookie de sesión `HttpOnly`, `SameSite=Lax`, `Secure` con HTTPS, limitada a `/cheve/` | `config.php` |
| Cierre automático por 1 hora de inactividad | `config.php`, `auth.php` |
| Token CSRF en todos los formularios, comparado con `hash_equals()` | `csrf.php` |
| MIME real verificado con `finfo` + `getimagesize()`, nunca por extensión | `subidas.php` |
| Archivos subidos renombrados al azar, extensión derivada del MIME | `subidas.php` |
| `img/uploads/` con ejecución de PHP desactivada | `img/uploads/.htaccess` |
| `data/` e `includes/` denegados por HTTP | sus `.htaccess` |
| Autocomprobación de que `data/` está realmente bloqueada | `admin.php` |
| Todo texto escapado con `htmlspecialchars()` al renderizar | `funciones.php` |
| Enlaces `javascript:` / `data:` rechazados al guardar | `guardar.php` |
| Escritura de JSON atómica (temporal + `rename`) para no corromper el espejo | `funciones.php` |
| Formulario público firmado con HMAC + caducidad de 2 h | `antispam.php` |
| Campo trampa para robots y mínimo de segundos para rellenar | `antispam.php` |
| Máximo 5 envíos por hora y por IP, para no servir de relé de spam | `antispam.php` |
| Saltos de línea eliminados de todo lo que va a una cabecera de correo (evita inyección de `Bcc:`) | `correo.php` |
| Contraseña SMTP fuera del código, en `includes/` (denegado por HTTP) | `config-correo.php` |
| Los errores de SMTP van al log del servidor, nunca a la pantalla del visitante | `enviar-evento.php` |
| Conexión al servidor de correo cifrada con TLS y certificado verificado | `correo.php` |

### Lo que añade la base de datos

| Medida | Dónde |
|---|---|
| **Todos** los valores viajan como parámetros (`prepare`/`execute`); nunca se concatena nada en un SQL | `bd.php` y todo el que consulta |
| `PDO::ATTR_EMULATE_PREPARES = false`: consultas preparadas reales del servidor, no simuladas | `bd.php` |
| Los comodines `%` y `_` del buscador se escapan, para que un `%` escrito por alguien no case con todo | `flyers.php` |
| Las escrituras que van juntas van en transacción: o entran todas o no entra ninguna | `bd.php`, `contenido.php` |
| Los errores de MySQL van al log; en pantalla queda un mensaje genérico, nunca el nombre de la base ni del usuario | `bd.php` |
| Contraseña de MySQL fuera del código, en `includes/` (denegado por HTTP) | `config-bd.php` |
| El texto libre que llega al contador de intentos se acota antes de tocar la base | `auth.php` |
| El nombre del archivo de una foto se valida contra un patrón antes de abrirlo, aunque venga de nuestra propia base | `flyers.php` |
| Las fotos de flyers sin publicar se guardan fuera del alcance del navegador y se sirven solo con sesión | `data/flyers/.htaccess`, `flyer-imagen.php` |
| La copia pública de una foto existe solo mientras el flyer está publicado; al despublicar se borra | `flyers.php` |
| La solicitud se guarda **antes** de mandar el correo: un fallo de SMTP ya no pierde el evento | `enviar-evento.php` |
| Lista blanca de campos editables en la ficha de un flyer: lo que no esté declarado no se guarda | `flyers.php` |
| `instalar.php` deja de ser público en cuanto existe el primer usuario | `instalar.php` |

---

## 12. Problemas frecuentes

**Error 500 al abrir el sitio después de subir.**
Casi siempre es el `.htaccess`. Si tu plan no permite alguna directiva,
comenta la línea `Options -Indexes`. Mira el detalle en
cPanel → **Métricas → Errores**.

**El sitio sigue mostrando la versión vieja.**
`index.html` todavía tiene prioridad: renómbralo a `index.html.bak`.
Vacía también la caché del navegador (Ctrl+Shift+R).

**"No hay conexión con la base de datos".**
La propia pantalla dice el motivo concreto. Los tres de siempre:
falta el prefijo de cPanel en el nombre de la base o del usuario en
`cheve/includes/config-bd.php`; la contraseña está mal; o se olvidó el paso de
**añadir el usuario a la base con TODOS LOS PRIVILEGIOS**. Abre
`cheve/instalar.php` para verlo con detalle.

Mientras eso pasa, `uruaplan.com` sigue funcionando con el espejo del
contenido; lo único que no se ve es la cartelera de eventos.

**El panel redirige solo a `instalar.php`.**
Faltan tablas por crear. Pulsa el botón de esa página y listo.

**Un flyer no se deja publicar.**
Solo se puede publicar lo que está **aceptado**: acéptalo primero. Si ya lo
está y sigue sin dejar, es que no encuentra su foto en `cheve/data/flyers/` o
que `img/uploads/` no tiene permiso de escritura (755).

**En la bandeja sale la etiqueta amarilla "Sin aviso".**
La solicitud llegó y está completa, pero el correo de aviso no se pudo enviar.
No se perdió nada; revisa el correo con `probar-correo.php` (§6).

**"No se pudo escribir el archivo contenido-espejo.json".**
Permisos: `cheve/data/` en 755. El contenido se guarda igual en MySQL —eso no
falla—, pero sin espejo la web se queda sin red de seguridad si la base cae.

**Las imágenes grandes no suben.**
El límite de PHP del hosting es menor que el archivo. En cPanel →
**Selector de PHP → Opciones**, sube `upload_max_filesize` y `post_max_size`
a 32M. Si no tienes esa opción, crea un archivo `.user.ini` en `public_html`
con:

```
upload_max_filesize = 32M
post_max_size = 40M
```

**El formulario de eventos no manda nada / da error.**
Abre `uruaplan.com/cheve/probar-correo.php` (con la sesión iniciada): esa
página dice exactamente qué falla. Por orden de probabilidad: falta la
contraseña de `noreply@uruaplan.com` en `cheve/includes/config-correo.php`, esa
contraseña ya no es la buena, o el certificado del servidor no casa con el
nombre puesto en `smtp_host`. Ver §6.

**La prueba dice que salió bien, pero no llega nada.**
Casi seguro es el **enrutamiento de correo**: cPanel se cree el servidor de
correo de `uruaplan.com` y deja el aviso en un buzón local suyo en vez de
mandarlo a Zoho. cPanel → **Enrutamiento de correo** → `uruaplan.com` →
**"Intercambiador de correo remoto"**. Ver §6.

**El correo llega a Spam.**
Márcalo como "No es spam" una vez y los siguientes entran bien. Si pasa de
forma sistemática, es el **SPF**: ahora quien envía es el servidor del hosting,
no Zoho, así que el registro SPF del dominio tiene que autorizarlo también
(normalmente basta con añadir `+a +mx`). Sin eso, Gmail ve un correo que dice
venir de `@uruaplan.com` desde un servidor no autorizado. Ver §6.

**El visitante ve "No pudimos enviar tu solicitud".**
El detalle técnico no se le enseña a él, va al log: cPanel → **Métricas →
Errores**, busca las líneas que empiezan por `[uruaplan]`.

**Olvidé mi contraseña.**
No hay recuperación por correo (el envío de correo del sitio es solo para el
formulario de eventos; el panel no manda nada).

Quien tenga acceso a cPanel puede ponerla a mano:

1. Crea un archivo temporal `hash.php` en `public_html` con
   `<?php echo password_hash('LaNuevaClave', PASSWORD_DEFAULT);`
2. Ábrelo en el navegador y **copia el resultado**.
3. cPanel → **phpMyAdmin** → tu base → tabla `usuarios` → *Editar* en la fila
   de esa persona. Pega el hash en la columna `hash`, pon `debe_cambiar` a `1`
   y guarda.
4. **Borra `hash.php`** del servidor.

Ese `debe_cambiar = 1` obliga a elegir una contraseña nueva al entrar, así que
la que acabas de poner no se queda circulando.

### Respaldos

Todo lo importante está ahora en MySQL, así que el respaldo es el de la base:
cPanel → **Asistente de copia de seguridad → Descargar una base de datos
MySQL**, o desde phpMyAdmin → *Exportar*. Conviene bajarlo de vez en cuando,
y sobre todo antes de tocar nada.

Lo único que vive fuera de la base son las fotos: `img/uploads/` y
`cheve/data/flyers/`. Un respaldo completo son esas dos carpetas más el
volcado de MySQL.

---

## 13. Cosas pendientes de limpiar (no urgentes)

- `img/eventos/Constancia_Laboral_Centro_de_Computo_David_Suchiapa.docx` está
  en una carpeta pública y es descargable por cualquiera. **Bórralo del
  servidor.**
- `__stress.html` es una versión antigua de la página. No la subas.
- En `js/main.js` quedan el modal de eventos (`#eventModal`) y el objeto
  `sampleEventDetails`: son código muerto, no hay ninguna tarjeta de evento
  en la página que los active. Se pueden borrar cuando quieras.
