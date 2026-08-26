/* ==========================================================================
   URUAPLAN - Interactive JavaScript Logic
   ========================================================================== */

/**
 * Configuración dinámica inyectada desde el backend (window.URUAPLAN).
 */
const CFG = Object.assign({
  whatsapp: '524520000000',
  correoForm: 'uruaplan@gmail.com',
  fondos: [],
  flyer: {}
}, window.URUAPLAN || {});

// Textos de respaldo del flyer: los que el editor haya puesto en el panel,
// o los originales si el campo viniera vacío.
const FLYER_DEF = Object.assign({
  titulo: 'GRAN FESTIVAL CULTURAL, ARTÍSTICO Y CONCIERTO EN VIVO URUAPAN',
  subtitulo: 'PRESENTACIONES EN VIVO, PABELLÓN GASTRONÓMICO Y EXPOSICIÓN DE ARTE',
  fecha: 'SÁBADO 00 DE MES',
  hora: '00:00 PM',
  contacto: 'INFORMES Y ORGANIZACIÓN: @URUAPLAN_OFICIAL Y REDES SOCIALES',
  precio: 'ENTRADA LIBRE CON PREVIO REGISTRO / ACCESO GRATUITO',
  ubicacion: 'PLAZA MORELOS Y EXPLANADA DEL CENTRO HISTÓRICO, URUAPAN, MICHOACÁN',
  descripcion: 'Gran evento y festival cultural abierto a todo el público en Uruapan, Michoacán. Disfruta de una jornada inolvidable llena de tradición, presentaciones musicales en vivo con reconocidas agrupaciones locales e invitadas, pabellón gastronómico tradicional, exposiciones artesanales, talleres interactivos y múltiples actividades pensadas para el disfrute de toda la familia.'
}, CFG.flyer || {});

