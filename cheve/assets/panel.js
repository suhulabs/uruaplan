/* ==========================================================================
   PANEL URUAPLAN — comportamiento del administrador
   Sin librerías: JavaScript nativo, compatible con navegadores actuales.
   ========================================================================== */

(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    ojoContrasenas();
    contadoresDeTexto();
    vistaPreviaIconos();
    vistaPreviaArchivos();
    pestanas();
    listasDinamicas();
    interruptoresVisibilidad();
    modalEncuadre();
    avisoDeCambios();
    fuerzaDeContrasena();
    cerrarAlertas();
  });

  /* ------------------------------------------------------------------------
     Geometría del collage
     El mosaico del sitio es una rejilla de 12 columnas con filas de 210 px,
     y cada casilla ocupa 3, 4 o 5 columnas SEGÚN SU POSICIÓN en la lista.
     Estos valores salen de css/styles.css (.gallery-item.gi-1 … .gi-13);
     si allí cambian los "grid-column: span N", hay que actualizarlos aquí.
     ------------------------------------------------------------------------ */
  var SPANS_COLLAGE = [4, 5, 3, 3, 4, 5, 5, 4, 3, 3, 3, 3, 3];
  var COLLAGE_ANCHO = 1400;
  var COLLAGE_HUECO = 8;
  var COLLAGE_ALTO_FILA = 210;

  /**
   * Proporción del hueco donde acabará la imagen, en escritorio y en celular.
   *
   *  collage -> casilla del mosaico; el ancho depende de la POSICIÓN en la
   *             lista (3, 4 o 5 columnas). En celular son 2 columnas, casi
   *             cuadradas.
   *  hero    -> el fondo ocupa la pantalla entera: apaisado en computadora
   *             y muy vertical en celular, que es donde más se recorta.
   */
  function proporcionHueco(marco, indice) {
    if (marco === 'flyer') {
      // El hueco de la foto dentro de la tarjeta del flyer. En la web el
      // alto es flexible, pero 4/3 es lo que acaba midiendo en la práctica
      // y es exactamente lo que se fija en la vista de celular.
      return {
        escritorio: 4 / 3,
        movil: 4 / 3,
        nombre: 'la foto principal del flyer'
      };
    }

    if (marco === 'hero') {
      return {
        escritorio: 16 / 9,
        movil: 9 / 16,
        nombre: 'el fondo #' + (indice + 1) + ' de la portada'
      };
    }

    var span = SPANS_COLLAGE[indice % SPANS_COLLAGE.length];
    var anchoColumna = (COLLAGE_ANCHO - 11 * COLLAGE_HUECO) / 12;
    var ancho = span * anchoColumna + (span - 1) * COLLAGE_HUECO;

    return {
      escritorio: ancho / COLLAGE_ALTO_FILA,
      movil: 1,
      nombre: 'la casilla #' + (indice + 1) + ' del collage'
    };
  }

  /* ------------------------------------------------------------------------
     Mostrar / ocultar contraseña
     ------------------------------------------------------------------------ */
  function ojoContrasenas() {
    document.querySelectorAll('.btn-ojo').forEach(function (boton) {
      boton.addEventListener('click', function () {
        var campo = document.getElementById(boton.getAttribute('data-ver'));
        if (!campo) return;

        var oculto = campo.type === 'password';
        campo.type = oculto ? 'text' : 'password';

        var icono = boton.querySelector('i');
        if (icono) {
          icono.className = oculto ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye';
        }
        boton.setAttribute('aria-label', oculto ? 'Ocultar contraseña' : 'Mostrar contraseña');
      });
    });
  }

  /* ------------------------------------------------------------------------
     Contador de caracteres en los campos con maxlength
     ------------------------------------------------------------------------ */
  function contadoresDeTexto() {
    document.querySelectorAll('[data-contador]').forEach(function (campo) {
      var salida = campo.parentElement
        ? campo.parentElement.querySelector('.contador')
        : null;
      if (!salida) return;

      var max = parseInt(campo.getAttribute('maxlength'), 10);
      if (!max) return;

      var pintar = function () {
        var usados = campo.value.length;
        salida.textContent = usados + '/' + max;
        salida.classList.toggle('cerca', usados >= max * 0.9);
      };

      campo.addEventListener('input', pintar);
      pintar();
    });
  }

  /* ------------------------------------------------------------------------
     Vista previa en vivo de los iconos de Font Awesome
     ------------------------------------------------------------------------ */
  function vistaPreviaIconos() {
    document.querySelectorAll('[data-icono]').forEach(function (campo) {
      var caja = campo.parentElement.querySelector('.icono-preview i');
      if (!caja) return;

      campo.addEventListener('input', function () {
        // Solo se aceptan clases con el formato de Font Awesome.
        caja.className = campo.value.trim().replace(/[^a-zA-Z0-9 \-]/g, '');
      });
    });
  }

  /* ------------------------------------------------------------------------
     Vista previa del archivo recién elegido (antes de subirlo)
     ------------------------------------------------------------------------ */
  function vistaPreviaArchivos() {
    document.addEventListener('change', function (evento) {
      var entrada = evento.target;
      if (!entrada.classList || !entrada.classList.contains('input-archivo')) return;
      if (!entrada.files || !entrada.files[0]) return;

      var archivo = entrada.files[0];
      var destino = document.getElementById(entrada.getAttribute('data-preview'));
      var url = URL.createObjectURL(archivo);

      if (!destino) {
        // La fila no tenía archivo: hay que sustituir el marcador "Sin archivo".
        var hueco = entrada.closest('.campo-archivo');
        if (!hueco) return;
        var vacio = hueco.querySelector('.vista-vacia');
        if (!vacio) return;

        var nuevo = archivo.type.indexOf('video') === 0
          ? document.createElement('video')
          : document.createElement('img');
        nuevo.src = url;
        nuevo.id = entrada.getAttribute('data-preview');
        if (nuevo.tagName === 'VIDEO') {
          nuevo.muted = true;
          nuevo.loop = true;
          nuevo.playsInline = true;
        }
        vacio.parentElement.replaceChild(nuevo, vacio);
        return;
      }

      destino.src = url;
      destino.style.display = '';

      // Fuera de las listas del editor (por ejemplo en la ficha de un flyer)
      // el campo no vive dentro de un .campo-archivo y no hay ruta que pintar.
      var contenedor = entrada.closest('.campo-archivo');
      var etiquetaRuta = contenedor ? contenedor.querySelector('.ruta-actual') : null;
      if (etiquetaRuta) {
        etiquetaRuta.textContent = 'Nuevo: ' + archivo.name;
      }
    });
  }

  /* ------------------------------------------------------------------------
     Pestañas laterales
     ------------------------------------------------------------------------ */
  function pestanas() {
    var botones = document.querySelectorAll('.pestana');
    var campoTab = document.getElementById('tabActiva');
    if (!botones.length) return;

    botones.forEach(function (boton) {
      boton.addEventListener('click', function () {
        var idPanel = boton.getAttribute('data-panel');

        botones.forEach(function (b) { b.classList.remove('activa'); });
        boton.classList.add('activa');

        document.querySelectorAll('.panel').forEach(function (p) {
          p.classList.toggle('visible', p.id === idPanel);
        });

        if (campoTab) {
          campoTab.value = idPanel.replace(/^panel-/, '');
        }

        window.scrollTo({ top: 0, behavior: 'smooth' });
      });
    });
  }

  /* ------------------------------------------------------------------------
     Listas dinámicas: agregar, quitar y reordenar filas
     ------------------------------------------------------------------------ */
  function listasDinamicas() {
    document.querySelectorAll('.bloque-lista').forEach(function (bloque) {
      var contenedor = bloque.querySelector('.filas');
      var plantilla = bloque.querySelector('.plantilla-fila');
      var botonAgregar = bloque.querySelector('.btn-agregar');

      renumerar(bloque);

      // --- Agregar ---
      if (botonAgregar && plantilla) {
        botonAgregar.addEventListener('click', function () {
          var indice = parseInt(bloque.getAttribute('data-siguiente'), 10) || 0;
          bloque.setAttribute('data-siguiente', indice + 1);

          var copia = plantilla.content.cloneNode(true);

          // Sustituye el marcador __IDX__ por el índice real en todos los
          // atributos que lo llevan (name, id, for, data-preview).
          copia.querySelectorAll('*').forEach(function (nodo) {
            ['name', 'id', 'for', 'data-preview'].forEach(function (attr) {
              var valor = nodo.getAttribute(attr);
              if (valor && valor.indexOf('__IDX__') !== -1) {
                nodo.setAttribute(attr, valor.split('__IDX__').join(indice));
              }
            });
          });

          var fila = copia.querySelector('.fila-lista');
          if (fila) fila.setAttribute('data-indice', indice);

          contenedor.appendChild(copia);
          renumerar(bloque);
          marcarSucio();

          // La fila recién creada también necesita su resumen de encuadre.
          var nueva = contenedor.lastElementChild;
          if (nueva) {
            nueva.querySelectorAll('[data-encuadre]').forEach(pintarResumen);
          }

          var agregada = contenedor.lastElementChild;
          if (agregada) {
            agregada.scrollIntoView({ behavior: 'smooth', block: 'center' });
            var primerInput = agregada.querySelector('input[type="file"]');
            if (primerInput) primerInput.focus({ preventScroll: true });
          }
        });
      }

      // --- Quitar y mover (delegación: también aplica a filas nuevas) ---
      bloque.addEventListener('click', function (evento) {
        var botonEliminar = evento.target.closest('[data-eliminar]');
        var botonMover = evento.target.closest('[data-mover]');

        if (botonEliminar) {
          var fila = botonEliminar.closest('.fila-lista');
          if (!fila) return;
          if (!window.confirm('¿Quitar este elemento de la lista?\n\nEl cambio se aplica al guardar.')) return;

          fila.classList.add('saliendo');
          window.setTimeout(function () {
            fila.remove();
            renumerar(bloque);
          }, 180);
          marcarSucio();
          return;
        }

        if (botonMover) {
          var actual = botonMover.closest('.fila-lista');
          if (!actual) return;

          var direccion = parseInt(botonMover.getAttribute('data-mover'), 10);
          var vecino = direccion < 0
            ? actual.previousElementSibling
            : actual.nextElementSibling;

          if (!vecino) return;

          if (direccion < 0) {
            contenedor.insertBefore(actual, vecino);
          } else {
            contenedor.insertBefore(vecino, actual);
          }

          renumerar(bloque);
          marcarSucio();
          actual.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
      });
    });
  }

  /**
   * Renumera las etiquetas visibles y actualiza el contador de la cabecera.
   * El ORDEN de guardado es el orden del DOM, así que esto solo es cosmético.
   */
  function renumerar(bloque) {
    var filas = bloque.querySelectorAll('.fila-lista');

    filas.forEach(function (fila, i) {
      var etiqueta = fila.querySelector('.fila-numero');
      if (etiqueta) etiqueta.textContent = '#' + (i + 1);

      var subir = fila.querySelector('[data-mover="-1"]');
      var bajar = fila.querySelector('[data-mover="1"]');
      if (subir) subir.disabled = (i === 0);
      if (bajar) bajar.disabled = (i === filas.length - 1);
    });

    var contador = bloque.querySelector('.contador-filas');
    if (contador) contador.textContent = filas.length;
  }

  /* ------------------------------------------------------------------------
     Interruptores "Mostrar en la web"
     Atenúan la fila para que se vea de un vistazo qué está oculto.
     ------------------------------------------------------------------------ */
  function interruptoresVisibilidad() {
    var pintar = function (casilla) {
      var fila = casilla.closest('.fila-lista');
      if (fila) fila.classList.toggle('oculta', !casilla.checked);
    };

    document.querySelectorAll('.campo-check input[type="checkbox"]').forEach(function (casilla) {
      pintar(casilla);
      casilla.addEventListener('change', function () { pintar(casilla); });
    });

    // Las filas que se agregan después también quedan cubiertas.
    document.addEventListener('change', function (evento) {
      var destino = evento.target;
      if (destino.type === 'checkbox' && destino.closest('.campo-check')) {
        pintar(destino);
      }
    });
  }

  /* ------------------------------------------------------------------------
     Modal de encuadre
     Permite elegir qué parte de la foto se ve dentro de la casilla del
     collage (que la recorta con object-fit: cover) y cuánto se acerca.
     Se guarda como pos_x / pos_y / zoom en campos ocultos de la fila.
     ------------------------------------------------------------------------ */
  function modalEncuadre() {
    var modal = document.getElementById('modalEncuadre');
    if (!modal) return;

    var marco       = document.getElementById('encMarco');
    var marcoMovil  = document.getElementById('encMarcoMovil');
    var img         = document.getElementById('encImg');
    var video       = document.getElementById('encVideo');
    var imgMovil    = document.getElementById('encImgMovil');
    var videoMovil  = document.getElementById('encVideoMovil');
    var deslizador  = document.getElementById('encZoom');
    var salidaZoom  = document.getElementById('encZoomValor');
    var aviso       = document.getElementById('encAviso');
    var textoEjes   = document.getElementById('encEjes');
    var etiquetaSlot = modal.querySelector('[data-enc-slot]');

    // Estado de la edición en curso.
    var campoActivo = null;   // .campo-encuadre de la fila que se está editando
    var posX = 50, posY = 50, zoom = 1;
    var natural = { w: 0, h: 0 };
    var focoPrevio = null;

    // --- Pintado -----------------------------------------------------------

    function aplicarEstilo(elemento) {
      if (!elemento) return;
      elemento.style.setProperty('--enc-pos', posX + '% ' + posY + '%');
      elemento.style.setProperty('--enc-zoom', zoom);
      elemento.style.objectPosition = posX + '% ' + posY + '%';
      elemento.style.transformOrigin = posX + '% ' + posY + '%';
      elemento.style.transform = 'scale(' + zoom + ')';
    }

    function refrescar() {
      [img, video, imgMovil, videoMovil].forEach(aplicarEstilo);
      deslizador.value = zoom;
      salidaZoom.textContent = Number(zoom).toFixed(2) + '×';

      // Mientras no se conozcan las medidas reales del archivo no se puede
      // decir nada sobre el arrastre; sin esto parpadea un "no se puede
      // mover" justo antes de que cargue la imagen.
      if (!natural.w || !natural.h) {
        marco.setAttribute('data-ejes', 'ninguno');
        textoEjes.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Cargando…';
        aviso.hidden = true;
        return;
      }

      // Con object-fit: cover solo se puede desplazar el eje en el que
      // SOBRA imagen. Casi todas las fotos sobran solo por un lado, así que
      // hay que decirlo: si no, uno arrastra en horizontal, no pasa nada
      // y parece que está roto.
      var rangos = rangosArrastre();
      var puedeX = rangos.x >= 1;
      var puedeY = rangos.y >= 1;

      var ejes = puedeX && puedeY ? 'ambos'
               : puedeX ? 'horizontal'
               : puedeY ? 'vertical'
               : 'ninguno';

      marco.setAttribute('data-ejes', ejes);

      var mensajes = {
        ambos:      '<i class="fa-solid fa-up-down-left-right"></i> Puedes moverla en cualquier dirección.',
        vertical:   '<i class="fa-solid fa-up-down"></i> Esta foto solo se puede mover <strong>arriba y abajo</strong>: de ancho ya encaja justa.',
        horizontal: '<i class="fa-solid fa-left-right"></i> Esta foto solo se puede mover <strong>a los lados</strong>: de alto ya encaja justa.',
        ninguno:    '<i class="fa-solid fa-lock"></i> Encaja exacta en la casilla, no hay nada que mover.'
      };
      textoEjes.innerHTML = mensajes[ejes];

      // El aviso grande solo cuando de verdad no hay nada que hacer sin zoom.
      var bloqueada = ejes === 'ninguno' && zoom <= 1.001;
      aviso.hidden = !bloqueada;
      if (bloqueada) {
        aviso.textContent = 'Esta foto encaja casi exacta en la casilla, así que no hay nada '
          + 'que reencuadrar. Sube el zoom si quieres acercarla y entonces sí podrás moverla.';
      }
    }

    /**
     * Cuántos píxeles de imagen quedan fuera del marco en cada eje.
     * Es lo que determina cuánto recorrido real tiene el arrastre:
     * object-position va de 0% a 100% repartido en ese sobrante.
     */
    function rangosArrastre() {
      if (!natural.w || !natural.h) return { x: 0, y: 0 };

      var caja = marco.getBoundingClientRect();
      if (!caja.width || !caja.height) return { x: 0, y: 0 };

      var arImagen = natural.w / natural.h;
      var arMarco  = caja.width / caja.height;

      var anchoMostrado, altoMostrado;
      if (arImagen > arMarco) {
        altoMostrado  = caja.height;
        anchoMostrado = altoMostrado * arImagen;
      } else {
        anchoMostrado = caja.width;
        altoMostrado  = anchoMostrado / arImagen;
      }

      var sobranteX = Math.max(0, anchoMostrado - caja.width);
      var sobranteY = Math.max(0, altoMostrado - caja.height);

      // El zoom recorta de más y añade recorrido en los dos ejes.
      return {
        x: sobranteX * zoom + caja.width * (zoom - 1),
        y: sobranteY * zoom + caja.height * (zoom - 1)
      };
    }

    // --- Apertura y cierre --------------------------------------------------

    function abrir(campo) {
      var idPreview = campo.getAttribute('data-preview');
      var origen = document.getElementById(idPreview);

      if (!origen || !origen.getAttribute('src')) {
        window.alert('Primero elige una foto o un video en esta fila; después podrás encuadrarla.');
        return;
      }

      campoActivo = campo;
      focoPrevio = document.activeElement;

      posX = parseFloat(campo.querySelector('[data-enc="x"]').value) || 50;
      posY = parseFloat(campo.querySelector('[data-enc="y"]').value) || 50;
      zoom = parseFloat(campo.querySelector('[data-enc="z"]').value) || 1;

      // Proporción del hueco que le toca a esta fila por su posición.
      // Fuera de las listas (por ejemplo en la ficha de un flyer) no hay
      // fila ni posición: el hueco es siempre el mismo.
      var fila = campo.closest('.fila-lista');
      var indice = 0;

      if (fila && fila.parentElement) {
        indice = Array.prototype.slice.call(fila.parentElement.children).indexOf(fila);
      }

      var hueco = proporcionHueco(campo.getAttribute('data-marco'), indice);
      marco.style.aspectRatio = String(hueco.escritorio);
      marcoMovil.style.aspectRatio = String(hueco.movil);
      etiquetaSlot.textContent = hueco.nombre;

      // El modal se muestra ANTES de cargar el medio: mientras está oculto,
      // getBoundingClientRect() devuelve ceros y los cálculos de arrastre
      // saldrían mal.
      modal.hidden = false;
      document.body.style.overflow = 'hidden';

      var esVideo = origen.tagName === 'VIDEO';
      var src = origen.getAttribute('src');

      img.hidden = esVideo;
      imgMovil.hidden = esVideo;
      video.hidden = !esVideo;
      videoMovil.hidden = !esVideo;

      var alLeerMedidas = function (w, h) {
        natural.w = w;
        natural.h = h;
        refrescar();
      };

      natural.w = 0;
      natural.h = 0;

      if (esVideo) {
        if (video.getAttribute('src') !== src) {
          video.src = src;
          videoMovil.src = src;
        }

        // Sin esto el modal muestra un rectángulo negro: un <video> parado
        // y sin poster no pinta ningún fotograma.
        var reproducir = function (v) {
          var intento = v.play();
          if (intento && typeof intento.catch === 'function') {
            intento.catch(function () { /* algunos navegadores lo bloquean; da igual */ });
          }
        };
        reproducir(video);
        reproducir(videoMovil);

        // Si el video ya estaba cargado de una apertura anterior, el evento
        // loadedmetadata no vuelve a dispararse: hay que leerlo directamente.
        if (video.readyState >= 1 && video.videoWidth) {
          alLeerMedidas(video.videoWidth, video.videoHeight);
        } else {
          video.addEventListener('loadedmetadata', function alCargar() {
            video.removeEventListener('loadedmetadata', alCargar);
            alLeerMedidas(video.videoWidth, video.videoHeight);
          });
        }
      } else {
        if (img.getAttribute('src') !== src) {
          img.src = src;
          imgMovil.src = src;
        }

        if (img.complete && img.naturalWidth) {
          alLeerMedidas(img.naturalWidth, img.naturalHeight);
        } else {
          img.addEventListener('load', function alCargar() {
            img.removeEventListener('load', alCargar);
            alLeerMedidas(img.naturalWidth, img.naturalHeight);
          });
        }
      }

      refrescar();
      deslizador.focus();
    }

    function cerrar() {
      modal.hidden = true;
      document.body.style.overflow = '';
      campoActivo = null;

      if (video.src) video.pause();
      if (videoMovil.src) videoMovil.pause();

      if (focoPrevio && typeof focoPrevio.focus === 'function') focoPrevio.focus();
    }

    function aplicar() {
      if (!campoActivo) return;

      campoActivo.querySelector('[data-enc="x"]').value = Math.round(posX * 100) / 100;
      campoActivo.querySelector('[data-enc="y"]').value = Math.round(posY * 100) / 100;
      campoActivo.querySelector('[data-enc="z"]').value = Math.round(zoom * 1000) / 1000;

      // La miniatura de la fila muestra el resultado sin tener que guardar.
      var preview = document.getElementById(campoActivo.getAttribute('data-preview'));
      aplicarEstilo(preview);

      pintarResumen(campoActivo);
      marcarSucio();
      cerrar();
    }

    // --- Arrastre -----------------------------------------------------------

    var arrastrando = false;
    var ultimoX = 0, ultimoY = 0;

    marco.addEventListener('pointerdown', function (evento) {
      arrastrando = true;
      ultimoX = evento.clientX;
      ultimoY = evento.clientY;
      marco.classList.add('arrastrando');
      marco.setPointerCapture(evento.pointerId);
    });

    marco.addEventListener('pointermove', function (evento) {
      if (!arrastrando) return;

      var dx = evento.clientX - ultimoX;
      var dy = evento.clientY - ultimoY;
      ultimoX = evento.clientX;
      ultimoY = evento.clientY;

      var rangos = rangosArrastre();

      // Se resta porque arrastrar la imagen hacia la izquierda
      // significa querer ver la parte derecha (object-position sube).
      if (rangos.x >= 1) {
        posX = Math.min(100, Math.max(0, posX - (dx / rangos.x) * 100));
      }
      if (rangos.y >= 1) {
        posY = Math.min(100, Math.max(0, posY - (dy / rangos.y) * 100));
      }

      refrescar();
    });

    var soltar = function (evento) {
      if (!arrastrando) return;
      arrastrando = false;
      marco.classList.remove('arrastrando');
      if (evento.pointerId !== undefined && marco.hasPointerCapture(evento.pointerId)) {
        marco.releasePointerCapture(evento.pointerId);
      }
    };

    marco.addEventListener('pointerup', soltar);
    marco.addEventListener('pointercancel', soltar);

    // --- Controles ----------------------------------------------------------

    deslizador.addEventListener('input', function () {
      zoom = parseFloat(deslizador.value);
      refrescar();
    });

    modal.querySelector('[data-enc-centrar]').addEventListener('click', function () {
      posX = 50;
      posY = 50;
      zoom = 1;
      refrescar();
    });

    modal.querySelector('[data-enc-aplicar]').addEventListener('click', aplicar);

    modal.querySelectorAll('[data-enc-cerrar]').forEach(function (boton) {
      boton.addEventListener('click', cerrar);
    });

    // Clic en el fondo oscuro.
    modal.addEventListener('click', function (evento) {
      if (evento.target === modal) cerrar();
    });

    document.addEventListener('keydown', function (evento) {
      if (modal.hidden) return;
      if (evento.key === 'Escape') cerrar();
      if (evento.key === 'Enter' && evento.target !== deslizador) aplicar();
    });

    // --- Enganche con las filas --------------------------------------------

    document.addEventListener('click', function (evento) {
      var boton = evento.target.closest('.btn-encuadre');
      if (!boton) return;
      var campo = boton.closest('[data-encuadre]');
      if (campo) abrir(campo);
    });

    // Estado inicial de todas las filas ya presentes.
    document.querySelectorAll('[data-encuadre]').forEach(function (campo) {
      pintarResumen(campo);

      var preview = document.getElementById(campo.getAttribute('data-preview'));
      if (!preview) return;

      var x = parseFloat(campo.querySelector('[data-enc="x"]').value) || 50;
      var y = parseFloat(campo.querySelector('[data-enc="y"]').value) || 50;
      var z = parseFloat(campo.querySelector('[data-enc="z"]').value) || 1;

      preview.style.objectPosition = x + '% ' + y + '%';
      preview.style.transformOrigin = x + '% ' + y + '%';
      preview.style.transform = 'scale(' + z + ')';
    });
  }

  /**
   * Texto de una línea que resume el encuadre de una fila.
   */
  function pintarResumen(campo) {
    var salida = campo.querySelector('.encuadre-resumen');
    if (!salida) return;

    var x = parseFloat(campo.querySelector('[data-enc="x"]').value) || 50;
    var y = parseFloat(campo.querySelector('[data-enc="y"]').value) || 50;
    var z = parseFloat(campo.querySelector('[data-enc="z"]').value) || 1;

    var tocado = Math.abs(x - 50) > 0.01 || Math.abs(y - 50) > 0.01 || Math.abs(z - 1) > 0.001;

    if (!tocado) {
      salida.textContent = 'Centrada, sin zoom';
      salida.classList.remove('ajustado');
      return;
    }

    var partes = [];
    if (Math.abs(x - 50) > 0.01 || Math.abs(y - 50) > 0.01) {
      partes.push('foco ' + Math.round(x) + '% / ' + Math.round(y) + '%');
    }
    if (Math.abs(z - 1) > 0.001) {
      partes.push('zoom ' + z.toFixed(2) + '×');
    }

    salida.textContent = partes.join(' · ');
    salida.classList.add('ajustado');
  }

  /* ------------------------------------------------------------------------
     Aviso de cambios sin guardar
     ------------------------------------------------------------------------ */
  var sucio = false;

  function marcarSucio() {
    sucio = true;
    var estado = document.getElementById('estadoCambios');
    if (estado) {
      estado.textContent = 'Tienes cambios sin guardar';
      estado.classList.add('pendiente');
    }
  }

  function avisoDeCambios() {
    var formulario = document.getElementById('formContenido');
    if (!formulario) return;

    formulario.addEventListener('input', marcarSucio);
    formulario.addEventListener('change', marcarSucio);

    formulario.addEventListener('submit', function () {
      sucio = false;

      var boton = document.getElementById('btnGuardar');
      if (boton) {
        boton.disabled = true;
        boton.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Guardando…';
      }

      var estado = document.getElementById('estadoCambios');
      if (estado) {
        estado.textContent = 'Subiendo cambios…';
      }
    });

    window.addEventListener('beforeunload', function (evento) {
      if (!sucio) return;
      evento.preventDefault();
      evento.returnValue = '';
    });
  }

  /* ------------------------------------------------------------------------
     Medidor de fuerza y coincidencia de contraseñas
     ------------------------------------------------------------------------ */
  function fuerzaDeContrasena() {
    var nueva = document.querySelector('[data-fuerza]');
    var medidor = document.getElementById('medidorFuerza');
    var repetida = document.getElementById('repetida');
    var aviso = document.getElementById('avisoCoincide');

    if (nueva && medidor) {
      var barra = medidor.querySelector('.medidor-barra span');
      var texto = medidor.querySelector('.medidor-texto');

      nueva.addEventListener('input', function () {
        var valor = nueva.value;
        medidor.hidden = valor.length === 0;

        var puntos = 0;
        if (valor.length >= 10) puntos++;
        if (valor.length >= 14) puntos++;
        if (/[a-z]/.test(valor) && /[A-Z]/.test(valor)) puntos++;
        if (/[0-9]/.test(valor)) puntos++;
        if (/[^A-Za-z0-9]/.test(valor)) puntos++;

        var niveles = [
          { ancho: '20%', color: '#f87171', nombre: 'Muy débil' },
          { ancho: '35%', color: '#f87171', nombre: 'Débil' },
          { ancho: '55%', color: '#fbbf24', nombre: 'Aceptable' },
          { ancho: '75%', color: '#fbbf24', nombre: 'Buena' },
          { ancho: '90%', color: '#a3e635', nombre: 'Fuerte' },
          { ancho: '100%', color: '#a3e635', nombre: 'Excelente' }
        ];

        var nivel = niveles[Math.min(puntos, niveles.length - 1)];
        barra.style.width = nivel.ancho;
        barra.style.background = nivel.color;
        texto.textContent = 'Seguridad: ' + nivel.nombre;
        texto.style.color = nivel.color;
      });
    }

    if (nueva && repetida && aviso) {
      var comprobar = function () {
        if (repetida.value === '') {
          aviso.textContent = '';
          aviso.className = 'aviso-coincide';
          return;
        }
        var coinciden = nueva.value === repetida.value;
        aviso.textContent = coinciden ? '✓ Las contraseñas coinciden' : '✗ No coinciden';
        aviso.className = 'aviso-coincide ' + (coinciden ? 'bien' : 'mal');
      };

      repetida.addEventListener('input', comprobar);
      nueva.addEventListener('input', comprobar);
    }
  }

  /* ------------------------------------------------------------------------
     Las alertas de éxito se desvanecen solas
     ------------------------------------------------------------------------ */
  function cerrarAlertas() {
    document.querySelectorAll('[data-autocerrar]').forEach(function (alerta) {
      window.setTimeout(function () {
        alerta.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
        alerta.style.opacity = '0';
        alerta.style.transform = 'translateY(-8px)';
        window.setTimeout(function () { alerta.remove(); }, 520);
      }, 5000);
    });
  }
})();
