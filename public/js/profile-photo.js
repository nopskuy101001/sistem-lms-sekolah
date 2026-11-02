(() => {
    const form = document.querySelector('[data-profile-photo-form]');
    if (!form) return;

    const input = form.querySelector('[data-profile-photo-input]');
    const image = form.querySelector('[data-profile-photo-image]');
    const initial = form.querySelector('[data-profile-photo-initial]');
    const preview = form.querySelector('[data-profile-photo-preview]');
    const zoom = form.querySelector('[data-profile-photo-zoom]');
    const positionX = form.querySelector('[data-profile-photo-x]');
    const positionY = form.querySelector('[data-profile-photo-y]');
    const zoomOutput = form.querySelector('[data-profile-zoom-output]');
    const xOutput = form.querySelector('[data-profile-x-output]');
    const yOutput = form.querySelector('[data-profile-y-output]');
    let previewUrl = null;
    let dragState = null;

    const clamp = (value, min, max) => Math.min(max, Math.max(min, value));

    function updateAdjustment() {
        const scale = Number(zoom.value) / 100;
        const x = Number(positionX.value);
        const y = Number(positionY.value);
        const shiftX = Math.round((50 - x) * (scale - 1) * 100) / 100;
        const shiftY = Math.round((50 - y) * (scale - 1) * 100) / 100;

        image.style.objectPosition = `${x}% ${y}%`;
        image.style.transform = `translate(${shiftX}%, ${shiftY}%) scale(${scale})`;
        zoomOutput.value = `${zoom.value}%`;
        xOutput.value = `${positionX.value}%`;
        yOutput.value = `${positionY.value}%`;
    }

    input.addEventListener('change', () => {
        if (previewUrl) URL.revokeObjectURL(previewUrl);
        previewUrl = null;
        const file = input.files?.[0];
        if (!file || !file.type.startsWith('image/')) return;

        previewUrl = URL.createObjectURL(file);
        image.src = previewUrl;
        image.hidden = false;
        if (initial) initial.hidden = true;
        updateAdjustment();
    });

    for (const control of [zoom, positionX, positionY]) {
        control.addEventListener('input', updateAdjustment);
    }

    preview.addEventListener('pointerdown', (event) => {
        if (image.hidden) return;
        preview.setPointerCapture(event.pointerId);
        dragState = {
            pointerId: event.pointerId,
            startClientX: event.clientX,
            startClientY: event.clientY,
            startX: Number(positionX.value),
            startY: Number(positionY.value),
        };
    });

    preview.addEventListener('pointermove', (event) => {
        if (!dragState || event.pointerId !== dragState.pointerId) return;
        const rect = preview.getBoundingClientRect();
        positionX.value = String(Math.round(clamp(dragState.startX - ((event.clientX - dragState.startClientX) / rect.width) * 100, 0, 100)));
        positionY.value = String(Math.round(clamp(dragState.startY - ((event.clientY - dragState.startClientY) / rect.height) * 100, 0, 100)));
        updateAdjustment();
    });

    const stopDrag = (event) => {
        if (dragState?.pointerId === event.pointerId) dragState = null;
    };
    preview.addEventListener('pointerup', stopDrag);
    preview.addEventListener('pointercancel', stopDrag);
    preview.addEventListener('keydown', (event) => {
        const movement = 2;
        if (!['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(event.key)) return;
        event.preventDefault();
        if (event.key === 'ArrowLeft') positionX.value = String(clamp(Number(positionX.value) - movement, 0, 100));
        if (event.key === 'ArrowRight') positionX.value = String(clamp(Number(positionX.value) + movement, 0, 100));
        if (event.key === 'ArrowUp') positionY.value = String(clamp(Number(positionY.value) - movement, 0, 100));
        if (event.key === 'ArrowDown') positionY.value = String(clamp(Number(positionY.value) + movement, 0, 100));
        updateAdjustment();
    });

    window.addEventListener('pagehide', () => {
        if (previewUrl) URL.revokeObjectURL(previewUrl);
    }, {once: true});
    updateAdjustment();
})();
