(() => {
    'use strict';

    document.querySelectorAll('[data-tarifario-year-filter]').forEach(container => {
        const year = container.querySelector('[name="tarifario_anio"]');
        const tarifario = container.querySelector('[name="tarifario_contrato_id"]');
        const emptyMessage = container.querySelector('#tarifario-year-empty');
        if (!year || !tarifario) return;

        const placeholder = tarifario.options[0].cloneNode(true);
        const options = [...tarifario.options].slice(1).map(option => option.cloneNode(true));
        const catalog = JSON.parse(document.getElementById('servicios-contrato-config')?.textContent || '{}');

        function syncServices() {
            const serviceYear = tarifario.value ? tarifario.selectedOptions[0].dataset.anio : year.value;
            const services = catalog[serviceYear] || [];
            document.querySelectorAll('[data-template-link]').forEach(link => {
                const url = new URL(link.href);
                url.searchParams.set('anio', serviceYear);
                if (tarifario.value) url.searchParams.set('tarifario_id', tarifario.value);
                else url.searchParams.delete('tarifario_id');
                link.href = url.toString();
            });
            document.querySelectorAll('[data-services-link]').forEach(link => {
                const url = new URL(link.href);
                url.searchParams.set('anio', serviceYear);
                link.href = url.toString();
            });
            const select = document.getElementById('servicio');
            if (select) {
                const selected = select.value;
                select.replaceChildren(new Option('Seleccione…', ''), ...services.map(name => new Option(name, name)));
                select.value = services.includes(selected) ? selected : '';
            }
            const list = document.getElementById('year-services-list');
            if (list) list.replaceChildren(...(services.length ? services : ['No hay servicios registrados para este año.']).map(name => {
                const item = document.createElement('li'); item.textContent = name; return item;
            }));
            container.dispatchEvent(new CustomEvent('tarifario-services-change', { bubbles: true, detail: { year: serviceYear, services } }));
        }

        function filterByYear() {
            const selected = tarifario.value;
            const matching = options.filter(option => option.dataset.anio === year.value);
            tarifario.replaceChildren(placeholder.cloneNode(true), ...matching.map(option => option.cloneNode(true)));
            tarifario.value = matching.some(option => option.value === selected) ? selected : '';
            if (emptyMessage) emptyMessage.hidden = matching.length > 0;
            if (selected !== tarifario.value) tarifario.dispatchEvent(new Event('change', { bubbles: true }));
            syncServices();
        }

        year.addEventListener('change', filterByYear);
        tarifario.addEventListener('change', syncServices);
        const refresh = container.querySelector('[data-refresh-services]');
        const serviceStatus = container.querySelector('[data-services-status]');
        refresh?.addEventListener('click', async () => {
            const requestedYear = tarifario.value ? tarifario.selectedOptions[0].dataset.anio : year.value;
            refresh.disabled = true;
            serviceStatus.textContent = 'Actualizando servicios…';
            try {
                const url = new URL(container.querySelector('[data-services-link]').href);
                const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                if (!response.ok) throw new Error('Unable to load services');
                const data = await response.json();
                catalog[requestedYear] = data.servicios;
                syncServices();
                serviceStatus.textContent = 'Lista de servicios actualizada.';
            } catch {
                serviceStatus.textContent = 'No se pudo actualizar la lista. Inténtalo nuevamente.';
            } finally { refresh.disabled = false; }
        });
        filterByYear();
    });
})();
