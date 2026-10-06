    <x-manager.sidebar variant="manager"
        class="dashboard-sidebar glass-dark w-64 text-white shrink-0 hidden md:flex flex-col shadow-2xl overflow-hidden relative">
        <!-- Glassmorphism backdrop -->
        <div class="absolute inset-0 bg-white/5 backdrop-blur-xl pointer-events-none"></div>

        <!-- Content -->
        <div class="relative z-10 flex flex-col h-full">
            <!-- Logo/Brand Section -->
            <div class="p-6 border-b border-white/10 backdrop-blur-md bg-linear-to-r from-white/10 to-transparent">
                <div class="flex items-center gap-3 mb-2">
                    @php
                        $restLogo = null;
                        $logoPath = $restaurant?->logo_path ?: $platformLogo;
                        if (!$restaurant && !$platformLogo) {
                            $restLogo = asset('images/codeibex-mark.svg');
                        } elseif ($logoPath) {
                            $restLogo = asset('storage/' . ltrim($logoPath, '/'));
                        }
                    @endphp

                    @if($restaurant && method_exists($restaurant, 'getPublicUrl'))
                        <a href="{{ $restaurant->getPublicUrl() }}" target="_blank"
                            class="inline-block rounded-xl overflow-hidden group" aria-label="Open public menu">
                            @if($restLogo)
                                <img src="{{ $restLogo }}" alt="{{ $restaurant?->name ?? $platformName }}"
                                    class="w-12 h-12 rounded-xl object-contain bg-white/5 p-1 shadow-lg group-hover:scale-105 transition-transform" />
                            @else
                                <div
                                    class="w-12 h-12 bg-linear-to-br from-hut-yellow to-amber-600 rounded-xl flex items-center justify-center font-display font-bold text-hut-dark shadow-lg">
                                    {{ strtoupper(substr($restaurant?->name ?? 'P', 0, 1)) }}
                                </div>
                            @endif
                        </a>
                    @else
                        @if($restLogo)
                            <img src="{{ $restLogo }}" alt="{{ $restaurant?->name ?? $platformName }}"
                                class="w-12 h-12 rounded-xl object-contain bg-white/5 p-1 shadow-lg">
                        @else
                            <div
                                class="w-12 h-12 bg-linear-to-br from-hut-yellow to-amber-600 rounded-xl flex items-center justify-center font-display font-bold text-hut-dark shadow-lg">
                                {{ strtoupper(substr($restaurant?->name ?? 'P', 0, 1)) }}
                            </div>
                        @endif
                    @endif

                    <div>
                        <p class="font-display font-bold text-lg">{{ $restaurant?->name ?? $platformName }}</p>
                        <p class="text-xs text-hut-yellow font-semibold">
                            {{ $restaurant ? 'Business Management' : $platformTagline }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Navigation -->
    @include('manager.layout.navbar')

            <!-- Logout Button -->
            <div class="logout-panel border-t border-white/10 backdrop-blur-md bg-gradient-to-r from-white/5 to-transparent">
                <form action="{{ route($logoutRoute) }}" method="POST" class="w-full">
                    @csrf
                    <button type="submit"
                        class="logout-button w-full flex items-center gap-3 rounded-lg text-xs font-semibold transition-all duration-300">
                        <i class="fas fa-power-off"></i> <span>Logout</span>
                    </button>
                </form>
            </div>
        </div>
    </x-manager.sidebar>