const dashboardThemeKey = 'codeibex-dashboard-theme';
const dashboardPresetKey = 'codeibex-dashboard-preset';

document.documentElement.dataset.dashboardTheme =
    localStorage.getItem(dashboardThemeKey) || 'light';
document.documentElement.dataset.themePreset =
    localStorage.getItem(dashboardPresetKey) || 'default';

function initMobileNavigation() {
    const toggle = document.getElementById('mobile-nav-toggle');
    const sidebar = document.querySelector('.dashboard-sidebar');
    const backdrop = document.getElementById('mobile-nav-backdrop');
    if (!toggle || !sidebar || !backdrop) return;

    const close = () => {
        sidebar.classList.remove('mobile-open');
        sidebar.style.removeProperty('display');
        backdrop.classList.add('hidden');
        toggle.setAttribute('aria-expanded', 'false');
        toggle.setAttribute('aria-label', 'Open navigation');
        toggle.querySelector('i')?.classList.replace('fa-xmark', 'fa-bars');
    };

    toggle.addEventListener('click', () => {
        const open = !sidebar.classList.contains('mobile-open');
        sidebar.classList.toggle('mobile-open', open);
        if (open) {
            sidebar.style.setProperty('display', 'flex', 'important');
        } else {
            sidebar.style.removeProperty('display');
        }
        backdrop.classList.toggle('hidden', !open);
        toggle.setAttribute('aria-expanded', String(open));
        toggle.setAttribute('aria-label', open ? 'Close navigation' : 'Open navigation');
        toggle.querySelector('i')?.classList.toggle('fa-bars', !open);
        toggle.querySelector('i')?.classList.toggle('fa-xmark', open);
    });

    backdrop.addEventListener('click', close);
    sidebar.querySelectorAll('a').forEach((link) => link.addEventListener('click', close));
    sidebar.querySelector('a[class~="bg-white/20"]')?.scrollIntoView({ block: 'nearest' });
}

