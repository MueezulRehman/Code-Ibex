            <x-manager.navbar variant="manager"
                class="dashboard-sidebar-nav flex-1 px-3 py-4 space-y-2">
                <!-- Main Dashboard Link -->
                <a href="{{ route($navPrefix . '.dashboard') }}"
                    class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-300 hover:scale-105 {{ request()->routeIs($navPrefix . '.dashboard') ? 'bg-white/20 text-hut-yellow shadow-lg' : 'text-gray-200 hover:bg-white/10' }}">
                    <i class="fas fa-chart-line text-lg"></i>
                    <span>Dashboard</span>
                </a>
                @if($showManagerNav && $moduleEnabled('pos'))
                    <a href="{{ route('manager.pos.index') }}" title="Open point of sale"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-semibold transition-all duration-300 {{ request()->routeIs('manager.pos.*') ? 'bg-white/20 text-hut-yellow shadow-lg' : 'bg-white/5 text-gray-100 hover:bg-white/10' }}">
                        <i class="fas fa-cash-register text-lg"></i>
                        <span class="flex-1 truncate">{{ $restaurant?->getPosConfig()['title'] ?? 'POS' }}</span>
                        <span class="rounded-full bg-emerald-400/20 px-2 py-0.5 text-[10px] uppercase tracking-wide text-emerald-200">Fast</span>
                    </a>
                @endif

                @if(!$showManagerNav)
                    <!-- Platform Section -->
                    <div class="pt-4 pb-1">
                        <p class="nav-section-label">Platform</p>
                    </div>
                    <a href="{{ route('admin.restaurants.index') }}"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-300 {{ request()->routeIs('admin.restaurants.*') && !request()->routeIs('admin.restaurants.create') ? 'bg-white/20 text-hut-yellow shadow-lg' : 'text-gray-200 hover:bg-white/10' }}">
                        <i class="fas fa-store text-lg"></i>
                        <span class="flex-1 truncate">Businesses</span>
                    </a>
                    <a href="{{ route('admin.restaurants.create') }}"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-300 {{ request()->routeIs('admin.restaurants.create') ? 'bg-white/20 text-hut-yellow shadow-lg' : 'text-gray-200 hover:bg-white/10' }}">
                        <i class="fas fa-plus-circle text-lg"></i>
                        <span class="flex-1 truncate">Register Business</span>
                    </a>
                    <a href="{{ route('admin.business-types.index') }}"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-300 {{ request()->routeIs('admin.business-types.*') ? 'bg-white/20 text-hut-yellow shadow-lg' : 'text-gray-200 hover:bg-white/10' }}">
                        <i class="fas fa-tag text-lg"></i>
                        <span class="flex-1 truncate">Business Types</span>
                    </a>
                    <a href="{{ route('admin.modules.index') }}"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-300 {{ request()->routeIs('admin.modules.*') ? 'bg-white/20 text-hut-yellow shadow-lg' : 'text-gray-200 hover:bg-white/10' }}">
                        <i class="fas fa-puzzle-piece text-lg"></i>
                        <span class="flex-1 truncate">Modules</span>
                    </a>
                    <a href="{{ route('admin.subscription-plans.index') }}"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-300 {{ request()->routeIs('admin.subscription-plans.*') ? 'bg-white/20 text-hut-yellow shadow-lg' : 'text-gray-200 hover:bg-white/10' }}">
                        <i class="fas fa-credit-card text-lg"></i>
                        <span class="flex-1 truncate">Plans</span>
                    </a>
                    <a href="{{ route('admin.feedback.index') }}"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-300 {{ request()->routeIs('admin.feedback.*') ? 'bg-white/20 text-hut-yellow shadow-lg' : 'text-gray-200 hover:bg-white/10' }}">
                        <i class="fas fa-comments text-lg"></i>
                        <span class="flex-1 truncate">Feedback</span>
                    </a>
                    <a href="{{ route('admin.notifications.index') }}"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-300 {{ request()->routeIs('admin.notifications.*') ? 'bg-white/20 text-hut-yellow shadow-lg' : 'text-gray-200 hover:bg-white/10' }}">
                        <i class="fas fa-bell text-lg"></i>
                        <span class="flex-1 truncate">Notifications</span>
                    </a>
                    <a href="{{ route('admin.account.edit') }}"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-300 {{ request()->routeIs('admin.account.*') ? 'bg-white/20 text-hut-yellow shadow-lg' : 'text-gray-200 hover:bg-white/10' }}">
                        <i class="fas fa-user-circle text-lg"></i>
                        <span class="flex-1 truncate">My Account</span>
                    </a>
                    <a href="{{ route('admin.platform.settings') }}"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-300 {{ request()->routeIs('admin.platform.settings*') ? 'bg-white/20 text-hut-yellow shadow-lg' : 'text-gray-200 hover:bg-white/10' }}">
                        <i class="fas fa-sliders-h text-lg"></i>
                        <span class="flex-1 truncate">Platform Settings</span>
                    </a>
                @else
                    <!-- Manager Navigation -->
                    <p class="nav-section-label">Daily operations</p>
                    @if($moduleEnabled('orders') || $moduleEnabled('delivery') || $moduleEnabled('reservations') || $moduleEnabled('kitchen-display') || $moduleEnabled('fitting-room'))
                        <div class="nav-dropdown {{ request()->routeIs('manager.orders.*') || request()->routeIs('manager.deliveries.*') || request()->routeIs('manager.reservations.*') || request()->routeIs('manager.kitchen-display.*') || request()->routeIs('manager.fitting-room.*') ? 'has-active' : '' }}">
                            <a href="{{ $moduleEnabled('orders') ? route('manager.orders.index') : route('manager.deliveries.index') }}" title="Operations"
                                class="nav-dropdown-trigger flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-300 {{ request()->routeIs('manager.orders.*') || request()->routeIs('manager.deliveries.*') || request()->routeIs('manager.reservations.*') || request()->routeIs('manager.kitchen-display.*') || request()->routeIs('manager.fitting-room.*') ? 'bg-white/20 text-hut-yellow shadow-lg' : 'text-gray-200 hover:bg-white/10' }}">
                                <i class="fas fa-layer-group text-lg"></i><span class="flex-1 truncate">Operations</span><i class="fas fa-chevron-right nav-chevron"></i>
                            </a>
                            <div class="nav-dropdown-menu" aria-label="Operations">
                                @if($moduleEnabled('orders')) <a href="{{ route('manager.orders.index') }}" class="{{ request()->routeIs('manager.orders.*') ? 'is-active' : '' }}"><i class="fas fa-shopping-cart"></i><span>Orders</span></a> @endif
                                @if($moduleEnabled('delivery')) <a href="{{ route('manager.deliveries.index') }}" class="{{ request()->routeIs('manager.deliveries.*') ? 'is-active' : '' }}"><i class="fas fa-truck"></i><span>Deliveries</span></a> @endif
                                @if($moduleEnabled('reservations')) <a href="{{ route('manager.reservations.index') }}" class="{{ request()->routeIs('manager.reservations.*') ? 'is-active' : '' }}"><i class="fas fa-calendar-days"></i><span>Reservations</span></a> @endif
                                @if($moduleEnabled('kitchen-display')) <a href="{{ route('manager.kitchen-display.index') }}" class="{{ request()->routeIs('manager.kitchen-display.*') ? 'is-active' : '' }}"><i class="fas fa-fire-burner"></i><span>Kitchen screen</span></a> @endif
                                @if($moduleEnabled('fitting-room')) <a href="{{ route('manager.fitting-room.index') }}" class="{{ request()->routeIs('manager.fitting-room.*') ? 'is-active' : '' }}"><i class="fas fa-person-dress"></i><span>Fitting room</span></a> @endif
                            </div>
                        </div>
                    @endif

                    @if($moduleEnabled('pos') || $moduleEnabled('customers') || $moduleEnabled('appointments'))
                        <p class="nav-section-label">Sales operations</p>
                    @endif
                    @if($moduleEnabled('pos') || $moduleEnabled('customers') || $moduleEnabled('appointments'))
                        <div class="nav-dropdown {{ request()->routeIs('manager.sales.*') || request()->routeIs('manager.customers.*') || request()->routeIs('manager.appointments.*') ? 'has-active' : '' }}">
                            <a href="{{ $moduleEnabled('customers') ? route('manager.customers.index') : route('manager.sales.index') }}" title="Sales and customer tools"
                                class="nav-dropdown-trigger flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-300 {{ request()->routeIs('manager.sales.*') || request()->routeIs('manager.customers.*') || request()->routeIs('manager.appointments.*') ? 'bg-white/20 text-hut-yellow shadow-lg' : 'text-gray-200 hover:bg-white/10' }}">
                                <i class="fas fa-cash-register text-lg"></i><span class="flex-1 truncate">Sales</span><i class="fas fa-chevron-right nav-chevron"></i>
                            </a>
                            <div class="nav-dropdown-menu" aria-label="Sales and customer tools">
                                @if($moduleEnabled('pos')) <a href="{{ route('manager.sales.index') }}" class="{{ request()->routeIs('manager.sales.*') ? 'is-active' : '' }}"><i class="fas fa-chart-bar"></i><span>Sales history</span></a> @endif
                                @if($moduleEnabled('customers')) <a href="{{ route('manager.customers.index') }}" class="{{ request()->routeIs('manager.customers.*') ? 'is-active' : '' }}"><i class="fas fa-user-friends"></i><span>Customers</span></a> @endif
                                @if($moduleEnabled('appointments')) <a href="{{ route('manager.appointments.index') }}" class="{{ request()->routeIs('manager.appointments.*') ? 'is-active' : '' }}"><i class="fas fa-calendar-check"></i><span>Appointments</span></a> @endif
                            </div>
                        </div>
                    @endif
                    @if($moduleEnabled('memberships'))
                        <a href="{{ route('manager.gym.index') }}"
                            class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-300 {{ request()->routeIs('manager.gym.*') ? 'bg-white/20 text-hut-yellow shadow-lg' : 'text-gray-200 hover:bg-white/10' }}">
                            <i class="fas fa-id-card text-lg"></i>
                            <span class="flex-1 truncate">Memberships</span>
                        </a>
                    @endif
                    @if($moduleEnabled('service-packages'))
                        <a href="{{ route('manager.service-packages.index') }}"
                            class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-300 {{ request()->routeIs('manager.service-packages.*') ? 'bg-white/20 text-hut-yellow shadow-lg' : 'text-gray-200 hover:bg-white/10' }}">
                            <i class="fas fa-spa text-lg"></i>
                            <span class="flex-1 truncate">Services</span>
                        </a>
                    @endif
                    @if($moduleEnabled('commissions'))
                        <a href="{{ route('manager.commissions.index') }}"
                            class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-300 {{ request()->routeIs('manager.commissions.*') ? 'bg-white/20 text-hut-yellow shadow-lg' : 'text-gray-200 hover:bg-white/10' }}">
                            <i class="fas fa-percent text-lg"></i>
                            <span class="flex-1 truncate">Commission</span>
                        </a>
                    @endif
                    @if($moduleEnabled('recipes') || $moduleEnabled('production-batches'))
                        <a href="{{ route('manager.recipes.index') }}"
                            class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-300 {{ request()->routeIs('manager.recipes.*') ? 'bg-white/20 text-hut-yellow shadow-lg' : 'text-gray-200 hover:bg-white/10' }}">
                            <i class="fas fa-book-open text-lg"></i>
                            <span class="flex-1 truncate">Recipes</span>
                        </a>
                    @endif

                    @if($moduleEnabled('sales-returns'))
                        <a href="{{ route('manager.sales-returns.index') }}"
                            class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-300 {{ request()->routeIs('manager.sales-returns.*') ? 'bg-white/20 text-hut-yellow shadow-lg' : 'text-gray-200 hover:bg-white/10' }}">
                            <i class="fas fa-rotate-left text-lg"></i>
                            <span class="flex-1 truncate">Returns</span>
                        </a>
                    @endif

                    @if($moduleEnabled('purchasing'))
                        <a href="{{ route('manager.purchasing.index') }}"
                            class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-300 {{ request()->routeIs('manager.purchasing.*') ? 'bg-white/20 text-hut-yellow shadow-lg' : 'text-gray-200 hover:bg-white/10' }}">
                            <i class="fas fa-truck-ramp-box text-lg"></i>
                            <span class="flex-1 truncate">Purchases</span>
                        </a>
                    @endif
                    @if($moduleEnabled('expiry-tracking'))
                        <a href="{{ route('manager.expiry-tracking.index') }}"
                            class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-300 {{ request()->routeIs('manager.expiry-tracking.*') ? 'bg-white/20 text-hut-yellow shadow-lg' : 'text-gray-200 hover:bg-white/10' }}">
                            <i class="fas fa-calendar-days text-lg"></i>
                            <span class="flex-1 truncate">Expiry</span>
                        </a>
                    @endif
                    @if($moduleEnabled('delivery-zones'))
                        <a href="{{ route('manager.delivery-zones.index') }}"
                            class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-300 {{ request()->routeIs('manager.delivery-zones.*') ? 'bg-white/20 text-hut-yellow shadow-lg' : 'text-gray-200 hover:bg-white/10' }}">
                            <i class="fas fa-map-location-dot text-lg"></i>
                            <span class="flex-1 truncate">Delivery</span>
                        </a>
                    @endif
                    @if($moduleEnabled('coupons'))
                        <a href="{{ route('manager.coupons.index') }}"
                            class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-300 {{ request()->routeIs('manager.coupons.*') ? 'bg-white/20 text-hut-yellow shadow-lg' : 'text-gray-200 hover:bg-white/10' }}">
                            <i class="fas fa-ticket text-lg"></i>
                            <span class="flex-1 truncate">Discounts</span>
                        </a>
                    @endif
                    @if($moduleEnabled('suppliers'))
                        <a href="{{ route('manager.suppliers.index') }}"
                            class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-300 {{ request()->routeIs('manager.suppliers.*') ? 'bg-white/20 text-hut-yellow shadow-lg' : 'text-gray-200 hover:bg-white/10' }}">
                            <i class="fas fa-building text-lg"></i>
                            <span class="flex-1 truncate">Suppliers</span>
                        </a>
                    @endif
                    @if($moduleEnabled('warranty') || $moduleEnabled('repairs'))
                        <a href="{{ route('manager.service-cases.index') }}"
                            class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-300 {{ request()->routeIs('manager.service-cases.*') ? 'bg-white/20 text-hut-yellow shadow-lg' : 'text-gray-200 hover:bg-white/10' }}">
                            <i class="fas fa-screwdriver-wrench text-lg"></i>
                            <span class="flex-1 truncate">Repairs</span>
                        </a>
                    @endif
                    @if($moduleEnabled('barcode-labels'))
                        <a href="{{ route('manager.barcode-labels.index') }}"
                            class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-300 {{ request()->routeIs('manager.barcode-labels.*') ? 'bg-white/20 text-hut-yellow shadow-lg' : 'text-gray-200 hover:bg-white/10' }}"><i
                                class="fas fa-barcode text-lg"></i>                                <span class="flex-1 truncate">Barcodes</span></a>
                    @endif
                    @if($moduleEnabled('profit-margins'))
                        <a href="{{ route('manager.profit-margins.index') }}"
                            class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-300 {{ request()->routeIs('manager.profit-margins.*') ? 'bg-white/20 text-hut-yellow shadow-lg' : 'text-gray-200 hover:bg-white/10' }}"><i
                                class="fas fa-chart-line text-lg"></i><span class="flex-1 truncate">Profit</span></a>
                    @endif
                    @if($moduleEnabled('brands') || $moduleEnabled('collections') || $moduleEnabled('loyalty') || $moduleEnabled('stock-transfers') || $moduleEnabled('trade-ins') || $moduleEnabled('installments'))
                        <a href="{{ route('manager.retail-operations.index') }}"
                            class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-300 {{ request()->routeIs('manager.retail-operations.*') ? 'bg-white/20 text-hut-yellow shadow-lg' : 'text-gray-200 hover:bg-white/10' }}"><i
                                class="fas fa-toolbox text-lg"></i><span class="flex-1 truncate">Retail tools</span></a>
                    @endif
                    @if($moduleEnabled('stock-transfers'))
                        <a href="{{ route('manager.branches.index') }}"
                            class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-300 {{ request()->routeIs('manager.branches.*') ? 'bg-white/20 text-hut-yellow shadow-lg' : 'text-gray-200 hover:bg-white/10' }}">
                            <i class="fas fa-code-branch text-lg"></i>
                            <span class="flex-1 truncate">Branches</span>
                        </a>
                        <a href="{{ route('manager.branch-inventory.index') }}"
                            class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-300 {{ request()->routeIs('manager.branch-inventory.*') ? 'bg-white/20 text-hut-yellow shadow-lg' : 'text-gray-200 hover:bg-white/10' }}">
                            <i class="fas fa-boxes-stacked text-lg"></i>
                            <span class="flex-1 truncate">Branch stock</span>
                        </a>
                    @endif
                    @if($moduleEnabled('wholesale-price-lists') || $moduleEnabled('sales-representatives'))
                        <a href="{{ route('manager.wholesale.index') }}"
                            class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-300 {{ request()->routeIs('manager.wholesale.*') ? 'bg-white/20 text-hut-yellow shadow-lg' : 'text-gray-200 hover:bg-white/10' }}">
                            <i class="fas fa-scale-balanced text-lg"></i>
                            <span class="flex-1 truncate">Wholesale</span>
                        </a>
                    @endif
                    <!-- Stock Analysis Link -->
                    @if($moduleEnabled('stock'))
                        <div class="pt-4 pb-1">
                            <p class="nav-section-label">Stock overview</p>
                        </div>
                        <div class="nav-dropdown {{ request()->routeIs('manager.stock-analysis.*') || request()->routeIs('manager.stock.*') || request()->routeIs('manager.item-sales.*') ? 'has-active' : '' }}">
                            <a href="{{ route('manager.stock.index') }}" title="Inventory and stock tools"
                                class="nav-dropdown-trigger flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-300 {{ request()->routeIs('manager.stock-analysis.*') || request()->routeIs('manager.stock.*') || request()->routeIs('manager.item-sales.*') ? 'bg-white/20 text-hut-yellow shadow-lg' : 'text-gray-200 hover:bg-white/10' }}">
                                <i class="fas fa-boxes-stacked text-lg"></i><span class="flex-1 truncate">Stock</span><i class="fas fa-chevron-right nav-chevron"></i>
                            </a>
                            <div class="nav-dropdown-menu" aria-label="Inventory and stock tools">
                                <a href="{{ route('manager.stock-analysis.index') }}" class="{{ request()->routeIs('manager.stock-analysis.*') ? 'is-active' : '' }}"><i class="fas fa-chart-pie"></i><span>Stock report</span></a>
                                <a href="{{ route('manager.stock.index') }}" class="{{ request()->routeIs('manager.stock.*') ? 'is-active' : '' }}"><i class="fas fa-boxes"></i><span>Stock items</span></a>
                                @if($moduleEnabled('item-sales')) <a href="{{ route('manager.item-sales.index') }}" class="{{ request()->routeIs('manager.item-sales.*') ? 'is-active' : '' }}"><i class="fas fa-tags"></i><span>Item sales</span></a> @endif
                            </div>
                        </div>
                    @endif

                    @if($moduleEnabled('medical'))
                        <div class="pt-4 pb-1">
                            <p class="nav-section-label">Healthcare</p>
                        </div>
                        <div class="nav-dropdown {{ request()->routeIs('manager.medicines.*') || request()->routeIs('manager.purchases.*') || request()->routeIs('manager.controlled-medicines.*') || request()->routeIs('manager.insurance.*') ? 'has-active' : '' }}">
                            <a href="{{ route('manager.medicines.index') }}" title="Medical management"
                                class="nav-dropdown-trigger flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-300 {{ request()->routeIs('manager.medicines.*') || request()->routeIs('manager.purchases.*') || request()->routeIs('manager.controlled-medicines.*') || request()->routeIs('manager.insurance.*') ? 'bg-white/20 text-hut-yellow shadow-lg' : 'text-gray-200 hover:bg-white/10' }}">
                                <i class="fas fa-kit-medical text-lg"></i><span class="flex-1 truncate">Health</span><i class="fas fa-chevron-right nav-chevron"></i>
                            </a>
                            <div class="nav-dropdown-menu" aria-label="Medical management">
                                <a href="{{ route('manager.medicines.index') }}" class="{{ request()->routeIs('manager.medicines.*') ? 'is-active' : '' }}"><i class="fas fa-pills"></i><span>Medicines</span></a>
                                <a href="{{ route('manager.purchases.index') }}" class="{{ request()->routeIs('manager.purchases.*') ? 'is-active' : '' }}"><i class="fas fa-box-open"></i><span>Purchases</span></a>
                        @if($moduleEnabled('controlled-medicines'))
                            <a href="{{ route('manager.controlled-medicines.index') }}" class="{{ request()->routeIs('manager.controlled-medicines.*') ? 'is-active' : '' }}"><i class="fas fa-file-prescription"></i><span>Controlled medicines</span></a>
                        @endif

                        @if($moduleEnabled('hospital-departments'))
                            <div class="nav-dropdown {{ request()->routeIs('manager.departments.*') ? 'has-active' : '' }}">
                                <a href="{{ route('manager.departments.index') }}" title="Hospital departments"
                                    class="nav-dropdown-trigger flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-300 {{ request()->routeIs('manager.departments.*') ? 'bg-white/20 text-hut-yellow shadow-lg' : 'text-gray-200 hover:bg-white/10' }}">
                                    <i class="fas fa-building-columns text-lg"></i><span class="flex-1 truncate">Departments</span>
                                </a>
                            </div>
                        @endif
                        @if($moduleEnabled('hospital-admissions'))
                            <div class="nav-dropdown {{ request()->routeIs('manager.admissions.*') ? 'has-active' : '' }}">
                                <a href="{{ route('manager.admissions.index') }}" title="Hospital admissions"
                                    class="nav-dropdown-trigger flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-300 {{ request()->routeIs('manager.admissions.*') ? 'bg-white/20 text-hut-yellow shadow-lg' : 'text-gray-200 hover:bg-white/10' }}">
                                    <i class="fas fa-bed-pulse text-lg"></i><span class="flex-1 truncate">Admissions</span>
                                </a>
                            </div>
                            <div class="nav-dropdown {{ request()->routeIs('manager.hospital-reports.*') ? 'has-active' : '' }}">
                                <a href="{{ route('manager.hospital-reports.index') }}" title="Hospital operational reports"
                                    class="nav-dropdown-trigger flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-300 {{ request()->routeIs('manager.hospital-reports.*') ? 'bg-white/20 text-hut-yellow shadow-lg' : 'text-gray-200 hover:bg-white/10' }}">
                                    <i class="fas fa-chart-line text-lg"></i><span class="flex-1 truncate">Hospital Reports</span>
                                </a>
                            </div>
                        @endif
                        @if($moduleEnabled('hospital-wards-beds'))
                            <div class="nav-dropdown {{ request()->routeIs('manager.wards.*') ? 'has-active' : '' }}">
                                <a href="{{ route('manager.wards.index') }}" title="Hospital wards and beds"
                                    class="nav-dropdown-trigger flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-300 {{ request()->routeIs('manager.wards.*') ? 'bg-white/20 text-hut-yellow shadow-lg' : 'text-gray-200 hover:bg-white/10' }}">
                                    <i class="fas fa-bed text-lg"></i><span class="flex-1 truncate">Wards &amp; Beds</span>
                                </a>
                            </div>
                        @endif
                        @if($moduleEnabled('hospital-nursing'))
                            <div class="nav-dropdown {{ request()->routeIs('manager.nursing.*') ? 'has-active' : '' }}">
                                <a href="{{ route('manager.nursing.index') }}" title="Nursing assignments"
                                    class="nav-dropdown-trigger flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-300 {{ request()->routeIs('manager.nursing.*') ? 'bg-white/20 text-hut-yellow shadow-lg' : 'text-gray-200 hover:bg-white/10' }}">
                                    <i class="fas fa-user-nurse text-lg"></i><span class="flex-1 truncate">Nursing</span>
                                </a>
                            </div>
                        @endif
                        @if($moduleEnabled('hospital-staff-rosters'))
                            <div class="nav-dropdown {{ request()->routeIs('manager.staff-rosters.*') ? 'has-active' : '' }}">
                                <a href="{{ route('manager.staff-rosters.index') }}" title="Hospital staff rosters"
                                    class="nav-dropdown-trigger flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-300 {{ request()->routeIs('manager.staff-rosters.*') ? 'bg-white/20 text-hut-yellow shadow-lg' : 'text-gray-200 hover:bg-white/10' }}">
                                    <i class="fas fa-calendar-days text-lg"></i><span class="flex-1 truncate">Staff Rosters</span>
                                </a>
                            </div>
                        @endif
                        @if($moduleEnabled('hospital-vital-signs'))
                            <div class="nav-dropdown {{ request()->routeIs('manager.vitals.*') ? 'has-active' : '' }}">
                                <a href="{{ route('manager.vitals.index') }}" title="Vital signs"
                                    class="nav-dropdown-trigger flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-300 {{ request()->routeIs('manager.vitals.*') ? 'bg-white/20 text-hut-yellow shadow-lg' : 'text-gray-200 hover:bg-white/10' }}">
                                    <i class="fas fa-heart-pulse text-lg"></i><span class="flex-1 truncate">Vital Signs</span>
                                </a>
                            </div>
                        @endif
                        @if($moduleEnabled('hospital-clinical-notes'))
                            <div class="nav-dropdown {{ request()->routeIs('manager.hospital-clinical-notes.*') ? 'has-active' : '' }}">
                                <a href="{{ route('manager.hospital-clinical-notes.index') }}" title="Clinical notes"
                                    class="nav-dropdown-trigger flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-300 {{ request()->routeIs('manager.hospital-clinical-notes.*') ? 'bg-white/20 text-hut-yellow shadow-lg' : 'text-gray-200 hover:bg-white/10' }}">
                                    <i class="fas fa-notes-medical text-lg"></i><span class="flex-1 truncate">Clinical Notes</span>
                                </a>
                            </div>
                        @endif
                        @if($moduleEnabled('hospital-care-plans'))
                            <div class="nav-dropdown {{ request()->routeIs('manager.hospital-care-plans.*') ? 'has-active' : '' }}">
                                <a href="{{ route('manager.hospital-care-plans.index') }}" title="Inpatient care plans"
                                    class="nav-dropdown-trigger flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-300 {{ request()->routeIs('manager.hospital-care-plans.*') ? 'bg-white/20 text-hut-yellow shadow-lg' : 'text-gray-200 hover:bg-white/10' }}">
                                    <i class="fas fa-clipboard-list text-lg"></i><span class="flex-1 truncate">Care Plans</span>
                                </a>
                            </div>
                        @endif
                        @if($moduleEnabled('hospital-laboratory'))
                            <div class="nav-dropdown {{ request()->routeIs('manager.laboratory.*') ? 'has-active' : '' }}">
                                <a href="{{ route('manager.laboratory.index') }}" title="Laboratory"
                                    class="nav-dropdown-trigger flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-300 {{ request()->routeIs('manager.laboratory.*') ? 'bg-white/20 text-hut-yellow shadow-lg' : 'text-gray-200 hover:bg-white/10' }}">
                                    <i class="fas fa-flask text-lg"></i><span class="flex-1 truncate">Laboratory</span>
                                </a>
                            </div>
                        @endif
                        @if($moduleEnabled('hospital-radiology'))
                            <div class="nav-dropdown {{ request()->routeIs('manager.radiology.*') ? 'has-active' : '' }}">
                                <a href="{{ route('manager.radiology.index') }}" title="Radiology"
                                    class="nav-dropdown-trigger flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-300 {{ request()->routeIs('manager.radiology.*') ? 'bg-white/20 text-hut-yellow shadow-lg' : 'text-gray-200 hover:bg-white/10' }}">
                                    <i class="fas fa-x-ray text-lg"></i><span class="flex-1 truncate">Radiology</span>
                                </a>
                            </div>
                        @endif
                        @if($moduleEnabled('hospital-operating-theatre'))
                            <div class="nav-dropdown {{ request()->routeIs('manager.operating-theatre.*') ? 'has-active' : '' }}">
                                <a href="{{ route('manager.operating-theatre.index') }}" title="Operating theatre"
                                    class="nav-dropdown-trigger flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-300 {{ request()->routeIs('manager.operating-theatre.*') ? 'bg-white/20 text-hut-yellow shadow-lg' : 'text-gray-200 hover:bg-white/10' }}">
                                    <i class="fas fa-person-surgery text-lg"></i><span class="flex-1 truncate">Operating Theatre</span>
                                </a>
                            </div>
                        @endif
                        @if($moduleEnabled('hospital-billing'))
                            <div class="nav-dropdown {{ request()->routeIs('manager.hospital-billing.*') ? 'has-active' : '' }}">
                                <a href="{{ route('manager.hospital-billing.index') }}" title="Hospital billing"
                                    class="nav-dropdown-trigger flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-300 {{ request()->routeIs('manager.hospital-billing.*') ? 'bg-white/20 text-hut-yellow shadow-lg' : 'text-gray-200 hover:bg-white/10' }}">
                                    <i class="fas fa-file-invoice-dollar text-lg"></i><span class="flex-1 truncate">Billing</span>
                                </a>
                                <a href="{{ route('manager.hospital-billing.insurance.index') }}" class="mt-1 flex items-center gap-3 rounded-xl px-4 py-2 text-xs text-gray-300 hover:bg-white/10"><i class="fas fa-id-card"></i><span>Patient insurance</span></a>
                            </div>
                        @endif
                        @if($moduleEnabled('hospital-medication'))
                            <div class="nav-dropdown {{ request()->routeIs('manager.hospital-medication.*') ? 'has-active' : '' }}">
                                <a href="{{ route('manager.hospital-medication.index') }}" title="Inpatient medication"
                                    class="nav-dropdown-trigger flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-300 {{ request()->routeIs('manager.hospital-medication.*') ? 'bg-white/20 text-hut-yellow shadow-lg' : 'text-gray-200 hover:bg-white/10' }}">
                                    <i class="fas fa-pills text-lg"></i><span class="flex-1 truncate">Medication</span>
                                </a>
                            </div>
                        @endif
                        @if($moduleEnabled('insurance'))
                            <a href="{{ route('manager.insurance.index') }}" class="{{ request()->routeIs('manager.insurance.*') ? 'is-active' : '' }}"><i class="fas fa-shield-heart"></i><span>Insurance</span></a>
                                </div>
                            </div>
                        @endif
                    @endif

                    @if($moduleEnabled('custom-fields') || $moduleEnabled('custom-workflows'))
                        <div class="nav-dropdown {{ request()->routeIs('manager.customization.*') ? 'has-active' : '' }}">
                            <a href="{{ route('manager.customization.index') }}" title="Custom business configuration"
                                class="nav-dropdown-trigger flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-300 {{ request()->routeIs('manager.customization.*') ? 'bg-white/20 text-hut-yellow shadow-lg' : 'text-gray-200 hover:bg-white/10' }}">
                                <i class="fas fa-wand-magic-sparkles text-lg"></i><span class="flex-1 truncate">Custom fields</span><i class="fas fa-chevron-right nav-chevron"></i>
                            </a>
                            <div class="nav-dropdown-menu" aria-label="Custom business configuration">
                                <a href="{{ route('manager.customization.index') }}" class="{{ request()->routeIs('manager.customization.*') ? 'is-active' : '' }}"><i class="fas fa-sliders"></i><span>Fields & workflows</span></a>
                            </div>
                        </div>
                    @endif

                    @if($moduleEnabled('menu') || $moduleEnabled('categories') || $moduleEnabled('deals'))
                        <div class="pt-4 pb-1">
                            <p class="nav-section-label">Products & menu</p>
                        </div>
                        <div class="nav-dropdown {{ request()->routeIs('manager.menu-items.*') || request()->routeIs('manager.categories.*') || request()->routeIs('manager.deals.*') ? 'has-active' : '' }}">
                            <a href="{{ $moduleEnabled('menu') ? route('manager.menu-items.index') : route('manager.categories.index') }}" title="Menu management"
                                class="nav-dropdown-trigger flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-300 {{ request()->routeIs('manager.menu-items.*') || request()->routeIs('manager.categories.*') || request()->routeIs('manager.deals.*') ? 'bg-white/20 text-hut-yellow shadow-lg' : 'text-gray-200 hover:bg-white/10' }}">
                                <i class="fas fa-utensils text-lg"></i><span class="flex-1 truncate">Menu</span><i class="fas fa-chevron-right nav-chevron"></i>
                            </a>
                            <div class="nav-dropdown-menu" aria-label="Menu management">
                                @if($moduleEnabled('menu')) <a href="{{ route('manager.menu-items.index') }}" class="{{ request()->routeIs('manager.menu-items.*') ? 'is-active' : '' }}"><i class="fas fa-utensils"></i><span>Menu items</span></a> @endif
                                @if($moduleEnabled('categories')) <a href="{{ route('manager.categories.index') }}" class="{{ request()->routeIs('manager.categories.*') ? 'is-active' : '' }}"><i class="fas fa-folder-open"></i><span>Categories</span></a> @endif
                                @if($moduleEnabled('deals')) <a href="{{ route('manager.deals.index') }}" class="{{ request()->routeIs('manager.deals.*') ? 'is-active' : '' }}"><i class="fas fa-gift"></i><span>Deals</span></a> @endif
                            </div>
                        </div>
                    @endif

                    @if($moduleEnabled('cashbook') || $moduleEnabled('expenses'))
                        <div class="pt-4 pb-1">
                            <p class="nav-section-label">Finance</p>
                        </div>
                        <div class="nav-dropdown {{ request()->routeIs('manager.cashbook.*') || request()->routeIs('manager.expenses.*') ? 'has-active' : '' }}">
                            <a href="{{ $moduleEnabled('cashbook') ? route('manager.cashbook.index') : route('manager.expenses.index') }}" title="Financial tools"
                                class="nav-dropdown-trigger flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-300 {{ request()->routeIs('manager.cashbook.*') || request()->routeIs('manager.expenses.*') ? 'bg-white/20 text-hut-yellow shadow-lg' : 'text-gray-200 hover:bg-white/10' }}">
                                <i class="fas fa-wallet text-lg"></i><span class="flex-1 truncate">Money</span><i class="fas fa-chevron-right nav-chevron"></i>
                            </a>
                            <div class="nav-dropdown-menu" aria-label="Financial tools">
                                @if($moduleEnabled('cashbook')) <a href="{{ route('manager.cashbook.index') }}" class="{{ request()->routeIs('manager.cashbook.*') ? 'is-active' : '' }}"><i class="fas fa-money-bill-wave"></i><span>Cashbook</span></a> @endif
                                @if($moduleEnabled('expenses')) <a href="{{ route('manager.expenses.index') }}" class="{{ request()->routeIs('manager.expenses.*') ? 'is-active' : '' }}"><i class="fas fa-receipt"></i><span>Expenses</span></a> @endif
                            </div>
                        </div>
                    @endif

                    @if($moduleEnabled('hr') || $moduleEnabled('staff') || $moduleEnabled('attendance') || $moduleEnabled('salary'))
                        <div class="pt-4 pb-1">
                            <p class="nav-section-label">People</p>
                        </div>
                        <div class="nav-dropdown {{ request()->routeIs('manager.staff.*') || request()->routeIs('manager.attendance.*') ? 'has-active' : '' }}">
                            <a href="{{ route('manager.staff.index') }}" title="Staff and attendance"
                                class="nav-dropdown-trigger flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-300 {{ request()->routeIs('manager.staff.*') || request()->routeIs('manager.attendance.*') ? 'bg-white/20 text-hut-yellow shadow-lg' : 'text-gray-200 hover:bg-white/10' }}">
                                <i class="fas fa-users-gear text-lg"></i>
                                <span class="flex-1 truncate">Staff</span>
                                <i class="fas fa-chevron-right nav-chevron"></i>
                            </a>
                            <div class="nav-dropdown-menu" aria-label="People management">
                                @if($moduleEnabled('hr') || $moduleEnabled('staff'))
                                    <a href="{{ route('manager.staff.index') }}" class="{{ request()->routeIs('manager.staff.*') ? 'is-active' : '' }}">
                                        <i class="fas fa-users"></i><span>Staff</span>
                                    </a>
                                @endif
                                @if($moduleEnabled('hr') || $moduleEnabled('attendance'))
                                    <a href="{{ route('manager.attendance.index') }}" class="{{ request()->routeIs('manager.attendance.*') ? 'is-active' : '' }}">
                                        <i class="fas fa-calendar-check"></i><span>Attendance</span>
                                    </a>
                                @endif
                            </div>
                        </div>
                    @endif

                    @if($moduleEnabled('theme'))
                        <div class="pt-4 pb-1">
                            <p class="nav-section-label">Business settings</p>
                        </div>
                        <div class="nav-dropdown {{ request()->routeIs('manager.restaurant.profile.*') || request()->routeIs('manager.business.theme.*') || request()->routeIs('manager.storefront-notice.*') ? 'has-active' : '' }}">
                            <a href="{{ route('manager.restaurant.profile.edit') }}" title="Business settings"
                                class="nav-dropdown-trigger flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-300 {{ request()->routeIs('manager.restaurant.profile.*') || request()->routeIs('manager.business.theme.*') || request()->routeIs('manager.storefront-notice.*') ? 'bg-white/20 text-hut-yellow shadow-lg' : 'text-gray-200 hover:bg-white/10' }}">
                                <i class="fas fa-sliders text-lg"></i>
                                <span class="flex-1 truncate">Business</span>
                                <i class="fas fa-chevron-right nav-chevron"></i>
                            </a>
                            <div class="nav-dropdown-menu" aria-label="Business setup">
                                <a href="{{ route('manager.restaurant.profile.edit') }}" class="{{ request()->routeIs('manager.restaurant.profile.*') ? 'is-active' : '' }}">
                                    <i class="fas fa-building"></i><span>Business profile</span>
                                </a>
                                <a href="{{ route('manager.business.theme.edit') }}" class="{{ request()->routeIs('manager.business.theme.*') ? 'is-active' : '' }}">
                                    <i class="fas fa-palette"></i><span>Theme</span>
                                </a>
                            </div>
                        </div>
                    @endif
                    @if($moduleEnabled('storefront-notices') || $moduleEnabled('theme'))
                        <a href="{{ route('manager.storefront-notice.edit') }}"
                            class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-300 {{ request()->routeIs('manager.storefront-notice.*') ? 'bg-white/20 text-hut-yellow shadow-lg' : 'text-gray-200 hover:bg-white/10' }}">
                            <i class="fas fa-bullhorn text-lg"></i>
                            <span class="flex-1 truncate">Store message</span>
                        </a>
                    @endif
                    <div class="nav-dropdown {{ request()->routeIs('manager.notifications.*') || request()->routeIs('manager.account.*') || request()->routeIs('manager.subscription.*') ? 'has-active' : '' }}">
                        <a href="{{ route('manager.account.edit') }}" title="Account and workspace settings"
                            class="nav-dropdown-trigger flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-300 {{ request()->routeIs('manager.notifications.*') || request()->routeIs('manager.account.*') || request()->routeIs('manager.subscription.*') ? 'bg-white/20 text-hut-yellow shadow-lg' : 'text-gray-200 hover:bg-white/10' }}">
                            <i class="fas fa-user-gear text-lg"></i><span class="flex-1 truncate">Account</span><i class="fas fa-chevron-right nav-chevron"></i>
                        </a>
                        <div class="nav-dropdown-menu" aria-label="Account and workspace settings">
                            <a href="{{ route('manager.notifications.index') }}" class="{{ request()->routeIs('manager.notifications.*') ? 'is-active' : '' }}"><i class="fas fa-bell"></i><span>Notifications</span></a>
                            <a href="{{ route('manager.account.edit') }}" class="{{ request()->routeIs('manager.account.*') ? 'is-active' : '' }}"><i class="fas fa-user-circle"></i><span>My account</span></a>
                            <a href="{{ route('manager.subscription.show') }}" class="{{ request()->routeIs('manager.subscription.*') ? 'is-active' : '' }}"><i class="fas fa-credit-card"></i><span>Subscription</span></a>
                        </div>
                    </div>
                    @if($moduleEnabled('reports'))
                        <div
                            class="nav-dropdown {{ request()->routeIs('manager.reports.*') || request()->routeIs('manager.sales.*') ? 'has-active' : '' }}">
                            <a href="{{ route('manager.reports.index') }}" title="Reports & exports"
                                class="nav-dropdown-trigger flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-300 {{ request()->routeIs('manager.reports.*') ? 'bg-white/20 text-hut-yellow shadow-lg' : 'text-gray-200 hover:bg-white/10' }}">
                                <i class="fas fa-chart-column text-lg"></i>
                                <span class="flex-1 truncate">Reports</span>
                                <i class="fas fa-chevron-right nav-chevron"></i>
                            </a>
                            <div class="nav-dropdown-menu" aria-label="Report actions">
                                <a href="{{ route('manager.reports.index') }}"
                                    class="{{ request()->routeIs('manager.reports.index') ? 'is-active' : '' }}">
                                    <i class="fas fa-table"></i><span>All Reports</span>
                                </a>
                                <a href="{{ route('manager.reports.create') }}"
                                    class="{{ request()->routeIs('manager.reports.create') ? 'is-active' : '' }}">
                                    <i class="fas fa-plus"></i><span>Create Report</span>
                                </a>
                                <a href="{{ route('manager.sales.index') }}"
                                    class="{{ request()->routeIs('manager.sales.*') ? 'is-active' : '' }}">
                                    <i class="fas fa-chart-bar"></i><span>Sales History</span>
                                </a>
                            </div>
                        </div>
                    @endif
                @endif
            </x-manager.navbar>