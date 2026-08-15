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
});
