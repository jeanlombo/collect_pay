
(() => {
    document.documentElement.classList.add('cp-motion-ready');

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('cp-in-view');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.12 });

    document.querySelectorAll('.impact-service-card,.real-revenue-cards article,.summary-card')
        .forEach((el, index) => {
            el.style.transitionDelay = `${Math.min(index * 65, 260)}ms`;
            observer.observe(el);
        });

    const easeOut = t => 1 - Math.pow(1 - t, 4);

    document.querySelectorAll('.live-count').forEach((el) => {
        const target = Number(el.dataset.value || 0);
        const duration = 1100;
        const start = performance.now();

        const draw = now => {
            const p = Math.min(1, (now - start) / duration);
            el.textContent = Math.round(target * easeOut(p)).toLocaleString('fr-FR');
            if (p < 1) requestAnimationFrame(draw);
        };
        requestAnimationFrame(draw);
    });

    document.querySelectorAll('.live-money').forEach((el) => {
        const target = Number(el.dataset.value || 0);
        const currency = el.dataset.currency || 'CDF';
        const duration = 1250;
        const start = performance.now();

        const draw = now => {
            const p = Math.min(1, (now - start) / duration);
            const value = target * easeOut(p);
            el.textContent = new Intl.NumberFormat('fr-FR', {
                maximumFractionDigits: currency === 'CDF' ? 0 : 2
            }).format(value) + ' ' + currency;
            if (p < 1) requestAnimationFrame(draw);
        };
        requestAnimationFrame(draw);
    });

    // QR camera public page.
    const video = document.getElementById('qrVideo');
    const startBtn = document.getElementById('startQrCamera');
    const stopBtn = document.getElementById('stopQrCamera');
    const placeholder = document.getElementById('cameraPlaceholder');
    const status = document.getElementById('scannerStatus');
    const compatibility = document.getElementById('scannerCompatibility');
    const qrField = document.getElementById('qrContent');
    const qrForm = document.getElementById('qrVerifyForm');

    if (!video || !startBtn) return;

    let stream = null;
    let detector = null;
    let scanning = false;

    async function stopCamera() {
        scanning = false;
        if (stream) {
            stream.getTracks().forEach(track => track.stop());
            stream = null;
        }
        video.pause();
        video.srcObject = null;
        video.style.display = 'none';
        if (placeholder) placeholder.style.display = 'flex';
        startBtn.disabled = false;
        if (stopBtn) stopBtn.disabled = true;
        if (status) status.textContent = 'Caméra arrêtée';
    }

    async function scanLoop() {
        if (!scanning || !detector) return;

        try {
            const codes = await detector.detect(video);
            const qr = codes.find(code => code.rawValue);

            if (qr && qr.rawValue) {
                scanning = false;
                if (qrField) qrField.value = qr.rawValue;
                if (status) status.textContent = 'QR détecté — vérification…';
                await stopCamera();
                if (window.CP_QR_AUTO_SUBMIT && qrForm) {
                    qrForm.submit();
                    return;
                }
            }
        } catch (err) {}

        if (scanning) requestAnimationFrame(scanLoop);
    }

    startBtn.addEventListener('click', async () => {
        if (!navigator.mediaDevices?.getUserMedia) {
            if (status) status.textContent = 'Caméra non disponible';
            if (compatibility) compatibility.textContent = 'Votre navigateur ne permet pas l’accès caméra. Utilisez le mode manuel.';
            return;
        }

        if (!('BarcodeDetector' in window)) {
            if (status) status.textContent = 'Scanner QR non supporté';
            if (compatibility) compatibility.textContent = 'Ce navigateur ne dispose pas du lecteur QR natif. Utilisez le mode manuel ou un navigateur récent.';
            return;
        }

        try {
            const formats = await BarcodeDetector.getSupportedFormats();
            if (!formats.includes('qr_code')) {
                throw new Error('QR non supporté');
            }

            detector = new BarcodeDetector({ formats: ['qr_code'] });
            stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: { ideal: 'environment' } },
                audio: false
            });

            video.srcObject = stream;
            await video.play();
            video.style.display = 'block';
            if (placeholder) placeholder.style.display = 'none';
            startBtn.disabled = true;
            if (stopBtn) stopBtn.disabled = false;
            if (status) status.textContent = 'Recherche du QR…';
            scanning = true;
            requestAnimationFrame(scanLoop);
        } catch (err) {
            if (status) status.textContent = 'Impossible d’ouvrir le scanner';
            if (compatibility) compatibility.textContent = 'Autorisez la caméra ou utilisez le mode manuel.';
            await stopCamera();
        }
    });

    stopBtn?.addEventListener('click', stopCamera);
    window.addEventListener('beforeunload', stopCamera);
})();
