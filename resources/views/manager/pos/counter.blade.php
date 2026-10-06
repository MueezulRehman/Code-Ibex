@extends('manager.layout.master')
@section('title', $posConfig['title'])

@section('page-content')
    @php
        $resolvePosImage = function (?string $path): ?string {
            if (!$path)
                return null;
            if (\Illuminate\Support\Str::startsWith($path, ['http://', 'https://']))
                return $path;
            if (is_file(public_path('images/' . $path)))
                return asset('images/' . $path);
            if (is_file(public_path($path)))
                return asset($path);
            if (is_file(storage_path('app/public/' . $path)))
                return asset('storage/' . $path);
            return null;
        };
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
    {{-- Line edit inputs are part of the modal; stray duplicates removed to avoid ID conflicts --}}

    <div class="pos-workspace grid lg:grid-cols-3 gap-4 items-start">
        <div class="lg:col-span-2 space-y-4 min-w-0">
            <div id="pos-scan-panel" class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm space-y-3">
                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                            {{ $posConfig['title'] ?? 'POS' }}
                        </p>
                        <h2 class="font-display font-semibold text-hut-dark text-lg">Search, scan &amp; bill</h2>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <a href="{{ route('manager.customers.index') }}"
                            class="rounded-full border border-gray-200 bg-gray-50 px-3 py-1.5 text-xs font-semibold text-hut-dark hover:bg-hut-yellow/20">{{ $customers->count() }}
                            customers</a>
                        <button type="button" id="scroll-catalog"
                            class="rounded-full bg-hut-dark px-3 py-1.5 text-xs font-semibold text-white hover:bg-gray-800">Browse
                            catalog</button>
                    </div>
                </div>
                <div class="relative">
                    <i class="fas fa-barcode absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <input type="text" id="scan-input" autofocus placeholder="{{ $posConfig['search_placeholder'] }}"
                            class="w-full border border-gray-200 rounded-xl bg-gray-50 pl-10 pr-4 py-3 text-base focus:border-hut-green focus:bg-white focus:ring-2 focus:ring-hut-green/20 outline-none">
                        @include('manager.partials.barcode-scanner', ['inputId' => 'scan-input'])
                    </div>
                </div>
                <p class="text-xs text-gray-400">Scan barcode, or type <strong>name / SKU / number</strong> and press Enter.
                </p>
            </div>

            <div id="search-results" class="grid grid-cols-2 sm:grid-cols-3 gap-3" role="listbox"
                aria-label="Search results">
            </div>
            <div id="recent-items" class="hidden flex flex-wrap gap-2 items-center"></div>

            <div id="pos-catalog-panel"
                class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 max-h-[calc(100vh-8rem)] overflow-hidden flex flex-col">
                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h3 class="font-display font-semibold text-hut-dark text-sm">{{ $posConfig['item_label_plural'] }}
                            Catalog</h3>
                        <p class="text-xs text-gray-500">Browse by category or search by medicine name, generic, or batch.
                        </p>
                    </div>
                    @if(($showMedicalItems ?? false))
                        <div class="flex-1 md:max-w-md">
                            <input type="text" id="medical-search" placeholder="Search medicine or batch"
                                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-hut-yellow focus:ring-1 focus:ring-hut-yellow outline-none">
                        </div>
                    @endif
                </div>

                @if(($showMedicalItems ?? false))
                    <div class="mt-3 flex flex-wrap gap-2" id="medical-category-pills">
                        <button type="button"
                            class="medical-category-pill rounded-full border border-hut-yellow/40 bg-hut-yellow/10 px-3 py-1.5 text-xs font-semibold text-hut-dark {{ empty($selectedCategory) || $selectedCategory === 'all' ? 'active' : '' }}"
                            data-category="all">All</button>
                        @foreach(($medicineCategories ?? collect()) as $category)
                            <button type="button"
                                class="medical-category-pill rounded-full border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-600 hover:border-hut-yellow hover:text-hut-dark {{ $selectedCategory === 'cat-' . $category->id ? 'active bg-hut-yellow/10 border-hut-yellow/40 font-bold' : '' }}"
                                data-category="cat-{{ $category->id }}">{{ $category->name }}</button>
                        @endforeach
                        @if(($uncategorized ?? collect())->count())
                            <button type="button"
                                class="medical-category-pill rounded-full border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-600 hover:border-hut-yellow hover:text-hut-dark {{ $selectedCategory === 'uncategorized' ? 'active bg-hut-yellow/10 border-hut-yellow/40 font-bold' : '' }}"
                                data-category="uncategorized">Uncategorized</button>
                        @endif
                    </div>
                    <div class="mt-2 text-[11px] text-gray-500">
                        @if(!empty($selectedCategory) && $selectedCategory !== 'all' && $selectedCategory !== 'uncategorized')
                            Showing: {{ $selectedCategoryName ?? 'Selected category' }}
                        @elseif($selectedCategory === 'uncategorized')
                            Showing: Uncategorized medicines
                        @else
                            Showing: All medicines
                        @endif
                    </div>
                @endif

                <div class="mt-2 flex items-center gap-2">
                    <label for="category-filter" class="text-sm text-gray-500">Category:</label>
                    <select id="category-filter" class="border border-gray-200 rounded-lg px-2 py-1 text-sm bg-white">
                        <option value="all">All</option>
                    </select>
                </div>

                <div class="mt-4 flex-1 overflow-y-auto pr-1" id="all-items">
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                        @if(($showMedicalItems ?? false))
                            @foreach(($medicineCategories ?? collect()) as $category)
                                @if($category->medicines->isNotEmpty())
                                    <div class="col-span-full" data-category-group="cat-{{ $category->id }}"
                                        data-category-id="{{ $category->id }}" data-category-name="{{ $category->name }}">
                                        <div class="mb-2 rounded-lg bg-gray-50 px-3 py-2 text-sm font-semibold text-hut-dark">
                                            {{ $category->name }}
                                        </div>
                                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                                            @foreach($category->medicines as $medicine)
                                                @if($medicine->batches->isEmpty())
                                                    <div class="bg-white border border-dashed border-gray-200 rounded-xl p-3 text-left shadow-sm"
                                                        data-category-id="{{ $medicine->category_id ?? 0 }}"
                                                        data-category-name="{{ $medicine->category?->name ?? 'Uncategorized' }}"
                                                        data-search="{{ strtolower($medicine->name . ' ' . ($medicine->generic_name ?? '')) }}">
                                                        <p class="font-display font-semibold text-sm text-hut-dark truncate">
                                                            {{ $medicine->name }}
                                                        </p>
                                                        @if(!empty($medicine->dosage_form))
                                                            <p class="text-[11px] text-gray-500">Form: {{ $medicine->dosage_form }}</p>
                                                        @endif
                                                        @if(!empty($medicine->strength))
                                                            <p class="text-[11px] text-gray-500">Strength: {{ $medicine->strength }}</p>
                                                        @endif
                                                        <p class="text-[11px] text-gray-500">Category:
                                                            {{ $medicine->category?->name ?? 'Uncategorized' }}
                                                        </p>
                                                        <p class="text-xs text-gray-400">No batch stock yet</p>
                                                    </div>
                                                @else
                                                    @foreach($medicine->batches as $batch)
                                                        <button type="button"
                                                            class="pos-item-card relative bg-white border border-gray-100 rounded-xl p-3 text-left shadow-sm hover:shadow-md hover:border-hut-yellow transition"
                                                            data-type="medicine_batch" data-id="{{ $batch->id }}"
                                                            data-unit="{{ method_exists($medicine, 'unitLabel') ? $medicine->unitLabel() : ($medicine->unit ?? 'kg') }}"
                                                            data-allow-fraction="{{ method_exists($medicine, 'allowsFractionalQty') ? ($medicine->allowsFractionalQty() ? '1' : '0') : '1' }}"
                                                            data-name="{{ $medicine->name }} — Batch {{ $batch->batch_number }}"
                                                            data-sku="{{ $medicine->sku }}" data-price="{{ $batch->selling_price }}"
                                                            data-stock="{{ $batch->quantity }}" data-category-id="{{ $medicine->category_id ?? 0 }}"
                                                            data-category-name="{{ $medicine->category?->name ?? 'Uncategorized' }}"
                                                            data-show-modal="{{ ($medicine->pos_show_line_edit ?? false) ? '1' : (($medicine->category?->pos_show_line_edit ?? false) ? '1' : '0') }}"
                                                            data-search="{{ strtolower($medicine->name . ' ' . ($medicine->generic_name ?? '') . ' ' . $batch->batch_number) }}">
                                                            <div
                                                                class="mb-2 flex aspect-4/3 items-center justify-center overflow-hidden rounded-lg bg-gray-50">
                                                                <i class="fas fa-pills text-3xl text-hut-green/50" aria-hidden="true"></i>
                                                            </div>
                                                            <p class="font-display font-semibold text-sm text-hut-dark truncate">
                                                                {{ $medicine->name }}
                                                            </p>
                                                            @if(!empty($medicine->dosage_form))
                                                                <p class="text-[11px] text-gray-500">Form: {{ $medicine->dosage_form }}</p>
                                                            @endif
                                                            @if(!empty($medicine->strength))
                                                                <p class="text-[11px] text-gray-500">Strength: {{ $medicine->strength }}</p>
                                                            @endif
                                                            <p class="text-[11px] text-gray-500">Category:
                                                                {{ $medicine->category?->name ?? 'Uncategorized' }}
                                                            </p>
                                                            <p class="text-xs text-gray-400">Batch: {{ $batch->batch_number }} · Exp:
                                                                {{ $batch->expiry_date?->toDateString() ?? 'N/A' }}
                                                            </p>
                                                            <p class="text-xs text-hut-green font-medium mt-1">Rs.
                                                                {{ number_format($batch->selling_price) }}
                                                            </p>
                                                            @if(($medicine->pos_show_line_edit ?? false) || ($medicine->category?->pos_show_line_edit ?? false))
                                                                <span class="absolute top-2 right-2 text-amber-600 text-sm"
                                                                    title="Line-edit enabled">⚑</span>
                                                            @endif
                                                            <p
                                                                class="text-[11px] {{ $batch->quantity <= ($medicine->min_stock ?? 0) ? 'text-hut-red' : 'text-gray-400' }} mt-0.5">
                                                                Stock: {{ $batch->quantity }}</p>
                                                        </button>
                                                    @endforeach
                                                @endif
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            @endforeach

                            @if(($uncategorized ?? collect())->count())
                                <div class="col-span-full" data-category-group="uncategorized" data-category-id=""
                                    data-category-name="Uncategorized">
                                    <div class="mb-2 rounded-lg bg-gray-50 px-3 py-2 text-sm font-semibold text-hut-dark">
                                        Uncategorized</div>
                                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                                        @foreach($uncategorized as $medicine)
                                            @if($medicine->batches->isEmpty())
                                                <div class="bg-white border border-dashed border-gray-200 rounded-xl p-3 text-left shadow-sm"
                                                    data-category-id="{{ $medicine->category_id ?? 0 }}"
                                                    data-category-name="{{ $medicine->category?->name ?? 'Uncategorized' }}"
                                                    data-search="{{ strtolower($medicine->name . ' ' . ($medicine->generic_name ?? '')) }}">
                                                    <p class="font-display font-semibold text-sm text-hut-dark truncate">
                                                        {{ $medicine->name }}
                                                    </p>
                                                    <p class="text-xs text-gray-400">No batch stock yet</p>
                                                </div>
                                            @else
                                                @foreach($medicine->batches as $batch)
                                                    <button type="button"
                                                        class="pos-item-card bg-white border border-gray-100 rounded-xl p-3 text-left shadow-sm hover:shadow-md hover:border-hut-yellow transition"
                                                        data-type="medicine_batch" data-id="{{ $batch->id }}"
                                                        data-unit="{{ method_exists($medicine, 'unitLabel') ? $medicine->unitLabel() : ($medicine->unit ?? 'kg') }}"
                                                        data-allow-fraction="{{ method_exists($medicine, 'allowsFractionalQty') ? ($medicine->allowsFractionalQty() ? '1' : '0') : '1' }}"
                                                        data-name="{{ $medicine->name }} — Batch {{ $batch->batch_number }}"
                                                        data-sku="{{ $medicine->sku }}" data-price="{{ $batch->selling_price }}"
                                                        data-stock="{{ $batch->quantity }}" data-category-id="{{ $medicine->category_id ?? 0 }}"
                                                        data-category-name="{{ $medicine->category?->name ?? 'Uncategorized' }}"
                                                        data-show-modal="{{ ($medicine->pos_show_line_edit ?? false) ? '1' : (($medicine->category?->pos_show_line_edit ?? false) ? '1' : '0') }}"
                                                        data-search="{{ strtolower($medicine->name . ' ' . ($medicine->generic_name ?? '') . ' ' . $batch->batch_number) }}">
                                                        <div
                                                            class="mb-2 flex aspect-4/3 items-center justify-center overflow-hidden rounded-lg bg-gray-50">
                                                            <i class="fas fa-pills text-3xl text-hut-green/50" aria-hidden="true"></i>
                                                        </div>
                                                        <p class="font-display font-semibold text-sm text-hut-dark truncate">
                                                            {{ $medicine->name }}
                                                        </p>
                                                        <p class="text-xs text-gray-400">Batch: {{ $batch->batch_number }} · Exp:
                                                            {{ $batch->expiry_date?->toDateString() ?? 'N/A' }}
                                                        </p>
                                                        <p class="text-xs text-hut-green font-medium mt-1">Rs.
                                                            {{ number_format($batch->selling_price) }}
                                                        </p>
                                                        <p
                                                            class="text-[11px] {{ $batch->quantity <= ($medicine->min_stock ?? 0) ? 'text-hut-red' : 'text-gray-400' }} mt-0.5">
                                                            Stock: {{ $batch->quantity }}</p>
                                                    </button>
                                                @endforeach
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        @else
                            @foreach($items as $item)
                                @if($item->variants->count())
                                    @foreach($item->variants as $variant)
                                        <button type="button"
                                            class="pos-item-card relative bg-white border border-gray-100 rounded-xl p-3 text-left shadow-sm hover:shadow-md hover:border-hut-yellow transition"
                                            data-type="variant" data-id="{{ $variant->id }}"
                                            data-name="{{ $item->name }} — {{ $variant->variant_name }}" data-sku="{{ $variant->sku }}"
                                            data-price="{{ $variant->getEffectivePrice() }}" data-stock="{{ $variant->quantity_available }}"
                                            data-unit="{{ $item->unitLabel() }}"
                                            data-allow-fraction="{{ $item->allowsFractionalQty() ? '1' : '0' }}"
                                            data-show-modal="{{ $item->pos_show_line_edit ? '1' : ($item->category?->pos_show_line_edit ? '1' : '0') }}"
                                            data-category-id="{{ $item->category_id ?? '' }}"
                                            data-category-name="{{ $item->category?->name ?? '' }}">
                                            @php $itemImg = $resolvePosImage($item->image); @endphp
                                            <div
                                                class="mb-2 flex aspect-4/3 items-center justify-center overflow-hidden rounded-lg bg-gray-50">
                                                @if($itemImg)
                                                    <img src="{{ $itemImg }}" alt="" loading="lazy" decoding="async"
                                                        class="h-full w-full object-contain" />
                                                @else
                                                    <i class="fas fa-box-open text-3xl text-hut-green/50" aria-hidden="true"></i>
                                                @endif
                                            </div>
                                            <p class="font-display font-semibold text-sm text-hut-dark truncate">{{ $item->name }}</p>
                                            <p class="text-xs text-gray-400">{{ $variant->variant_name }} · {{ $variant->sku }}</p>
                                            <p class="text-xs text-hut-green font-medium mt-1">Rs.
                                                {{ number_format($variant->getEffectivePrice()) }}
                                            </p>
                                            @if($item->pos_show_line_edit || ($item->category?->pos_show_line_edit ?? false))
                                                <span class="absolute top-2 right-2 text-amber-600 text-sm" title="Line-edit enabled">⚑</span>
                                            @endif
                                        </button>
                                    @endforeach
                                @else
                                    <button type="button"
                                        class="pos-item-card relative bg-white border border-gray-100 rounded-xl p-3 text-left shadow-sm hover:shadow-md hover:border-hut-yellow transition"
                                        data-type="menu_item" data-id="{{ $item->id }}" data-name="{{ $item->name }}"
                                        data-sku="{{ $item->sku }}" data-price="{{ $item->price ?? 0 }}"
                                        data-track-stock="{{ $item->track_stock ? '1' : '0' }}" data-stock="{{ $item->stock_quantity }}"
                                        data-unit="{{ $item->unitLabel() }}"
                                        data-allow-fraction="{{ $item->allowsFractionalQty() ? '1' : '0' }}"
                                        data-show-modal="{{ $item->pos_show_line_edit ? '1' : ($item->category?->pos_show_line_edit ? '1' : '0') }}"
                                        data-category-id="{{ $item->category_id ?? '' }}"
                                        data-category-name="{{ $item->category?->name ?? '' }}">
                                        @php $itemImg = $resolvePosImage($item->image); @endphp
                                        <div
                                            class="mb-2 flex aspect-4/3 items-center justify-center overflow-hidden rounded-lg bg-gray-50">
                                            @if($itemImg)
                                                <img src="{{ $itemImg }}" alt="" loading="lazy" decoding="async"
                                                    class="h-full w-full object-contain" />
                                            @else
                                                <i class="fas fa-box-open text-3xl text-hut-green/50" aria-hidden="true"></i>
                                            @endif
                                        </div>
                                        <p class="font-display font-semibold text-sm text-hut-dark truncate">{{ $item->name }}</p>
                                        <p class="text-xs text-gray-400">{{ $item->sku ?: 'No code' }}</p>
                                        <p class="text-xs text-hut-green font-medium mt-1">Rs. {{ number_format($item->price ?? 0) }}
                                        </p>
                                        @if($item->pos_show_line_edit || ($item->category?->pos_show_line_edit ?? false))
                                            <span class="absolute top-2 right-2 text-amber-600 text-sm" title="Line-edit enabled">⚑</span>
                                        @endif
                                        @if($item->track_stock)
                                            <p class="text-[11px] {{ $item->isLowStock() ? 'text-hut-red' : 'text-gray-400' }} mt-0.5">
                                                Stock: {{ $item->stock_quantity }}</p>
                                        @endif
                                    </button>
                                @endif
                            @endforeach
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Fixed billing panel (not movable) --}}
        <aside id="pos-cart-panel"
            class="bg-white rounded-2xl border border-gray-200 shadow-md p-0 h-fit lg:sticky lg:top-4 lg:self-start flex flex-col w-full">
            <div class="bg-hut-dark text-white px-4 py-3 flex items-center justify-between shrink-0">
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-widest text-hut-yellow/90">Billing</p>
                    <h2 class="font-display font-bold text-base leading-tight">Current Bill</h2>
                </div>
                <span id="cart-count-badge"
                    class="inline-flex h-7 min-w-7 items-center justify-center rounded-full bg-hut-yellow text-hut-dark px-2 text-xs font-bold">0</span>
            </div>
            <div class="p-4">

                <div id="cart-lines" class="space-y-2 mb-3">
                    <p id="cart-empty" class="text-sm text-gray-400 text-center py-6">No items yet — scan or search a
                        {{ strtolower($posConfig['item_label']) }}.
                    </p>
                </div>

                <div class="border-t border-gray-100 pt-3 space-y-2 text-sm">
                    <div class="flex justify-between text-gray-500">
                        <span>Total before discount</span>
                        <span id="cart-subtotal">Rs. 0</span>
                    </div>
                    <div class="rounded-lg border border-dashed border-amber-200 bg-amber-50/50 p-2 space-y-2">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-amber-800">Bill discount</p>
                        <div class="grid min-w-0 grid-cols-[auto_minmax(0,1fr)] gap-2">
                            <select id="bill-discount-type" name="bill_discount_type" form="checkout-form"
                                class="min-w-0 rounded-lg border border-gray-200 bg-white px-2 py-1.5 text-xs">
                                <option value="percent">%</option>
                                <option value="fixed">Rs</option>
                            </select>
                            <input type="number" id="bill-discount-value" name="bill_discount_value" form="checkout-form"
                                min="0" step="1" value="0" placeholder="0"
                                class="w-full min-w-0 rounded-lg border border-gray-200 px-2 py-1.5 text-xs">
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
                            class="block text-[11px] font-semibold uppercase tracking-wide text-gray-500">Cash
                            received</label>
                        <input type="number" id="cash-received" name="amount_received" form="checkout-form" step="1" min="0"
                            placeholder="Leave empty to charge customer debt"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-hut-green focus:ring-hut-green">
                        <input type="hidden" id="accept-short-payment" name="accept_short_payment_without_debt" value="0"
                            form="checkout-form">
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-gray-500">Change / balance</span>
                            <span id="cash-summary-text" class="font-semibold text-hut-dark">Rs. 0</span>
                        </div>
                    </div>
                </div>

                <div id="safety-warning"
                    class="hidden rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800"></div>

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
                                @selected(($savedCustomerId ?? null) == $customer->id)>
                                {{ $customer->name }} • {{ $customer->phone }} @if($customer->balance > 0) (Due Rs.
                                {{ number_format($customer->balance, 2) }}) @endif
                            </option>
                        @endforeach
                    </select>
                    @if(($branches ?? collect())->isNotEmpty())
                        <select name="branch_id" form="checkout-form" required
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm bg-white">
                            <option value="">Select selling branch</option>
                            @foreach($branches as $branch)
                            <option value="{{ $branch->id }}">{{ $branch->name }} ({{ $branch->code }})</option>@endforeach
                        </select>
                    @endif
                    @if(($priceLists ?? collect())->isNotEmpty())
                        <select name="wholesale_price_list_id" form="checkout-form"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm bg-white">
                            <option value="">Standard retail pricing</option>
                            @foreach($priceLists as $priceList)
                                <option value="{{ $priceList->id }}">
                                    {{ $priceList->name }}{{ $priceList->customer_group ? ' · ' . $priceList->customer_group : '' }}
                            </option>@endforeach
                        </select>
                    @endif
                    <form id="new-customer-form" method="POST" action="{{ route('manager.customers.store') }}"
                        class="hidden space-y-2">
                        @csrf
                        <input type="hidden" name="redirect_to_pos" value="1">
                        <input type="hidden" name="cart" id="customer-register-cart" value="[]">
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

                <form id="checkout-form" method="POST" action="{{ route('manager.pos.checkout') }}" class="mt-4 space-y-2"
                    novalidate>
                    @csrf
                    <input type="hidden" id="pos-short-payment-allowed"
                        value="{{ $posConfig['allow_short_payment_without_debt'] ? '1' : '0' }}">
                    <input type="hidden" id="pos-short-payment-threshold"
                        value="{{ $posConfig['short_payment_threshold'] ?? 0 }}">
                    @if(($posConfig['mode'] ?? '') === 'medical')
                        <input type="hidden" name="order_type" value="takeaway">
                        <div class="rounded-lg border border-green-100 bg-green-50 px-3 py-2 text-sm text-green-800">Only
                            takeaway orders are supported in medical mode.</div>
                        <div class="mt-3 space-y-3">
                            <label class="flex items-center gap-2 text-sm">
                                <input type="checkbox" name="prescription" value="1"
                                    class="h-4 w-4 rounded border-gray-300 text-hut-green focus:ring-hut-green">
                                <span>Prescription attached</span>
                            </label>
                            <input type="number" name="prescription_doctor_id" placeholder="Doctor ID (optional)"
                                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-hut-green focus:ring-hut-green">
                        </div>
                    @else
                        <select name="order_type" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
                            <option value="takeaway">Takeaway</option>
                            <option value="dine_in">Dine-in</option>
                            <option value="table">Table Order</option>
                            <option value="delivery">Delivery</option>
                            <option value="online">Online</option>
                        </select>
                    @endif
                    <div id="table-number-wrapper" class="hidden">
                        <input type="text" name="table_number" placeholder="Table number"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
                    </div>
                    <input type="text" name="customer_name" placeholder="Customer name (optional)"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
                    <input type="text" name="customer_phone" placeholder="Phone (optional)"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
                    <select name="payment_method" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
                        <option value="cash">Cash</option>
                        <option value="online">Online</option>
                    </select>
                    <input type="hidden" name="cart" id="cart-input">
                    <button type="submit" id="checkout-btn"
                        class="btn-accent w-full text-center py-3 text-base font-bold shadow-sm" disabled>Complete
                        Sale</button>
                </form>
            </div>
        </aside>
    </div>


    {{-- Quick-add unknown barcode product --}}
    <div id="quick-add-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40">
        <div class="w-full max-w-md rounded-2xl bg-white shadow-xl border border-gray-200 overflow-hidden">
            <div class="bg-hut-dark text-white px-4 py-3 flex items-center justify-between">
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-widest text-hut-yellow/90">New product</p>
                    <h3 class="font-display font-bold">Register &amp; add to bill</h3>
                </div>
                <button type="button" id="quick-add-close"
                    class="text-white/80 hover:text-white text-xl leading-none">&times;</button>
            </div>
            <form id="quick-add-form" class="p-4 space-y-3">
                <p class="text-xs text-gray-500">This barcode is not in your catalog. Enter name and selling price once — it
                    will be saved for next time.</p>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 mb-1">Barcode</label>
                    <input type="text" id="quick-add-barcode" readonly
                        class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm font-mono">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 mb-1">Product name *</label>
                    <input type="text" id="quick-add-name" required
                        class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-hut-green focus:ring-1 focus:ring-hut-green"
                        placeholder="Product name">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 mb-1">Selling price (Rs.) *</label>
                    <input type="number" id="quick-add-price" required min="0" step="0.01"
                        class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-hut-green focus:ring-1 focus:ring-hut-green"
                        placeholder="0.00">
                </div>
                <p id="quick-add-status" class="text-xs text-gray-500 min-h-[1rem]"></p>
                <div class="flex gap-2 pt-1">
                    <button type="button" id="quick-add-cancel"
                        class="flex-1 rounded-lg border border-gray-200 px-3 py-2.5 text-sm font-semibold text-gray-600 hover:bg-gray-50">Cancel</button>
                    <button type="submit" id="quick-add-submit"
                        class="flex-1 rounded-lg bg-hut-dark px-3 py-2.5 text-sm font-semibold text-white hover:bg-gray-800">Save
                        &amp; add to bill</button>
                </div>
            </form>
        </div>
    </div>


    {{-- Charge unpaid amount to customer debt (professional modal, not browser alert) --}}
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
                <div id="debt-new-customer-wrap" class="mt-2 space-y-2">
                    <label class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Or register new
                        customer</label>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <input id="debt-new-customer-name" type="text" placeholder="Customer name"
                            class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" />
                        <input id="debt-new-customer-phone" type="text" placeholder="Phone"
                            class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" />
                    </div>
                    <p id="debt-new-customer-error" class="text-xs text-red-600 hidden mt-1"></p>
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

    {{-- Short-payment acceptance modal: accept small underpayments without creating debt --}}
    <div id="short-accept-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40">
        <div class="w-full max-w-md rounded-2xl bg-white shadow-xl border border-gray-200 overflow-hidden">
            <div class="bg-hut-dark text-white px-4 py-3">
                <p class="text-[10px] font-bold uppercase tracking-widest text-hut-yellow/90">Short payment</p>
                <h3 class="font-display font-bold">Small short payment detected</h3>
            </div>
            <div class="p-4 space-y-3 text-sm">
                <p class="text-gray-600">The cash received is less than the bill total but within the allowed short-payment
                    threshold.</p>
                <p class="text-sm">Choose how to proceed:</p>
                <div class="flex gap-2 pt-1">
                    <button type="button" id="short-accept-keep"
                        class="flex-1 rounded-lg bg-hut-dark px-3 py-2.5 text-sm font-semibold text-white">Accept payment
                        (do not add to debt)</button>
                    <button type="button" id="short-accept-debt"
                        class="flex-1 rounded-lg border border-amber-600 px-3 py-2.5 text-sm font-semibold text-amber-700">Charge
                        shortfall to customer</button>
                </div>
                <div class="text-xs text-gray-500">You can change this behaviour in POS settings.</div>
            </div>
        </div>
    </div>

    {{-- Line edit modal: set quantity & price before adding to bill --}}
    <div id="line-edit-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40">
        <div class="w-full max-w-md rounded-2xl bg-white shadow-xl border border-gray-200 overflow-hidden">
            <div class="px-4 py-3 flex items-center justify-between bg-gray-50">
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500">Add to bill</p>
                    <h3 id="line-edit-title" class="font-display font-bold text-lg">Item</h3>
                </div>
                <button type="button" id="line-edit-close"
                    class="text-gray-600 hover:text-gray-800 text-xl leading-none">&times;</button>
            </div>
            <div class="p-4 space-y-3">
                <p id="line-edit-name" class="font-medium text-hut-dark"></p>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Quantity <span id="line-edit-unit-label"
                            class="text-xs text-gray-400">&nbsp;</span></label>
                    <input id="line-edit-qty" type="number" min="0.001" step="0.01" value="1"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
                    <p id="line-edit-stock-warning" class="text-xs text-red-600 mt-1 hidden"></p>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Unit price (Rs.)</label>
                    <input id="line-edit-price" type="number" min="0" step="0.01" value="0"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Sell mode</label>
                    <select id="line-edit-mode" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm bg-white">
                        <option value="by_qty">By quantity / weight</option>
                        <option value="by_total">By total price (customer pays)</option>
                    </select>
                </div>
                <div id="line-edit-total-wrap" style="display:none;">
                    <label class="block text-xs text-gray-500 mb-1">Amount customer pays (Rs.)</label>
                    <input id="line-edit-total" type="number" min="0" step="0.01" value="0"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
                    <p id="line-edit-computed" class="text-[11px] text-gray-500 mt-1">Give weight: <span
                            id="line-edit-computed-qty">0.00</span> <span id="line-edit-computed-unit"
                            class="text-gray-400"></span></p>
                </div>
                <div class="flex gap-2 pt-1">
                    <button type="button" id="line-edit-cancel"
                        class="flex-1 rounded-lg border border-gray-200 px-3 py-2.5 text-sm font-semibold text-gray-600 hover:bg-gray-50">Cancel</button>
                    <button type="button" id="line-edit-add"
                        class="flex-1 rounded-lg bg-hut-dark px-3 py-2.5 text-sm font-semibold text-white hover:bg-gray-800">Add
                        to bill</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const cart = []; // {type, id, quantity, name, price}
            // currency helper: keep two decimal precision for rupees
            const pkr = (n) => Math.max(0, Math.round((Number(n) || 0) * 100) / 100);
            const pkrFmt = (n) => 'Rs. ' + pkr(n).toLocaleString(undefined, { minimumFractionDigits: 2 });
            const meta = {}; // key `${type}:${id}` -> {name, price, stock}
            const savedCart = @json($savedCart ?? []);
            const savedCustomerId = @json($savedCustomerId ?? null);
            const highlightedLine = @json($errorHighlight ?? null);
            const orderTypeSelect = document.querySelector('select[name="order_type"]');
            const tableNumberWrapper = document.getElementById('table-number-wrapper');
            const customerSelect = document.getElementById('customer-select');
            if (customerSelect && savedCustomerId) {
                customerSelect.value = String(savedCustomerId);
                customerSelect.dispatchEvent(new Event('change', { bubbles: true }));
            }
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
                    updateCashSummary();
                });
            }

            function updateCashSummary() {
                if (!cashReceivedInput || !cashSummaryText) return;
                const received = pkr(cashReceivedInput.value);
                const difference = received - currentTotal;
                const absDifference = Math.abs(difference);
                const customerSelect = document.getElementById('customer-select');
                const selected = customerSelect?.selectedOptions?.[0];
                const debt = selected?.value ? (parseFloat(selected.dataset.balance || '0') || 0) : 0;

                if (difference < 0) {
                    cashSummaryText.textContent = 'Rs. ' + absDifference.toLocaleString(undefined, { minimumFractionDigits: 2 }) + ' due' + (selected?.value ? ' (to debt)' : '');
                    cashSummaryText.className = 'font-semibold text-hut-red';
                    return;
                }

                if (difference > 0 && debt > 0) {
                    const toDebt = Math.min(difference, debt);
                    const changeLeft = Math.round((difference - toDebt) * 100) / 100;
                    cashSummaryText.textContent = 'Rs. ' + toDebt.toLocaleString(undefined, { minimumFractionDigits: 2 }) + ' → debt'
                        + (changeLeft > 0 ? (' · Rs. ' + changeLeft.toLocaleString(undefined, { minimumFractionDigits: 2 }) + ' change') : '');
                    cashSummaryText.className = 'font-semibold text-hut-green';
                    return;
                }

                cashSummaryText.textContent = 'Rs. ' + absDifference.toLocaleString(undefined, { minimumFractionDigits: 2 }) + (difference >= 0 ? ' change' : ' due');
                cashSummaryText.className = 'font-semibold ' + (difference >= 0 ? 'text-hut-green' : 'text-hut-red');
            }

            if (paymentMethodSelect && cashReceivedInput) {
                const setCashInputState = () => {
                    const isCash = paymentMethodSelect.value === 'cash';
                    cashReceivedInput.disabled = !isCash;
                    cashReceivedInput.required = false; // empty cash → debt modal, not browser required
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

            const scanInput = document.getElementById('scan-input');
            const resultsBox = document.getElementById('search-results');
            const lookupUrl = @json(route('manager.pos.lookup'));
            const medicalSearchInput = document.getElementById('medical-search');
            const medicalCategoryPills = document.getElementById('medical-category-pills');
            const allItemsGrid = document.getElementById('all-items');

            function applyMedicalFilters() {
                if (!medicalSearchInput || !allItemsGrid) return;

                const term = medicalSearchInput.value.trim().toLowerCase();
                const activeCategory = medicalCategoryPills ? document.querySelector('.medical-category-pill.active')?.dataset.category || 'all' : 'all';
                let visibleItemCount = 0;
                let visibleGroupCount = 0;

                allItemsGrid.querySelectorAll('[data-category-group]').forEach((group) => {
                    const groupCategoryId = group.dataset.categoryId || '';
                    const groupCategoryGroup = group.dataset.categoryGroup || '';

                    // Check if this group matches the selected category
                    const groupMatchesCategory = activeCategory === 'all'
                        || (activeCategory === 'uncategorized' && groupCategoryGroup === 'uncategorized')
                        || (activeCategory.startsWith('cat-') && activeCategory === `cat-${groupCategoryId}`);

                    let groupVisibleCount = 0;

                    // Filter items within this category group
                    group.querySelectorAll('.pos-item-card, [data-search]').forEach((item) => {
                        const itemSearchText = (item.dataset.search || '').toLowerCase();
                        const matchesSearch = !term || itemSearchText.includes(term);
                        const itemCategoryId = item.dataset.categoryId || '';

                        // Check if this item matches the selected category
                        const itemMatchesCategory = activeCategory === 'all'
                            || (activeCategory === 'uncategorized' && (!itemCategoryId || itemCategoryId === '' || item.dataset.categoryName === 'Uncategorized'))
                            || (activeCategory.startsWith('cat-') && activeCategory === `cat-${itemCategoryId}`);

                        const shouldShow = matchesSearch && itemMatchesCategory;
                        item.style.display = shouldShow ? '' : 'none';
                        if (shouldShow) groupVisibleCount++;
                    });

                    // Show/hide the entire category group header based on whether it has visible items
                    const shouldShowGroup = groupMatchesCategory && groupVisibleCount > 0;
                    group.style.display = shouldShowGroup ? '' : 'none';
                    if (shouldShowGroup) {
                        visibleGroupCount++;
                        visibleItemCount += groupVisibleCount;
                    }
                });

                // Show empty state if no items match
                const existingEmpty = document.getElementById('medical-empty-state');
                if (existingEmpty) existingEmpty.remove();
                if (visibleItemCount === 0) {
                    const emptyState = document.createElement('div');
                    emptyState.id = 'medical-empty-state';
                    emptyState.className = 'col-span-full rounded-xl border border-dashed border-gray-200 bg-gray-50 px-4 py-6 text-center text-sm text-gray-500';
                    emptyState.textContent = term
                        ? `No medicines match "${term}" in the ${activeCategory === 'all' ? 'catalog' : 'selected category'}.`
                        : 'No medicines in this category.';
                    allItemsGrid.appendChild(emptyState);
                }
            }

            if (medicalSearchInput) {
                medicalSearchInput.addEventListener('input', applyMedicalFilters);
            }

            if (medicalCategoryPills) {
                applyMedicalFilters();
                medicalCategoryPills.querySelectorAll('.medical-category-pill').forEach((pill) => {
                    pill.addEventListener('click', () => {
                        const category = pill.dataset.category || 'all';
                        const url = new URL(window.location.href);
                        url.searchParams.set('category', category);
                        window.location.href = url.toString();
                    });
                });
            }
            // Category filter (generic) — populate from item data attributes and apply
            const categoryFilter = document.getElementById('category-filter');
            function populateCategoryFilter() {
                if (!categoryFilter) return;
                const seen = new Set();
                document.querySelectorAll('#all-items [data-category-id]').forEach(el => {
                    const id = String(el.dataset.categoryId || '').trim();
                    const name = el.dataset.categoryName || el.dataset.categoryName || (id ? id : 'Uncategorized');
                    if (!id || seen.has(id)) return;
                    seen.add(id);
                    const opt = document.createElement('option');
                    opt.value = id;
                    opt.textContent = name;
                    categoryFilter.appendChild(opt);
                });
            }
            function applyCategoryFilter() {
                if (!categoryFilter) return;
                const val = categoryFilter.value;
                document.querySelectorAll('#all-items [data-category-group]').forEach(group => {
                    if (val === 'all' || String(group.dataset.categoryId) === val) group.style.display = '';
                    else group.style.display = 'none';
                });
                // Also hide lone item cards (retail layout) by their category metadata
                document.querySelectorAll('#all-items .pos-item-card').forEach(card => {
                    const cid = String(card.dataset.categoryId || '').trim();
                    if (val === 'all' || cid === '' || cid === val) card.style.display = '';
                    else card.style.display = 'none';
                });
            }
            categoryFilter?.addEventListener('change', applyCategoryFilter);
            populateCategoryFilter();
            // ---- Advanced POS search: debounce, abort, ranking UI, keyboard, recent ----
            let debounceTimer = null;
            let lookupAbort = null;
            let activeResultIndex = -1;
            const RECENT_KEY = 'pos_recent_items_' + @json((string) (auth()->user()?->effectiveRestaurantId() ?? '0'));

            function loadRecent() {
                try { return JSON.parse(localStorage.getItem(RECENT_KEY) || '[]'); } catch (e) { return []; }
            }
            function saveRecent(entry) {
                const list = loadRecent().filter((x) => !(x.type === entry.type && x.id === entry.id));
                list.unshift(entry);
                localStorage.setItem(RECENT_KEY, JSON.stringify(list.slice(0, 8)));
                renderRecent();
            }
            function renderRecent() {
                const box = document.getElementById('recent-items');
                if (!box) return;
                const list = loadRecent();
                if (!list.length) { box.classList.add('hidden'); box.innerHTML = ''; return; }
                box.classList.remove('hidden');
                box.innerHTML = '<span class="text-[11px] font-semibold uppercase tracking-wide text-gray-400 mr-1">Recent</span>' +
                    list.map((item) => `<button type="button" class="recent-chip rounded-full border border-gray-200 bg-white px-3 py-1 text-xs font-medium text-hut-dark hover:border-hut-yellow hover:bg-hut-yellow/10"
                                                                            data-type="${item.type}" data-id="${item.id}" data-name="${escapeHtml(item.name)}" data-price="${item.price}" data-stock="${item.stock === null || item.stock === undefined ? '' : item.stock}">${escapeHtml(item.name)}</button>`).join('');
            }
            renderRecent();

            document.getElementById('recent-items')?.addEventListener('click', (e) => {
                const chip = e.target.closest('.recent-chip');
                if (!chip) return;
                const stock = chip.dataset.stock === '' ? null : parseInt(chip.dataset.stock, 10);
                addToCart(chip.dataset.type, parseInt(chip.dataset.id, 10), chip.dataset.name, parseFloat(chip.dataset.price), Number.isNaN(stock) ? null : stock);
                afterAddClear();
            });

            // Scanners type digits very fast and often do NOT send Enter.
            // Detect a finished barcode (6–14 digits) after a short quiet period, then auto-add.
            let lastScanValue = '';
            scanInput.addEventListener('input', () => {
                clearTimeout(debounceTimer);
                const term = scanInput.value.trim();
                activeResultIndex = -1;
                if (term.length < 1) { resultsBox.innerHTML = ''; return; }

                const isBarcode = /^[0-9]{6,14}$/.test(term);
                // Typical EAN-8 / UPC / EAN-13 lengths — treat as complete scan sooner
                const looksCompleteBarcode = /^[0-9]{8}$/.test(term)
                    || /^[0-9]{12}$/.test(term)
                    || /^[0-9]{13}$/.test(term)
                    || /^[0-9]{14}$/.test(term);

                if (isBarcode) {
                    // Fast path: after scanner finishes (no more keys ~120ms), auto lookup + add
                    const delay = looksCompleteBarcode ? 120 : 180;
                    debounceTimer = setTimeout(() => {
                        const current = scanInput.value.trim();
                        if (current !== term) return; // still typing
                        if (current === lastScanValue) return; // already handled
                        lastScanValue = current;
                        runLookup(current, true); // true = auto-add to bill, then clear field
                    }, delay);
                    return;
                }

                // Text search (name / SKU with letters)
                if (term.length < 2) { resultsBox.innerHTML = ''; return; }
                debounceTimer = setTimeout(() => runLookup(term, false), 200);
            });

            scanInput.addEventListener('keydown', (e) => {
                const cards = Array.from(resultsBox.querySelectorAll('.result-card'));
                if (e.key === 'ArrowDown') {
                    if (!cards.length) return;
                    e.preventDefault();
                    activeResultIndex = Math.min(activeResultIndex + 1, cards.length - 1);
                    highlightResult(cards);
                    return;
                }
                if (e.key === 'ArrowUp') {
                    if (!cards.length) return;
                    e.preventDefault();
                    activeResultIndex = Math.max(activeResultIndex - 1, 0);
                    highlightResult(cards);
                    return;
                }
                if (e.key === 'Escape') {
                    resultsBox.innerHTML = '';
                    activeResultIndex = -1;
                    return;
                }
                if (e.key === 'Enter') {
                    e.preventDefault();
                    if (activeResultIndex >= 0 && cards[activeResultIndex]) {
                        cards[activeResultIndex].click();
                        return;
                    }
                    const term = scanInput.value.trim();
                    if (!term) return;
                    runLookup(term, true);
                }
            });

            function highlightResult(cards) {
                cards.forEach((c, i) => {
                    c.classList.toggle('ring-2', i === activeResultIndex);
                    c.classList.toggle('ring-hut-green', i === activeResultIndex);
                    c.classList.toggle('bg-hut-yellow/20', i === activeResultIndex);
                    if (i === activeResultIndex) c.scrollIntoView({ block: 'nearest' });
                });
            }

            function runLookup(term, autoAddIfSingle) {
                if (lookupAbort) lookupAbort.abort();
                lookupAbort = new AbortController();
                const isBarcodeScan = /^[0-9]{6,14}$/.test(term);
                // Barcode scans should always try to auto-add
                const shouldAutoAdd = autoAddIfSingle || isBarcodeScan;

                fetch(lookupUrl + '?q=' + encodeURIComponent(term), { signal: lookupAbort.signal })
                    .then(r => r.json())
                    .then(data => {
                        const items = data.items || [];
                        const matchType = data.match || 'search';

                        // Unknown barcode → quick register modal (clear input first)
                        if ((!items || !items.length) && isBarcodeScan) {
                            afterAddClear();
                            openQuickAdd(term);
                            return;
                        }

                        // Try to resolve a single sellable line from results
                        const line = resolveSingleSellableLine(items);
                        if (shouldAutoAdd && line) {
                            addToCart(line.type, line.id, line.name, line.price, line.stock);
                            try {
                                saveRecent({
                                    type: line.type,
                                    id: line.id,
                                    name: line.name,
                                    price: line.price,
                                    stock: line.stock,
                                });
                            } catch (err) { }
                            afterAddClear(); // clear barcode field + refocus immediately
                            return;
                        }

                        // Multiple matches → show list for keyboard/click selection
                        renderResults(items);
                        activeResultIndex = items.length ? 0 : -1;
                        highlightResult(Array.from(resultsBox.querySelectorAll('.result-card')));
                    })
                    .catch(err => { if (err.name !== 'AbortError') console.warn(err); });
            }

            /**
             * Pick one cart line from lookup results when there is a clear single choice.
             * Prefer exact single batch / single variant / plain menu item.
             */
            function resolveSingleSellableLine(items) {
                if (!items || !items.length) return null;

                // One product with one batch
                if (items.length === 1 && items[0].batches && items[0].batches.length === 1) {
                    const item = items[0];
                    const b = item.batches[0];
                    if (b.quantity != null && b.quantity <= 0) return null;
                    return {
                        type: 'medicine_batch',
                        id: b.id,
                        name: item.name + ' — Batch ' + b.batch_number,
                        price: parseFloat(b.price),
                        stock: b.quantity,
                    };
                }

                // One product with one variant
                if (items.length === 1 && items[0].variants && items[0].variants.length === 1) {
                    const item = items[0];
                    const v = item.variants[0];
                    if (v.quantity_available != null && v.quantity_available <= 0) return null;
                    return {
                        type: 'variant',
                        id: v.id,
                        name: item.name + ' — ' + v.name,
                        price: parseFloat(v.price),
                        stock: v.quantity_available,
                    };
                }

                // One plain menu item (no sizes/variants/batches)
                if (items.length === 1) {
                    const item = items[0];
                    const hasBatches = item.batches && item.batches.length > 0;
                    const hasVariants = item.variants && item.variants.length > 0;
                    if (!hasBatches && !hasVariants && item.id && !item.has_sizes) {
                        if (item.track_stock && item.stock_quantity != null && item.stock_quantity <= 0) return null;
                        return {
                            type: 'menu_item',
                            id: item.id,
                            name: item.name,
                            price: parseFloat(item.price),
                            stock: item.track_stock ? item.stock_quantity : null,
                        };
                    }
                }

                // Multiple products but total sellable "simple" lines is exactly one
                const simpleLines = [];
                items.forEach((item) => {
                    if (item.batches && item.batches.length) {
                        item.batches.forEach((b) => {
                            if (b.quantity != null && b.quantity <= 0) return;
                            simpleLines.push({
                                type: 'medicine_batch',
                                id: b.id,
                                name: item.name + ' — Batch ' + b.batch_number,
                                price: parseFloat(b.price),
                                stock: b.quantity,
                            });
                        });
                    } else if (item.variants && item.variants.length) {
                        item.variants.forEach((v) => {
                            if (v.quantity_available != null && v.quantity_available <= 0) return;
                            simpleLines.push({
                                type: 'variant',
                                id: v.id,
                                name: item.name + ' — ' + v.name,
                                price: parseFloat(v.price),
                                stock: v.quantity_available,
                            });
                        });
                    } else if (item.id && !item.has_sizes) {
                        if (item.track_stock && item.stock_quantity != null && item.stock_quantity <= 0) return;
                        simpleLines.push({
                            type: 'menu_item',
                            id: item.id,
                            name: item.name,
                            price: parseFloat(item.price),
                            stock: item.track_stock ? item.stock_quantity : null,
                        });
                    }
                });
                if (simpleLines.length === 1) return simpleLines[0];
                return null;
            }

            function afterAddClear() {
                if (scanInput) scanInput.value = '';
                if (resultsBox) resultsBox.innerHTML = '';
                activeResultIndex = -1;
                lastScanValue = '';
                scanInput?.focus({ preventScroll: true });
            }

            function renderResults(items) {
                resultsBox.innerHTML = '';
                if (!items.length) {
                    resultsBox.innerHTML = '<div class="col-span-full text-sm text-gray-400 px-1">No matches. Try another name, SKU, or barcode.</div>';
                    return;
                }
                items.forEach(item => {
                    if (item.batches && item.batches.length) {
                        item.batches.forEach(b => {
                            const out = b.quantity <= 0;
                            resultsBox.insertAdjacentHTML('beforeend', resultCardHtml('medicine_batch', b.id, item.name + ' — Batch ' + b.batch_number, item.sku || '', b.price, b.quantity, out));
                        });
                    } else if (item.variants && item.variants.length) {
                        item.variants.forEach(v => {
                            const out = v.quantity_available !== null && v.quantity_available <= 0;
                            resultsBox.insertAdjacentHTML('beforeend', resultCardHtml('variant', v.id, item.name + ' — ' + v.name, v.sku, v.price, v.quantity_available, out, item.image));
                        });
                    } else if (item.id && item.name) {
                        if (item.batches && Array.isArray(item.batches) && item.batches.length === 0) {
                            resultsBox.insertAdjacentHTML('beforeend', `<div class="rounded-xl border border-dashed border-gray-200 bg-white p-3 text-sm text-gray-500">
                                                                                    <p class="font-semibold text-hut-dark">${escapeHtml(item.name)}</p>
                                                                                    <p class="text-xs text-gray-400">No batch stock yet. Add a purchase batch first.</p>
                                                                                </div>`);
                        } else {
                            const out = item.track_stock && item.stock_quantity <= 0;
                            resultsBox.insertAdjacentHTML('beforeend', resultCardHtml('menu_item', item.id, item.name, item.sku || '', item.price, item.track_stock ? item.stock_quantity : null, out, item.image));
                        }
                    }
                });
            }

            function resultCardHtml(type, id, name, sku, price, stock, outOfStock, image) {
                const disabled = outOfStock ? 'opacity-50 cursor-not-allowed' : 'hover:shadow-md';
                const stockLabel = stock === null || stock === undefined ? '' : (outOfStock ? '<span class="text-hut-red text-[11px]">Out of stock</span>' : `<span class="text-[11px] text-gray-400">Stock: ${stock}</span>`);
                const imageMarkup = image ? `<img src="/images/${escapeHtml(image)}" alt="" loading="lazy" decoding="async" class="mb-2 h-20 w-full rounded-lg object-contain bg-white">` : '';
                return `<button type="button" role="option" class="result-card bg-hut-yellow/10 border border-hut-yellow/40 rounded-xl p-3 text-left shadow-sm transition ${disabled}"
                                                                                data-type="${type}" data-id="${id}" data-name="${escapeHtml(name)}" data-price="${price}" data-stock="${stock === null || stock === undefined ? '' : stock}" ${outOfStock ? 'disabled' : ''}>
                                                                                ${imageMarkup}
                                                                                <p class="font-display font-semibold text-sm text-hut-dark truncate">${escapeHtml(name)}</p>
                                                                                <p class="text-xs text-gray-400">${sku ? escapeHtml(sku) : ''}</p>
                                                                                <p class="text-xs text-hut-green font-medium mt-1">Rs. ${Number(price).toLocaleString()}</p>
                                                                                ${stockLabel}
                                                                            </button>`;
            }

            function escapeHtml(s) {
                return String(s).replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]));
            }

            document.addEventListener('click', (e) => {
                const card = e.target.closest('.pos-item-card, .result-card');
                if (!card || card.disabled) return;
                const stock = card.dataset.stock === '' || card.dataset.stock === undefined ? null : parseInt(card.dataset.stock);

                // For quick search result cards, auto-add immediately.
                if (card.classList.contains('result-card')) {
                    addToCart(card.dataset.type, parseInt(card.dataset.id), card.dataset.name, parseFloat(card.dataset.price), stock);
                    try {
                        saveRecent({
                            type: card.dataset.type,
                            id: parseInt(card.dataset.id, 10),
                            name: card.dataset.name,
                            price: parseFloat(card.dataset.price),
                            stock: stock,
                        });
                    } catch (err) { }
                    afterAddClear();
                    return;
                }

                // For catalog item cards, open edit modal so cashier can set qty/price for this bill.
                if (card.classList.contains('pos-item-card')) {
                    const cardUnit = card.dataset.unit || '';
                    const allowFraction = card.dataset.allowFraction === '1' || card.dataset.allowFraction === 'true';
                    // Only open full line-edit modal for fractional/weight items or when explicitly requested via data-show-modal="1".
                    const explicit = card.dataset.showModal === '1' || card.dataset.showModal === 'true';
                    const shouldOpenModal = explicit || allowFraction;
                    if (shouldOpenModal) {
                        openLineEdit(card.dataset.type, parseInt(card.dataset.id, 10), card.dataset.name, parseFloat(card.dataset.price), stock, cardUnit, allowFraction);
                        return;
                    }
                    // Otherwise, add with default quantity 1 immediately.
                    addToCart(card.dataset.type, parseInt(card.dataset.id, 10), card.dataset.name, parseFloat(card.dataset.price), stock);
                    try { saveRecent({ type: card.dataset.type, id: parseInt(card.dataset.id, 10), name: card.dataset.name, price: parseFloat(card.dataset.price), stock: stock }); } catch (err) { }
                    afterAddClear();
                    return;
                }
            });

            const linesBox = document.getElementById('cart-lines');
            const emptyMsg = document.getElementById('cart-empty');
            const totalBox = document.getElementById('cart-total');
            const cartInput = document.getElementById('cart-input');
            const checkoutBtn = document.getElementById('checkout-btn');
            const cartPanel = document.getElementById('pos-cart-panel');
            function addToCart(type, id, name, price, stock, amount = 1) {
                const key = type + ':' + id;
                meta[key] = { name, price, stock };
                const amt = Math.round((Number(amount) || 1) * 100) / 100;
                // Only merge into an existing line if the unit price matches exactly (so price-adjusted lines are separate)
                const existing = cart.find(l => l.type === type && l.id === id && Math.round((Number(l.price || 0)) * 100) / 100 === Math.round((Number(price || 0)) * 100) / 100);
                let idx = -1;
                if (existing) {
                    if (stock !== null && existing.quantity + amt > stock) {
                        alert('Only ' + stock + ' in stock.');
                        return -1;
                    }
                    existing.quantity = Math.round((existing.quantity + amt) * 100) / 100;
                    idx = cart.indexOf(existing);
                } else {
                    if (stock !== null && stock <= 0) {
                        alert('Out of stock.');
                        return -1;
                    }
                    cart.push({ type, id, quantity: amt, name, price, stock });
                    idx = cart.length - 1;
                }
                renderCart();
                return idx;
            }

            function updateSafetyWarning() {
                const warningBox = document.getElementById('safety-warning');
                if (!warningBox) return;
                const hasMedicalItems = cart.some(line => line.type === 'medicine_batch');
                if (!hasMedicalItems) {
                    warningBox.classList.add('hidden');
                    warningBox.textContent = '';
                    return;
                }
                warningBox.classList.remove('hidden');
                warningBox.textContent = 'Medical safety checks are active: allergy and drug-interaction warnings will be enforced at checkout.';
            }

            function matchesHighlight(line) {
                return highlightedLine && line.type === highlightedLine.type && line.id === highlightedLine.id;
            }

            function hydrateCart() {
                if (!Array.isArray(savedCart) || !savedCart.length) return;
                savedCart.forEach((line) => {
                    const type = line.type || 'menu_item';
                    const id = parseInt(line.id, 10);
                    const quantity = parseFloat(line.quantity || 1) || 1;
                    const name = line.name || `${type} ${id}`;
                    const price = parseFloat(line.price || line.unitPrice || 0);
                    const stock = line.stock ?? null;
                    const key = type + ':' + id;
                    meta[key] = { name, price, stock };
                    cart.push({ type, id, quantity, name, price });
                });
                renderCart();
            }

            function renderCart() {
                linesBox.querySelectorAll('.cart-line').forEach(el => el.remove());
                let total = 0;
                let origSubtotal = 0;

                cart.forEach((line, idx) => {
                    const info = meta[line.type + ':' + line.id] || { name: line.name || '', price: line.price || 0, stock: null };
                    const unitPrice = (typeof line.price !== 'undefined' ? parseFloat(line.price) : info.price) || 0;
                    // If the line was added by price-as-total and cashier provided an original total,
                    // use that original total for subtotal/discount calculations and display so the UI
                    // preserves the exact amount the customer paid (even if qty rounding changes multiplication)
                    const lineGross = (line.added_by_price_total && typeof line.original_line_total !== 'undefined' && line.original_line_total !== null)
                        ? parseFloat(line.original_line_total)
                        : (unitPrice * line.quantity);
                    origSubtotal += lineGross;
                    const ldType = line.line_discount_type || 'percent';
                    const ldVal = parseFloat(line.line_discount_value || 0) || 0;
                    let lineNet = lineGross;
                    if (ldVal > 0) {
                        if (ldType === 'percent') lineNet = Math.max(0, lineGross * (1 - Math.min(100, ldVal) / 100));
                        else lineNet = Math.max(0, lineGross - Math.min(lineGross, ldVal));
                    }
                    lineNet = pkr(lineNet);
                    total += lineNet;

                    const priceAsTotalBadge = line.added_by_price_total ? `<span class="ml-2" title="Customer paid: ${pkrFmt(line.original_line_total || lineGross)}" style="color:#92400e;font-weight:700;">⚑</span>` : '';

                    linesBox.insertAdjacentHTML('beforeend', `
                                                                            <div class="cart-line space-y-1 text-sm border-b border-gray-50 pb-2 ${matchesHighlight(line) ? 'rounded-lg border border-amber-300 bg-amber-50 px-2 py-2' : ''}">
                                                                                <div class="flex items-center justify-between gap-1">
                                                                                    <div class="flex-1 min-w-0">
                                                                                        <p class="font-medium text-gray-900 truncate">${info.name} ${priceAsTotalBadge}</p>
                                                                                        <p class="text-xs text-gray-400">Rs. ${Number(unitPrice).toLocaleString(undefined, { minimumFractionDigits: 2 })} × ${line.quantity}${ldVal > 0 ? ' · disc.' : ''}</p>
                                                                                    </div>
                                                                                    <div class="flex items-center gap-1 shrink-0">
                                                                                        <button type="button" class="qty-btn w-6 h-6 rounded bg-gray-100 hover:bg-gray-200" data-idx="${idx}" data-dir="-1">−</button>
                                                                                        <input type="number" min="0.01" step="0.01" value="${line.quantity}" class="cart-qty-input w-20 text-center rounded border border-gray-200 px-1 py-0.5" data-idx="${idx}">
                                                                                        <button type="button" class="qty-btn w-6 h-6 rounded bg-gray-100 hover:bg-gray-200" data-idx="${idx}" data-dir="1">+</button>
                                                                                        <button type="button" class="remove-btn text-hut-red text-xs ml-1" data-idx="${idx}">✕</button>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="flex items-center gap-1">
                                                                                    <select class="line-disc-type rounded border border-gray-200 text-[10px] px-1 py-0.5 bg-white" data-idx="${idx}">
                                                                                        <option value="percent" ${ldType === 'percent' ? 'selected' : ''}>%</option>
                                                                                        <option value="fixed" ${ldType === 'fixed' ? 'selected' : ''}>Rs</option>
                                                                                    </select>
                                                                                    <input type="number" min="0" step="0.01" value="${ldVal}" placeholder="Disc"
                                                                                        class="line-disc-value w-16 rounded border border-gray-200 text-[10px] px-1 py-0.5" data-idx="${idx}" step="1">
                                                                                    ${line.added_by_price_total ? `
                                                                                        <span class="text-[11px] text-gray-600">Unit: Rs. ${Number(unitPrice).toLocaleString(undefined, { minimumFractionDigits: 2 })}</span>
                                                                                        <input type="number" min="0" step="0.01" value="${line.original_line_total || lineGross}" class="cart-total-input w-28 rounded border border-gray-200 text-[10px] px-1 py-0.5 ml-2" data-idx="${idx}">
                                                                                        <span class="text-[10px] text-gray-500 ml-auto">Rs. ${lineNet.toLocaleString()}</span>
                                                                                    ` : `
                                                                                        <input type="number" min="0" step="0.01" value="${unitPrice}" class="cart-price-input w-24 rounded border border-gray-200 text-[10px] px-1 py-0.5 ml-2" data-idx="${idx}">
                                                                                        <span class="text-[10px] text-gray-500 ml-auto">Rs. ${lineNet.toLocaleString()}</span>
                                                                                    `}
                                                                                </div>
                                                                            </div>`);
                });

                emptyMsg.style.display = cart.length ? 'none' : '';
                const subtotalEl = document.getElementById('cart-subtotal');
                if (subtotalEl) subtotalEl.textContent = 'Rs. ' + origSubtotal.toLocaleString();
                const billType = document.getElementById('bill-discount-type')?.value || 'percent';
                const billVal = parseFloat(document.getElementById('bill-discount-value')?.value || '0') || 0;
                let billDisc = 0;
                if (billVal > 0) {
                    billDisc = billType === 'percent' ? total * Math.min(100, billVal) / 100 : Math.min(total, billVal);
                    billDisc = pkr(billDisc);
                }
                const discEl = document.getElementById('bill-discount-amount');
                if (discEl) discEl.textContent = '− Rs. ' + billDisc.toLocaleString();
                currentTotal = pkr(Math.max(0, total - billDisc));
                totalBox.textContent = 'Rs. ' + currentTotal.toLocaleString();
                cartInput.value = JSON.stringify(cart.map((line) => ({ ...line })));
                const registerCartInput = document.getElementById('customer-register-cart');
                if (registerCartInput) {
                    registerCartInput.value = JSON.stringify(cart.map((line) => ({ ...line })));
                }
                checkoutBtn.disabled = cart.length === 0;
                const badge = document.getElementById('cart-count-badge');
                if (badge) badge.textContent = String(cart.reduce((n, l) => n + (l.quantity || l.qty || 1), 0));
                updateSafetyWarning();
                updateCashSummary();
            }


            linesBox.addEventListener('change', (e) => {
                const typeEl = e.target.closest('.line-disc-type');
                const valEl = e.target.closest('.line-disc-value');
                if (typeEl) {
                    const idx = parseInt(typeEl.dataset.idx, 10);
                    if (cart[idx]) { cart[idx].line_discount_type = typeEl.value; renderCart(); }
                }
                if (valEl) {
                    const idx = parseInt(valEl.dataset.idx, 10);
                    if (cart[idx]) { cart[idx].line_discount_value = parseFloat(valEl.value) || 0; renderCart(); }
                }

                // Quantity input changed (allows fractional weights)
                if (e.target && e.target.classList && e.target.classList.contains('cart-qty-input')) {
                    const idx = parseInt(e.target.dataset.idx, 10);
                    if (!cart[idx]) return;
                    let v = parseFloat(e.target.value);
                    if (Number.isNaN(v) || v <= 0) {
                        cart.splice(idx, 1);
                        renderCart();
                        return;
                    }
                    const key = cart[idx].type + ':' + cart[idx].id;
                    const stock = meta[key]?.stock ?? cart[idx].stock ?? null;
                    if (stock !== null && stock !== undefined && v > stock) {
                        v = stock;
                        e.target.value = v;
                    }
                    cart[idx].quantity = Math.round(v * 100) / 100;
                    // Keep original_line_total in sync for price-as-total lines
                    if (cart[idx].added_by_price_total) {
                        const unit = parseFloat(cart[idx].price) || 0;
                        cart[idx].original_line_total = Math.round((cart[idx].quantity * unit) * 100) / 100;
                    }
                    renderCart();
                }

                // Per-line price changed (override for this bill only)
                if (e.target && e.target.classList && e.target.classList.contains('cart-price-input')) {
                    const idx = parseInt(e.target.dataset.idx, 10);
                    if (!cart[idx]) return;
                    let p = parseFloat(e.target.value);
                    if (Number.isNaN(p) || p < 0) p = 0;
                    cart[idx].price = Math.round(p * 100) / 100;
                    renderCart();
                }
                // Per-line total changed for price-as-total lines (adjust qty)
                if (e.target && e.target.classList && e.target.classList.contains('cart-total-input')) {
                    const idx = parseInt(e.target.dataset.idx, 10);
                    if (!cart[idx]) return;
                    let t = parseFloat(e.target.value);
                    if (Number.isNaN(t) || t < 0) t = 0;
                    // Preserve original_total and recompute quantity using stored unit price
                    const unit = parseFloat(cart[idx].price) || 0;
                    cart[idx].original_line_total = Math.round(t * 100) / 100;
                    if (unit > 0) {
                        const q = Math.round((t / unit) * 100) / 100;
                        cart[idx].quantity = q;
                    }
                    renderCart();
                }
            });
            document.getElementById('bill-discount-type')?.addEventListener('change', () => renderCart());
            document.getElementById('bill-discount-value')?.addEventListener('input', () => renderCart());

            linesBox.addEventListener('click', (e) => {
                const qtyBtn = e.target.closest('.qty-btn');
                const removeBtn = e.target.closest('.remove-btn');
                if (qtyBtn) {
                    const idx = parseInt(qtyBtn.dataset.idx);
                    if (!cart[idx]) return;
                    const info = meta[cart[idx].type + ':' + cart[idx].id] || {};
                    const dir = parseInt(qtyBtn.dataset.dir) || 0;
                    const step = parseFloat(qtyBtn.dataset.step || '1');
                    const proposed = Math.round((cart[idx].quantity + dir * step) * 100) / 100;
                    if (dir > 0 && info.stock !== null && info.stock !== undefined && proposed > info.stock) {
                        alert('Only ' + info.stock + ' in stock.');
                        return;
                    }
                    cart[idx].quantity = proposed;
                    if (cart[idx].quantity <= 0) cart.splice(idx, 1);
                    renderCart();
                } else if (removeBtn) {
                    cart.splice(parseInt(removeBtn.dataset.idx), 1);
                    renderCart();
                }
            });

            let debtConfirmProceed = false;

            document.getElementById('checkout-form').addEventListener('submit', (event) => {
                cartInput.value = JSON.stringify(cart.map((line) => ({ ...line })));

                const receivedRaw = cashReceivedInput?.value;
                const received = receivedRaw === '' || receivedRaw === null || receivedRaw === undefined
                    ? null
                    : parseFloat(receivedRaw);
                const total = currentTotal || 0;
                const method = paymentMethodSelect?.value || 'cash';

                // Online payment — no cash field required
                if (method !== 'cash') {
                    debtConfirmProceed = false;
                    return;
                }

                // Already confirmed debt charge
                if (debtConfirmProceed) {
                    debtConfirmProceed = false;
                    if (received === null || Number.isNaN(received)) {
                        cashReceivedInput.value = '0';
                    }
                    return;
                }

                const paid = (received === null || Number.isNaN(received)) ? 0 : pkr(received);
                const due = pkr(Math.max(0, total - paid));

                // Full payment entered (whole rupees)
                if (received !== null && !Number.isNaN(received) && pkr(received) >= 0 && due <= 0) {
                    if (cashReceivedInput) cashReceivedInput.value = String(pkr(received));
                    return;
                }

                // Unpaid or partial — professional debt modal (never browser alert)
                event.preventDefault();

                const dueInt = pkr(due);
                const paidInt = pkr(paid);
                const totalInt = pkr(total);
                if (dueInt <= 0) return; // fully paid after integer rounding

                const customerSelect = document.getElementById('customer-select');
                const pickerWrap = document.getElementById('debt-customer-picker-wrap');
                const picker = document.getElementById('debt-customer-picker');

                // Populate picker from main customer list
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
                    if (!customerSelect.value) {
                        pickerWrap?.classList.remove('hidden');
                    } else {
                        pickerWrap?.classList.add('hidden');
                    }
                }

                function fillDebtDetails(opt) {
                    const name = opt?.dataset?.name || opt?.textContent?.trim() || '—';
                    const phone = opt?.dataset?.phone || '—';
                    const currentBal = pkr(opt?.dataset?.balance || 0);
                    const newBal = currentBal + dueInt;
                    document.getElementById('debt-customer-name').textContent = name;
                    document.getElementById('debt-customer-phone').textContent = phone;
                    document.getElementById('debt-customer-balance').textContent = pkrFmt(currentBal);
                    document.getElementById('debt-bill-total').textContent = pkrFmt(totalInt);
                    document.getElementById('debt-cash-received').textContent = pkrFmt(paidInt);
                    document.getElementById('debt-amount').textContent = pkrFmt(dueInt);
                    document.getElementById('debt-new-balance').textContent = pkrFmt(newBal);
                }

                const selected = customerSelect?.selectedOptions?.[0];
                if (selected?.value) {
                    fillDebtDetails(selected);
                } else if (picker?.options?.length) {
                    fillDebtDetails(picker.options[0]);
                } else {
                    fillDebtDetails(null);
                    document.getElementById('debt-customer-name').textContent = 'No customers on file';
                }

                // If short-payment allowed and within threshold, show a short-accept modal
                const posShortAllowed = document.getElementById('pos-short-payment-allowed')?.value === '1';
                const posThreshold = parseInt(document.getElementById('pos-short-payment-threshold')?.value || '0', 10) || 0;
                if (posShortAllowed && dueInt <= posThreshold) {
                    document.getElementById('short-accept-modal')?.classList.remove('hidden');
                    // Wire buttons
                    document.getElementById('short-accept-keep')?.addEventListener('click', () => {
                        // Mark acceptance and submit
                        document.getElementById('accept-short-payment').value = '1';
                        if (cashReceivedInput && (cashReceivedInput.value === '' || cashReceivedInput.value === null)) {
                            cashReceivedInput.value = '0';
                        }
                        if (cashReceivedInput) cashReceivedInput.value = String(pkr(cashReceivedInput.value));
                        document.getElementById('short-accept-modal')?.classList.add('hidden');
                        debtConfirmProceed = false;
                        // Use submit() for compatibility with older browsers
                        const _form = document.getElementById('checkout-form');
                        if (_form) {
                            // ensure native submission (bypass potential requestSubmit quirks)
                            _form.submit();
                        }
                    });
                    document.getElementById('short-accept-debt')?.addEventListener('click', () => {
                        // Close short modal and fall back to debt modal flow
                        document.getElementById('short-accept-modal')?.classList.add('hidden');
                        document.getElementById('debt-modal-error')?.classList.add('hidden');
                        document.getElementById('debt-confirm-modal')?.classList.remove('hidden');
                    });
                    return;
                }

                document.getElementById('debt-modal-error')?.classList.add('hidden');
                document.getElementById('debt-confirm-modal')?.classList.remove('hidden');

                picker?.addEventListener('change', function onPick() {
                    fillDebtDetails(picker.selectedOptions[0]);
                }, { once: false });
            });

            document.getElementById('debt-cancel')?.addEventListener('click', () => {
                document.getElementById('debt-confirm-modal')?.classList.add('hidden');
            });
            document.getElementById('debt-confirm-modal')?.addEventListener('click', (e) => {
                if (e.target.id === 'debt-confirm-modal') {
                    document.getElementById('debt-confirm-modal')?.classList.add('hidden');
                }
            });
            document.getElementById('debt-confirm')?.addEventListener('click', async () => {
                const customerSelect = document.getElementById('customer-select');
                const picker = document.getElementById('debt-customer-picker');
                const newNameEl = document.getElementById('debt-new-customer-name');
                const newPhoneEl = document.getElementById('debt-new-customer-phone');
                const newError = document.getElementById('debt-new-customer-error');

                // Prefer picker selection when present
                if (picker?.value) {
                    if (customerSelect) customerSelect.value = picker.value;
                }

                // If main customer select still empty, attempt to register new customer
                if (!customerSelect?.value) {
                    const newName = newNameEl?.value?.trim();
                    const newPhone = newPhoneEl?.value?.trim();
                    if (!newName || !newPhone) {
                        const err = document.getElementById('debt-modal-error');
                        if (err) {
                            err.textContent = 'Select a customer or enter name and phone to register a new customer.';
                            err.classList.remove('hidden');
                        }
                        document.getElementById('debt-customer-picker-wrap')?.classList.remove('hidden');
                        return;
                    }

                    // Create customer via AJAX
                    newError.classList.add('hidden');
                    try {
                        const resp = await fetch(@json(route('manager.customers.store')), {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({ name: newName, phone: newPhone, ajax: 1 })
                        });

                        if (!resp.ok) {
                            const body = await resp.json().catch(() => ({}));
                            newError.textContent = body.message || 'Failed to create customer.';
                            newError.classList.remove('hidden');
                            return;
                        }

                        const data = await resp.json();
                        const customer = data.customer;
                        if (!customer || !customer.id) {
                            newError.textContent = 'Server returned invalid customer.';
                            newError.classList.remove('hidden');
                            return;
                        }

                        // Add to main select and pick it
                        const opt = document.createElement('option');
                        opt.value = customer.id;
                        opt.textContent = customer.name + ' — ' + (customer.phone || '');
                        opt.dataset.name = customer.name;
                        opt.dataset.phone = customer.phone || '';
                        opt.dataset.balance = customer.balance ?? 0;
                        customerSelect.appendChild(opt);
                        customerSelect.value = customer.id;
                    } catch (err) {
                        newError.textContent = err?.message || 'Failed to create customer.';
                        newError.classList.remove('hidden');
                        return;
                    }
                }

                // At this point we have a selected customer in main select
                const selected = customerSelect.selectedOptions[0];
                const nameInput = document.querySelector('#checkout-form input[name="customer_name"]');
                const phoneInput = document.querySelector('#checkout-form input[name="customer_phone"]');
                if (nameInput) nameInput.value = selected.dataset.name || '';
                if (phoneInput) phoneInput.value = selected.dataset.phone || '';

                if (cashReceivedInput && (cashReceivedInput.value === '' || cashReceivedInput.value === null)) {
                    cashReceivedInput.value = '0';
                }
                // Force whole rupees
                if (cashReceivedInput) cashReceivedInput.value = String(pkr(cashReceivedInput.value));
                debtConfirmProceed = true;
                document.getElementById('debt-confirm-modal')?.classList.add('hidden');
                const _form = document.getElementById('checkout-form'); if (_form) _form.submit();
            });

            document.getElementById('scroll-catalog')?.addEventListener('click', () => {
                document.getElementById('all-items')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });

            // ---- Line edit modal wiring ----
            const lineEditModal = document.getElementById('line-edit-modal');
            const lineEditName = document.getElementById('line-edit-name');
            const lineEditQty = document.getElementById('line-edit-qty');
            const lineEditPrice = document.getElementById('line-edit-price');
            const lineEditMode = document.getElementById('line-edit-mode');
            const lineEditTotalWrap = document.getElementById('line-edit-total-wrap');
            const lineEditTotal = document.getElementById('line-edit-total');
            const lineEditComputedQty = document.getElementById('line-edit-computed-qty');
            const lineEditComputedUnit = document.getElementById('line-edit-computed-unit');
            const lineEditUnitLabel = document.getElementById('line-edit-unit-label');
            const lineEditStockWarning = document.getElementById('line-edit-stock-warning');
            const lineEditAddBtn = document.getElementById('line-edit-add');
            const lineEditPriceIsTotal = document.getElementById('line-edit-price-is-total');
            let _pendingLine = null;

            function openLineEdit(type, id, name, price, stock, unit = '', allowFraction = false) {
                _pendingLine = { type, id, name, price, stock, unit, allowFraction };
                if (!lineEditModal) return;
                lineEditName.textContent = name;
                lineEditQty.value = '1';
                lineEditPrice.value = (Number(price) || 0).toFixed(2);
                // default mode: by quantity
                if (lineEditMode) lineEditMode.value = 'by_qty';
                if (lineEditTotal) lineEditTotal.value = (Number(price) || 0).toFixed(2);
                if (lineEditComputedQty) lineEditComputedQty.textContent = Number(lineEditQty.value).toFixed(2);
                if (lineEditTotalWrap) lineEditTotalWrap.style.display = 'none';
                if (lineEditPriceIsTotal) lineEditPriceIsTotal.checked = false;
                // set unit label from pending line or default for medicine_batch
                const displayUnit = (_pendingLine && _pendingLine.unit) ? _pendingLine.unit : ((_pendingLine && _pendingLine.type === 'medicine_batch') ? 'kg' : '');
                if (lineEditUnitLabel) lineEditUnitLabel.textContent = displayUnit ? `(${displayUnit})` : '';
                if (lineEditComputedUnit) lineEditComputedUnit.textContent = displayUnit;
                // ensure price field is editable by default; it will be readonly in by-total mode
                if (lineEditPrice) lineEditPrice.readOnly = false;
                // clear stock warning
                if (lineEditStockWarning) { lineEditStockWarning.classList.add('hidden'); lineEditStockWarning.textContent = ''; }
                if (lineEditAddBtn) lineEditAddBtn.disabled = false;
                lineEditModal.classList.remove('hidden');
            }

            function closeLineEdit() {
                _pendingLine = null;
                if (!lineEditModal) return;
                lineEditModal.classList.add('hidden');
            }

            document.getElementById('line-edit-close')?.addEventListener('click', closeLineEdit);
            document.getElementById('line-edit-cancel')?.addEventListener('click', closeLineEdit);
            document.getElementById('line-edit-add')?.addEventListener('click', () => {
                if (!_pendingLine) return closeLineEdit();
                let qty = parseFloat(lineEditQty.value) || 1;
                let price = parseFloat(lineEditPrice.value) || _pendingLine.price || 0;
                if (lineEditPriceIsTotal && lineEditPriceIsTotal.checked) {
                    const total = parseFloat(lineEditPrice.value) || 0;
                    const origUnit = _pendingLine.price || 0;
                    qty = origUnit > 0 ? Math.round((total / origUnit) * 100) / 100 : qty;
                    price = origUnit; // preserve unit price
                }
                const maybeIdx = addToCart(_pendingLine.type, _pendingLine.id, _pendingLine.name, price, _pendingLine.stock, qty);
                // If added via "price as total", mark the line and store original line total
                if (lineEditPriceIsTotal && lineEditPriceIsTotal.checked && maybeIdx !== -1) {
                    const total = parseFloat(lineEditPrice.value) || 0;
                    cart[maybeIdx].added_by_price_total = true;
                    cart[maybeIdx].original_line_total = Math.round(total * 100) / 100;
                    renderCart();
                }
                try {
                    saveRecent({ type: _pendingLine.type, id: _pendingLine.id, name: _pendingLine.name, price: price, stock: _pendingLine.stock });
                } catch (err) { }
                closeLineEdit();
                afterAddClear();
            });
            // Modal field interactions: switch between quantity and total-based selling
            if (lineEditMode) {
                lineEditMode.addEventListener('change', () => {
                    const mode = lineEditMode.value;
                    if (mode === 'by_total') {
                        if (lineEditTotalWrap) lineEditTotalWrap.style.display = '';
                        // initialize total from current qty * unit price
                        const p = parseFloat(_pendingLine?.price || lineEditPrice.value) || 0;
                        const q = parseFloat(lineEditQty.value) || 1;
                        if (lineEditTotal) lineEditTotal.value = (Math.round(p * q * 100) / 100).toFixed(2);
                        if (lineEditComputedQty) lineEditComputedQty.textContent = (q).toFixed(2);
                        // When selling by total, keep the unit price readonly and show the original unit price
                        if (lineEditPrice) {
                            lineEditPrice.value = (Number(_pendingLine?.price || lineEditPrice.value) || 0).toFixed(2);
                            lineEditPrice.readOnly = true;
                        }
                    } else {
                        if (lineEditTotalWrap) lineEditTotalWrap.style.display = 'none';
                        if (lineEditPrice) lineEditPrice.readOnly = false;
                    }
                });
            }

            function recalcQtyFromTotal() {
                // Prefer the original catalog/unit price from the pending line when computing quantity
                const p = parseFloat(_pendingLine?.price || lineEditPrice.value) || 0;
                const t = parseFloat(lineEditTotal.value) || 0;
                if (p <= 0) {
                    lineEditQty.value = '0.00';
                    if (lineEditComputedQty) lineEditComputedQty.textContent = '0.00';
                    return;
                }
                const q = Math.round((t / p) * 100) / 100;
                lineEditQty.value = q.toFixed(2);
                if (lineEditComputedQty) lineEditComputedQty.textContent = q.toFixed(2);
                validateLineQty(q);
            }

            if (lineEditTotal) {
                lineEditTotal.addEventListener('input', () => {
                    if (!lineEditMode || lineEditMode.value !== 'by_total') return;
                    recalcQtyFromTotal();
                });
            }

            if (lineEditPrice) {
                lineEditPrice.addEventListener('input', () => {
                    // When in by_total mode the unit price is readonly and calculations use the original unit price.
                    if (lineEditMode && lineEditMode.value === 'by_total') {
                        // restore displayed unit price to original pending price
                        lineEditPrice.value = (Number(_pendingLine?.price || 0)).toFixed(2);
                        return;
                    }
                    // when unit price changes in by_qty mode, keep total/qty consistent depending on mode
                    if (lineEditMode && lineEditMode.value === 'by_total' && lineEditTotal) {
                        recalcQtyFromTotal();
                    } else {
                        // update computed display
                        const p = parseFloat(lineEditPrice.value) || 0;
                        const q = parseFloat(lineEditQty.value) || 0;
                        if (lineEditTotal) lineEditTotal.value = (Math.round(p * q * 100) / 100).toFixed(2);
                        if (lineEditComputedQty) lineEditComputedQty.textContent = (q).toFixed(2);
                    }
                    validateLineQty(parseFloat(lineEditQty.value) || 0);
                });
            }

            if (lineEditQty) {
                lineEditQty.addEventListener('input', () => {
                    const q = parseFloat(lineEditQty.value) || 0;
                    const p = parseFloat(lineEditPrice.value) || 0;
                    if (lineEditMode && lineEditMode.value === 'by_total') {
                        // ignore direct qty editing in by_total mode; instead update total to reflect qty
                        if (lineEditTotal) lineEditTotal.value = (Math.round(p * q * 100) / 100).toFixed(2);
                    } else {
                        if (lineEditTotal) lineEditTotal.value = (Math.round(p * q * 100) / 100).toFixed(2);
                    }
                    if (lineEditComputedQty) lineEditComputedQty.textContent = (q).toFixed(2);
                    validateLineQty(q);
                });
            }

            function validateLineQty(q) {
                // Check against pending stock (if available)
                const stock = _pendingLine?.stock ?? null;
                if (stock !== null && stock !== undefined) {
                    if (q > Number(stock)) {
                        if (lineEditStockWarning) { lineEditStockWarning.textContent = `Only ${stock} available in stock.`; lineEditStockWarning.classList.remove('hidden'); }
                        if (lineEditAddBtn) lineEditAddBtn.disabled = true;
                        return false;
                    }
                }
                // basic sanity checks
                if (q <= 0) {
                    if (lineEditStockWarning) { lineEditStockWarning.textContent = 'Quantity must be greater than zero.'; lineEditStockWarning.classList.remove('hidden'); }
                    if (lineEditAddBtn) lineEditAddBtn.disabled = true;
                    return false;
                }
                if (lineEditStockWarning) { lineEditStockWarning.classList.add('hidden'); lineEditStockWarning.textContent = ''; }
                if (lineEditAddBtn) lineEditAddBtn.disabled = false;
                return true;
            }

            // ---- Quick add unknown barcode ----
            const quickModal = document.getElementById('quick-add-modal');
            const quickForm = document.getElementById('quick-add-form');
            const quickBarcode = document.getElementById('quick-add-barcode');
            const quickName = document.getElementById('quick-add-name');
            const quickPrice = document.getElementById('quick-add-price');
            const quickStatus = document.getElementById('quick-add-status');
            const quickStoreUrl = @json(route('manager.barcode.quick'));
            const barcodeLookupUrl = @json(route('manager.barcode.lookup'));
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

            function openQuickAdd(code) {
                if (!quickModal) return;
                quickBarcode.value = code;
                quickName.value = '';
                quickPrice.value = '';
                quickStatus.textContent = 'Looking up product name…';
                quickModal.classList.remove('hidden');
                fetch(barcodeLookupUrl + '?barcode=' + encodeURIComponent(code), { headers: { 'Accept': 'application/json' } })
                    .then(r => r.json())
                    .then(data => {
                        if (data.found && data.name) {
                            quickName.value = data.name;
                            quickStatus.textContent = data.price != null
                                ? 'Details found. Confirm price and save.'
                                : 'Name found. Enter your selling price.';
                            if (data.price != null) quickPrice.value = data.price;
                        } else {
                            quickStatus.textContent = 'Barcode not in database. Enter name and price.';
                        }
                        quickName.focus();
                    })
                    .catch(() => {
                        quickStatus.textContent = 'Enter name and price for this new product.';
                        quickName.focus();
                    });
            }

            function closeQuickAdd() {
                quickModal?.classList.add('hidden');
                scanInput?.focus();
            }

            document.getElementById('quick-add-close')?.addEventListener('click', closeQuickAdd);
            document.getElementById('quick-add-cancel')?.addEventListener('click', closeQuickAdd);
            quickModal?.addEventListener('click', (e) => { if (e.target === quickModal) closeQuickAdd(); });

            quickForm?.addEventListener('submit', (e) => {
                e.preventDefault();
                const barcode = quickBarcode.value.trim();
                const name = quickName.value.trim();
                const price = parseFloat(quickPrice.value);
                if (!name || Number.isNaN(price) || price < 0) {
                    quickStatus.textContent = 'Name and a valid price are required.';
                    return;
                }
                quickStatus.textContent = 'Saving…';
                const submitBtn = document.getElementById('quick-add-submit');
                if (submitBtn) submitBtn.disabled = true;

                fetch(quickStoreUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ barcode, name, price }),
                })
                    .then(async (r) => {
                        const data = await r.json().catch(() => ({}));
                        if (!r.ok) {
                            const msg = data.message || (data.errors ? Object.values(data.errors).flat().join(' ') : 'Could not save product');
                            throw new Error(msg);
                        }
                        return data;
                    })
                    .then((data) => {
                        if (data.line) {
                            addToCart(data.line.type, data.line.id, data.line.name, data.line.price, data.line.stock ?? null);
                            try {
                                saveRecent({
                                    type: data.line.type,
                                    id: data.line.id,
                                    name: data.line.name,
                                    price: data.line.price,
                                    stock: data.line.stock ?? null,
                                });
                            } catch (err) { }
                        }
                        closeQuickAdd();
                        afterAddClear();
                    })
                    .catch((err) => {
                        quickStatus.textContent = err.message || 'Save failed.';
                    })
                    .finally(() => {
                        if (submitBtn) submitBtn.disabled = false;
                    });
            });


            // Ensure short-payment modal buttons always have handlers (in case
            // the dynamic binding path isn't hit for some flows). These handlers
            // are safe to attach repeatedly because we use optional chaining.
            document.getElementById('short-accept-keep')?.addEventListener('click', () => {
                document.getElementById('accept-short-payment').value = '1';
                if (cashReceivedInput && (cashReceivedInput.value === '' || cashReceivedInput.value === null)) {
                    cashReceivedInput.value = '0';
                }
                if (cashReceivedInput) cashReceivedInput.value = String(pkr(cashReceivedInput.value));
                document.getElementById('short-accept-modal')?.classList.add('hidden');
                debtConfirmProceed = false;
                const _form = document.getElementById('checkout-form');
                if (_form) _form.submit();
            });

            document.getElementById('short-accept-debt')?.addEventListener('click', () => {
                document.getElementById('short-accept-modal')?.classList.add('hidden');
                document.getElementById('debt-modal-error')?.classList.add('hidden');
                document.getElementById('debt-confirm-modal')?.classList.remove('hidden');
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