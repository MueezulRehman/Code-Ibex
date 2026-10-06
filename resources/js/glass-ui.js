const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

function animateDashboardCounters() {
    const counters = document.querySelectorAll('[data-count-up]');
    if (!counters.length) return;

    const animate = (element) => {
        if (element.dataset.counted === 'true') return;

        const original = element.textContent.trim().replace(/\s+/g, ' ');
        const match = original.match(/^(\D*?)(-?[\d,]+(?:\.\d+)?)(\D*)$/);
        if (!match) return;

        const target = Number(match[2].replace(/,/g, ''));
        if (!Number.isFinite(target)) return;

        element.dataset.counted = 'true';
        if (reducedMotion.matches) return;

        const decimals = (match[2].split('.')[1] || '').length;
        const formatter = new Intl.NumberFormat(undefined, {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals,
        });
        const startTime = performance.now();
        const duration = 520;

        const tick = (now) => {
            const progress = Math.min((now - startTime) / duration, 1);
            const easedProgress = 1 - ((1 - progress) ** 3);
            const value = target * easedProgress;
            element.textContent = `${match[1]}${formatter.format(progress === 1 ? target : value)}${match[3]}`;
            if (progress < 1) requestAnimationFrame(tick);
        };

        element.textContent = `${match[1]}${formatter.format(0)}${match[3]}`;
        requestAnimationFrame(tick);
    };

    if (!('IntersectionObserver' in window)) {
        counters.forEach(animate);
        return;
    }

    const observer = new IntersectionObserver((entries, counterObserver) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) return;
            animate(entry.target);
            counterObserver.unobserve(entry.target);
        });
    }, { threshold: 0.25 });

    counters.forEach((counter) => observer.observe(counter));
}

function animateDynamicallyMountedCards() {
    const main = document.querySelector('main');
    if (!main || reducedMotion.matches || !('MutationObserver' in window)) return;

    const cardSelector = [
        '.metric-card',
        '.page-card',
        '.page-table-wrap',
        '[class*="chart-container"]',
        '.pos-item-card',
        'div[class*="bg-white"][class*="rounded"]',
        'section[class*="bg-white"][class*="rounded"]',
        'article[class*="bg-white"][class*="rounded"]',
    ].join(',');

    const animate = (card) => {
        if (card.dataset.glassMounted === 'true') return;
        card.dataset.glassMounted = 'true';
        card.classList.add('glass-card-mounted');
        card.addEventListener('animationend', () => {
            card.classList.remove('glass-card-mounted');
        }, { once: true });
    };

    const mountObserver = 'IntersectionObserver' in window
        ? new IntersectionObserver((entries, observer) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                animate(entry.target);
                observer.unobserve(entry.target);
            });
        }, { threshold: 0.05 })
        : null;

    const collectCards = (node) => {
        if (!(node instanceof Element)) return;
        const cards = [];
        if (node.matches(cardSelector)) cards.push(node);
        cards.push(...node.querySelectorAll(cardSelector));
        cards.forEach((card) => {
            if (mountObserver) mountObserver.observe(card);
            else animate(card);
        });
    };

    const releaseCards = (node) => {
        if (!(node instanceof Element) || !mountObserver) return;
        if (node.matches(cardSelector)) mountObserver.unobserve(node);
        node.querySelectorAll(cardSelector).forEach((card) => mountObserver.unobserve(card));
    };

    const mutationObserver = new MutationObserver((records) => {
        records.forEach((record) => {
            record.removedNodes.forEach(releaseCards);
            record.addedNodes.forEach(collectCards);
        });
    });
    mutationObserver.observe(main, { childList: true, subtree: true });
}

function initializeGlassUI() {
    animateDashboardCounters();
    animateDynamicallyMountedCards();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeGlassUI, { once: true });
} else {
    initializeGlassUI();
}
