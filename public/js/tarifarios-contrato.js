(() => {
    'use strict';

    function panelDelFragmento(fragmento) {
        const panel = document.getElementById(fragmento.slice(1));
        return panel?.matches('details[data-tarifario-panel]') ? panel : null;
    }

    document.querySelectorAll('.tarifario-pagina a[href^="#"]').forEach(enlace => {
        const panel = panelDelFragmento(enlace.hash);
        if (panel) enlace.addEventListener('click', () => { panel.open = true; });
    });

    function abrirPanelDelFragmento() {
        const panel = panelDelFragmento(window.location.hash);
        if (!panel) return;
        panel.open = true;
        panel.scrollIntoView({ block: 'start' });
    }

    window.addEventListener('hashchange', abrirPanelDelFragmento);
    abrirPanelDelFragmento();

    document.querySelectorAll('[data-abrir-acciones]').forEach(boton => {
        const modal = document.getElementById(boton.dataset.abrirAcciones);
        if (!modal?.showModal) return;

        boton.addEventListener('click', () => modal.showModal());
        modal.addEventListener('click', event => {
            if (event.target.closest('a[href]') && !event.ctrlKey && !event.metaKey && !event.shiftKey && !event.altKey) modal.close();
        });
    });
    document.querySelector('[data-acciones-reabrir]')?.showModal?.();

    const modalAprobacion = document.getElementById('tarifario-aprobacion');
    if (modalAprobacion) {
        const revisar = modalAprobacion.querySelector('[data-aprobacion-revisar]');
        let botonActivo;

        function aprobarSeleccionado() {
            if (!botonActivo || botonActivo.disabled) return;
            botonActivo.closest('dialog')?.close();
            botonActivo.disabled = true;
            botonActivo.form.submit();
        }

        document.querySelectorAll('[data-aprobar-tarifario]').forEach(boton => {
            boton.addEventListener('click', () => {
                botonActivo = boton;
                const { nombre, anio, cantidad, revisarUrl, tieneTitular } = boton.dataset;
                if (typeof modalAprobacion.showModal !== 'function') {
                    if (window.confirm(`¿Seguro que quieres aprobar el tarifario ${nombre} · ${anio} para su uso oficial? Revisa bien los datos antes de confirmar.`)) aprobarSeleccionado();
                    return;
                }

                modalAprobacion.querySelector('[data-aprobacion-nombre]').textContent = nombre;
                modalAprobacion.querySelector('[data-aprobacion-anio]').textContent = anio;
                modalAprobacion.querySelector('[data-aprobacion-cantidad]').textContent = cantidad;
                modalAprobacion.querySelector('[data-aprobacion-efecto]').textContent = tieneTitular === '1'
                    ? 'Los precios quedarán protegidos y se aplicarán a las nuevas guías de la empresa.'
                    : 'Los precios quedarán protegidos y el tarifario estará listo para asignarse a empresas.';
                revisar.href = revisarUrl;
                modalAprobacion.showModal();
            });
        });

        revisar.addEventListener('click', () => {
            modalAprobacion.close();
            botonActivo?.closest('dialog')?.close();
        });
        modalAprobacion.querySelector('[data-aprobacion-confirmar]').addEventListener('click', () => {
            if (!modalAprobacion.open) return;
            modalAprobacion.close();
            aprobarSeleccionado();
        });
        window.addEventListener('pageshow', event => {
            if (event.persisted && botonActivo) botonActivo.disabled = false;
        });
    }

    const normalizar = texto => texto.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();

    const selectorEmpresa = document.getElementById('empresa_titular_id');
    if (selectorEmpresa && window.jQuery?.fn.select2) {
        window.jQuery(selectorEmpresa).select2({
            width: '100%',
            placeholder: selectorEmpresa.options[0].text,
            language: { noResults: () => 'No se encontraron empresas.' },
        });
    }

    document.querySelectorAll('[data-tarifario-lista]').forEach(lista => {
        const controles = lista.querySelector('[data-tarifario-controles]');
        if (!controles) return;

        const buscador = controles.querySelector('[data-tarifario-busqueda]');
        const resultados = controles.querySelector('[data-tarifario-resultados]');
        const sinResultados = lista.querySelector('[data-tarifario-sin-resultados]');
        const filas = Array.from(lista.querySelectorAll('[data-tarifario-fila]'), fila => ({
            fila,
            texto: normalizar(fila.dataset.tarifarioFila),
        }));

        function filtrar() {
            const palabras = normalizar(buscador.value).split(/\s+/).filter(Boolean);
            let coincidencias = 0;
            for (const { fila, texto } of filas) {
                const coincide = palabras.every(palabra => texto.includes(palabra));
                fila.hidden = !coincide;
                if (coincide) coincidencias++;
            }
            resultados.textContent = `Mostrando ${coincidencias} de ${filas.length}`;
            sinResultados.hidden = coincidencias > 0;
        }

        buscador.addEventListener('input', filtrar);
        filtrar();
        controles.hidden = false;
    });

    const filtros = document.getElementById('tarifaFiltersForm');
    if (filtros) {
        const estado = document.getElementById('tarifaAutoStatus');
        let temporizador;

        function programarEnvio(demora) {
            if (!estado.hidden) return;
            clearTimeout(temporizador);
            temporizador = setTimeout(() => filtros.requestSubmit(), demora);
        }

        filtros.querySelectorAll('select').forEach(campo => {
            campo.addEventListener('change', () => programarEnvio(180));
        });
        filtros.querySelector('input[type="search"]').addEventListener('input', () => programarEnvio(650));
        filtros.addEventListener('submit', () => {
            clearTimeout(temporizador);
            estado.hidden = false;
        });
        window.addEventListener('pageshow', () => { estado.hidden = true; });
    }
})();
