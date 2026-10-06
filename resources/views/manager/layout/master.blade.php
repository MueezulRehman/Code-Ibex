<!DOCTYPE html>
<html lang="en">

<head>
    <script>
        document.documentElement.dataset.dashboardTheme = localStorage.getItem('codeibex-dashboard-theme') || 'light';
    </script>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin') â€” {{ $showManagerNav && $restaurant ? $restaurant->name : $platformName }}</title>
    <link rel="icon" href="{{ asset('images/codeibex-mark.svg') }}" type="image/svg+xml">
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700&family=Inter:wght@400;500;600&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        @media (max-width: 767px) {
            .dashboard-shell .dashboard-sidebar.mobile-open {
                display: flex !important;
            }
        }
    </style>
    @stack('styles')
</head>

<body class="dashboard-shell {{ $isManagerPanel ? 'manager-panel' : 'platform-panel' }} min-h-screen flex"
    style="{{ $dashboardStyle }} --dashboard-light: {{ $dashboardLight }}; --dashboard-accent: {{ $dashboardAccent }}; --dashboard-primary: {{ $dashboardPrimary }}; --dashboard-dark: {{ $dashboardDark }}; --dashboard-dark-light: {{ $dashboardDarkPalette['background'] }}; --dashboard-dark-surface: {{ $dashboardDarkPalette['surface'] }}; --dashboard-dark-accent: {{ $dashboardDarkPalette['accent'] }}; --dashboard-dark-primary: {{ $dashboardDarkPalette['primary'] }}; --dashboard-dark-dark: {{ $dashboardDarkPalette['dark'] }};">

    <!-- Modern Glassmorphic Sidebar -->
    @include('manager.layout.sidebar')

    <button type="button" id="mobile-nav-backdrop" class="mobile-nav-backdrop hidden md:hidden"
        aria-label="Close navigation"></button>

    <div class="flex-1 flex flex-col min-w-0">
        <!-- Modern Header -->
        @include('manager.layout.header')


        <!-- Impersonation Banner -->
        @if($impersonatedRestaurant)
            <div
                class="bg-gradient-to-r from-hut-yellow/20 to-amber-100/20 text-hut-dark text-sm px-6 py-3 border-b border-hut-yellow/30 flex items-center justify-between backdrop-blur-sm">
                <span><i class="fas fa-info-circle mr-2"></i>You're managing
                    <strong>{{ $impersonatedRestaurant->name }}</strong> â€” changes affect live data</span>
                <form action="{{ route('admin.restaurants.exit') }}" method="POST" class="inline">
                    @csrf
                    <button class="text-xs font-bold underline hover:no-underline">Exit</button>
                </form>
            </div>
        @endif

        <!-- Success Message -->
        @if(session('success'))
            <div
                class="bg-gradient-to-r from-green-50 to-emerald-50 text-green-700 text-sm px-6 py-3 border-b border-green-200 flex items-center gap-2">
                <i class="fas fa-check-circle"></i>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        <!-- Main Content -->
        {{-- Master layout content section: pages provide only this region. --}}
        <main class="glass-canvas flex-1 p-6 overflow-auto" data-master-section="content">
            @yield('page-content')
        </main>
        @include('manager.layout.footer')

    </div>

    <div id="admin-confirm-modal"
        class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/25 p-4 backdrop-blur-sm"
        aria-hidden="true">
        <div class="w-full max-w-md rounded-2xl border border-white/70 bg-white/85 p-6 shadow-2xl backdrop-blur-xl"
            role="dialog" aria-modal="true" aria-labelledby="admin-confirm-title">
            <div class="mb-5 flex items-start gap-4">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-red-50 text-red-600">
                    <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
                </div>
                <div>
                    <h2 id="admin-confirm-title" class="text-lg font-semibold text-hut-dark">Confirm deletion</h2>
                    <p id="admin-confirm-message" class="mt-1 text-sm leading-6 text-slate-600">Are you sure you want to
                        delete this record?</p>
                </div>
            </div>
            <div class="flex justify-end gap-3">
                <button type="button" id="admin-confirm-cancel"
                    class="rounded-lg border border-slate-200 bg-white/80 px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-white">
                    Cancel
                </button>
                <button type="button" id="admin-confirm-submit"
                    class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700">
                    Delete
                </button>
            </div>
        </div>
    </div>

    @stack('scripts')

    @include('partials.manager-new-order-listener')
    <script>
        (function () {
            var shell = document.querySelector('.dashboard-shell');
            var sidebar = shell?.querySelector('.dashboard-sidebar');
            var toggle = document.getElementById('mobile-nav-toggle');
            var backdrop = document.getElementById('mobile-nav-backdrop');
            if (!shell || !sidebar || !toggle || !backdrop) return;

            var setMobileNavigationOpen = function (open) {
                sidebar.classList.toggle('mobile-open', open);
                if (open) {
                    sidebar.style.setProperty('display', 'flex', 'important');
                    sidebar.style.setProperty('position', 'fixed', 'important');
                    sidebar.style.setProperty('inset', '0 auto 0 0', 'important');
                    sidebar.style.setProperty('z-index', '60', 'important');
                    sidebar.style.setProperty('width', 'min(84vw, 18rem)', 'important');
                    sidebar.style.setProperty('max-height', '100dvh', 'important');
                    sidebar.style.setProperty('overflow-y', 'auto', 'important');
                } else {
                    ['display', 'position', 'inset', 'z-index', 'width', 'max-height', 'overflow-y']
                        .forEach(function (property) { sidebar.style.removeProperty(property); });
                }
                backdrop.classList.toggle('hidden', !open);
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                toggle.setAttribute('aria-label', open ? 'Close navigation' : 'Open navigation');
                var icon = toggle.querySelector('i');
                if (icon) {
                    icon.classList.toggle('fa-bars', !open);
                    icon.classList.toggle('fa-xmark', open);
                }
            };

            document.addEventListener('click', function (event) {
                var target = event.target instanceof Element ? event.target : null;
                if (!target) return;

                if (target.closest('#mobile-nav-toggle')) {
                    event.preventDefault();
                    event.stopImmediatePropagation();
                    setMobileNavigationOpen(toggle.getAttribute('aria-expanded') !== 'true');
                } else if (target.closest('#mobile-nav-backdrop')) {
                    setMobileNavigationOpen(false);
                } else if (target.closest('.dashboard-sidebar a')) {
                    setMobileNavigationOpen(false);
                }
            }, true);

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true') {
                    setMobileNavigationOpen(false);
                }
            });

            window.addEventListener('resize', function () {
                if (window.innerWidth >= 768) setMobileNavigationOpen(false);
            });
        })();

        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('main input[required], main select[required], main textarea[required]').forEach(function (field) {
                field.setAttribute('aria-required', 'true');
                var label = field.id ? document.querySelector('label[for="' + CSS.escape(field.id) + '"]') : field.closest('div')?.querySelector('label');
                if (!label || label.querySelector('[data-required-indicator]')) return;
                var indicator = document.createElement('span');
                indicator.dataset.requiredIndicator = 'true';
                indicator.className = 'ml-1 text-red-600';
                indicator.textContent = '*';
                indicator.title = 'Required field';
                indicator.setAttribute('aria-label', 'Required field');
                label.appendChild(indicator);
            });
        });
    </script>