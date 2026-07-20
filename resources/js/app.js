import Alpine from 'alpinejs';
import { checkoutApp } from './checkout.js';
import { requestJson } from './shared/http.js';

window.Alpine = Alpine;
window.paymentRequest = requestJson;

Alpine.data('checkoutApp', checkoutApp);
Alpine.start();

document.querySelectorAll('[data-install-form]').forEach((form) => {
    form.addEventListener('submit', () => {
        const submitButton = form.querySelector('button[type="submit"]');

        if (!(submitButton instanceof HTMLButtonElement) || submitButton.disabled) {
            return;
        }

        submitButton.disabled = true;
        submitButton.setAttribute('aria-busy', 'true');
        submitButton.textContent = submitButton.dataset.submitLabel ?? '正在处理…';
    });
});
