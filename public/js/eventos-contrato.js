(() => {
    const listado = document.getElementById('eventos-contrato-listado');
    const buscando = document.getElementById('contrato-buscando');
    const foto = document.getElementById('contrato-foto');
    if (!listado || !buscando || !foto) return;

    let cierreBusqueda;
    const movimientoReducido = window.matchMedia('(prefers-reduced-motion: reduce)');

    function iniciarModalBusqueda() {
        window.Livewire.hook('commit', ({ component, commit, succeed, fail }) => {
            if (!listado.contains(component.el) || !commit.calls.some(({ method }) => method === 'searchRegistros')) return;

            clearTimeout(cierreBusqueda);
            if (!buscando.open) buscando.showModal();
            const inicio = performance.now();

            succeed(() => {
                const duracionMinima = movimientoReducido.matches ? 0 : 450;
                cierreBusqueda = setTimeout(() => buscando.close(), Math.max(0, duracionMinima - (performance.now() - inicio)));
            });
            fail(() => buscando.close());
        });
    }

    if (window.Livewire) iniciarModalBusqueda();
    else document.addEventListener('livewire:init', iniciarModalBusqueda, { once: true });

    const visor = foto.querySelector('[data-foto-visor]');
    const imagen = visor.querySelector('img');
    const mensaje = visor.querySelector('[data-foto-mensaje]');
    const menos = foto.querySelector('[data-foto-zoom="out"]');
    const mas = foto.querySelector('[data-foto-zoom="in"]');
    const ajustar = foto.querySelector('[data-foto-zoom="reset"]');
    const porcentaje = foto.querySelector('[data-foto-nivel]');
    const maxZoom = 4;
    const paso = .25;
    let zoom = 1;
    let arrastre;
    let abriendoFoto = false;
    let pantallaCompletaDelVisor = false;

    function actualizarControles() {
        porcentaje.textContent = `${Math.round(zoom * 100)}%`;
        menos.disabled = ajustar.disabled = imagen.hidden || zoom === 1;
        mas.disabled = imagen.hidden || zoom === maxZoom;
    }

    function ajustarTamano() {
        const escala = Math.min(visor.offsetWidth / imagen.naturalWidth, visor.offsetHeight / imagen.naturalHeight);
        imagen.style.width = `${Math.floor(imagen.naturalWidth * escala * zoom)}px`;
        imagen.style.height = `${Math.floor(imagen.naturalHeight * escala * zoom)}px`;
        visor.classList.toggle('is-zoomed', visor.scrollWidth > visor.clientWidth || visor.scrollHeight > visor.clientHeight);
    }

    function aplicarZoom(nivel, x, y) {
        const siguiente = Math.min(maxZoom, Math.max(1, nivel));
        if (imagen.hidden || siguiente === zoom) return;

        const area = visor.getBoundingClientRect();
        x ??= area.left + visor.clientWidth / 2;
        y ??= area.top + visor.clientHeight / 2;
        const anterior = imagen.getBoundingClientRect();
        const horizontal = (x - anterior.left) / anterior.width;
        const vertical = (y - anterior.top) / anterior.height;

        zoom = siguiente;
        ajustarTamano();
        actualizarControles();

        const nueva = imagen.getBoundingClientRect();
        visor.scrollLeft += nueva.left + horizontal * nueva.width - x;
        visor.scrollTop += nueva.top + vertical * nueva.height - y;
    }

    listado.addEventListener('click', async event => {
        const enlace = event.target.closest('a[data-contrato-foto]');
        if (!enlace || event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;

        event.preventDefault();
        if (foto.open || abriendoFoto) return;
        abriendoFoto = true;

        const pagina = document.documentElement;
        if (document.fullscreenEnabled && !document.fullscreenElement) {
            try {
                await pagina.requestFullscreen();
                pantallaCompletaDelVisor = true;
            } catch {
                // El visor ocupa toda la ventana si el navegador restringe la pantalla completa.
            }
        }

        zoom = 1;
        imagen.hidden = true;
        mensaje.hidden = false;
        mensaje.textContent = 'Cargando foto de entrega…';
        actualizarControles();
        foto.showModal();
        abriendoFoto = false;
        imagen.src = enlace.href;
    });

    imagen.addEventListener('load', () => {
        if (!foto.open) return;
        mensaje.hidden = true;
        imagen.hidden = false;
        ajustarTamano();
        actualizarControles();
    });

    imagen.addEventListener('error', () => {
        if (!foto.open) return;
        mensaje.textContent = 'No se pudo cargar la foto de entrega.';
    });

    foto.addEventListener('close', () => {
        if (foto.open) return;
        imagen.removeAttribute('src');
        visor.classList.remove('is-zoomed');
        if (pantallaCompletaDelVisor && document.fullscreenElement === document.documentElement) {
            document.exitFullscreen().catch(() => {});
        }
        pantallaCompletaDelVisor = false;
    });

    mas.addEventListener('click', () => aplicarZoom(zoom + paso));
    menos.addEventListener('click', () => aplicarZoom(zoom - paso));
    ajustar.addEventListener('click', () => aplicarZoom(1));

    visor.addEventListener('wheel', event => {
        if (imagen.hidden || event.ctrlKey || event.metaKey || event.deltaY === 0) return;
        event.preventDefault();
        aplicarZoom(zoom - Math.sign(event.deltaY) * paso, event.clientX, event.clientY);
    }, { passive: false });

    visor.addEventListener('pointerdown', event => {
        if (event.pointerType !== 'mouse' || event.button !== 0 || !visor.classList.contains('is-zoomed')) return;

        arrastre = {
            x: event.clientX + visor.scrollLeft,
            y: event.clientY + visor.scrollTop,
        };
        visor.setPointerCapture(event.pointerId);
        visor.classList.add('is-dragging');
    });

    visor.addEventListener('pointermove', event => {
        if (!arrastre || !visor.hasPointerCapture(event.pointerId)) return;
        visor.scrollLeft = arrastre.x - event.clientX;
        visor.scrollTop = arrastre.y - event.clientY;
    });

    visor.addEventListener('lostpointercapture', () => {
        arrastre = null;
        visor.classList.remove('is-dragging');
    });

    window.addEventListener('resize', () => {
        if (foto.open && !imagen.hidden) ajustarTamano();
    });
})();
