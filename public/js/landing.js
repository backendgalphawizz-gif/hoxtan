(() => {
    'use strict';

    // Nav scroll effect
    const nav = document.querySelector('.nav');
    const onScroll = () => nav?.classList.toggle('scrolled', window.scrollY > 40);
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();

    // Mobile menu
    const toggle = document.querySelector('.nav__toggle');
    const links = document.querySelector('.nav__links');
    toggle?.addEventListener('click', () => links?.classList.toggle('open'));

    // Floating particles
    const particles = document.querySelector('.particles');
    if (particles) {
        for (let i = 0; i < 24; i++) {
            const p = document.createElement('span');
            p.className = 'particle';
            p.style.left = `${Math.random() * 100}%`;
            p.style.animationDuration = `${8 + Math.random() * 12}s`;
            p.style.animationDelay = `${Math.random() * 10}s`;
            p.style.width = p.style.height = `${2 + Math.random() * 4}px`;
            particles.appendChild(p);
        }
    }

    // Scroll reveal
    const reveals = document.querySelectorAll('.reveal');
    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                    observer.unobserve(entry.target);
                }
            });
        },
        { threshold: 0.12, rootMargin: '0px 0px -40px 0px' },
    );
    reveals.forEach((el) => observer.observe(el));

    // Animate rate numbers on load
    document.querySelectorAll('[data-count]').forEach((el) => {
        const target = parseFloat(el.dataset.count);
        const prefix = el.dataset.prefix || '';
        const suffix = el.dataset.suffix || '';
        const duration = 1400;
        const start = performance.now();

        const tick = (now) => {
            const progress = Math.min((now - start) / duration, 1);
            const eased = 1 - Math.pow(1 - progress, 3);
            const value = target * eased;
            el.textContent = prefix + value.toLocaleString('en-IN', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            }) + suffix;
            if (progress < 1) requestAnimationFrame(tick);
        };

        requestAnimationFrame(tick);
    });

    // Smooth anchor scroll
    document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
        anchor.addEventListener('click', (e) => {
            const id = anchor.getAttribute('href');
            if (!id || id === '#') return;
            const target = document.querySelector(id);
            if (!target) return;
            e.preventDefault();
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            links?.classList.remove('open');
        });
    });

    // Feature detail modal
    const featureModal = document.getElementById('featureModal');
    const featuresDataEl = document.getElementById('landing-features-data');
    let featuresData = [];
    let lastFocusedElement = null;

    if (featuresDataEl) {
        try {
            featuresData = JSON.parse(featuresDataEl.textContent || '[]');
        } catch {
            featuresData = [];
        }
    }

    const openFeatureModal = (index) => {
        const feature = featuresData[index];
        if (!feature || !featureModal) return;

        const iconEl = document.getElementById('featureModalIcon');
        const titleEl = document.getElementById('featureModalTitle');
        const summaryEl = document.getElementById('featureModalSummary');
        const listEl = document.getElementById('featureModalList');

        if (iconEl) iconEl.textContent = feature.icon || '';
        if (titleEl) titleEl.textContent = feature.title || '';
        if (summaryEl) summaryEl.textContent = feature.text || '';

        if (listEl) {
            listEl.innerHTML = '';
            (feature.details || []).forEach((point) => {
                const item = document.createElement('li');
                item.textContent = point;
                listEl.appendChild(item);
            });
        }

        lastFocusedElement = document.activeElement;
        featureModal.hidden = false;
        featureModal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';

        const closeBtn = featureModal.querySelector('.feature-modal__close');
        closeBtn?.focus();
    };

    const closeFeatureModal = () => {
        if (!featureModal) return;

        featureModal.hidden = true;
        featureModal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';

        if (lastFocusedElement instanceof HTMLElement) {
            lastFocusedElement.focus();
        }
    };

    document.querySelectorAll('[data-feature-index]').forEach((card) => {
        card.addEventListener('click', () => {
            openFeatureModal(parseInt(card.dataset.featureIndex || '0', 10));
        });
    });

    featureModal?.querySelectorAll('[data-feature-modal-close]').forEach((el) => {
        el.addEventListener('click', closeFeatureModal);
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && featureModal && !featureModal.hidden) {
            closeFeatureModal();
        }
    });
})();
