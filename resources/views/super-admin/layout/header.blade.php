        <header class="dashboard-header glass-header text-white border-b border-white/10 px-6 py-4 shadow-lg backdrop-blur-md">
            <div class="dashboard-header-inner flex justify-between items-center gap-6">
                <div class="dashboard-header-title flex min-w-0 flex-1 items-start gap-2">
                    <button type="button" id="mobile-nav-toggle"
                        class="mt-0.5 inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white/10 text-white hover:bg-white/20 md:hidden"
                        aria-label="Open navigation" aria-expanded="false">
                        <i class="fas fa-bars" aria-hidden="true"></i>
                    </button>
                    <div class="min-w-0">
                        <h1 class="font-display font-bold text-2xl tracking-tight">@yield('title', 'Dashboard')</h1>
                        <p class="text-sm text-gray-300 mt-1">
                            @if($impersonatedRestaurant)
                                <i class="fas fa-search mr-2"></i>Managing {{ $impersonatedRestaurant->name }}
                            @elseif($isSuperAdmin)
                                <i class="fas fa-crown mr-2"></i>Platform Administration
                            @else
                                <i class="fas fa-building mr-2"></i>Business Management
                            @endif
                        </p>
                    </div>
                </div>

                <div class="dashboard-header-actions flex items-center gap-6">
                    <div class="header-nav-search" id="header-nav-search">
                        <i class="fas fa-search" aria-hidden="true"></i>
                        <input id="header-nav-search-input" type="search" placeholder="Search pages..." autocomplete="off" aria-label="Search available pages" aria-controls="header-nav-search-results">
                        <div id="header-nav-search-results" class="header-nav-search-results hidden" role="listbox"></div>
                    </div>
                    <!-- Current Time -->
                    <div class="hidden sm:block text-right text-sm">
                        <p class="font-semibold">{{ now()->format('D, M d') }}</p>
                        <p class="text-gray-400 text-xs">{{ now()->format('g:i A') }}</p>
                    </div>

                    <div class="relative" id="notification-menu">
                        <button type="button" id="notification-menu-button"
                            class="relative flex h-10 w-10 items-center justify-center rounded-xl bg-white/10 text-white hover:bg-white/20"
                            aria-label="Recent notifications" aria-expanded="false">
                            <i class="fas fa-bell"></i>
                            @if($unreadNotificationCount)
                                <span
                                    class="absolute -right-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold text-white">{{ $unreadNotificationCount > 99 ? '99+' : $unreadNotificationCount }}</span>
                            @endif
                        </button>
                        <div id="notification-menu-panel"
                            class="notification-panel
                            absolute right-0 top-12 z-50 hidden w-80 overflow-hidden rounded-xl border border-gray-200 bg-white text-gray-800 shadow-2xl">
                            <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3">
                                <span class="font-semibold">Recent notifications</span>
                                <a href="{{ route($navPrefix . '.notifications.index') }}"
                                    title="View all notifications" aria-label="View all notifications"
                                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-blue-700 hover:bg-blue-50">
                                    <i class="fas fa-eye" aria-hidden="true"></i>
                                </a>
                            </div>
                            <div class="max-h-80 overflow-y-auto">
                                @forelse($recentNotifications as $recentNotification)
                                    <form method="POST"
                                        action="{{ route($navPrefix . '.notifications.read', $recentNotification) }}"
                                        class="border-b border-gray-100">
                                        @csrf
                                        <button type="submit"
                                            class="block w-full px-4 py-3 text-left hover:bg-gray-50 {{ $recentNotification->read_at ? '' : 'bg-amber-50' }}">
                                            <p class="text-sm font-semibold">{{ $recentNotification->title }}</p>
                                            <p class="mt-1 line-clamp-2 text-xs text-gray-600">
                                                {{ $recentNotification->message }}
                                            </p>
                                        </button>
                                    </form>
                                @empty
                                    <p class="px-4 py-6 text-center text-sm text-gray-500">No notifications yet.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    <button type="button" id="dashboard-theme-toggle"
                        class="flex h-10 w-10 items-center justify-center rounded-xl bg-white/10 text-white hover:bg-white/20"
                        aria-label="Switch dashboard theme" title="Switch dashboard theme">
                        <i class="fas fa-moon" aria-hidden="true"></i>
                    </button>
                    <label class="sr-only" for="dashboard-theme-preset">Theme preset</label>
                    <select id="dashboard-theme-preset"
                        class="h-10 rounded-xl border-0 bg-white/10 px-2 text-xs text-white focus:ring-2 focus:ring-white/40">
                        <option value="default" class="text-slate-900">Default</option>
                        <option value="ocean" class="text-slate-900">Ocean</option>
                        <option value="forest" class="text-slate-900">Forest</option>
                        <option value="sunset" class="text-slate-900">Sunset</option>
                    </select>

                    <!-- User Profile -->
                    <div class="flex items-center gap-3 pl-6 border-l border-white/10">
                        <div class="dashboard-user-details text-right">
                            <p class="font-semibold text-sm">{{ auth()->user()->name }}</p>
                            <p class="text-xs text-hut-yellow">
                                @if(auth()->user()->isSuperAdmin())
                                    Super Admin
                                @else
                                    Manager
                                @endif
                            </p>
                        </div>
                        <div
                            class="w-10 h-10 bg-gradient-to-br from-hut-yellow to-amber-600 rounded-xl flex items-center justify-center font-bold text-hut-dark shadow-lg border-2 border-white/20">
                            {{ substr(auth()->user()->name, 0, 1) }}
                        </div>
                    </div>
                </div>
            </div>
        </header>