function initManagerNavigationGroups() {
    const nav = document.querySelector('.dashboard-shell.manager-panel .dashboard-sidebar nav');
    if (!nav) return;

    const groups = [
        { key: 'restaurant-operations', label: 'Stock & supply', icon: 'fa-boxes-stacked', items: ['Barcodes', 'Returns', 'Purchases', 'Expiry', 'Suppliers', 'Branch stock', 'Wholesale'] },
        { key: 'retail-operations', label: 'Products & services', icon: 'fa-layer-group', items: ['Recipes', 'Delivery', 'Discounts', 'Repairs', 'Profit', 'Retail tools', 'Branches'] },
        { key: 'services-memberships', label: 'Memberships & services', icon: 'fa-id-card', items: ['Memberships', 'Services', 'Commission'] },
    ];

    groups.forEach((definition) => {
        const candidates = [...nav.children].filter((element) => {
            if (element.tagName !== 'A') return false;
            return definition.items.includes(element.querySelector('span')?.textContent?.trim() || '');
        });
        if (candidates.length < 2) return;

        const active = candidates.some((item) => item.className.includes('bg-white/20'));
        const wrapper = document.createElement('div');
        wrapper.className = `nav-dropdown ${active ? 'has-active' : ''}`;
        wrapper.dataset.navGroup = definition.key;

        const heading = document.createElement('p');
        heading.className = 'nav-section-label mt-3 mb-1 px-4';
        heading.textContent = definition.label;

        const trigger = document.createElement('a');
        trigger.href = candidates[0].href;
        trigger.title = definition.label;
        trigger.className = `nav-dropdown-trigger flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-300 ${active ? 'bg-white/20 text-hut-yellow shadow-lg' : 'text-gray-200 hover:bg-white/10'}`;
        trigger.innerHTML = `<i class="fas ${definition.icon} text-lg"></i><span class="flex-1 truncate">${definition.label}</span><i class="fas fa-chevron-right nav-chevron"></i>`;

        const menu = document.createElement('div');
        menu.className = 'nav-dropdown-menu';
        menu.setAttribute('aria-label', definition.label);
        candidates[0].before(heading, wrapper);
        candidates.forEach((item) => {
            item.className = item.className.replace('px-4 py-3', 'px-3 py-2');
            menu.appendChild(item);
        });
        wrapper.append(trigger, menu);
    });

    nav.querySelectorAll('.nav-dropdown').forEach((dropdown) => {
        const menu = dropdown.querySelector('.nav-dropdown-menu');
        const trigger = dropdown.querySelector('.nav-dropdown-trigger');
        if (!menu || !menu.querySelector('a[href]')) {
            const heading = dropdown.previousElementSibling;
            dropdown.remove();
            if (heading?.matches('.nav-section-label') ||
                (heading?.querySelector('.nav-section-label') && !heading.querySelector('a'))) {
                heading.remove();
            }
            return;
        }

        const expanded = dropdown.classList.contains('has-active');
        dropdown.classList.toggle('is-open', expanded);
        trigger?.setAttribute('aria-expanded', String(expanded));
        if (trigger) {
            menu.id ||= `nav-menu-${dropdown.dataset.navGroup || Math.random().toString(36).slice(2)}`;
            trigger.setAttribute('aria-controls', menu.id);
        }
    });

    nav.addEventListener('click', (event) => {
        const trigger = event.target.closest('.nav-dropdown-trigger');
        const dropdown = trigger?.closest('.nav-dropdown');
        const menu = dropdown?.querySelector('.nav-dropdown-menu');
        if (!trigger || !dropdown || !menu?.querySelector('a[href]')) return;

        if (dropdown.classList.contains('is-open')) return;

        event.preventDefault();
        nav.querySelectorAll('.nav-dropdown.is-open').forEach((openDropdown) => {
            openDropdown.classList.remove('is-open');
            openDropdown.querySelector('.nav-dropdown-trigger')?.setAttribute('aria-expanded', 'false');
        });
        dropdown.classList.add('is-open');
        trigger.setAttribute('aria-expanded', 'true');
    });
}

function initPageSearch() {
    const shell = document.getElementById('header-nav-search');
    const input = document.getElementById('header-nav-search-input');
    const results = document.getElementById('header-nav-search-results');
    const nav = document.querySelector('.dashboard-sidebar nav');
    if (!shell || !input || !results || !nav) return;

    const links = [...nav.querySelectorAll('a[href]')]
        .filter((link) => !link.classList.contains('nav-dropdown-trigger'))
        .map((link) => ({ href: link.href, label: link.textContent.trim().replace(/\s+/g, ' ') }))
        .filter((item, index, all) => item.label && all.findIndex((candidate) => candidate.href === item.href) === index);

    const render = (query) => {
        const normalized = query.trim().toLowerCase();
        results.replaceChildren();
        if (!normalized) {
            results.classList.add('hidden');
            return;
        }

        const matches = links.filter((item) => item.label.toLowerCase().includes(normalized)).slice(0, 8);
        if (!matches.length) {
            const empty = document.createElement('p');
            empty.className = 'px-3 py-3 text-xs text-white/60';
            empty.textContent = 'No matching page found.';
            results.appendChild(empty);
        } else {
            matches.forEach((item) => {
                const link = document.createElement('a');
                link.href = item.href;
                link.setAttribute('role', 'option');
                link.textContent = item.label;
                results.appendChild(link);
            });
        }
        results.classList.remove('hidden');
    };

    input.addEventListener('input', () => render(input.value));
    input.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        input.value = '';
        render('');
        input.blur();
    });
    document.addEventListener('click', (event) => {
        if (!shell.contains(event.target)) results.classList.add('hidden');
    });
}

