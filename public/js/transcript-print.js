(() => {
    document.querySelector('[data-print-transcript]')?.addEventListener('click', () => window.print());
    window.addEventListener('load', () => window.print(), {once: true});
})();
