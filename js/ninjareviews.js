(function (window, document) {
    'use strict';

    const DEFAULTS = {
        selector: '.ninjareviews',
        apiUrl: './reviews.php',
        slidesToShow: 3,
        mobileSlidesToShow: 1,
        responsiveBreakpoint: 768,
        autoplay: true,
        autoplayDelay: 4000,
        swipeEnabled: true,
        swipeThreshold: 50,
        showAuthorPhoto: true,
        showDate: true,
        showGoogleLink: true
    };

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value == null ? '' : String(value);
        return div.innerHTML;
    }

    function safeUrl(value) {
        if (!value) return '';
        try {
            const url = new URL(value, window.location.href);
            if (url.protocol === 'https:' || url.protocol === 'http:') {
                return url.href;
            }
        } catch (_) {}
        return '';
    }

    function stars(rating) {
        const n = Math.max(0, Math.min(5, Number(rating) || 0));
        let html = '';
        for (let i = 0; i < n; i++) {
            html += '<span class="nr-star" aria-hidden="true">⭐</span>';
        }
        return html;
    }

    function card(review, settings) {
        const authorName = escapeHtml(review.author_name);
        const text = escapeHtml(review.text);
        const photo = safeUrl(review.author_photo);
        const authorUrl = safeUrl(review.author_url);
        const reviewUrl = safeUrl(review.review_url);

        const photoHtml = settings.showAuthorPhoto && photo
            ? '<img class="nr-author-photo" src="' + escapeHtml(photo) +
              '" alt="Foto di ' + authorName + '" loading="lazy">'
            : '';

        const authorNameHtml = authorUrl
            ? '<a href="' + escapeHtml(authorUrl) + '" target="_blank" rel="noopener noreferrer">' +
              authorName + '</a>'
            : '<span>' + authorName + '</span>';

        const googleLinkHtml = settings.showGoogleLink && reviewUrl
            ? '<a class="nr-google-link" href="' + escapeHtml(reviewUrl) +
              '" target="_blank" rel="noopener noreferrer">Leggi su Google</a>'
            : '';

        const dateHtml = settings.showDate && review.relative_time
            ? '<span class="nr-review-date">' + escapeHtml(review.relative_time) + '</span>'
            : '';

        return '' +
            '<article class="nr-slide">' +
              '<div class="nr-card">' +
                '<div class="nr-quote" aria-hidden="true">&#8220;</div>' +
                '<div class="nr-review-text">' +
                  '<p>' + text + '</p>' +
                '</div>' +
                '<div class="nr-footer">' +
                  '<div class="nr-author">' +
                    '<div class="nr-author-photo-wrap">' + photoHtml + '</div>' +
                    '<div class="nr-author-info">' +
                      '<div class="nr-author-name">' + authorNameHtml + '</div>' +
                      '<div class="nr-rating" aria-label="' + escapeHtml(review.rating) + ' stelle">' +
                        stars(review.rating) +
                      '</div>' +
                    '</div>' +
                  '</div>' +
                  '<div class="nr-meta">' + dateHtml + googleLinkHtml + '</div>' +
                '</div>' +
              '</div>' +
            '</article>';
    }

    function createWidget(container, userOptions) {
        const settings = Object.assign({}, DEFAULTS, userOptions || {});
        let currentIndex = 0;
        let timer = null;
        let touchStartX = 0;
        let touchStartY = 0;

        container.classList.add('nr-widget');
        container.innerHTML =
            '<div class="nr-loading" role="status">Caricamento recensioni…</div>';

        function getSlides() {
            return Array.from(container.querySelectorAll('.nr-slide'));
        }

        function slidesToShow() {
            return window.innerWidth <= settings.responsiveBreakpoint
                ? settings.mobileSlidesToShow
                : settings.slidesToShow;
        }

        function maxIndex() {
            return Math.max(getSlides().length - slidesToShow(), 0);
        }

        function update() {
            const track = container.querySelector('.nr-track');
            if (!track) return;

            const slides = getSlides();
            const max = maxIndex();

            currentIndex = Math.max(0, Math.min(currentIndex, max));

            const first = slides[0];
            if (!first) return;

            const width = first.getBoundingClientRect().width;
            track.style.transform = 'translate3d(' + (-currentIndex * width) + 'px, 0, 0)';

            container.querySelectorAll('.nr-dot').forEach(function (dot, index) {
                dot.classList.toggle('is-active', index === currentIndex);
                dot.setAttribute('aria-current', index === currentIndex ? 'true' : 'false');
            });
        }

        function next() {
            currentIndex = currentIndex >= maxIndex() ? 0 : currentIndex + 1;
            update();
        }

        function prev() {
            currentIndex = currentIndex <= 0 ? maxIndex() : currentIndex - 1;
            update();
        }

        function restartAutoplay() {
            if (!settings.autoplay) return;
            clearInterval(timer);
            timer = setInterval(next, settings.autoplayDelay);
        }

        function render(reviews) {
            const slidesHtml = reviews.map(function (review) {
                return card(review, settings);
            }).join('');

            container.innerHTML =
                '<div class="nr-viewport">' +
                  '<div class="nr-track">' + slidesHtml + '</div>' +
                  '<button class="nr-nav nr-prev" type="button" aria-label="Recensione precedente">' + '\u2039' + '</button>' +
                  '<button class="nr-nav nr-next" type="button" aria-label="Recensione successiva">' + '\u203A' + '</button>' +
                '</div>' +
                '<div class="nr-dots" role="tablist" aria-label="Navigazione recensioni"></div>' +
                '<div class="nr-attribution">Recensioni da Google</div>';

            const dots = container.querySelector('.nr-dots');
            const count = maxIndex() + 1;

            for (let i = 0; i < count; i++) {
                const dot = document.createElement('button');
                dot.type = 'button';
                dot.className = 'nr-dot' + (i === 0 ? ' is-active' : '');
                dot.setAttribute('aria-label', 'Vai alla recensione ' + (i + 1));
                dot.addEventListener('click', function () {
                    currentIndex = i;
                    update();
                    restartAutoplay();
                });
                dots.appendChild(dot);
            }

            container.querySelector('.nr-prev').addEventListener('click', function () {
                prev();
                restartAutoplay();
            });

            container.querySelector('.nr-next').addEventListener('click', function () {
                next();
                restartAutoplay();
            });

            if (settings.swipeEnabled) {
                const viewport = container.querySelector('.nr-viewport');

                viewport.addEventListener('touchstart', function (event) {
                    const touch = event.changedTouches[0];
                    touchStartX = touch.clientX;
                    touchStartY = touch.clientY;
                }, { passive: true });

                viewport.addEventListener('touchend', function (event) {
                    const touch = event.changedTouches[0];
                    const diffX = touch.clientX - touchStartX;
                    const diffY = touch.clientY - touchStartY;

                    if (Math.abs(diffX) < settings.swipeThreshold ||
                        Math.abs(diffY) > Math.abs(diffX)) {
                        return;
                    }

                    diffX > 0 ? prev() : next();
                    restartAutoplay();
                }, { passive: true });
            }

            container.addEventListener('mouseenter', function () {
                clearInterval(timer);
            });

            container.addEventListener('mouseleave', restartAutoplay);

            window.addEventListener('resize', update);
            update();
            restartAutoplay();
        }

        function showError(message) {
            container.innerHTML =
                '<div class="nr-error" role="alert">' +
                escapeHtml(message || 'Impossibile caricare le recensioni.') +
                '</div>';
        }

        fetch(settings.apiUrl, {
            method: 'GET',
            headers: { 'Accept': 'application/json' }
        })
        .then(function (response) {
            return response.json().then(function (data) {
                if (!response.ok || !data.success) {
                    throw new Error(data.error && data.error.message
                        ? data.error.message
                        : 'Errore nel caricamento delle recensioni.');
                }
                return data;
            });
        })
        .then(function (data) {
            render(Array.isArray(data.reviews) ? data.reviews : []);
        })
        .catch(function (error) {
            console.error('[NinjaReviews]', error);
            showError('Non è stato possibile caricare le recensioni.');
        });
    }

    window.NinjaReviews = {
        init: function (options) {
            const settings = Object.assign({}, DEFAULTS, options || {});
            const elements = document.querySelectorAll(settings.selector);

            elements.forEach(function (element) {
                createWidget(element, settings);
            });
        }
    };
})(window, document);
