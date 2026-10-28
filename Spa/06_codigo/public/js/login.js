'use strict';

const form = document.querySelector('[data-login-form]');
const status = document.querySelector('[data-status]');
const toggle = document.querySelector('[data-password-toggle]');
const submit = document.querySelector('[data-submit]');

if (status && status.dataset.hasErrors === 'true') {
    status.focus({ preventScroll: true });
}

if (toggle) {
    const password = document.getElementById(toggle.getAttribute('aria-controls'));

    if (password) {
        toggle.hidden = false;

        toggle.addEventListener('click', () => {
            const reveal = password.type === 'password';

            password.type = reveal ? 'text' : 'password';
            toggle.textContent = reveal ? 'Ocultar' : 'Mostrar';
            toggle.setAttribute('aria-pressed', reveal ? 'true' : 'false');
            toggle.setAttribute('aria-label', reveal ? 'Ocultar contraseña' : 'Mostrar contraseña');
            password.focus({ preventScroll: true });
        });
    }
}

if (form && submit) {
    form.addEventListener('submit', () => {
        if (!form.checkValidity()) {
            return;
        }

        const label = submit.querySelector('[data-submit-label]');
        const spinner = submit.querySelector('[data-spinner]');

        submit.disabled = true;
        submit.setAttribute('aria-busy', 'true');
        form.setAttribute('aria-busy', 'true');

        if (label) {
            label.textContent = 'Ingresando…';
        }

        if (spinner) {
            spinner.hidden = false;
        }
    });
}
