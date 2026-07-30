import './bootstrap';

const sidebar = document.getElementById('sidebar');
const sidebarToggle = document.getElementById('sidebar-toggle');
const sidebarBackdrop = document.getElementById('sidebar-backdrop');
const sidebarClose = document.getElementById('sidebar-close');

const setSidebarOpen = (isOpen) => {
    if (!sidebar || !sidebarToggle || !sidebarBackdrop) {
        return;
    }

    sidebar.classList.toggle('-translate-x-full', !isOpen);
    sidebarBackdrop.classList.toggle('hidden', !isOpen);
    sidebarToggle.setAttribute('aria-expanded', String(isOpen));
    document.body.classList.toggle('overflow-hidden', isOpen);
};

sidebarToggle?.addEventListener('click', () => {
    setSidebarOpen(sidebarToggle.getAttribute('aria-expanded') !== 'true');
});

sidebarBackdrop?.addEventListener('click', () => setSidebarOpen(false));
sidebarClose?.addEventListener('click', () => setSidebarOpen(false));

sidebar?.querySelectorAll('a').forEach((link) => {
    link.addEventListener('click', () => setSidebarOpen(false));
});

window.matchMedia('(min-width: 1024px)').addEventListener('change', (event) => {
    if (event.matches) {
        setSidebarOpen(false);
    }
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        setSidebarOpen(false);
    }
});

document.querySelectorAll('.password-toggle').forEach((button) => {
    button.addEventListener('click', () => {
        const input = document.getElementById(button.dataset.target);

        if (!input) {
            return;
        }

        const isHidden = input.type === 'password';
        input.type = isHidden ? 'text' : 'password';

        button
            .querySelector('[data-password-show]')
            ?.classList.toggle('hidden', isHidden);

        button
            .querySelector('[data-password-hide]')
            ?.classList.toggle('hidden', !isHidden);

        button.setAttribute(
            'aria-label',
            isHidden
                ? button.dataset.hideLabel ?? 'Sembunyikan kata sandi'
                : button.dataset.showLabel ?? 'Tampilkan kata sandi',
        );
    });
});

document.querySelectorAll('form[data-confirm-message]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        const message = form.dataset.confirmMessage;

        if (message && !window.confirm(message)) {
            event.preventDefault();
        }
    });
});

document.querySelectorAll('form').forEach((form) => {
    form.addEventListener('submit', () => {
        const submitButton = form.querySelector(
            'button[type="submit"][data-submit-label]',
        );

        if (!submitButton) {
            return;
        }

        submitButton.disabled = true;
        submitButton.textContent = submitButton.dataset.submitLabel;
        submitButton.classList.add('cursor-wait', 'opacity-70');
    });
});
