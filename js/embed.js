(function () {
    'use strict';

    // The loader derives all asset URLs from its own script tag, so it can be hosted anywhere.
    var current = document.currentScript;
    if (!current) return;

    var baseUrl = current.src.replace(/\/js\/embed\.js(?:\?.*)?$/, "");
    var cssUrl = baseUrl + '/css/ninjareviews.css';
    var widgetScriptUrl = baseUrl + '/js/ninjareviews.js';
    // Allow a custom API endpoint while keeping the standard same-installation endpoint as default.
    var apiUrl = current.getAttribute('data-api-url') || (baseUrl + '/reviews.php');
    var reviewsContainer = document.createElement('section');
    reviewsContainer.className = 'ninjareviews';
    current.parentNode.insertBefore(reviewsContainer, current.nextSibling);

    function loadScript(src, callback) {
        // Load the widget after the container and stylesheet exist; async=false preserves this order.
        var script = document.createElement('script');
        script.src = src;
        script.async = false;
        script.onload = callback;
        script.onerror = function () {
            console.error('[NinjaReviews] Errore nel caricamento dello script:', src);
        };
        document.head.appendChild(script);
    }

    function loadCss() {
        // Multiple embed tags on one page should share a single stylesheet element.
        if (document.querySelector('link[data-ninjareviews-css]')) return;
        var link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = cssUrl;
        link.setAttribute('data-ninjareviews-css', 'true');
        document.head.appendChild(link);
    }
    loadCss();
    loadScript(widgetScriptUrl, function () {
        console.log('[NinjaReviews] ninjareviews.js caricato');
        console.log('[NinjaReviews] NinjaReviews:', window.NinjaReviews);

        if (window.NinjaReviews && typeof window.NinjaReviews.init === 'function') {
            window.NinjaReviews.init({ apiUrl: apiUrl });
        } else {
            console.error('[NinjaReviews] NinjaReviews.init non disponibile');
        }
    });
}());
