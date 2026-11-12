(function () {
    'use strict';

    function initializeMenus() {
        const $ = window.jQuery;
        if (!$ || !$.fn.Treeview) return;

        // AdminLTE normally waits for window.load, including every image.
        // Initialize now and remove its later initializer to avoid duplicate clicks.
        $(window).off('load.lte.treeview');
        $('[data-widget="treeview"]').each(function () {
            const menu = $(this);
            if (!menu.data('lte.treeview')) menu.Treeview('init');
        });
    }

    initializeMenus();
    document.addEventListener('livewire:navigated', initializeMenus);
}());
