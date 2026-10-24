// Verkleinert Bilder vor dem Hochladen im Browser (Canvas), damit auch Handyfotos (3–10 MB) durch
// Server-Limits von oft nur 2 MB passen. Gilt für <input type="file" data-resize="MAXSEITE">.
//   data-resize-keep="png"  → PNG bleibt PNG (Logo mit Transparenz), sonst wird JPEG erzeugt
// Fällt jedes Mal sauber auf die Originaldatei zurück; der Server prüft und verkleinert ohnehin nochmals.
(function () {
    'use strict';
    if (!window.HTMLCanvasElement || typeof DataTransfer === 'undefined') return;

    const SKIP_UNDER = 1.2 * 1024 * 1024; // kleine Dateien innerhalb der Grösse bleiben unangetastet
    const ALLOWED = /^image\/(png|jpeg|webp)$/;

    const formatSize = (bytes) => (bytes >= 1048576 ? (bytes / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(bytes / 1024)) + ' KB');

    async function decode(file) {
        if (window.createImageBitmap) {
            try { return await createImageBitmap(file, { imageOrientation: 'from-image' }); } catch (e) { /* weiter */ }
            try { return await createImageBitmap(file); } catch (e) { /* weiter */ }
        }
        return new Promise((resolve, reject) => {
            const url = URL.createObjectURL(file);
            const img = new Image();
            img.onload = () => { URL.revokeObjectURL(url); resolve(img); };
            img.onerror = () => { URL.revokeObjectURL(url); reject(new Error('decode')); };
            img.src = url;
        });
    }

    async function shrink(file, maxSide, keepPng) {
        if (!ALLOWED.test(file.type)) return file;
        let img;
        try { img = await decode(file); } catch (e) { return file; }
        const width = img.naturalWidth || img.width;
        const height = img.naturalHeight || img.height;
        const scale = Math.min(1, maxSide / Math.max(width, height));
        if (scale === 1 && file.size <= SKIP_UNDER) return file;

        const type = keepPng && file.type === 'image/png' ? 'image/png' : 'image/jpeg';
        const canvas = document.createElement('canvas');
        canvas.width = Math.max(1, Math.round(width * scale));
        canvas.height = Math.max(1, Math.round(height * scale));
        const ctx = canvas.getContext('2d');
        if (!ctx) return file;
        if (type === 'image/jpeg') { // JPEG kennt keine Transparenz: sonst würde sie schwarz
            ctx.fillStyle = '#fff';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
        }
        ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
        if (img.close) img.close();

        const blob = await new Promise((resolve) => canvas.toBlob(resolve, type, 0.85));
        if (!blob || (scale === 1 && blob.size >= file.size)) return file; // Canvas-Limit erreicht o.ä.
        const base = file.name.replace(/\.[^.]+$/, '') || 'bild';
        return new File([blob], base + (type === 'image/png' ? '.png' : '.jpg'), { type, lastModified: Date.now() });
    }

    document.querySelectorAll('input[type="file"][data-resize]').forEach((input) => {
        const maxSide = parseInt(input.dataset.resize, 10);
        if (!maxSide || !input.form) return;
        const keepPng = input.dataset.resizeKeep === 'png';
        const status = input.form.querySelector('[data-resize-status]');
        let pending = null;

        input.addEventListener('change', () => {
            const files = Array.from(input.files || []);
            if (files.length === 0) { if (status) status.textContent = ''; return; }
            if (status) status.textContent = 'Bilder werden verkleinert …';
            pending = (async () => {
                const out = [];
                for (const file of files) out.push(await shrink(file, maxSide, keepPng));
                const transfer = new DataTransfer();
                out.forEach((file) => transfer.items.add(file));
                input.files = transfer.files;
                if (status) {
                    const total = out.reduce((sum, file) => sum + file.size, 0);
                    status.textContent = out.length + (out.length === 1 ? ' Bild' : ' Bilder') + ', ' + formatSize(total) + ' – bereit zum Hochladen';
                }
            })().catch(() => { if (status) status.textContent = ''; }).finally(() => { pending = null; });
        });

        // Klickt jemand «Hochladen», bevor die Verkleinerung fertig ist: kurz warten, dann absenden.
        // stopPropagation verhindert, dass forms.js (data-once) den Button vor dem eigentlichen Absenden sperrt.
        input.form.addEventListener('submit', (event) => {
            if (!pending) return;
            event.preventDefault();
            event.stopPropagation();
            const submitter = event.submitter || undefined;
            pending.then(() => input.form.requestSubmit(submitter));
        }, true);
    });
})();
