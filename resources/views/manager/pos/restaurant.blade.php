@extends('manager.layout.master')
@section('title', $posConfig['title'])

@section('page-content')
    @php
        $resolvePosImage = function (?string $path): ?string {
            if (!$path) {
                return null;
            }
            if (\Illuminate\Support\Str::startsWith($path, ['http://', 'https://'])) {
                return $path;
            }
            if (is_file(public_path('images/' . $path))) {
                return asset('images/' . $path);
            }
            if (is_file(public_path($path))) {
                return asset($path);
            }
            if (is_file(storage_path('app/public/' . $path))) {
                return asset('storage/' . $path);
            }
            return null;
        };
        $dealPosterFiles = collect(glob(public_path('images/deals/*')) ?: [])
            ->filter(fn($path) => is_file($path))
            ->sort()
            ->values();
    @endphp
    @if(isset($errors) && $errors->any())
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <p class="font-semibold mb-1">Could not complete the sale:</p>
            <ul class="list-disc list-inside">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    <div class="pos-workspace grid grid-cols-1 lg:grid-cols-3 gap-4">

        {{-- Menu / item picker --}}
        <div class="lg:col-span-2 space-y-4 min-w-0">
            <div class="glass-strong rounded-2xl p-4 space-y-3">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Restaurant POS</p>
                        <h2 class="font-display font-semibold text-hut-dark text-lg">Search menu &amp; build the bill</h2>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <a href="{{ route('manager.customers.index') }}"
                            class="rounded-full border border-gray-200 bg-gray-50 px-3 py-1.5 text-xs font-semibold text-hut-dark hover:bg-hut-yellow/20">{{ $customers->count() }}
                            customers</a>
                        <button type="button" id="toggle-all-items"
                            class="rounded-full border border-hut-dark/20 bg-hut-dark px-3 py-1.5 text-xs font-semibold text-white hover:bg-gray-800">Show
                            all items</button>
                    </div>
                </div>
                <div class="relative">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                    <input type="search" id="pos-search" autocomplete="off" placeholder="Search by item name or price…"
                        class="w-full rounded-xl border border-gray-200 bg-gray-50 pl-10 pr-4 py-2.5 text-sm focus:border-hut-green focus:bg-white focus:ring-2 focus:ring-hut-green/20 outline-none" />
                </div>
            </div>

            <div class="flex flex-wrap gap-2" id="category-tabs">
                <button type="button" class="cat-tab-btn px-4 py-2 rounded-lg text-sm font-medium bg-hut-dark text-white"
                    data-cat="all">All</button>
                @foreach($categories as $cat)
                    <button type="button"
                        class="cat-tab-btn px-4 py-2 rounded-lg text-sm font-medium bg-white border border-gray-200 hover:bg-gray-50"
                        data-cat="{{ $cat->id }}">{{ $cat->icon }} {{ $cat->name }}</button>
                @endforeach
                @if($deals->count())
                    <button type="button"
                        class="cat-tab-btn px-4 py-2 rounded-lg text-sm font-medium bg-white border border-gray-200 hover:bg-gray-50"
                        data-cat="deals">🎁 Deals</button>
                @endif
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-3" id="item-grid">
                @foreach($categories as $cat)
                    @foreach($cat->availableMenuItems as $item)
                        <button type="button"
                            class="pos-item-card card-hover tilt-3d relative bg-white/70 border border-gray-100 rounded-xl p-3 text-left shadow-sm hover:shadow-md hover:border-hut-yellow transition"
                            data-cat="{{ $cat->id }}" data-id="{{ $item->id }}" data-name="{{ $item->name }}"
                            data-price="{{ (float) ($item->price ?? $item->display_price ?? 0) }}"
                            data-has-sizes="{{ $item->has_sizes ? '1' : '0' }}"
                            data-sizes='{{ $item->has_sizes ? $item->sizes->map(fn($s) => ["label" => $s->size_label, "price" => (float) $s->price])->toJson() : "[]" }}'
                            data-allows-toppings="{{ $item->allows_toppings ? '1' : '0' }}"
                            data-show-modal="{{ ($item->pos_show_line_edit ?? false) || ($cat->pos_show_line_edit ?? false) ? '1' : '0' }}">
                            @php
                                $itemImg = $resolvePosImage($item->image);
                                $itemName = strtolower($item->name . ' ' . $cat->name);
                                $itemIcon = str_contains($itemName, 'pizza') ? 'fa-pizza-slice' : (str_contains($itemName, 'burger') ? 'fa-burger' : (str_contains($itemName, 'pasta') ? 'fa-bowl-food' : 'fa-box-open'));
                            @endphp
                            <div class="mb-2 flex aspect-[4/3] items-center justify-center overflow-hidden rounded-lg bg-gray-50">
                                @if($itemImg)
                                    <img src="{{ $itemImg }}" alt="" loading="lazy" decoding="async"
                                        class="h-full w-full object-contain" />
                                @else
                                    <i class="fas {{ $itemIcon }} text-3xl text-hut-green/50" aria-hidden="true"></i>
                                @endif
                            </div>
                            <p class="font-display font-semibold text-sm text-hut-dark truncate">{{ $item->name }}</p>
                            <p class="text-xs text-gray-400 mt-1">
                                {{ $item->has_sizes ? 'From Rs. ' . number_format($item->display_price) : 'Rs. ' . number_format($item->price) }}
                            </p>
                            @if($item->pos_show_line_edit || $cat->pos_show_line_edit)
                                <span class="absolute right-2 top-2 text-amber-600 text-sm" title="Line-edit enabled"
                                    aria-label="Line-edit enabled">⚑</span>
                            @endif
                        </button>
                    @endforeach
                @endforeach

                @foreach($deals as $deal)
                    <button type="button"
                        class="pos-item-card card-hover tilt-3d bg-hut-yellow/10 border border-hut-yellow/30 rounded-xl p-3 text-left shadow-sm hover:shadow-md transition"
                        data-cat="deals" data-deal="1" data-id="{{ $deal->id }}" data-name="{{ $deal->name }}"
                        data-price="{{ $deal->price }}" data-has-sizes="0" data-sizes="[]" data-allows-toppings="0">
                        @php
                            $dealImg = $resolvePosImage($deal->image);
                            if (!$dealImg) {
                                $dealPoster = $dealPosterFiles->get(max(0, ((int) $deal->deal_number) - 1));
                                $dealImg = $dealPoster ? asset('images/deals/' . basename($dealPoster)) : null;
                            }
                        @endphp
                        <div class="mb-2 flex aspect-[4/3] items-center justify-center overflow-hidden rounded-lg bg-white/70">
                            @if($dealImg)
                                <img src="{{ $dealImg }}" alt="" loading="lazy" decoding="async"
                                    class="h-full w-full object-contain" />
                            @else
                                <i class="fas fa-tags text-3xl text-hut-green/50" aria-hidden="true"></i>
                            @endif
                        </div>
                        <p class="font-display font-semibold text-sm text-hut-dark truncate">🎁 {{ $deal->name }}</p>
                        <p class="text-xs text-hut-yellow-dark mt-1 font-medium">Rs. {{ number_format($deal->price) }}</p>
                    </button>
                @endforeach
            </div>
        </div>

        {{-- Current bill (fixed on the right) --}}
        <div
            class="bg-white rounded-2xl shadow-lg border border-hut-dark/10 p-4 h-fit lg:sticky lg:top-4 lg:max-h-[calc(100vh-6rem)] lg:overflow-y-auto order-first lg:order-none ring-1 ring-hut-yellow/20">
            <div class="flex items-center justify-between mb-3 pb-2 border-b border-gray-100">
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-widest text-hut-yellow-dark">Billing</p>
                    <h2 class="font-display font-bold text-hut-dark text-lg leading-tight">Current Bill</h2>
                </div>
                <span id="cart-count-badge"
                    class="inline-flex h-7 min-w-7 items-center justify-center rounded-full bg-hut-dark px-2 text-xs font-bold text-white">0</span>
            </div>

            <div id="cart-lines" class="space-y-2 max-h-64 overflow-y-auto mb-3">
                <p id="cart-empty" class="text-sm text-gray-400 text-center py-6">No items yet — search or tap a menu item.
                </p>
            </div>

            <div class="border-t border-gray-100 pt-3 space-y-2 text-sm">
                <div class="flex justify-between text-gray-500">
                    <span>Total before discount</span>
                    <span id="cart-subtotal">Rs. 0</span>
                </div>
                <div class="rounded-lg border border-dashed border-amber-200 bg-amber-50/50 p-2 space-y-2">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-amber-800">Bill discount</p>
                    <div class="flex gap-2">
                        <select id="bill-discount-type" name="bill_discount_type" form="checkout-form"
                            class="rounded-lg border border-gray-200 px-2 py-1.5 text-xs bg-white">
                            <option value="percent">%</option>
                            <option value="fixed">Rs</option>
                        </select>
                        <input type="number" id="bill-discount-value" name="bill_discount_value" form="checkout-form"
                            min="0" step="1" value="0" placeholder="0"
                            class="flex-1 rounded-lg border border-gray-200 px-2 py-1.5 text-xs">
                    </div>
                    <div class="flex justify-between text-xs text-amber-900">
                        <span>Discount</span>
                        <span id="bill-discount-amount">− Rs. 0</span>
                    </div>
                </div>
                <div class="flex justify-between font-display font-bold text-lg text-hut-dark">
                    <span>Total after discount</span>
                    <span id="cart-total">Rs. 0</span>
                </div>
                <div class="rounded-lg border border-gray-100 bg-gray-50 p-2 space-y-2">
                    <label for="cash-received"
                        class="block text-[11px] font-semibold uppercase tracking-wide text-gray-500">Cash received</label>
                    <input type="number" id="cash-received" name="amount_received" form="checkout-form" step="1" min="0"
                        placeholder="Leave empty to charge customer debt"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-hut-green focus:ring-hut-green">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-500">Change / balance</span>
                        <span id="cash-summary-text" class="font-semibold text-hut-dark">Rs. 0</span>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-gray-100 bg-gray-50 p-3 space-y-2">
                <div class="flex items-center justify-between">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Customer</p>
                    <button type="button" id="toggle-new-customer"
                        class="text-xs font-semibold text-hut-dark hover:underline">+ Add customer</button>
                </div>
                <input type="search" id="customer-search" placeholder="Search customer by name or phone"
                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm bg-white">
                <div id="customer-search-results"
                    class="hidden max-h-48 overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-sm"></div>
                <select id="customer-select" name="customer_id" form="checkout-form"
                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm bg-white">
                    <option value="">Walk-in customer</option>
                    @foreach(($customers ?? collect()) as $customer)
                        <option value="{{ $customer->id }}" data-name="{{ $customer->name }}"
                            data-phone="{{ $customer->phone }}" data-balance="{{ $customer->balance }}"
                            data-credit-limit="{{ $customer->credit_limit }}"
                            @selected(($savedCustomerId ?? null) == $customer->id)>{{ $customer->name }} •
                            {{ $customer->phone }} @if($customer->balance > 0) (Due Rs.
                            {{ number_format($customer->balance, 2) }}) @endif
                        </option>
                    @endforeach
                </select>
                @if(($branches ?? collect())->isNotEmpty())
                    <select name="branch_id" form="checkout-form" required
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm bg-white">
                        <option value="">Select selling branch</option>
                        @foreach($branches as $branch)
                            <option value="{{ $branch->id }}" @selected(($assignedBranchId ?? null) == $branch->id)>
                        {{ $branch->name }} ({{ $branch->code }})</option>@endforeach
                    </select>
                @endif
                @if(($priceLists ?? collect())->isNotEmpty())
                    <select name="wholesale_price_list_id" form="checkout-form"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm bg-white">
                        <option value="">Standard pricing</option>
                        @foreach($priceLists as $priceList)
                        <option value="{{ $priceList->id }}">{{ $priceList->name }}</option>@endforeach
                    </select>
                @endif
                <form id="new-customer-form" method="POST" action="{{ route('manager.customers.store') }}"
                    class="hidden space-y-2">
                    @csrf
                    <div class="grid gap-2 sm:grid-cols-2">
                        <input type="text" name="name" required placeholder="New customer name"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm bg-white">
                        <input type="text" name="phone" required placeholder="Phone"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm bg-white">
                    </div>
                    <button type="submit"
                        class="w-full rounded-lg border border-hut-yellow/40 bg-hut-yellow/10 px-3 py-2 text-sm font-semibold text-hut-dark hover:bg-hut-yellow/20">Register
                        customer</button>
                </form>
            </div>

            <form id="checkout-form" method="POST" action="{{ route('manager.pos.checkout') }}" class="mt-4 space-y-2">
                @csrf
                <select name="order_type" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
                    <option value="takeaway">Takeaway</option>
                    <option value="dine_in">Dine-in</option>
                    <option value="table">Table Order</option>
                    <option value="delivery">Delivery</option>
                    <option value="online">Online</option>
                </select>
                <div id="table-number-wrapper" class="hidden">
                    <select name="table_number" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
                        <option value="">Select table (optional)</option>
                        @foreach(($tables ?? collect()) as $t)
                            <option value="{{ $t->number ?? $t->label }}">{{ $t->number ?? $t->label }} @if($t->seats) —
                            {{ $t->seats }} seats @endif
                            </option>
                        @endforeach
                    </select>
                </div>
                <input type="text" name="customer_name" placeholder="Customer name (optional)"
                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
                <input type="text" name="customer_phone" placeholder="Phone (optional)"
                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
                <select name="payment_method" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
                    <option value="cash">Cash</option>
                    <option value="online">Online</option>
                </select>
                <textarea name="notes" placeholder="Notes (optional)" rows="2"
                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm"></textarea>
                <input type="hidden" name="cart" id="cart-input">
                <button type="submit" id="checkout-btn" class="btn-accent w-full text-center" disabled>Complete
                    Sale</button>
            </form>
        </div>
    </div>

    {{-- Size/topping picker modal --}}
    <div id="line-edit-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-sm rounded-xl bg-white p-5 shadow-xl">
            <h3 id="line-edit-name" class="mb-4 font-display font-bold text-hut-dark">Edit sale line</h3>
            <div class="space-y-3">
                <div>
                    <label for="line-edit-qty"
                        class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Quantity</label>
                    <input id="line-edit-qty" type="number" min="0.01" step="0.01" value="1"
                        class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-hut-green focus:outline-none">
                </div>
                <div>
                    <label for="line-edit-price"
                        class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Unit price
                        (Rs.)</label>
                    <input id="line-edit-price" type="number" min="0" step="0.01" value="0"
                        class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-hut-green focus:outline-none">
                </div>
                <div class="flex gap-2 pt-1">
                    <button type="button" id="line-edit-cancel"
                        class="flex-1 rounded-lg border border-gray-200 px-3 py-2.5 text-sm font-semibold text-gray-600 hover:bg-gray-50">Cancel</button>
                    <button type="button" id="line-edit-add" class="btn-primary flex-1">Add to Sale</button>
                </div>
            </div>
        </div>
    </div>

    <div id="item-modal" class="fixed inset-0 bg-black/40 hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-xl p-5 w-full max-w-sm">
            <h3 id="modal-item-name" class="font-display font-bold text-hut-dark mb-3">Item</h3>
            <div id="modal-sizes" class="space-y-2 mb-3"></div>
            <div id="modal-toppings" class="space-y-2 mb-3"></div>
            <div class="flex gap-2">
                <button type="button" id="modal-add-btn" class="btn-primary flex-1">Add to Sale</button>
                <button type="button" id="modal-cancel-btn"
                    class="px-4 py-2.5 rounded-lg border border-gray-200 text-sm">Cancel</button>
            </div>
        </div>
    </div>


    {{-- Shared: unpaid balance → customer debt (all business types) --}}
    <div id="debt-confirm-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40">
        <div class="w-full max-w-md rounded-2xl bg-white shadow-xl border border-gray-200 overflow-hidden">
            <div class="bg-amber-600 text-white px-4 py-3">
                <p class="text-[10px] font-bold uppercase tracking-widest text-amber-100">Account debt</p>
                <h3 class="font-display font-bold text-lg">Confirm unpaid balance</h3>
            </div>
            <div class="p-4 space-y-3 text-sm">
                <p class="text-gray-600">Cash received is less than the bill total. Choose the customer whose account should
                    hold the remaining amount.</p>
                <div id="debt-customer-picker-wrap" class="hidden space-y-1">
                    <label class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Select customer</label>
                    <select id="debt-customer-picker"
                        class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm bg-white"></select>
                </div>
                <div class="rounded-xl border border-gray-100 bg-gray-50 p-3 space-y-2">
                    <div class="flex justify-between"><span class="text-gray-500">Customer</span><span
                            id="debt-customer-name" class="font-semibold text-hut-dark">—</span></div>
                    <div class="flex justify-between"><span class="text-gray-500">Phone</span><span id="debt-customer-phone"
                            class="font-medium text-gray-700">—</span></div>
                    <div class="flex justify-between"><span class="text-gray-500">Current balance due</span><span
                            id="debt-customer-balance" class="font-medium text-red-600">Rs. 0</span></div>
                    <div class="flex justify-between border-t border-gray-200 pt-2"><span class="text-gray-500">Bill
                            total</span><span id="debt-bill-total" class="font-semibold">Rs. 0</span></div>
                    <div class="flex justify-between"><span class="text-gray-500">Cash received</span><span
                            id="debt-cash-received" class="font-medium">Rs. 0</span></div>
                    <div class="flex justify-between text-base"><span class="font-semibold text-hut-dark">Amount to
                            debt</span><span id="debt-amount" class="font-bold text-red-600">Rs. 0</span></div>
                    <div class="flex justify-between text-xs text-gray-500"><span>New balance after sale</span><span
                            id="debt-new-balance">Rs. 0</span></div>
                </div>
                <p id="debt-modal-error" class="text-xs text-red-600 hidden"></p>
                <div class="flex gap-2 pt-1">
                    <button type="button" id="debt-cancel"
                        class="flex-1 rounded-lg border border-gray-200 px-3 py-2.5 text-sm font-semibold text-gray-600 hover:bg-gray-50">Cancel</button>
                    <button type="button" id="debt-confirm"
                        class="flex-1 rounded-lg bg-amber-600 px-3 py-2.5 text-sm font-semibold text-white hover:bg-amber-700">Confirm
                        &amp; complete sale</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const cart = [];
            const pkr = (n) => Math.max(0, Math.round((Number(n) || 0) * 100) / 100);
            const pkrFmt = (n) => 'Rs. ' + pkr(n).toLocaleString(undefined, { minimumFractionDigits: 2 });
            let debtConfirmProceed = false;
            const savedCart = @json($savedCart ?? []);
            const highlightedLine = @json($errorHighlight ?? null);
            const toppings = @json($toppings->map(fn($t) => ['id' => $t->id, 'name' => $t->name, 'price' => (float) $t->price]));
            const orderTypeSelect = document.querySelector('select[name="order_type"]');
            const tableNumberWrapper = document.getElementById('table-number-wrapper');
            const customerSelect = document.getElementById('customer-select');
            const customerNameInput = document.querySelector('input[name="customer_name"]');
            const customerPhoneInput = document.querySelector('input[name="customer_phone"]');
            const paymentMethodSelect = document.querySelector('select[name="payment_method"]');
            const cashReceivedInput = document.getElementById('cash-received');
            const cashSummaryText = document.getElementById('cash-summary-text');
            let currentTotal = 0;

            function toggleTableField() {
                if (!tableNumberWrapper || !orderTypeSelect) return;
                tableNumberWrapper.style.display = orderTypeSelect.value === 'table' ? 'block' : 'none';
            }

            if (orderTypeSelect) {
                orderTypeSelect.addEventListener('change', toggleTableField);
                toggleTableField();
            }

            if (customerSelect && customerNameInput && customerPhoneInput) {
                customerSelect.addEventListener('change', () => {
                    const selected = customerSelect.selectedOptions[0];
                    if (!selected || !selected.value) {
                        customerNameInput.value = '';
                        customerPhoneInput.value = '';
                        return;
                    }
                    customerNameInput.value = selected.dataset.name || '';
                    customerPhoneInput.value = selected.dataset.phone || '';
                });
            }

            function updateCashSummary() {
                if (!cashReceivedInput || !cashSummaryText) return;
                const received = parseFloat(cashReceivedInput.value) || 0;
                const difference = received - currentTotal;
                const absDifference = Math.abs(difference);
                const isChange = difference >= 0;
                cashSummaryText.textContent = 'Rs. ' + absDifference.toLocaleString(undefined, { minimumFractionDigits: 2 }) + (isChange ? ' change' : ' due');
                cashSummaryText.className = 'font-semibold ' + (isChange ? 'text-hut-green' : 'text-hut-red');
            }

            if (paymentMethodSelect && cashReceivedInput) {
                const setCashInputState = () => {
                    const isCash = paymentMethodSelect.value === 'cash';
                    cashReceivedInput.disabled = !isCash;
                    cashReceivedInput.required = isCash;
                    cashReceivedInput.placeholder = isCash ? 'Enter cash received' : 'Disabled for online payment';
                    if (!isCash) {
                        cashReceivedInput.value = '';
                    }
                    updateCashSummary();
                };

                paymentMethodSelect.addEventListener('change', setCashInputState);
                cashReceivedInput.addEventListener('input', updateCashSummary);
                setCashInputState();
            }

            const grid = document.getElementById('item-grid');
            const tabs = document.getElementById('category-tabs');
            const modal = document.getElementById('item-modal');
            const lineEditModal = document.getElementById('line-edit-modal');
            const lineEditName = document.getElementById('line-edit-name');
            const lineEditQty = document.getElementById('line-edit-qty');
            const lineEditPrice = document.getElementById('line-edit-price');
            const linesBox = document.getElementById('cart-lines');
            const emptyMsg = document.getElementById('cart-empty');
            const totalBox = document.getElementById('cart-total');
            const cartInput = document.getElementById('cart-input');
            const checkoutBtn = document.getElementById('checkout-btn');
            let pendingCard = null;
            let pendingLine = null;

            const parseSizes = (card) => {
                const raw = card?.dataset?.sizes || '[]';
                try {
                    const parsed = JSON.parse(raw);
                    return Array.isArray(parsed) ? parsed : [];
                } catch (e) {
                    return [];
                }
            };

            function openModal(card, hasSizes, allowsToppings) {
                if (!modal || !card) return;
                document.getElementById('modal-item-name').textContent = card.dataset.name;
                const sizesBox = document.getElementById('modal-sizes');
                const toppingsBox = document.getElementById('modal-toppings');
                sizesBox.innerHTML = '';
                toppingsBox.innerHTML = '';

                if (hasSizes) {
                    const sizes = parseSizes(card);
                    sizes.forEach((s, i) => {
                        sizesBox.insertAdjacentHTML('beforeend', `
                            <label class="flex items-center justify-between border border-gray-200 rounded-lg px-3 py-2 text-sm cursor-pointer">
                                <span><input type="radio" name="modal-size" value="${s.label}" ${i === 0 ? 'checked' : ''} class="mr-2">${s.label}</span>
                                <span>Rs. ${Number(s.price).toLocaleString()}</span>
                            </label>
                        `);
                    });
                }

                if (allowsToppings && Array.isArray(toppings) && toppings.length) {
                    toppingsBox.insertAdjacentHTML('beforeend', '<p class="text-xs text-gray-400 mb-1">Toppings</p>');
                    toppings.forEach((t) => {
                        toppingsBox.insertAdjacentHTML('beforeend', `
                            <label class="flex items-center justify-between border border-gray-200 rounded-lg px-3 py-2 text-sm cursor-pointer">
                                <span><input type="checkbox" name="modal-topping" value="${t.id}" data-price="${t.price}" class="mr-2">${t.name}</span>
                                <span>+Rs. ${Number(t.price).toLocaleString()}</span>
                            </label>
                        `);
                    });
                }

                modal.classList.remove('hidden');
                modal.classList.add('flex');
            }

            function closeModal() {
                if (!modal) return;
                modal.classList.add('hidden');
                modal.classList.remove('flex');
                pendingCard = null;
            }

            function calculateUnitPrice(card, sizeLabel, toppingIds) {
                let unitPrice = parseFloat(card.dataset.price) || 0;
                if (sizeLabel) {
                    const sizes = parseSizes(card);
                    const match = sizes.find((s) => s.label === sizeLabel);
                    if (match) unitPrice = parseFloat(match.price) || unitPrice;
                }
                if (Array.isArray(toppingIds)) {
                    toppingIds.forEach((id) => {
                        const t = toppings.find((item) => item.id === id);
                        if (t) unitPrice += parseFloat(t.price) || 0;
                    });
                }
                return unitPrice;
            }

            function addToCart(card, sizeLabel, toppingIds, quantity = 1, priceOverride = null) {
                if (!card) return;
                const isDeal = String(card.dataset.deal || '0') === '1';
                const unitPrice = priceOverride === null ? calculateUnitPrice(card, sizeLabel, toppingIds) : priceOverride;
                const key = [card.dataset.id, isDeal ? 'deal' : 'menu_item', sizeLabel || '', Array.isArray(toppingIds) ? toppingIds.slice().sort().join(',') : ''].join('|');
                const existing = cart.find((line) => line.key === key);

                if (existing) {
                    existing.quantity += quantity;
                } else {
                    cart.push({
                        key,
                        type: isDeal ? 'deal' : 'menu_item',
                        id: parseInt(card.dataset.id, 10),
                        quantity,
                        size_label: sizeLabel,
                        topping_ids: Array.isArray(toppingIds) ? toppingIds : [],
                        name: card.dataset.name + (sizeLabel ? ' (' + sizeLabel + ')' : ''),
                        unitPrice,
                    });
                }
                renderCart();
            }

            function matchesHighlight(line) {
                return highlightedLine && line.type === highlightedLine.type && line.id === highlightedLine.id;
            }

            function hydrateCart() {
                if (!Array.isArray(savedCart) || !savedCart.length) return;
                savedCart.forEach((line) => {
                    const type = line.type || 'menu_item';
                    const id = parseInt(line.id, 10);
                    const quantity = parseInt(line.quantity || 1, 10);
                    const sizeLabel = line.size_label || null;
                    const toppingIds = Array.isArray(line.topping_ids) ? line.topping_ids : [];
                    const name = line.name || (type === 'deal' ? 'Deal' : 'Item');
                    const unitPrice = parseFloat(line.unitPrice || line.price || 0);
                    const key = [id, type, sizeLabel || '', toppingIds.slice().sort().join(',')].join('|');
                    cart.push({
                        key,
                        type,
                        id,
                        quantity,
                        size_label: sizeLabel,
                        topping_ids: toppingIds,
                        name: name + (sizeLabel ? ' (' + sizeLabel + ')' : ''),
                        unitPrice,
                    });
                });
                renderCart();
            }

            function renderCart() {
                if (!linesBox || !emptyMsg || !totalBox || !cartInput || !checkoutBtn) return;
                linesBox.querySelectorAll('.cart-line').forEach((el) => el.remove());
                let total = 0;

                cart.forEach((line, idx) => {
                    const lineGross = pkr(line.unitPrice * line.quantity);
                    const ldType = line.line_discount_type || 'percent';
                    const ldVal = parseFloat(line.line_discount_value || 0) || 0;
                    let lineNet = lineGross;
                    if (ldVal > 0) {
                        if (ldType === 'percent') lineNet = pkr(lineGross * (1 - Math.min(100, ldVal) / 100));
                        else lineNet = pkr(Math.max(0, lineGross - ldVal));
                    }
                    total += lineNet;

                    linesBox.insertAdjacentHTML('beforeend', `
                        <div class="cart-line space-y-1 text-sm border-b border-gray-50 pb-2 ${matchesHighlight(line) ? 'rounded-lg border border-amber-300 bg-amber-50 px-2 py-2' : ''}">
                            <div class="flex items-center justify-between gap-1">
                                <div class="flex-1 min-w-0">
                                    <p class="font-medium text-gray-900 truncate">${line.name}</p>
                                    <p class="text-xs text-gray-400">Rs. ${pkr(line.unitPrice).toLocaleString()} × ${line.quantity}${ldVal > 0 ? ' · disc.' : ''}</p>
                                </div>
                                <div class="flex items-center gap-1 shrink-0">
                                    <button type="button" class="qty-btn w-6 h-6 rounded bg-gray-100 hover:bg-gray-200" data-idx="${idx}" data-dir="-1">−</button>
                                    <span class="w-6 text-center">${line.quantity}</span>
                                    <button type="button" class="qty-btn w-6 h-6 rounded bg-gray-100 hover:bg-gray-200" data-idx="${idx}" data-dir="1">+</button>
                                    <button type="button" class="remove-btn text-hut-red text-xs ml-1" data-idx="${idx}">✕</button>
                                </div>
                            </div>
                            <div class="flex items-center gap-1">
                                <select class="line-disc-type rounded border border-gray-200 text-[10px] px-1 py-0.5 bg-white" data-idx="${idx}">
                                    <option value="percent" ${ldType === 'percent' ? 'selected' : ''}>%</option>
                                    <option value="fixed" ${ldType === 'fixed' ? 'selected' : ''}>Rs</option>
                                </select>
                                <input type="number" min="0" step="1" value="${ldVal}" placeholder="Disc" class="line-disc-value w-16 rounded border border-gray-200 text-[10px] px-1 py-0.5" data-idx="${idx}">
                                <span class="text-[10px] text-gray-500 ml-auto">${pkrFmt(lineNet)}</span>
                            </div>
                        </div>
                    `);
                });

                emptyMsg.style.display = cart.length ? 'none' : '';
                const subtotalEl = document.getElementById('cart-subtotal');
                if (subtotalEl) subtotalEl.textContent = pkrFmt(total);
                const billType = document.getElementById('bill-discount-type')?.value || 'percent';
                const billVal = parseFloat(document.getElementById('bill-discount-value')?.value || '0') || 0;
                let billDisc = 0;
                if (billVal > 0) {
                    billDisc = billType === 'percent' ? pkr(total * Math.min(100, billVal) / 100) : pkr(Math.min(total, billVal));
                }
                const discEl = document.getElementById('bill-discount-amount');
                if (discEl) discEl.textContent = '− ' + pkrFmt(billDisc);
                currentTotal = pkr(Math.max(0, total - billDisc));
                totalBox.textContent = pkrFmt(currentTotal);
                cartInput.value = JSON.stringify(cart.map((line) => ({ ...line })));
                checkoutBtn.disabled = cart.length === 0;
                const cartCountBadge = document.getElementById('cart-count-badge');
                if (cartCountBadge) {
                    cartCountBadge.textContent = String(cart.reduce((sum, line) => sum + (line.quantity || 1), 0));
                }
                updateCashSummary();
            }

            function handlePosCardClick(card) {
                if (!card) return;
                pendingCard = card;
                const hasSizes = String(card.dataset.hasSizes || '0') === '1' || String(card.dataset.hasSizes || '0') === 'true';
                const allowsToppings = String(card.dataset.allowsToppings || '0') === '1' || String(card.dataset.allowsToppings || '0') === 'true';
                const showLineEdit = String(card.dataset.showModal || '0') === '1' || String(card.dataset.showModal || '0') === 'true';

                if (!hasSizes && !allowsToppings) {
                    if (showLineEdit) {
                        openLineEdit(card, null, []);
                    } else {
                        addToCart(card, null, []);
                    }
                    return;
                }

                openModal(card, hasSizes, allowsToppings);
            }

            function openLineEdit(card, sizeLabel, toppingIds) {
                if (!card || !lineEditModal) return;
                pendingLine = { card, sizeLabel, toppingIds };
                lineEditName.textContent = card.dataset.name + (sizeLabel ? ' (' + sizeLabel + ')' : '');
                lineEditQty.value = '1';
                lineEditPrice.value = calculateUnitPrice(card, sizeLabel, toppingIds).toFixed(2);
                lineEditModal.classList.remove('hidden');
                lineEditModal.classList.add('flex');
                lineEditQty.focus();
            }

            function closeLineEdit() {
                if (!lineEditModal) return;
                pendingLine = null;
                lineEditModal.classList.add('hidden');
                lineEditModal.classList.remove('flex');
            }

            if (tabs) {
                tabs.addEventListener('click', (e) => {
                    const btn = e.target.closest('.cat-tab-btn');
                    if (!btn) return;
                    tabs.querySelectorAll('.cat-tab-btn').forEach((b) => b.classList.remove('bg-hut-dark', 'text-white'));
                    btn.classList.add('bg-hut-dark', 'text-white');
                    filterItems();
                });
            }

            if (grid) {
                grid.addEventListener('click', (e) => {
                    const card = e.target.closest('.pos-item-card');
                    handlePosCardClick(card);
                });
            }

            document.getElementById('modal-cancel-btn')?.addEventListener('click', closeModal);
            document.getElementById('modal-add-btn')?.addEventListener('click', () => {
                if (!pendingCard) return;
                const sizeInput = document.querySelector('input[name="modal-size"]:checked');
                const sizeLabel = sizeInput ? sizeInput.value : null;
                const toppingIds = Array.from(document.querySelectorAll('input[name="modal-topping"]:checked')).map((el) => parseInt(el.value, 10));
                if (String(pendingCard.dataset.showModal || '0') === '1') {
                    closeModal();
                    openLineEdit(pendingCard, sizeLabel, toppingIds);
                    return;
                }
                addToCart(pendingCard, sizeLabel, toppingIds);
                closeModal();
            });

            document.getElementById('line-edit-cancel')?.addEventListener('click', closeLineEdit);
            document.getElementById('line-edit-add')?.addEventListener('click', () => {
                if (!pendingLine) return;
                const quantity = Math.max(0.01, parseFloat(lineEditQty.value) || 1);
                const price = Math.max(0, parseFloat(lineEditPrice.value) || 0);
                addToCart(pendingLine.card, pendingLine.sizeLabel, pendingLine.toppingIds, quantity, price);
                closeLineEdit();
            });

            linesBox?.addEventListener('click', (e) => {
                const qtyBtn = e.target.closest('.qty-btn');
                const removeBtn = e.target.closest('.remove-btn');
                if (qtyBtn) {
                    const idx = parseInt(qtyBtn.dataset.idx, 10);
                    if (!cart[idx]) return;
                    cart[idx].quantity += parseInt(qtyBtn.dataset.dir, 10);
                    if (cart[idx].quantity <= 0) cart.splice(idx, 1);
                    renderCart();
                } else if (removeBtn) {
                    cart.splice(parseInt(removeBtn.dataset.idx, 10), 1);
                    renderCart();
                }
            });

            document.getElementById('bill-discount-type')?.addEventListener('change', () => renderCart());
            document.getElementById('bill-discount-value')?.addEventListener('input', () => renderCart());
            linesBox?.addEventListener('change', (e) => {
                const typeEl = e.target.closest('.line-disc-type');
                const valEl = e.target.closest('.line-disc-value');
                if (typeEl && cart[typeEl.dataset.idx]) {
                    cart[typeEl.dataset.idx].line_discount_type = typeEl.value;
                    renderCart();
                }
                if (valEl && cart[valEl.dataset.idx]) {
                    cart[valEl.dataset.idx].line_discount_value = parseFloat(valEl.value) || 0;
                    renderCart();
                }
            });

            document.getElementById('checkout-form')?.addEventListener('submit', (event) => {
                cartInput.value = JSON.stringify(cart.map((line) => ({ ...line })));
                const method = paymentMethodSelect?.value || 'cash';
                if (method !== 'cash') {
                    debtConfirmProceed = false;
                    return;
                }

                const receivedRaw = cashReceivedInput?.value;
                const received = (receivedRaw === '' || receivedRaw == null) ? null : pkr(receivedRaw);
                const total = currentTotal || 0;

                if (debtConfirmProceed) {
                    debtConfirmProceed = false;
                    if (received === null) cashReceivedInput.value = '0';
                    else cashReceivedInput.value = String(pkr(received));
                    return;
                }

                const paid = received == null ? 0 : pkr(received);
                const due = pkr(Math.max(0, total - paid));
                if (received != null && due <= 0) {
                    cashReceivedInput.value = String(pkr(received));
                    return;
                }

                event.preventDefault();
                if (due <= 0) return;

                const pickerWrap = document.getElementById('debt-customer-picker-wrap');
                const picker = document.getElementById('debt-customer-picker');
                if (picker && customerSelect) {
                    picker.innerHTML = '';
                    Array.from(customerSelect.options).forEach((opt) => {
                        if (!opt.value) return;
                        const o = document.createElement('option');
                        o.value = opt.value;
                        o.textContent = opt.textContent;
                        o.dataset.name = opt.dataset.name || '';
                        o.dataset.phone = opt.dataset.phone || '';
                        o.dataset.balance = opt.dataset.balance || '0';
                        if (opt.selected) o.selected = true;
                        picker.appendChild(o);
                    });
                    pickerWrap?.classList.toggle('hidden', !!customerSelect.value);
                }

                const fill = (opt) => {
                    const currentBal = pkr(opt?.dataset?.balance || 0);
                    document.getElementById('debt-customer-name').textContent = opt?.dataset?.name || opt?.textContent?.trim() || '—';
                    document.getElementById('debt-customer-phone').textContent = opt?.dataset?.phone || '—';
                    document.getElementById('debt-customer-balance').textContent = pkrFmt(currentBal);
                    document.getElementById('debt-bill-total').textContent = pkrFmt(total);
                    document.getElementById('debt-cash-received').textContent = pkrFmt(paid);
                    document.getElementById('debt-amount').textContent = pkrFmt(due);
                    document.getElementById('debt-new-balance').textContent = pkrFmt(currentBal + due);
                };
                const selected = customerSelect?.selectedOptions?.[0];
                fill(selected?.value ? selected : picker?.options?.[0]);
                document.getElementById('debt-modal-error')?.classList.add('hidden');
                document.getElementById('debt-confirm-modal')?.classList.remove('hidden');
                picker?.addEventListener('change', () => fill(picker.selectedOptions[0]));
            });

            document.getElementById('debt-cancel')?.addEventListener('click', () => {
                document.getElementById('debt-confirm-modal')?.classList.add('hidden');
            });

            document.getElementById('debt-confirm')?.addEventListener('click', () => {
                const picker = document.getElementById('debt-customer-picker');
                if (picker?.value && customerSelect) customerSelect.value = picker.value;
                if (!customerSelect?.value) {
                    const err = document.getElementById('debt-modal-error');
                    if (err) {
                        err.textContent = 'Select a customer to put this balance on their account.';
                        err.classList.remove('hidden');
                    }
                    document.getElementById('debt-customer-picker-wrap')?.classList.remove('hidden');
                    return;
                }
                const selected = customerSelect.selectedOptions[0];
                if (customerNameInput) customerNameInput.value = selected.dataset.name || '';
                if (customerPhoneInput) customerPhoneInput.value = selected.dataset.phone || '';
                if (cashReceivedInput && (cashReceivedInput.value === '' || cashReceivedInput.value == null)) cashReceivedInput.value = '0';
                if (cashReceivedInput) cashReceivedInput.value = String(pkr(cashReceivedInput.value));
                debtConfirmProceed = true;
                document.getElementById('debt-confirm-modal')?.classList.add('hidden');
                document.getElementById('checkout-form')?.requestSubmit();
            });

            const posSearch = document.getElementById('pos-search');
            const toggleAllBtn = document.getElementById('toggle-all-items');
            let showAllItems = true;

            function filterItems() {
                const q = (posSearch?.value || '').trim().toLowerCase();
                const activeCat = document.querySelector('.cat-tab-btn.bg-hut-dark')?.dataset.cat || 'all';
                document.querySelectorAll('#item-grid .pos-item-card').forEach((card) => {
                    const name = (card.dataset.name || card.querySelector('.font-display')?.textContent || '').toLowerCase();
                    const price = String(card.dataset.price || '');
                    const cat = card.dataset.cat || '';
                    const matchesSearch = !q || name.includes(q) || price.includes(q) || name.replace(/\s+/g, '').includes(q.replace(/\s+/g, ''));
                    const matchesCat = activeCat === 'all' || cat === activeCat || (activeCat === 'deals' && cat === 'deals');
                    card.style.display = (matchesSearch && matchesCat) ? '' : 'none';
                });
            }

            if (posSearch) {
                posSearch.addEventListener('input', filterItems);
                posSearch.focus();
            }

            if (toggleAllBtn) {
                toggleAllBtn.addEventListener('click', () => {
                    showAllItems = !showAllItems;
                    const allBtn = document.querySelector('.cat-tab-btn[data-cat="all"]');
                    if (allBtn) allBtn.click();
                    if (posSearch) {
                        posSearch.value = '';
                        filterItems();
                    }
                    toggleAllBtn.textContent = showAllItems ? 'Show all items' : 'Filter by category';
                    document.getElementById('item-grid')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                });
            }

            document.querySelectorAll('.cat-tab-btn').forEach((btn) => {
                btn.addEventListener('click', () => setTimeout(filterItems, 0));
            });

            hydrateCart();
        })();
    </script>


