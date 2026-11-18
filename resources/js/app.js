import './bootstrap';
import Swal from 'sweetalert2';
import JsBarcode from 'jsbarcode';

window.Swal = Swal;
const openPendingPrintWindow = (key) => {
    window[key] = window.open('about:blank', '_blank');
};

const navigatePendingPrintWindow = (key, eventPayload) => {
    const printWindow = window[key];
    const printUrl = eventPayload && typeof eventPayload.url === 'string'
        ? eventPayload.url
        : null;
    delete window[key];

    if (!printUrl) {
        console.error('No se recibió la dirección de la página de impresión.');
        printWindow?.close();

        return;
    }

    const destination = new URL(printUrl, window.location.origin);
    if (destination.origin !== window.location.origin) {
        console.error('Se rechazó una dirección de impresión fuera del sitio.');
        printWindow?.close();

        return;
    }

    if (!printWindow || printWindow.closed) {
        Swal.fire({
            icon: 'warning',
            title: 'Pestaña bloqueada',
            text: 'Permite las ventanas emergentes para abrir la página de impresión en otra pestaña.',
        });

        return;
    }

    printWindow.location.href = destination.href;
};

window.openInventoryBarcodePrintTab = () => {
    openPendingPrintWindow('pendingInventoryBarcodePrintWindow');
};
window.openHistoryPrintTab = () => {
    openPendingPrintWindow('pendingHistoryPrintWindow');
};

window.renderInventoryBarcode = (element, value) => {
    try {
        element.replaceChildren();
        JsBarcode(element, String(value), {
            format: 'CODE128',
            width: 2,
            height: 64,
            displayValue: false,
            margin: 12,
            background: '#ffffff',
            lineColor: '#111827',
        });

        return true;
    } catch (error) {
        console.error('No se pudo generar el código de barras del inventario.', error);
        element.textContent = 'No se pudo generar el código de barras.';

        return false;
    }
};

window.downloadInventoryBarcodeLabel = (element, value, description) => {
    try {
        const svgNamespace = 'http://www.w3.org/2000/svg';
        const barcodeWidth = Number.parseFloat(element.getAttribute('width'));
        const barcodeHeight = Number.parseFloat(element.getAttribute('height'));
        if (
            element.namespaceURI !== svgNamespace
            || !element.firstElementChild
            || !Number.isFinite(barcodeWidth)
            || !Number.isFinite(barcodeHeight)
        ) {
            throw new Error('El código de barras todavía no está disponible para descargar.');
        }

        const labelWidth = 240;
        const labelHeight = 144;
        const barcodeScale = Math.min(1, 224 / barcodeWidth, 88 / barcodeHeight);
        const label = document.createElementNS(svgNamespace, 'svg');
        label.setAttribute('xmlns', svgNamespace);
        label.setAttribute('width', '63.5mm');
        label.setAttribute('height', '38.1mm');
        label.setAttribute('viewBox', `0 0 ${labelWidth} ${labelHeight}`);
        label.setAttribute('role', 'img');
        label.setAttribute('aria-label', `Etiqueta SIGICOV ${value}`);

        const background = document.createElementNS(svgNamespace, 'rect');
        background.setAttribute('width', '100%');
        background.setAttribute('height', '100%');
        background.setAttribute('fill', '#ffffff');
        label.appendChild(background);

        const descriptionText = document.createElementNS(svgNamespace, 'text');
        const normalizedDescription = String(description || '').trim();
        descriptionText.textContent = normalizedDescription.length > 36
            ? `${normalizedDescription.slice(0, 33)}...`
            : normalizedDescription;
        descriptionText.setAttribute('x', String(labelWidth / 2));
        descriptionText.setAttribute('y', '14');
        descriptionText.setAttribute('text-anchor', 'middle');
        descriptionText.setAttribute('font-family', 'Arial, sans-serif');
        descriptionText.setAttribute('font-size', '8');
        descriptionText.setAttribute('fill', '#334155');
        label.appendChild(descriptionText);

        const barcodeGroup = document.createElementNS(svgNamespace, 'g');
        const scaledWidth = barcodeWidth * barcodeScale;
        const x = (labelWidth - scaledWidth) / 2;
        barcodeGroup.setAttribute('transform', `translate(${x} 22) scale(${barcodeScale})`);
        Array.from(element.childNodes).forEach((child) => {
            barcodeGroup.appendChild(child.cloneNode(true));
        });
        label.appendChild(barcodeGroup);

        const sigicovText = document.createElementNS(svgNamespace, 'text');
        sigicovText.textContent = String(value);
        sigicovText.setAttribute('x', String(labelWidth / 2));
        sigicovText.setAttribute('y', '132');
        sigicovText.setAttribute('text-anchor', 'middle');
        sigicovText.setAttribute('font-family', 'monospace');
        sigicovText.setAttribute('font-size', '11');
        sigicovText.setAttribute('font-weight', '700');
        sigicovText.setAttribute('fill', '#0f172a');
        label.appendChild(sigicovText);

        const serializedLabel = new XMLSerializer().serializeToString(label);
        const file = new Blob([`<?xml version="1.0" encoding="UTF-8"?>${serializedLabel}`], {
            type: 'image/svg+xml;charset=utf-8',
        });
        const fileUrl = URL.createObjectURL(file);
        const link = document.createElement('a');
        const safeSigicov = String(value).trim().replace(/[^a-zA-Z0-9._-]+/g, '-') || 'repuesto';
        link.href = fileUrl;
        link.download = `etiqueta-sigicov-${safeSigicov}.svg`;
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.setTimeout(() => URL.revokeObjectURL(fileUrl), 1000);

        return true;
    } catch (error) {
        console.error('No se pudo descargar la etiqueta del código de barras.', error);
        window.Swal?.fire({
            icon: 'error',
            title: 'No se pudo descargar',
            text: 'Vuelve a abrir el código de barras e inténtalo nuevamente.',
        });

        return false;
    }
};

