<meta name="google" content="notranslate">
<meta name="googlebot" content="notranslate">
<meta http-equiv="Content-Language" content="es">
<style>
    .notranslate,
    .skiptranslate,
    #google_translate_element,
    .goog-te-banner-frame,
    .goog-te-menu-frame,
    .goog-te-balloon-frame,
    .VIpgJd-ZVi9od-aZ2wEe-wOHMyf,
    .VIpgJd-ZVi9od-ORHb-OEVmcd {
        display: none !important;
        visibility: hidden !important;
    }

    body {
        top: 0 !important;
    }
    
</style>
<script>
    (function () {
        const applyNoTranslate = function () {
            document.documentElement.setAttribute('translate', 'no');
            document.documentElement.classList.add('notranslate');

            if (document.body) {
                document.body.setAttribute('translate', 'no');
                document.body.classList.add('notranslate');
            }
        };

        const blockDevToolsShortcuts = function (event) {
            const key = String(event.key || '').toLowerCase();
            const ctrlOrMeta = event.ctrlKey || event.metaKey;

            if (key === 'f12') {
                event.preventDefault();
                event.stopPropagation();
                return false;
            }

            if (ctrlOrMeta && event.shiftKey && ['i', 'j', 'c'].includes(key)) {
                event.preventDefault();
                event.stopPropagation();
                return false;
            }

            if (ctrlOrMeta && ['u'].includes(key)) {
                event.preventDefault();
                event.stopPropagation();
                return false;
            }

            return true;
        };

        const cleanupTranslateArtifacts = function () {
            applyNoTranslate();

            document.querySelectorAll(
                '.skiptranslate, #google_translate_element, .goog-te-banner-frame, .goog-te-menu-frame, .goog-te-balloon-frame, .VIpgJd-ZVi9od-aZ2wEe-wOHMyf, .VIpgJd-ZVi9od-ORHb-OEVmcd'
            ).forEach(function (node) {
                node.remove();
            });

            if (document.body && document.body.style.top) {
                document.body.style.top = '0px';
            }
        };

        applyNoTranslate();

        document.addEventListener('DOMContentLoaded', cleanupTranslateArtifacts);
        document.addEventListener('keydown', blockDevToolsShortcuts, true);
        document.addEventListener('contextmenu', function (event) {
            event.preventDefault();
        });

        window.addEventListener('load', cleanupTranslateArtifacts);
        window.addEventListener('resize', cleanupTranslateArtifacts);

        const observer = new MutationObserver(function () {
            cleanupTranslateArtifacts();
        });

        document.addEventListener('DOMContentLoaded', function () {
            observer.observe(document.documentElement, {
                childList: true,
                subtree: true,
                attributes: true,
                attributeFilter: ['class', 'style', 'translate'],
            });
        });
    })();
</script>
