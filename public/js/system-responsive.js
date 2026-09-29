(function () {
    'use strict';

    const TABLE_SELECTOR = 'table:not([data-no-responsive])';
    const MOBILE_QUERY = '(max-width: 767.98px)';

    function isMobileViewport() {
        return window.matchMedia(MOBILE_QUERY).matches;
    }

    function rememberAttribute(element, name) {
        const key = 'responsiveOriginal' + name.replace(/(^|-)([a-z])/g, function (_, separator, letter) {
            return letter.toUpperCase();
        });

        if (!(key in element.dataset)) {
            element.dataset[key] = element.hasAttribute(name) ? element.getAttribute(name) : '__missing__';
        }
    }

    function restoreAttribute(element, name) {
        const key = 'responsiveOriginal' + name.replace(/(^|-)([a-z])/g, function (_, separator, letter) {
            return letter.toUpperCase();
        });
        const value = element.dataset[key];

        if (typeof value === 'undefined') {
            return;
        }

        if (value === '__missing__') {
            element.removeAttribute(name);
        } else {
            element.setAttribute(name, value);
        }

        delete element.dataset[key];
    }

    function tableLabel(table) {
        const caption = table.querySelector('caption');
        const card = table.closest('.card');
        const title = card ? card.querySelector('.card-title') : null;

        return (caption?.textContent || title?.textContent || 'Tabla de datos').trim();
    }

    function headerLabels(table) {
        const headerRow = table.querySelector('thead tr:last-child');

        if (!headerRow) {
            return [];
        }

        return Array.from(headerRow.cells).map(function (cell, index) {
            const text = cell.textContent.replace(/\s+/g, ' ').trim();
            return text || (index === 0 ? 'Seleccionar' : 'Dato');
        });
    }

    function labelRows(table) {
        const labels = headerLabels(table);
        if (!labels.length) {
            return;
        }

        table.classList.add('responsive-card-table');

        table.querySelectorAll('tbody tr').forEach(function (row) {
            let columnIndex = 0;
            const cells = Array.from(row.children).filter(function (cell) {
                return cell.matches('td, th');
            });

            cells.forEach(function (cell) {
                const colspan = Math.max(Number(cell.getAttribute('colspan')) || 1, 1);

                if (colspan >= labels.length || cells.length === 1) {
                    cell.classList.add('responsive-full-cell');
                    cell.removeAttribute('data-label');
                } else {
                    cell.classList.remove('responsive-full-cell');
                    cell.setAttribute('data-label', labels[columnIndex] || 'Dato');
                }

                columnIndex += colspan;
            });
        });
    }

    function prepareTableContainer(table) {
        const existingShell = table.closest('.responsive-table-shell, .table-responsive, .dataTables_scrollBody');

        if (existingShell) {
            rememberAttribute(existingShell, 'role');
            rememberAttribute(existingShell, 'aria-label');
            rememberAttribute(existingShell, 'tabindex');
            existingShell.dataset.responsiveEnhanced = 'true';
            existingShell.classList.add('responsive-card-shell');
            existingShell.setAttribute('role', 'region');
            existingShell.setAttribute('aria-label', tableLabel(table));
            existingShell.setAttribute('tabindex', '0');
            return;
        }

        const shell = document.createElement('div');
        shell.className = 'responsive-table-shell responsive-card-shell';
        shell.dataset.responsiveGenerated = 'true';
        shell.setAttribute('role', 'region');
        shell.setAttribute('aria-label', tableLabel(table));
        shell.setAttribute('tabindex', '0');

        table.parentNode.insertBefore(shell, table);
        shell.appendChild(table);
    }

    function restoreDesktopTables() {
        document.querySelectorAll('table.responsive-card-table').forEach(function (table) {
            table.classList.remove('responsive-card-table');
            table.querySelectorAll('[data-label], .responsive-full-cell').forEach(function (cell) {
                cell.removeAttribute('data-label');
                cell.classList.remove('responsive-full-cell');
            });
        });

        document.querySelectorAll('[data-responsive-generated="true"]').forEach(function (shell) {
            const parent = shell.parentNode;
            if (!parent) {
                return;
            }

            while (shell.firstChild) {
                parent.insertBefore(shell.firstChild, shell);
            }
            shell.remove();
        });

        document.querySelectorAll('[data-responsive-enhanced="true"]').forEach(function (shell) {
            shell.classList.remove('responsive-card-shell');
            restoreAttribute(shell, 'role');
            restoreAttribute(shell, 'aria-label');
            restoreAttribute(shell, 'tabindex');
            delete shell.dataset.responsiveEnhanced;
        });
    }

    function enhanceTable(table) {
        if (
            !isMobileViewport() ||
            table.matches('[data-no-responsive], [data-responsive-mode="scroll"]') ||
            table.closest('.fc, .fc-view-harness, [data-no-responsive]')
        ) {
            return;
        }

        labelRows(table);
        prepareTableContainer(table);
    }

    function enhanceTables(root) {
        if (!isMobileViewport()) {
            return;
        }

        if (!root || root.nodeType !== Node.ELEMENT_NODE && root.nodeType !== Node.DOCUMENT_NODE) {
            return;
        }

        if (root.matches?.(TABLE_SELECTOR)) {
            enhanceTable(root);
        }

        root.querySelectorAll?.(TABLE_SELECTOR).forEach(enhanceTable);

        const parentTable = root.closest?.(TABLE_SELECTOR);
        if (parentTable) {
            labelRows(parentTable);
        }
    }

    function normalizeMenuSearchText(value) {
        return String(value || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/\s+/g, ' ')
            .toLocaleLowerCase();
    }

    function rememberMenuFilterState(element, key, value) {
        if (!(key in element.dataset)) {
            element.dataset[key] = value;
        }
    }

    function filterSidebarMenuList(list, query, inheritedMatch) {
        const filtering = query.length > 0;
        const children = Array.from(list.children);
        let currentHeader = null;
        let currentSectionHasMatch = false;
        let anyVisible = false;

        function finishHeaderSection() {
            if (!currentHeader) {
                return;
            }

            rememberMenuFilterState(currentHeader, 'siopFilterDisplay', currentHeader.style.display);
            currentHeader.style.display = filtering && !currentSectionHasMatch
                ? 'none'
                : (filtering ? '' : currentHeader.dataset.siopFilterDisplay);

            if (!filtering) {
                delete currentHeader.dataset.siopFilterDisplay;
            }
        }

        children.forEach(function (child) {
            if (child.classList.contains('nav-header')) {
                finishHeaderSection();
                currentHeader = child;
                currentSectionHasMatch = false;
                return;
            }

            let visible = true;

            if (child.matches('li.nav-item')) {
                visible = filterSidebarMenuItem(child, query, inheritedMatch);
            } else if (!child.querySelector('[data-sidebar-menu-filter]')) {
                visible = !filtering || inheritedMatch;
                rememberMenuFilterState(child, 'siopFilterDisplay', child.style.display);
                child.style.display = filtering ? (visible ? '' : 'none') : child.dataset.siopFilterDisplay;

                if (!filtering) {
                    delete child.dataset.siopFilterDisplay;
                }
            }

            if (visible) {
                currentSectionHasMatch = true;
                anyVisible = true;
            }
        });

        finishHeaderSection();
        return anyVisible;
    }

    function filterSidebarMenuItem(item, query, inheritedMatch) {
        const filtering = query.length > 0;
        const link = item.querySelector(':scope > .nav-link');
        const label = normalizeMenuSearchText(link ? link.textContent : item.textContent);
        const ownMatch = inheritedMatch || !filtering || label.includes(query);
        const submenu = item.querySelector(':scope > .nav-treeview');
        let childrenMatch = false;

        rememberMenuFilterState(item, 'siopFilterDisplay', item.style.display);

        if (submenu) {
            rememberMenuFilterState(submenu, 'siopFilterDisplay', submenu.style.display);
            rememberMenuFilterState(item, 'siopFilterMenuOpen', item.classList.contains('menu-open') ? 'true' : 'false');
            rememberMenuFilterState(item, 'siopFilterMenuOpening', item.classList.contains('menu-is-opening') ? 'true' : 'false');
            childrenMatch = filterSidebarMenuList(submenu, query, filtering && ownMatch);

            if (filtering && childrenMatch) {
                item.classList.add('menu-open');
                item.classList.remove('menu-is-opening');
                submenu.style.display = 'block';
            } else if (filtering) {
                item.classList.remove('menu-open', 'menu-is-opening');
                submenu.style.display = 'none';
            } else {
                item.classList.toggle('menu-open', item.dataset.siopFilterMenuOpen === 'true');
                item.classList.toggle('menu-is-opening', item.dataset.siopFilterMenuOpening === 'true');
                submenu.style.display = submenu.dataset.siopFilterDisplay;
                delete submenu.dataset.siopFilterDisplay;
                delete item.dataset.siopFilterMenuOpen;
                delete item.dataset.siopFilterMenuOpening;
            }
        }

        const visible = ownMatch || childrenMatch;
        item.style.display = filtering ? (visible ? '' : 'none') : item.dataset.siopFilterDisplay;

        if (!filtering) {
            delete item.dataset.siopFilterDisplay;
        }

        return visible;
    }

    function setupSidebarMenuFilter() {
        document.addEventListener('input', function (event) {
            const input = event.target.closest('[data-sidebar-menu-filter] .form-control-sidebar');
            if (!input) {
                return;
            }

            const menu = document.querySelector('.main-sidebar .nav-sidebar');
            if (!menu) {
                return;
            }

            filterSidebarMenuList(menu, normalizeMenuSearchText(input.value.trim()), false);
        });

        document.addEventListener('click', function (event) {
            const button = event.target.closest('[data-sidebar-menu-filter-focus]');
            if (button) {
                button.closest('[data-sidebar-menu-filter]')?.querySelector('.form-control-sidebar')?.focus();
            }
        });
    }

    function start() {
        setupSidebarMenuFilter();

        if (isMobileViewport()) {
            enhanceTables(document);
        }

        const content = document.querySelector('.content-wrapper, main') || document.body;
        if (!content || !window.MutationObserver) {
            return;
        }

        const observer = new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                mutation.addedNodes.forEach(enhanceTables);
            });
        });

        observer.observe(content, { childList: true, subtree: true });

        const mobileViewport = window.matchMedia(MOBILE_QUERY);
        const handleViewportChange = function (event) {
            if (event.matches) {
                enhanceTables(document);
            } else {
                restoreDesktopTables();
            }
        };

        if (typeof mobileViewport.addEventListener === 'function') {
            mobileViewport.addEventListener('change', handleViewportChange);
        } else if (typeof mobileViewport.addListener === 'function') {
            mobileViewport.addListener(handleViewportChange);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start, { once: true });
    } else {
        start();
    }

    document.addEventListener('livewire:navigated', function () {
        enhanceTables(document);
    });
})();
