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
    form.addEventListener('submit', (event) => {
        if (event.defaultPrevented) {
            return;
        }

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

const quantityFormatter = new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
});

document.querySelectorAll('[data-inventory-lines]').forEach((container) => {
    const list = container.querySelector('[data-inventory-line-list]');
    const template = container.querySelector(
        '[data-inventory-line-template]',
    );
    const addButton = container.querySelector(
        '[data-add-inventory-line]',
    );

    if (!list || !template || !addButton) {
        return;
    }

    const maxLines = Number(container.dataset.maxLines ?? 50);
    const stockLabelPrefix =
        container.dataset.stockLabel ?? 'Stok sistem';
    const existingIndexes = Array.from(
        list.querySelectorAll('[name^="items["]'),
    )
        .map((input) => input.name.match(/^items\[(\d+)]/))
        .filter(Boolean)
        .map((match) => Number(match[1]));
    let nextIndex =
        existingIndexes.length === 0
            ? 0
            : Math.max(...existingIndexes) + 1;

    const updateRemoveButtons = () => {
        const lines = list.querySelectorAll('[data-inventory-line]');
        const hasSingleLine = lines.length <= 1;

        lines.forEach((line) => {
            const removeButton = line.querySelector(
                '[data-remove-inventory-line]',
            );

            if (!removeButton) {
                return;
            }

            removeButton.disabled = hasSingleLine;
            removeButton.classList.toggle(
                'cursor-not-allowed',
                hasSingleLine,
            );
            removeButton.classList.toggle('opacity-40', hasSingleLine);
        });

        addButton.disabled = lines.length >= maxLines;
        addButton.classList.toggle(
            'cursor-not-allowed',
            lines.length >= maxLines,
        );
        addButton.classList.toggle(
            'opacity-50',
            lines.length >= maxLines,
        );
    };

    const refreshLine = (line) => {
        const select = line.querySelector(
            '[data-inventory-item-select]',
        );
        const stockLabel = line.querySelector(
            '[data-inventory-stock-label]',
        );
        const physicalInput = line.querySelector(
            '[data-physical-quantity]',
        );
        const differenceLabel = line.querySelector(
            '[data-inventory-difference]',
        );

        if (!select || !stockLabel) {
            return;
        }

        const option = select.options[select.selectedIndex];
        const stock = Number(option?.dataset.stock ?? '');
        const unit = option?.dataset.unit ?? '';

        stockLabel.textContent =
            option?.value && Number.isFinite(stock)
                ? `${stockLabelPrefix}: ${quantityFormatter.format(stock)} ${unit}`
                : `Pilih barang untuk melihat ${stockLabelPrefix.toLowerCase()}`;

        if (!physicalInput || !differenceLabel) {
            return;
        }

        const physical = Number(physicalInput.value);

        if (
            !option?.value ||
            physicalInput.value === '' ||
            !Number.isFinite(stock) ||
            !Number.isFinite(physical)
        ) {
            differenceLabel.textContent = 'Selisih: —';
            differenceLabel.classList.remove(
                'text-emerald-700',
                'text-red-700',
            );
            differenceLabel.classList.add('text-sky-700');

            return;
        }

        const difference = Math.round((physical - stock) * 100) / 100;
        const sign = difference > 0 ? '+' : '';

        differenceLabel.textContent = `Selisih: ${sign}${quantityFormatter.format(difference)} ${unit}`;
        differenceLabel.classList.remove(
            'text-sky-700',
            'text-emerald-700',
            'text-red-700',
        );
        differenceLabel.classList.add(
            difference < 0
                ? 'text-red-700'
                : difference > 0
                  ? 'text-emerald-700'
                  : 'text-sky-700',
        );
    };

    const bindLine = (line) => {
        line.querySelector('[data-inventory-item-select]')?.addEventListener(
            'change',
            () => refreshLine(line),
        );
        line.querySelector('[data-physical-quantity]')?.addEventListener(
            'input',
            () => refreshLine(line),
        );
        line.querySelector('[data-remove-inventory-line]')?.addEventListener(
            'click',
            () => {
                if (
                    list.querySelectorAll('[data-inventory-line]').length <=
                    1
                ) {
                    return;
                }

                line.remove();
                updateRemoveButtons();
            },
        );

        refreshLine(line);
    };

    list.querySelectorAll('[data-inventory-line]').forEach(bindLine);

    addButton.addEventListener('click', () => {
        if (
            list.querySelectorAll('[data-inventory-line]').length >=
            maxLines
        ) {
            return;
        }

        const markup = template.innerHTML.replaceAll(
            '__INDEX__',
            String(nextIndex),
        );
        nextIndex += 1;
        list.insertAdjacentHTML('beforeend', markup);

        const newLine = list.lastElementChild;

        if (newLine) {
            bindLine(newLine);
            newLine.querySelector('select, input')?.focus();
        }

        updateRemoveButtons();
    });

    updateRemoveButtons();
});

