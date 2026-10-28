'use strict';

const status = document.querySelector('[data-reservation-status]');

if (status && !status.hidden) {
    status.focus();
}

const form = document.querySelector('[data-reservation-form]');

if (form) {
    const service = form.querySelector('[data-reservation-service]');
    const start = form.querySelector('[data-reservation-start]');
    const end = form.querySelector('[data-reservation-end]');
    const duration = form.querySelector('[data-reservation-duration]');
    const emptyEnd = 'Selecciona un servicio y un inicio.';

    const pad = (value) => String(value).padStart(2, '0');

    const formatUtc = (date) => {
        return `${pad(date.getUTCDate())}/${pad(date.getUTCMonth() + 1)}/${date.getUTCFullYear()} ${pad(date.getUTCHours())}:${pad(date.getUTCMinutes())} UTC`;
    };

    const updateEnd = () => {
        if (!service || !start || !end) {
            return;
        }

        const option = service.selectedOptions[0];
        const minutes = Number(option?.dataset.duration || 0);
        const value = start.value.trim();

        if (!value || minutes <= 0) {
            end.textContent = emptyEnd;

            if (duration) {
                duration.textContent = 'El fin se calcula con la duración del servicio.';
            }

            return;
        }

        const iso = value.length === 16 ? `${value}:00Z` : `${value}Z`;
        const inicio = new Date(iso);

        if (Number.isNaN(inicio.getTime())) {
            end.textContent = emptyEnd;

            if (duration) {
                duration.textContent = 'El fin se calcula con la duración del servicio.';
            }

            return;
        }

        const fin = new Date(inicio.getTime() + (minutes * 60000));

        end.textContent = formatUtc(fin);

        if (duration) {
            duration.textContent = `Duración: ${minutes} min.`;
        }
    };

    service?.addEventListener('change', updateEnd);
    start?.addEventListener('input', updateEnd);
    start?.addEventListener('change', updateEnd);
    updateEnd();

    form.addEventListener('submit', () => {
        const submit = form.querySelector('[data-reservation-submit]');
        const label = form.querySelector('[data-reservation-submit-label]');
        const spinner = form.querySelector('[data-reservation-spinner]');

        if (!submit) {
            return;
        }

        submit.disabled = true;
        submit.setAttribute('aria-busy', 'true');
        form.setAttribute('aria-busy', 'true');

        if (label) {
            label.textContent = form.getAttribute('data-reservation-loading-label') || 'Creando reserva…';
        }

        if (spinner) {
            spinner.hidden = false;
        }
    });
}

document.querySelectorAll('[data-cancel-form]').forEach((form) => {
    form.addEventListener('submit', () => {
        const submit = form.querySelector('[data-cancel-submit]');
        const label = form.querySelector('[data-cancel-label]');
        const spinner = form.querySelector('[data-cancel-spinner]');

        if (!submit) {
            return;
        }

        submit.disabled = true;
        submit.setAttribute('aria-busy', 'true');
        form.setAttribute('aria-busy', 'true');

        if (label) {
            label.textContent = 'Cancelando…';
        }

        if (spinner) {
            spinner.hidden = false;
        }
    });
});