@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const search = document.getElementById('customer-search');
            const select = document.getElementById('customer-select');
            const toggle = document.getElementById('toggle-new-customer');
            const form = document.getElementById('new-customer-form');
            const results = document.getElementById('customer-search-results');
            if (!search || !select || !toggle || !form || !results) return;
            search.addEventListener('input', () => {
                const term = search.value.trim().toLowerCase();
                results.innerHTML = '';
                Array.from(select.options).forEach((option, index) => {
                    if (index === 0) return;
                    option.hidden = term !== '' && !(`${option.dataset.name || ''} ${option.dataset.phone || ''}`.toLowerCase().includes(term));
                    if (term && !option.hidden) {
                        const result = document.createElement('button');
                        result.type = 'button'; result.className = 'block w-full px-3 py-2 text-left text-sm hover:bg-gray-50';
                        result.innerHTML = `<strong>${option.dataset.name}</strong><span class="ml-2 text-gray-500">${option.dataset.phone}</span>`;
                        result.addEventListener('click', () => { select.value = option.value; search.value = `${option.dataset.name} · ${option.dataset.phone}`; results.classList.add('hidden'); select.dispatchEvent(new Event('change', { bubbles: true })); });
                        results.appendChild(result);
                    }
                });
                results.classList.toggle('hidden', !term || results.childElementCount === 0);
            });
            toggle.addEventListener('click', () => {
                form.classList.toggle('hidden');
                toggle.textContent = form.classList.contains('hidden') ? '+ Add customer' : '− Close';
            });
        });
    </script>
@endpush