document.querySelectorAll('[data-signature-pad]').forEach((pad) => {
    const canvas = pad.querySelector('[data-signature-canvas]');
    const input = pad.querySelector('[data-signature-input]');
    const clearButton = pad.querySelector('[data-signature-clear]');
    const error = pad.querySelector('[data-signature-error]');
    const form = pad.closest('form[data-signature-form]');

    if (!canvas || !input || !clearButton || !form) {
        return;
    }

    const context = canvas.getContext('2d');

    if (!context) {
        return;
    }

    let drawing = false;
    let hasInk = false;
    let lastPoint = null;

    const configureContext = () => {
        context.lineCap = 'round';
        context.lineJoin = 'round';
        context.lineWidth = 2.4 * window.devicePixelRatio;
        context.strokeStyle = '#0f172a';
    };

    const resizeCanvas = () => {
        const previous = hasInk ? canvas.toDataURL('image/png') : null;
        const ratio = Math.max(1, window.devicePixelRatio || 1);
        const bounds = canvas.getBoundingClientRect();
        canvas.width = Math.max(1, Math.round(bounds.width * ratio));
        canvas.height = Math.max(1, Math.round(bounds.height * ratio));
        configureContext();

        if (!previous) {
            return;
        }

        const image = new Image();
        image.onload = () => {
            context.drawImage(
                image,
                0,
                0,
                canvas.width,
                canvas.height,
            );
        };
        image.src = previous;
    };

    const pointFromEvent = (event) => {
        const bounds = canvas.getBoundingClientRect();
        const scaleX = canvas.width / bounds.width;
        const scaleY = canvas.height / bounds.height;

        return {
            x: (event.clientX - bounds.left) * scaleX,
            y: (event.clientY - bounds.top) * scaleY,
        };
    };

    const beginStroke = (event) => {
        event.preventDefault();
        drawing = true;
        hasInk = true;
        lastPoint = pointFromEvent(event);
        canvas.setPointerCapture?.(event.pointerId);
        error?.classList.add('hidden');
    };

    const drawStroke = (event) => {
        if (!drawing || !lastPoint) {
            return;
        }

        event.preventDefault();
        const nextPoint = pointFromEvent(event);
        context.beginPath();
        context.moveTo(lastPoint.x, lastPoint.y);
        context.lineTo(nextPoint.x, nextPoint.y);
        context.stroke();
        lastPoint = nextPoint;
    };

    const endStroke = (event) => {
        if (!drawing) {
            return;
        }

        drawing = false;
        lastPoint = null;
        canvas.releasePointerCapture?.(event.pointerId);
    };

    const clearSignature = () => {
        context.clearRect(0, 0, canvas.width, canvas.height);
        input.value = '';
        hasInk = false;
        lastPoint = null;
        error?.classList.add('hidden');
    };

    canvas.addEventListener('pointerdown', beginStroke);
    canvas.addEventListener('pointermove', drawStroke);
    canvas.addEventListener('pointerup', endStroke);
    canvas.addEventListener('pointercancel', endStroke);
    clearButton.addEventListener('click', clearSignature);

    form.addEventListener(
        'submit',
        (event) => {
            if (!hasInk) {
                event.preventDefault();
                error?.classList.remove('hidden');
                canvas.focus();

                return;
            }

            input.value = canvas.toDataURL('image/png');
        },
        true,
    );

    resizeCanvas();

    if (input.value.startsWith('data:image/png;base64,')) {
        const image = new Image();
        image.onload = () => {
            context.drawImage(
                image,
                0,
                0,
                canvas.width,
                canvas.height,
            );
            hasInk = true;
        };
        image.src = input.value;
    }

    window.addEventListener('resize', resizeCanvas);
});
