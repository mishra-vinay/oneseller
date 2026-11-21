// Small progressive enhancements for the storefront. Every page works without JavaScript.

// Sort menus and cart quantities submit as soon as they change.
document.addEventListener('change', (event) => {
    const form = event.target.closest('form[data-autosubmit]');
    if (form && (event.target.matches('select') || event.target.matches('input[type=number]'))) {
        form.requestSubmit();
    }
});

document.addEventListener('click', (event) => {
    // Product gallery: a thumbnail swaps the main image.
    const thumb = event.target.closest('[data-gallery-thumb]');
    if (thumb) {
        const main = document.querySelector('[data-gallery-main]');
        if (main) main.src = thumb.dataset.galleryThumb;
        thumb.parentElement.querySelectorAll('[data-gallery-thumb]').forEach((t) => t.setAttribute('aria-pressed', String(t === thumb)));
        return;
    }

    // Quantity stepper: − and + buttons around a number input.
    const step = event.target.closest('[data-step]');
    if (step) {
        const input = step.closest('[data-stepper]')?.querySelector('input[type=number]');
        if (!input) return;
        const min = Number(input.min || 1);
        const max = Number(input.max || 99);
        input.value = String(Math.min(max, Math.max(min, Number(input.value || min) + Number(step.dataset.step))));
    }
});
