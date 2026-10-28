'use strict';

const status = document.querySelector('[data-service-status]');

if (status && !status.hidden) {
    status.focus();
}

document.querySelectorAll('[data-service-form]').forEach((form) => {
    form.addEventListener('submit', () => {
        const submit = form.querySelector('[data-service-submit]');
        const label = form.querySelector('[data-service-submit-label]');
        const spinner = form.querySelector('[data-service-spinner]');

        if (!submit) {
            return;
        }

        submit.disabled = true;
        submit.setAttribute('aria-busy', 'true');
        form.setAttribute('aria-busy', 'true');

        if (label) {
            label.textContent = form.getAttribute('data-service-loading-label') || 'Creando…';
        }

        if (spinner) {
            spinner.hidden = false;
        }
    });
});

document.querySelectorAll('[data-inactivate-form]').forEach((form) => {
    form.addEventListener('submit', () => {
        const submit = form.querySelector('[data-inactivate-submit]');
        const label = form.querySelector('[data-inactivate-label]');
        const spinner = form.querySelector('[data-inactivate-spinner]');

        if (!submit) {
            return;
        }

        submit.disabled = true;
        submit.setAttribute('aria-busy', 'true');
        form.setAttribute('aria-busy', 'true');

        if (label) {
            label.textContent = 'Inactivando…';
        }

        if (spinner) {
            spinner.hidden = false;
        }
    });
});