const showLoginSuccess = () => {
    return Swal.fire({
        icon: 'success',
        title: '¡Bienvenido!',
        text: 'Has iniciado sesión correctamente.',
        timer: 2000,
        showConfirmButton: false,
    }).then(() => {
        delete document.body.dataset.loginSuccess;
    });
};

const showSessionLoginSuccess = () => {
    if (document.body.dataset.loginSuccess !== 'true') {
        return;
    }

    delete document.body.dataset.loginSuccess;
    showLoginSuccess();
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', showSessionLoginSuccess, { once: true });
} else {
    showSessionLoginSuccess();
}

let livewireListenersRegistered = false;

const registerLivewireListeners = () => {
    if (livewireListenersRegistered || typeof window.Livewire?.on !== 'function') {
        return;
    }

    livewireListenersRegistered = true;

    window.Livewire.on('notification', (eventPayload = {}) => {
        const payload = eventPayload && typeof eventPayload === 'object' ? eventPayload : {};
        const type = ['success', 'error', 'warning', 'info'].includes(payload.type)
            ? payload.type
            : 'info';
        const titles = {
            success: 'Confirmado',
            error: 'Error',
            warning: 'Atención',
            info: 'Información',
        };

        Swal.fire({
            icon: type,
            title: titles[type],
            text: payload.message || '',
            timer: 3000,
        });
    });

    window.Livewire.on('login-success', showLoginSuccess);
    window.Livewire.on('inventory-barcode-print-ready', (eventPayload = {}) => {
        navigatePendingPrintWindow('pendingInventoryBarcodePrintWindow', eventPayload);
    });
    window.Livewire.on('history-print-ready', (eventPayload = {}) => {
        navigatePendingPrintWindow('pendingHistoryPrintWindow', eventPayload);
    });
};

document.addEventListener('livewire:init', registerLivewireListeners);
document.addEventListener('DOMContentLoaded', registerLivewireListeners, { once: true });
registerLivewireListeners();