function initNotifications() {
    const button = document.getElementById('notification-menu-button');
    const panel = document.getElementById('notification-menu-panel');
    if (!button || !panel) return;

    button.addEventListener('click', () => {
        const hidden = panel.classList.toggle('hidden');
        button.setAttribute('aria-expanded', String(!hidden));
    });
    document.addEventListener('click', (event) => {
        if (event.target.closest('#notification-menu')) return;
        panel.classList.add('hidden');
        button.setAttribute('aria-expanded', 'false');
    });
}

function initDashboardTheme() {
    const button = document.getElementById('dashboard-theme-toggle');
    if (!button) return;
    const icon = button.querySelector('i');

    const apply = (theme) => {
        const dark = theme === 'dark';
        document.documentElement.dataset.dashboardTheme = dark ? 'dark' : 'light';
        localStorage.setItem(dashboardThemeKey, dark ? 'dark' : 'light');
        icon?.classList.toggle('fa-sun', dark);
        icon?.classList.toggle('fa-moon', !dark);
        button.setAttribute('aria-label', dark ? 'Switch to light theme' : 'Switch to dark theme');
        button.title = dark ? 'Switch to light theme' : 'Switch to dark theme';
    };

    apply(document.documentElement.dataset.dashboardTheme || 'light');
    button.addEventListener('click', () => {
        apply(document.documentElement.dataset.dashboardTheme === 'dark' ? 'light' : 'dark');
    });
}

function initDashboardPreset() {
    const select = document.getElementById('dashboard-theme-preset');
    if (!select) return;

    const presets = {
        default: {},
        ocean: { accent: '#67E8F9', primary: '#0E7490', dark: '#164E63' },
        forest: { accent: '#86EFAC', primary: '#15803D', dark: '#14532D' },
        sunset: { accent: '#FDBA74', primary: '#C2410C', dark: '#7C2D12' },
    };

    const apply = (preset) => {
        const normalized = ['default', 'ocean', 'forest', 'sunset'].includes(preset)
            ? preset
            : 'default';
        const values = presets[normalized];
        Object.entries({
            '--dashboard-accent': values.accent,
            '--dashboard-primary': values.primary,
            '--dashboard-dark': values.dark,
        }).forEach(([property, value]) => {
            if (value) document.body.style.setProperty(property, value);
            else document.body.style.removeProperty(property);
        });
        document.documentElement.dataset.themePreset = normalized;
        document.body.dataset.themePreset = normalized;
        select.value = normalized;
        localStorage.setItem(dashboardPresetKey, normalized);
    };

    apply(document.documentElement.dataset.themePreset || 'default');
    select.addEventListener('change', () => apply(select.value));
}

function initDeleteConfirmation() {
    const modal = document.getElementById('admin-confirm-modal');
    const message = document.getElementById('admin-confirm-message');
    const cancel = document.getElementById('admin-confirm-cancel');
    const submit = document.getElementById('admin-confirm-submit');
    if (!modal || !message || !cancel || !submit) return;
    let activeForm = null;

    const close = () => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        modal.setAttribute('aria-hidden', 'true');
        activeForm = null;
    };

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-confirm]');
        const form = trigger?.matches('form') ? trigger : trigger?.form;
        if (!form) return;
        event.preventDefault();
        activeForm = form;
        message.textContent = trigger.getAttribute('data-confirm') || 'Are you sure you want to continue?';
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        modal.setAttribute('aria-hidden', 'false');
        cancel.focus();
    });

    cancel.addEventListener('click', close);
    submit.addEventListener('click', () => {
        if (!activeForm) return;
        const form = activeForm;
        close();
        form.submit();
    });
    modal.addEventListener('click', (event) => {
        if (event.target === modal) close();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !modal.classList.contains('hidden')) close();
    });
}

document.addEventListener('DOMContentLoaded', () => {
    if (!document.querySelector('.dashboard-shell')) return;
    initMobileNavigation();
    initPageSearch();
    initManagerNavigationGroups();
    initNotifications();
    initDashboardTheme();
    initDashboardPreset();
    initDeleteConfirmation();
});
