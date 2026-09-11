/* NinjaReviews frontend widget. */
(function () {
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

    function mergeOptions(customOptions) {
        return Object.assign({}, DEFAULTS, customOptions || {});
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function safeUrl(value) {
        try {
            const url = new URL(String(value || ''), window.location.href);
            if (url.protocol === 'http:' || url.protocol === 'https:') return url.href;
        } catch (error) {
            return '';
        }
        return '';
    }

    function createStars(rating) {
        const value = Math.max(0, Math.min(5, Number(rating) || 0));
        return '★'.repeat(Math.round(value)) + '☆'.repeat(5 - Math.round(value));
    }

    function getInitials(name) {
        const parts = String(name || '').trim().split(/\s+/).filter(Boolean);
        if (!parts.length) return '?';
        return parts.slice(0, 2).map((part) => part.charAt(0).toUpperCase()).join('');
    }

    function renderReview(review, options) {
        const authorName = escapeHtml(review.author_name || 'Google user');
        const authorUrl = safeUrl(review.author_url);
        const authorPhoto = safeUrl(review.author_photo);
        const reviewUrl = safeUrl(review.review_url);
        const date = escapeHtml(review.relative_time || review.time || '');
        const text = escapeHtml(review.text || '');
        const rating = Number(review.rating) || 0;

        const photoHtml = options.showAuthorPhoto
            ? (authorPhoto
                ? `<img class="ninjareviews__author-photo" src="${escapeHtml(authorPhoto)}" alt="${authorName}" loading="lazy" referrerpolicy="no-referrer">`
                : `<span class="ninjareviews__author-photo ninjareviews__author-photo--fallback" aria-hidden="true">${escapeHtml(getInitials(review.author_name))}</span>`)
            : '';

        const nameHtml = authorUrl
            ? `<a href="${escapeHtml(authorUrl)}" target="_blank" rel="noopener noreferrer">${authorName}</a>`
            : `<span>${authorName}</span>`;

        const googleLinkHtml = options.showGoogleLink && reviewUrl
            ? `<a class="ninjareviews__google-link" href="${escapeHtml(reviewUrl)}" target="_blank" rel="noopener noreferrer">Leggi su Google</a>`
            : '';

        const dateHtml = options.showDate && date
            ? `<time class="ninjareviews__date">${date}</time>`
            : '';

        return `
            <article class="ninjareviews__card">
                <header class="ninjareviews__header">
                    ${photoHtml}
                    <div class="ninjareviews__author">
                        <div class="ninjareviews__author-name">${nameHtml}</div>
                        <div class="ninjareviews__rating" aria-label="Valutazione: ${rating} su 5">${createStars(rating)}</div>
                    </div>
                </header>
                <div class="ninjareviews__meta">${dateHtml}</div>
                <p class="ninjareviews__text">${text}</p>
                ${googleLinkHtml}
            </article>`;
    }

    async function loadReviews(apiUrl) {
        const response = await fetch(apiUrl, {
            method: 'GET',
            cache: 'no-store',
            credentials: 'omit',
            headers: { Accept: 'application/json' }
        });

        if (!response.ok) {
            throw new Error(`NinjaReviews API error: ${response.status}`);
        }

        const data = await response.json();
        if (!data || data.success !== true || !Array.isArray(data.reviews)) {
            throw new Error('NinjaReviews returned an invalid response.');
        }

        return data.reviews;
    }

    function resolveElements(root, selector) {
        if (!root) return [];
        if (typeof selector === 'string') {
            if (root.matches && root.matches(selector)) return [root];
            return Array.from(root.querySelectorAll(selector));
        }
        return [];
    }

    function createSlider(container, reviews, options) {
        if (!reviews.length) {
            container.innerHTML = '<p class="ninjareviews__empty">Nessuna recensione disponibile.</p>';
            return;
        }

        container.innerHTML = `
            <div class="ninjareviews__slider" data-ninjareviews-slider>
                <button class="ninjareviews__arrow ninjareviews__arrow--prev" type="button" aria-label="Recensioni precedenti">‹</button>
                <div class="ninjareviews__viewport">
                    <div class="ninjareviews__track"></div>
                </div>
                <button class="ninjareviews__arrow ninjareviews__arrow--next" type="button" aria-label="Recensioni successive">›</button>
                <div class="ninjareviews__dots" role="tablist" aria-label="Pagine recensioni"></div>
                <div class="ninjareviews__attribution">Recensioni da Google</div>
            </div>`;

        const slider = container.querySelector('[data-ninjareviews-slider]');
        const viewport = slider.querySelector('.ninjareviews__viewport');
        const track = slider.querySelector('.ninjareviews__track');
        const dots = slider.querySelector('.ninjareviews__dots');
        const prev = slider.querySelector('.ninjareviews__arrow--prev');
        const next = slider.querySelector('.ninjareviews__arrow--next');

        track.innerHTML = reviews.map((review) => renderReview(review, options)).join('');

        let index = 0;
        let perView = options.slidesToShow;
        let timer = null;
        let startX = null;

        function updatePerView() {
            perView = window.innerWidth <= options.responsiveBreakpoint
                ? options.mobileSlidesToShow
                : options.slidesToShow;
            perView = Math.max(1, Math.min(perView, reviews.length));
        }

        function pageCount() {
            return Math.max(1, Math.ceil(reviews.length / perView));
        }

        function maxIndex() {
            return Math.max(0, reviews.length - perView);
        }

        function renderDots() {
            dots.innerHTML = '';
            for (let i = 0; i < pageCount(); i += 1) {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'ninjareviews__dot';
                button.setAttribute('aria-label', `Vai alla pagina ${i + 1}`);
                button.addEventListener('click', () => goTo(i * perView));
                dots.appendChild(button);
            }
        }

        function update() {
            index = Math.max(0, Math.min(index, maxIndex()));
            const offset = (100 / perView) * index;
            track.style.transform = `translateX(-${offset}%)`;
            const activePage = Math.floor(index / perView);
            Array.from(dots.children).forEach((dot, dotIndex) => {
                dot.classList.toggle('is-active', dotIndex === activePage);
            });
            prev.disabled = index === 0;
            next.disabled = index >= maxIndex();
        }

        function goTo(nextIndex) {
            index = nextIndex > maxIndex() ? 0 : nextIndex;
            update();
        }

        function startAutoplay() {
            if (!options.autoplay || reviews.length <= perView) return;
            stopAutoplay();
            timer = window.setInterval(() => goTo(index + perView), options.autoplayDelay);
        }

        function stopAutoplay() {
            if (timer !== null) {
                window.clearInterval(timer);
                timer = null;
            }
        }

        prev.addEventListener('click', () => {
            index = index - perView < 0 ? maxIndex() : index - perView;
            update();
        });
        next.addEventListener('click', () => goTo(index + perView));

        if (options.swipeEnabled) {
            viewport.addEventListener('touchstart', (event) => {
                startX = event.touches[0]?.clientX ?? null;
                stopAutoplay();
            }, { passive: true });
            viewport.addEventListener('touchend', (event) => {
                if (startX === null) return;
                const endX = event.changedTouches[0]?.clientX ?? startX;
                const delta = endX - startX;
                startX = null;
                if (Math.abs(delta) >= options.swipeThreshold) {
                    if (delta < 0) next.click();
                    else prev.click();
                }
                startAutoplay();
            }, { passive: true });
        }

        slider.addEventListener('mouseenter', stopAutoplay);
        slider.addEventListener('mouseleave', startAutoplay);
        window.addEventListener('resize', () => {
            const oldPerView = perView;
            updatePerView();
            if (oldPerView !== perView) {
                renderDots();
                index = Math.floor(index / perView) * perView;
                update();
                startAutoplay();
            }
        });

        updatePerView();
        renderDots();
        update();
        startAutoplay();
    }

    async function init(element, customOptions) {
        const options = mergeOptions(customOptions);
        const containers = typeof element === 'string'
            ? Array.from(document.querySelectorAll(element))
            : (element ? [element] : resolveElements(document, options.selector));

        await Promise.all(containers.map(async (container) => {
            try {
                const apiUrl = container.dataset.apiUrl || options.apiUrl;
                const reviews = await loadReviews(apiUrl);
                createSlider(container, reviews, options);
            } catch (error) {
                console.error('[NinjaReviews]', error);
                container.innerHTML = '<p class="ninjareviews__error">Non è stato possibile caricare le recensioni.</p>';
            }
        }));
    }

    window.NinjaReviews = {
        init,
        defaults: Object.assign({}, DEFAULTS)
    };

    function autoInit() {
        init();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', autoInit, { once: true });
    } else {
        autoInit();
    }
})();
