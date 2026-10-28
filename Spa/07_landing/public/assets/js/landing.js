'use strict';

const applyFallback = (image) => {
    const fallback = image.dataset.fallback;

    if (!fallback || image.dataset.fallbackApplied === 'true') {
        return;
    }

    image.dataset.fallbackApplied = 'true';
    image.src = fallback;
};

document.querySelectorAll('img[data-fallback]').forEach((image) => {
    image.addEventListener('error', () => applyFallback(image));

    if (image.complete && image.naturalWidth === 0) {
        applyFallback(image);
    }
});

const revealItems = document.querySelectorAll('[data-reveal]');
const reduceMotionQuery = typeof window.matchMedia === 'function'
    ? window.matchMedia('(prefers-reduced-motion: reduce)')
    : null;
const prefersReducedMotion = reduceMotionQuery ? reduceMotionQuery.matches : false;

if (revealItems.length > 0 && 'IntersectionObserver' in window && !prefersReducedMotion) {
    const revealObserver = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) {
                return;
            }

            entry.target.classList.add('is-revealed');
            revealObserver.unobserve(entry.target);
        });
    }, {
        rootMargin: '0px 0px -8% 0px',
        threshold: 0.12,
    });

    revealItems.forEach((item) => revealObserver.observe(item));
    document.documentElement.classList.add('has-motion');
}

if (reduceMotionQuery) {
    const onMotionChange = (event) => {
        if (!event.matches) {
            return;
        }

        document.documentElement.classList.remove('has-motion');
        revealItems.forEach((item) => item.classList.add('is-revealed'));
    };

    if (typeof reduceMotionQuery.addEventListener === 'function') {
        reduceMotionQuery.addEventListener('change', onMotionChange);
    } else if (typeof reduceMotionQuery.addListener === 'function') {
        reduceMotionQuery.addListener(onMotionChange);
    }
}
