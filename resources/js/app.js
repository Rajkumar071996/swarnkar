import * as bootstrap from 'bootstrap';

window.bootstrap = bootstrap;

function isAppleTouchDevice() {
    return /iPad|iPhone|iPod/.test(navigator.userAgent)
        || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
}

document.addEventListener('DOMContentLoaded', () => {
    if (isAppleTouchDevice()) {
        document.documentElement.classList.add('is-ios');
    }

    const useNativeSelect = isAppleTouchDevice()
        || window.matchMedia('(max-width: 767.98px)').matches;

    if (useNativeSelect) {
        document.querySelectorAll('select[size]').forEach((el) => {
            el.removeAttribute('size');
        });
    }

    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => {
        new bootstrap.Tooltip(el);
    });

    document.querySelectorAll('[data-gs-autodismiss]').forEach((el) => {
        setTimeout(() => bootstrap.Alert.getOrCreateInstance(el).close(), 6000);
    });

    bindSignaturePads();
});

function bindSignaturePads() {
    document.querySelectorAll('[data-gs-signature-pad]').forEach((root) => {
        const canvas = root.querySelector('canvas');
        const input = root.querySelector('[data-gs-signature-input]');
        const clearBtn = root.querySelector('[data-gs-signature-clear]');

        if (! canvas || ! input) {
            return;
        }

        const ctx = canvas.getContext('2d');
        const cssWidth = canvas.clientWidth || 560;
        const cssHeight = canvas.clientHeight || 160;
        const ratio = window.devicePixelRatio || 1;

        canvas.width = Math.round(cssWidth * ratio);
        canvas.height = Math.round(cssHeight * ratio);
        ctx.scale(ratio, ratio);
        ctx.lineWidth = 2.25;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.strokeStyle = '#111';

        let drawing = false;
        let dirty = false;

        const point = (event) => {
            const rect = canvas.getBoundingClientRect();
            const source = event.touches ? event.touches[0] : event;

            return {
                x: source.clientX - rect.left,
                y: source.clientY - rect.top,
            };
        };

        const start = (event) => {
            event.preventDefault();
            drawing = true;
            dirty = true;
            const p = point(event);
            ctx.beginPath();
            ctx.moveTo(p.x, p.y);
        };

        const move = (event) => {
            if (! drawing) {
                return;
            }

            event.preventDefault();
            const p = point(event);
            ctx.lineTo(p.x, p.y);
            ctx.stroke();
        };

        const end = () => {
            if (! drawing) {
                return;
            }

            drawing = false;

            if (dirty) {
                input.value = canvas.toDataURL('image/png');
            }
        };

        canvas.addEventListener('pointerdown', start);
        canvas.addEventListener('pointermove', move);
        canvas.addEventListener('pointerup', end);
        canvas.addEventListener('pointerleave', end);
        canvas.addEventListener('touchstart', start, { passive: false });
        canvas.addEventListener('touchmove', move, { passive: false });
        canvas.addEventListener('touchend', end);

        clearBtn?.addEventListener('click', () => {
            ctx.clearRect(0, 0, cssWidth, cssHeight);
            dirty = false;
            input.value = root.querySelector('.gs-signature-preview') ? 'clear' : '';
        });
    });
}
