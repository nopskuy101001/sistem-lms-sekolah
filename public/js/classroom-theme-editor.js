(() => {
    for (const editor of document.querySelectorAll('[data-theme-editor]')) {
        const fileInput = editor.querySelector('[data-theme-file]');
        const preview = editor.querySelector('[data-theme-preview]');
        const image = editor.querySelector('[data-theme-preview-image]');
        const empty = editor.querySelector('[data-theme-empty]');
        const zoom = editor.querySelector('[data-theme-zoom]');
        const x = editor.querySelector('[data-theme-x]');
        const y = editor.querySelector('[data-theme-y]');
        const remove = editor.closest('form')?.querySelector('[name="remove_theme_image"]');
        const color = editor.closest('form')?.querySelector('[name="color"]');
        const originalSource = image.getAttribute('src');
        let objectUrl = null;

        function updateAdjustment() {
            const scale = Number(zoom.value) / 100;
            const horizontal = Number(x.value);
            const vertical = Number(y.value);

            image.style.objectPosition = `${horizontal}% ${vertical}%`;
            const shiftX = Math.round((50 - horizontal) * (scale - 1) * 100) / 100;
            const shiftY = Math.round((50 - vertical) * (scale - 1) * 100) / 100;
            image.style.transform = `translate(${shiftX}%, ${shiftY}%) scale(${scale})`;
            editor.querySelector('[data-theme-zoom-output]').value = `${zoom.value}%`;
            editor.querySelector('[data-theme-x-output]').value = `${x.value}%`;
            editor.querySelector('[data-theme-y-output]').value = `${y.value}%`;
        }

        function updateSource() {
            if (objectUrl) URL.revokeObjectURL(objectUrl);
            objectUrl = null;

            const file = fileInput.files?.[0];
            let source = remove?.checked ? null : originalSource;
            if (file && file.type.startsWith('image/') && !remove?.checked) {
                objectUrl = URL.createObjectURL(file);
                source = objectUrl;
            }

            if (source) image.src = source;
            else image.removeAttribute('src');
            image.hidden = !source;
            empty.hidden = !!source;
            preview.classList.toggle('lms-has-theme-image', !!source);
            updateAdjustment();
        }

        for (const control of [zoom, x, y]) control.addEventListener('input', updateAdjustment);
        fileInput.addEventListener('change', () => {
            if (fileInput.files?.length && remove) remove.checked = false;
            updateSource();
        });
        remove?.addEventListener('change', () => {
            if (remove.checked) fileInput.value = '';
            updateSource();
        });
        color?.addEventListener('change', () => { preview.dataset.color = color.value; });
        window.addEventListener('pagehide', () => {
            if (objectUrl) URL.revokeObjectURL(objectUrl);
        }, {once: true});
        updateSource();
    }
})();
