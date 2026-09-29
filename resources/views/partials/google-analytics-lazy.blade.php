<script>
    window.dataLayer = window.dataLayer || [];
    window.gtag = window.gtag || function () { window.dataLayer.push(arguments); };
    window.gtag('js', new Date());
    window.gtag('config', @json($trackingId));

    window.addEventListener('load', function () {
        window.setTimeout(function () {
            if (document.querySelector('script[data-lazy-gtag]')) return;
            var script = document.createElement('script');
            script.async = true;
            script.dataset.lazyGtag = 'true';
            script.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(@json($trackingId));
            document.head.appendChild(script);
        }, 0);
    }, { once: true });
</script>