document.addEventListener('DOMContentLoaded', () => {
  // Mobile Navigation Menu Toggle
  const mobileBtn = document.getElementById('mobileMenuBtn');
  const navLinks = document.getElementById('navLinks');

  if (mobileBtn && navLinks) {
    mobileBtn.addEventListener('click', () => {
      navLinks.classList.toggle('open');
      const icon = mobileBtn.querySelector('i');
      if (navLinks.classList.contains('open')) {
        icon.classList.remove('fa-bars');
        icon.classList.add('fa-xmark');
      } else {
        icon.classList.remove('fa-xmark');
        icon.classList.add('fa-bars');
      }
    });

    // Close menu when clicking link
    document.querySelectorAll('.nav-link').forEach(link => {
      link.addEventListener('click', () => {
        navLinks.classList.remove('open');
        const icon = mobileBtn.querySelector('i');
        if (icon) {
          icon.classList.remove('fa-xmark');
          icon.classList.add('fa-bars');
        }
      });
    });
  }

  // Sticky Navbar Effect on Scroll
  const navbar = document.getElementById('navbar');
  window.addEventListener('scroll', () => {
    if (window.scrollY > 50) {
      navbar.classList.add('scrolled');
    } else {
      navbar.classList.remove('scrolled');
    }
  });



  /* ------------------------------------------------------------------
     Envío del formulario de eventos.

     Antes esto armaba un enlace "mailto:" y hacía window.location. Eso
     solo funcionaba si la computadora del visitante tenía un programa de
     correo configurado (Outlook, Thunderbird...); en la mayoría no pasaba
     nada al darle a enviar. Además un mailto no puede llevar adjuntos, así
     que la foto del evento nunca viajaba.

     Ahora el formulario se manda a enviar-evento.php, que es quien envía
     el correo desde el servidor con la imagen adjunta de verdad.

     Si el visitante tuviera el JavaScript desactivado, el formulario se
     envía solo (action + method del HTML) y la página vuelve con el aviso
     en la URL. Este bloque es la versión bonita, sin recargar.
     ------------------------------------------------------------------ */
  const contactForm = document.getElementById('contactForm');
  const formMensaje = document.getElementById('formMensaje');

  // Se guarda ahora, antes de que nadie elija una foto, para poder devolver
  // la vista previa a su imagen original al vaciar el formulario.
  const flyerImgOriginal = (document.getElementById('flyerPreviewMainImg') || {}).src || '';

  /**
   * Tras un form.reset() los campos quedan vacíos pero la vista previa del
   * flyer sigue enseñando el evento anterior: los listeners que la pintan
   * solo reaccionan al evento 'input'. Se lo lanzamos a mano.
   */
  function restablecerVistaPrevia() {
    contactForm.querySelectorAll('input, textarea').forEach((campo) => {
      campo.dispatchEvent(new Event('input', { bubbles: true }));
    });

    const img = document.getElementById('flyerPreviewMainImg');
    if (img && flyerImgOriginal) {
      img.src = flyerImgOriginal;
    }
  }

  function mostrarMensajeForm(texto, ok) {
    if (!formMensaje) {
      showToast(texto);
      return;
    }

    formMensaje.textContent = texto;
    formMensaje.classList.toggle('ok', !!ok);
    formMensaje.classList.toggle('error', !ok);
    formMensaje.hidden = false;
    formMensaje.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }

  if (contactForm) {
    contactForm.addEventListener('submit', (e) => {
      e.preventDefault();

      const boton = contactForm.querySelector('button[type="submit"]');
      const textoBoton = boton ? boton.innerHTML : '';

      if (boton) {
        boton.disabled = true;
        boton.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Enviando…';
      }

      fetch(contactForm.action, {
        method: 'POST',
        body: new FormData(contactForm),
        headers: {
          // Le dice a enviar-evento.php que conteste en JSON en vez de
          // redirigir. Con FormData NO se pone Content-Type a mano: el
          // navegador tiene que añadir el "boundary" del multipart.
          'Accept': 'application/json',
          'X-Requested-With': 'fetch'
        }
      })
        .then((respuesta) => respuesta.json().catch(() => ({
          ok: false,
          mensaje: 'El servidor devolvió una respuesta inesperada. Inténtalo de nuevo o escríbenos por WhatsApp.'
        })))
        .then((datos) => {
          mostrarMensajeForm(datos.mensaje, datos.ok);

          if (datos.ok) {
            showToast('¡Solicitud enviada!');
            contactForm.reset();
            restablecerVistaPrevia();
          }
        })
        .catch(() => {
          mostrarMensajeForm(
            'No hay conexión con el servidor. Revisa tu internet e inténtalo de nuevo.',
            false
          );
        })
        .finally(() => {
          if (boton) {
            boton.disabled = false;
            boton.innerHTML = textoBoton;
          }
        });
    });
  }

  // Live Flyer Preview Updater Logic
  const formEventName = document.getElementById('formEventName');
  const formSubtitle = document.getElementById('formSubtitle');
  const formDate = document.getElementById('formDate');
  const formTime = document.getElementById('formTime');
  const formContact = document.getElementById('formContact');
  const formPrice = document.getElementById('formPrice');
  const formLocation = document.getElementById('formLocation');
  const formDescription = document.getElementById('formDescription');
  const formMainPhoto = document.getElementById('formMainPhoto');

  const flyerPreviewTitle = document.getElementById('flyerPreviewTitle');
  const flyerPreviewSubtitle = document.getElementById('flyerPreviewSubtitle');
  const flyerPreviewDate = document.getElementById('flyerPreviewDate');
  const flyerPreviewTime = document.getElementById('flyerPreviewTime');
  const flyerPreviewContact = document.getElementById('flyerPreviewContact');
  const flyerPreviewPrice = document.getElementById('flyerPreviewPrice');
  const flyerPreviewLocation = document.getElementById('flyerPreviewLocation');
  const flyerPreviewDesc = document.getElementById('flyerPreviewDesc');
  const flyerPreviewMainImg = document.getElementById('flyerPreviewMainImg');

  function formatDateDisplay(dateStr) {
    if (!dateStr) return FLYER_DEF.fecha;
    const parts = dateStr.split('-');
    if (parts.length === 3) {
      const year = parseInt(parts[0], 10);
      const monthIdx = parseInt(parts[1], 10) - 1;
      const day = parseInt(parts[2], 10);
      const d = new Date(year, monthIdx, day);
      const days = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
      const months = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
      if (months[monthIdx] && !isNaN(d.getTime())) {
        const dayOfWeek = days[d.getDay()];
        return `${dayOfWeek} ${day} de ${months[monthIdx]}`.toUpperCase();
      }
    }
    return dateStr.toUpperCase();
  }

  function formatTimeDisplay(timeStr) {
    if (!timeStr) return FLYER_DEF.hora;
    const parts = timeStr.split(':');
    if (parts.length >= 2) {
      let hours = parseInt(parts[0], 10);
      const minutes = parts[1];
      const ampm = hours >= 12 ? 'pm' : 'am';
      hours = hours % 12 || 12;
      const formattedHours = hours < 10 ? '0' + hours : hours;
      return `${formattedHours}:${minutes} ${ampm}`;
    }
    return timeStr;
  }

  if (formEventName && flyerPreviewTitle) {
    formEventName.addEventListener('input', (e) => {
      flyerPreviewTitle.textContent = e.target.value.trim() ? e.target.value.toUpperCase() : FLYER_DEF.titulo;
    });
  }

  if (formSubtitle && flyerPreviewSubtitle) {
    formSubtitle.addEventListener('input', (e) => {
      flyerPreviewSubtitle.textContent = e.target.value.trim() ? e.target.value.toUpperCase() : FLYER_DEF.subtitulo;
    });
  }

  if (formDate && flyerPreviewDate) {
    formDate.addEventListener('input', (e) => {
      flyerPreviewDate.textContent = formatDateDisplay(e.target.value);
    });
  }

  if (formTime && flyerPreviewTime) {
    formTime.addEventListener('input', (e) => {
      flyerPreviewTime.textContent = formatTimeDisplay(e.target.value);
    });
  }

  if (formContact && flyerPreviewContact) {
    formContact.addEventListener('input', (e) => {
      flyerPreviewContact.textContent = e.target.value.trim() || FLYER_DEF.contacto;
    });
  }

  if (formPrice && flyerPreviewPrice) {
    formPrice.addEventListener('input', (e) => {
      flyerPreviewPrice.textContent = e.target.value.trim() || FLYER_DEF.precio;
    });
  }

  if (formLocation && flyerPreviewLocation) {
    formLocation.addEventListener('input', (e) => {
      flyerPreviewLocation.textContent = e.target.value.trim() ? e.target.value.toUpperCase() : FLYER_DEF.ubicacion;
    });
  }

  const flyerPreviewDescWrapper = document.getElementById('flyerPreviewDescWrapper');
  if (formDescription && flyerPreviewDesc) {
    formDescription.addEventListener('input', (e) => {
      const val = e.target.value.trim();
      if (val !== '') {
        flyerPreviewDesc.textContent = val;
        if (flyerPreviewDescWrapper) flyerPreviewDescWrapper.style.display = '';
      } else {
        flyerPreviewDesc.textContent = '';
        if (flyerPreviewDescWrapper) flyerPreviewDescWrapper.style.display = 'none';
      }
    });
  }

  if (formMainPhoto && flyerPreviewMainImg) {
    formMainPhoto.addEventListener('change', (e) => {
      if (e.target.files && e.target.files[0]) {
        const fileURL = URL.createObjectURL(e.target.files[0]);
        flyerPreviewMainImg.src = fileURL;
      }
    });
  }

  const formOrgLogo = document.getElementById('formOrgLogo');
  const flyerPreviewOrgLogo = document.getElementById('flyerPreviewOrgLogo');

  if (formOrgLogo && flyerPreviewOrgLogo) {
    formOrgLogo.addEventListener('change', (e) => {
      if (e.target.files && e.target.files[0]) {
        const fileURL = URL.createObjectURL(e.target.files[0]);
        flyerPreviewOrgLogo.src = fileURL;
        flyerPreviewOrgLogo.style.display = 'block';
      } else {
        flyerPreviewOrgLogo.style.display = 'none';
        flyerPreviewOrgLogo.src = '';
      }
    });
  }

  // Contador de caracteres de cada campo limitado con maxlength.
  // El tope real lo aplica el navegador; esto solo lo hace visible
  // y avisa (en ámbar) cuando quedan pocos caracteres.
  document.querySelectorAll('.char-counter').forEach(counter => {
    const field = document.getElementById(counter.getAttribute('data-for'));
    if (!field) return;

    const max = parseInt(field.getAttribute('maxlength'), 10);
    if (!max) return;

    const render = () => {
      const used = field.value.length;
      counter.textContent = `${used}/${max} caracteres`;
      counter.classList.toggle('near-limit', used >= max * 0.9);
    };

    field.addEventListener('input', render);
    render();
  });

  // Año actual en el footer
  const footerYear = document.getElementById('footerYear');
  if (footerYear) {
    footerYear.textContent = new Date().getFullYear();
  }

  // Toast Notification Helper
  function showToast(msg) {
    let toast = document.getElementById('toastNotification');
    if (!toast) {
      toast = document.createElement('div');
      toast.id = 'toastNotification';
      toast.className = 'toast-notification';
      toast.innerHTML = `<i class="fa-solid fa-circle-check"></i> <span id="toastMsg"></span>`;
      document.body.appendChild(toast);
    }

    document.getElementById('toastMsg').textContent = msg;
    toast.classList.add('show');

    setTimeout(() => {
      toast.classList.remove('show');
    }, 3500);
  }

  // Instagram Story Image Carousel Handler (Alterna entre historia.png y selfie.png)
  const igSlides = document.querySelectorAll('.ig-slide');
  const igDots = document.querySelectorAll('.ig-dot');
  let currentIgSlide = 0;
  let igCarouselTimer = null;

  function showIgSlide(index) {
    if (!igSlides.length) return;
    currentIgSlide = (index + igSlides.length) % igSlides.length;

    igSlides.forEach((slide, idx) => {
      slide.classList.toggle('active', idx === currentIgSlide);
    });

    igDots.forEach((dot, idx) => {
      dot.classList.toggle('active', idx === currentIgSlide);
    });
  }

  function nextIgSlide() {
    showIgSlide(currentIgSlide + 1);
  }

  if (igSlides.length > 1) {
    igCarouselTimer = setInterval(nextIgSlide, 3500);

    igDots.forEach((dot, idx) => {
      dot.addEventListener('click', () => {
        clearInterval(igCarouselTimer);
        showIgSlide(idx);
        igCarouselTimer = setInterval(nextIgSlide, 3500);
      });
    });
  }

  /* ------------------------------------------------------------------------
     Lightbox de la Galería
     El collage recorta las fotos para encajarlas en el mosaico; aquí se ven
     completas. Los datos salen de los data-* que escribe index.php.
     ------------------------------------------------------------------------ */
  const lightbox = document.getElementById('lightbox');
  const galleryItems = Array.from(document.querySelectorAll('.gallery-item[data-src]'));

  if (lightbox && galleryItems.length) {
    const stage = document.getElementById('lightboxStage');
    const labelEl = document.getElementById('lightboxLabel');
    const counterEl = document.getElementById('lightboxCounter');
    const btnClose = document.getElementById('lightboxClose');
    const btnPrev = document.getElementById('lightboxPrev');
    const btnNext = document.getElementById('lightboxNext');

    let indiceActual = 0;
    let ultimoFoco = null;

    // Con un solo elemento las flechas sobran.
    const hayVarios = galleryItems.length > 1;
    btnPrev.hidden = !hayVarios;
    btnNext.hidden = !hayVarios;

    function pintarLightbox(indice) {
      indiceActual = (indice + galleryItems.length) % galleryItems.length;

      const item = galleryItems[indiceActual];
      const src = item.getAttribute('data-src');
      const tipo = item.getAttribute('data-tipo');
      const etiqueta = item.getAttribute('data-label') || '';
      const alt = item.getAttribute('data-alt') || etiqueta;

      stage.innerHTML = '';

      let medio;
      if (tipo === 'video') {
        medio = document.createElement('video');
        medio.src = src;
        medio.controls = true;
        medio.autoplay = true;
        medio.loop = true;
        medio.muted = true;
        medio.playsInline = true;
      } else {
        medio = document.createElement('img');
        medio.src = src;
        medio.alt = alt;
      }
      stage.appendChild(medio);

      labelEl.textContent = etiqueta;
      labelEl.style.display = etiqueta ? '' : 'none';
      counterEl.textContent = hayVarios
        ? `${indiceActual + 1} / ${galleryItems.length}`
        : '';

      // Precarga la siguiente y la anterior: al pasar se sienten instantáneas.
      if (hayVarios) {
        [1, -1].forEach(paso => {
          const vecino = galleryItems[(indiceActual + paso + galleryItems.length) % galleryItems.length];
          if (vecino.getAttribute('data-tipo') !== 'video') {
            new Image().src = vecino.getAttribute('data-src');
          }
        });
      }
    }

    function abrirLightbox(indice) {
      ultimoFoco = document.activeElement;
      pintarLightbox(indice);
      lightbox.classList.add('active');
      document.body.style.overflow = 'hidden';
      btnClose.focus();
    }

    function cerrarLightbox() {
      lightbox.classList.remove('active');
      document.body.style.overflow = 'auto';

      // Detiene el video para que no siga sonando de fondo.
      const video = stage.querySelector('video');
      if (video) video.pause();

      // Devuelve el foco a la foto desde la que se abrió (accesibilidad).
      if (ultimoFoco && typeof ultimoFoco.focus === 'function') {
        ultimoFoco.focus();
      }
    }

    galleryItems.forEach((item, indice) => {
      // Cada casilla pasa a ser un botón real: enfocable y usable con teclado.
      item.setAttribute('tabindex', '0');
      item.setAttribute('role', 'button');
      item.setAttribute('aria-label', `Ampliar: ${item.getAttribute('data-label') || 'imagen'}`);

      item.addEventListener('click', () => abrirLightbox(indice));
      item.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          abrirLightbox(indice);
        }
      });
    });

    btnClose.addEventListener('click', cerrarLightbox);
    btnPrev.addEventListener('click', () => pintarLightbox(indiceActual - 1));
    btnNext.addEventListener('click', () => pintarLightbox(indiceActual + 1));

    // Clic en el fondo (fuera de la imagen) cierra.
    lightbox.addEventListener('click', (e) => {
      if (e.target === lightbox || e.target === stage) cerrarLightbox();
    });

    document.addEventListener('keydown', (e) => {
      if (!lightbox.classList.contains('active')) return;

      if (e.key === 'Escape') cerrarLightbox();
      if (hayVarios && e.key === 'ArrowLeft') pintarLightbox(indiceActual - 1);
      if (hayVarios && e.key === 'ArrowRight') pintarLightbox(indiceActual + 1);
    });

    // Deslizar con el dedo en el celular.
    let inicioX = null;
    lightbox.addEventListener('touchstart', (e) => {
      inicioX = e.changedTouches[0].clientX;
    }, { passive: true });

    lightbox.addEventListener('touchend', (e) => {
      if (inicioX === null || !hayVarios) return;
      const recorrido = e.changedTouches[0].clientX - inicioX;
      if (Math.abs(recorrido) > 55) {
        pintarLightbox(indiceActual + (recorrido < 0 ? 1 : -1));
      }
      inicioX = null;
    }, { passive: true });
  }

  // Dynamic Background Slideshow (Hero section using all images in img/fondos/)
  const heroSlideshow = document.getElementById('heroSlideshow');
  const heroControls = document.getElementById('heroControls');

  const bgCandidateImages = (CFG.fondos || []).map(f => (
    typeof f === 'string'
      ? { url: f, x: 50, y: 50, zoom: 1 }
      : { url: f.url, x: f.x ?? 50, y: f.y ?? 50, zoom: f.zoom ?? 1 }
  )).filter(f => f.url);

  if (heroSlideshow && bgCandidateImages.length > 0) {
    let currentSlide = 0;
    let slideTimer = null;

    heroSlideshow.innerHTML = '';
    if (heroControls) heroControls.innerHTML = '';

    function cargarFondoSlide(idx) {
      const slide = slideElements[idx];
      const fondo = bgCandidateImages[idx];
      if (slide && fondo && slide.dataset.bgUrl) {
        slide.style.backgroundImage = `url('${slide.dataset.bgUrl}')`;
        delete slide.dataset.bgUrl;
      }
    }

    const slideElements = bgCandidateImages.map((fondo, idx) => {
      const slide = document.createElement('div');
      slide.className = `hero-slide${idx === 0 ? ' active' : ''}`;
      if (idx === 0) {
        slide.style.backgroundImage = `url('${fondo.url}')`;
      } else {
        slide.dataset.bgUrl = fondo.url;
      }
      slide.style.setProperty('--enc-pos', `${fondo.x}% ${fondo.y}%`);
      slide.style.setProperty('--enc-zoom', fondo.zoom);
      heroSlideshow.appendChild(slide);
      return slide;
    });

    const dotElements = heroControls ? bgCandidateImages.map((_, idx) => {
      const dot = document.createElement('button');
      dot.className = `hero-dot${idx === 0 ? ' active' : ''}`;
      dot.setAttribute('aria-label', `Ir a fondo ${idx + 1}`);
      dot.addEventListener('click', () => {
        goToSlide(idx);
        resetSlideTimer();
      });
      heroControls.appendChild(dot);
      return dot;
    }) : [];

    function goToSlide(index) {
      currentSlide = (index + bgCandidateImages.length) % bgCandidateImages.length;
      const siguiente = (currentSlide + 1) % bgCandidateImages.length;

      cargarFondoSlide(currentSlide);
      cargarFondoSlide(siguiente);

      slideElements.forEach((slide, idx) => {
        slide.classList.toggle('active', idx === currentSlide);
      });
      dotElements.forEach((dot, idx) => {
        dot.classList.toggle('active', idx === currentSlide);
      });
    }

    function resetSlideTimer() {
      if (slideTimer) clearInterval(slideTimer);
      if (bgCandidateImages.length > 1) {
        slideTimer = setInterval(() => goToSlide(currentSlide + 1), 5000);
      }
    }

    if (bgCandidateImages.length > 1) {
      resetSlideTimer();
    }
  }

  // Modal de Aviso de Privacidad
  const modalPrivacidad = document.getElementById('modalPrivacidad');
  const cerrarPrivacidad = document.getElementById('cerrarModalPrivacidad');
  const btnsPrivacidad = document.querySelectorAll('.abrir-privacidad');

  if (modalPrivacidad) {
    const cerrarModal = () => {
      modalPrivacidad.classList.remove('active');
      if (window.location.hash === '#modalPrivacidad') {
        history.replaceState(null, '', window.location.pathname + window.location.search);
      }
    };

    btnsPrivacidad.forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        modalPrivacidad.classList.add('active');
      });
    });

    if (cerrarPrivacidad) {
      cerrarPrivacidad.addEventListener('click', cerrarModal);
    }

    modalPrivacidad.addEventListener('click', (e) => {
      if (e.target === modalPrivacidad) {
        cerrarModal();
      }
    });

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && (modalPrivacidad.classList.contains('active') || window.location.hash === '#modalPrivacidad')) {
        cerrarModal();
      }
    });
  }
});
