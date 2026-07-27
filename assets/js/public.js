(() => {
    'use strict';

    const nav = document.getElementById('mainNav');
    const onScroll = () => {
        if (nav) nav.classList.toggle('scrolled', window.scrollY > 30);
    };
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });

    const revealItems = document.querySelectorAll('.reveal-card');
    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.12 });
        revealItems.forEach((item) => observer.observe(item));
    } else {
        revealItems.forEach((item) => item.classList.add('visible'));
    }

    const methodRadios = document.querySelectorAll('input[name="mode_paiement"]');
    const methodPanels = document.querySelectorAll('.method-panel');

    const setMethod = (method) => {
        methodPanels.forEach((panel) => {
            panel.classList.toggle('d-none', panel.dataset.method !== method);
        });
    };

    methodRadios.forEach((radio) => {
        radio.addEventListener('change', () => setMethod(radio.value));
        if (radio.checked) setMethod(radio.value);
    });
})();
