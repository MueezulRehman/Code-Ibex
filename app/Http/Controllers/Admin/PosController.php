<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cashbook;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerAllergy;
use App\Models\Deal;
use App\Models\MenuItem;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\MedicineCategory;
use App\Models\MedicineInteraction;
use App\Models\Order;
use App\Models\OrderItemTopping;
use App\Models\ProductVariant;
use App\Models\StockAdjustment;
use App\Models\Topping;
use App\Models\Table;
use App\Models\WholesalePriceList;
use App\Models\WholesalePriceListItem;
use App\Models\Branch;
use App\Models\BranchInventory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PosController extends Controller
{
    /**
     * Show the POS screen. The view rendered and the data loaded both
     * depend on the restaurant's POS mode (restaurant / retail / medical).
     */
    public function index(Request $request = null)
    {
        $request = $request ?? request();
        $restaurant = Auth::user()->effectiveRestaurant();
        abort_unless($restaurant, 403, 'No restaurant is linked to this account.');

        $posConfig = $restaurant->getPosConfigForRestaurant();
        $branches = Branch::where('restaurant_id', $restaurant->id)->where('is_active', true)->orderBy('name')->get();
        $assignedBranchId = Auth::user()->branch_id;
        $savedCart = session('pos_last_cart', []);
        $savedCustomerId = session('pos_last_customer_id');
        if ($savedCustomerId !== null && ! Customer::where('restaurant_id', $restaurant->id)->whereKey($savedCustomerId)->exists()) {
            $savedCustomerId = null;
            session()->forget('pos_last_customer_id');
        }
        $errorHighlight = session('pos_error_highlight');
        $checkoutError = session('pos_error_message');
        $selectedCategory = (string) $request->query('category', 'all');
        $selectedCategoryId = null;
        $selectedCategoryName = null;

        if ($selectedCategory !== 'all' && $selectedCategory !== '') {
            if ($selectedCategory === 'uncategorized') {
                $selectedCategoryName = 'Uncategorized';
            } else {
                $selectedCategoryId = (int) str_replace('cat-', '', $selectedCategory);
                $selectedCategoryName = MedicineCategory::find($selectedCategoryId)?->name;
            }
        }

        if ($posConfig['mode'] === 'restaurant') {
            $categories = Category::with(['availableMenuItems' => function ($q) {
                $q->with(['sizes', 'category']);
            }])->where('is_active', true)->orderBy('sort_order')->get();

            $toppings = Topping::all();
            $deals = Deal::active()->get();
            $tables = Table::where('restaurant_id', $restaurant->id)->orderBy('number')->get();
            $customers = Customer::where('restaurant_id', $restaurant->id)->orderBy('name')->get();
            $priceLists = WholesalePriceList::with('items')->where('restaurant_id', $restaurant->id)->where('is_active', true)->orderBy('name')->get();

            return view($posConfig['view'], compact('posConfig', 'categories', 'toppings', 'deals', 'tables', 'customers', 'priceLists', 'branches', 'assignedBranchId', 'savedCart', 'savedCustomerId', 'errorHighlight', 'checkoutError'));
        }

        // Retail / medical: flat, searchable product list. For medical mode,
        // or whenever this restaurant has seeded medicines with batches,
        // we expose medicines and their batches; retail uses MenuItem.
        $showMedicalItems = ($posConfig['mode'] ?? 'retail') === 'medical'
            || $restaurant->isModuleEnabled('medical')
            || Medicine::withoutGlobalScope('restaurant')->where(function ($q) use ($restaurant) {
                $q->whereNull('restaurant_id')->orWhere('restaurant_id', $restaurant->id);
            })->whereHas('batches', function ($q) use ($restaurant) {
                $q->where(function ($sub) use ($restaurant) {
                    $sub->whereNull('restaurant_id')->orWhere('restaurant_id', $restaurant->id);
                });
            })->exists();

        if ($showMedicalItems) {
            $items = $this->getMedicalItemsForPos($restaurant, $selectedCategory);

            $medicineCategories = MedicineCategory::with(['medicines' => function ($q) use ($restaurant) {
                $q->withoutGlobalScope('restaurant')->where(function ($sub) use ($restaurant) {
                    $sub->whereNull('restaurant_id')->orWhere('restaurant_id', $restaurant->id);
                })->with(['category', 'batches' => function ($batchQuery) use ($restaurant) {
                    $batchQuery->withoutGlobalScope('restaurant')->where(function ($sub) use ($restaurant) {
                        $sub->whereNull('restaurant_id')->orWhere('restaurant_id', $restaurant->id);
                    })->orderBy('expiry_date');
                }])->orderBy('name');
            }])->orderBy('name')->get();

            if ($selectedCategoryId) {
                $medicineCategories = $medicineCategories->filter(fn($category) => (int) $category->id === $selectedCategoryId);
            } elseif ($selectedCategory === 'uncategorized') {
                $medicineCategories = collect();
            }

            $uncategorized = $this->getMedicalItemsForPos($restaurant, 'uncategorized');

            $customers = Customer::where('restaurant_id', $restaurant->id)->orderBy('name')->get();
            $priceLists = WholesalePriceList::with('items')->where('restaurant_id', $restaurant->id)->where('is_active', true)->orderBy('name')->get();

            return view($posConfig['view'], compact('posConfig', 'items', 'showMedicalItems', 'medicineCategories', 'uncategorized', 'customers', 'priceLists', 'branches', 'assignedBranchId', 'savedCart', 'savedCustomerId', 'errorHighlight', 'checkoutError', 'selectedCategory', 'selectedCategoryName'));
        }

        // Retail: MenuItem list
        $items = MenuItem::with(['variants', 'category'])
            ->where('is_available', true)
            ->orderBy('name')
            ->get();

        $customers = Customer::where('restaurant_id', $restaurant->id)->orderBy('name')->get();
        $priceLists = WholesalePriceList::with('items')->where('restaurant_id', $restaurant->id)->where('is_active', true)->orderBy('name')->get();

        return view($posConfig['view'], compact('posConfig', 'items', 'customers', 'priceLists', 'branches', 'assignedBranchId', 'savedCart', 'savedCustomerId', 'errorHighlight', 'checkoutError'));
    }

    /**
     * AJAX lookup used by the retail/medical POS search box and barcode
     * scanner input. Supports:
     *  - barcode-first exact match (digits / typical EAN lengths)
     *  - multi-token name/SKU/generic search
     *  - weighted ranking (exact barcode/SKU > starts-with > contains)
     */
    public function lookup(Request $request)
    {
        $restaurant = Auth::user()->effectiveRestaurant();
        abort_unless($restaurant, 403);

        $term = trim((string) $request->query('q', ''));
        $posConfig = $restaurant->getPosConfigForRestaurant();

        if ($term === '') {
            return response()->json(['items' => []]);
        }

        $tokens = preg_split('/\s+/', mb_strtolower($term), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $isBarcodeLike = (bool) preg_match('/^[0-9]{6,14}$/', $term);

        $showMedicalItems = ($posConfig['mode'] ?? 'retail') === 'medical'
            || Medicine::withoutGlobalScope('restaurant')->where(function ($q) use ($restaurant) {
                $q->whereNull('restaurant_id')->orWhere('restaurant_id', $restaurant->id);
            })->whereHas('batches', function ($q) use ($restaurant) {
                $q->where(function ($sub) use ($restaurant) {
                    $sub->whereNull('restaurant_id')->orWhere('restaurant_id', $restaurant->id);
                });
            })->exists();

        if ($showMedicalItems) {
            $query = Medicine::with(['category', 'batches' => function ($q) use ($restaurant) {
                $q->where(function ($sub) use ($restaurant) {
                    $sub->whereNull('restaurant_id')->orWhere('restaurant_id', $restaurant->id);
                });
            }])->where(function ($sub) use ($restaurant) {
                $sub->whereNull('restaurant_id')->orWhere('restaurant_id', $restaurant->id);
            });

            // Barcode-first exact path
            if ($isBarcodeLike) {
                $exact = (clone $query)->where(function ($q) use ($term) {
                    $q->where('barcode', $term)->orWhere('sku', $term);
                })->limit(10)->get();
                if ($exact->isNotEmpty()) {
                    return response()->json([
                        'items' => $exact->map(fn($item) => $this->serializeItem($item))->values(),
                        'match' => 'barcode',
                    ]);
                }
            }

            $items = $query->where(function ($q) use ($term, $tokens) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('generic_name', 'like', "%{$term}%")
                    ->orWhere('sku', 'like', "%{$term}%")
                    ->orWhere('barcode', 'like', "%{$term}%");
                foreach ($tokens as $token) {
                    if (mb_strlen($token) < 2) {
                        continue;
                    }
                    $q->orWhere('name', 'like', "%{$token}%")
                        ->orWhere('generic_name', 'like', "%{$token}%")
                        ->orWhere('sku', 'like', "%{$token}%");
                }
            })
                ->limit(40)
                ->get();

            $ranked = $items->map(function ($item) use ($term, $tokens) {
                return [
                    'item' => $item,
                    'score' => $this->scorePosMatch($term, $tokens, [
                        'name' => $item->name,
                        'generic' => $item->generic_name,
                        'sku' => $item->sku,
                        'barcode' => $item->barcode,
                    ]),
                ];
            })
                ->sortByDesc('score')
                ->take(20)
                ->values();

            return response()->json([
                'items' => $ranked->map(fn($row) => $this->serializeItem($row['item']))->values(),
                'match' => 'search',
            ]);
        }

        $base = MenuItem::with('variants')
            ->where('is_available', true);

        if ($isBarcodeLike) {
            $exact = (clone $base)->where(function ($q) use ($term) {
                $q->where('barcode', $term)
                    ->orWhere('sku', $term)
                    ->orWhereHas('variants', fn($vq) => $vq->where('sku', $term));
            })->limit(10)->get();

            if ($exact->isNotEmpty()) {
                return response()->json([
                    'items' => $exact->map(fn($item) => $this->serializeItem($item))->values(),
                    'match' => 'barcode',
                ]);
            }
        }

        $items = $base->where(function ($q) use ($term, $tokens) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('sku', 'like', "%{$term}%")
                ->orWhere('barcode', 'like', "%{$term}%")
                ->orWhereHas('variants', function ($vq) use ($term) {
                    $vq->where('sku', 'like', "%{$term}%")
                        ->orWhere('variant_name', 'like', "%{$term}%");
                });
            foreach ($tokens as $token) {
                if (mb_strlen($token) < 2) {
                    continue;
                }
                $q->orWhere('name', 'like', "%{$token}%")
                    ->orWhere('sku', 'like', "%{$token}%");
            }
        })
            ->limit(40)
            ->get();

        $ranked = $items->map(function ($item) use ($term, $tokens) {
            $variantSkus = $item->variants->pluck('sku')->filter()->implode(' ');
            $variantNames = $item->variants->pluck('variant_name')->filter()->implode(' ');

            return [
                'item' => $item,
                'score' => $this->scorePosMatch($term, $tokens, [
                    'name' => $item->name,
                    'sku' => $item->sku,
                    'barcode' => $item->barcode,
                    'variant_sku' => $variantSkus,
                    'variant_name' => $variantNames,
                ]),
            ];
        })
            ->sortByDesc('score')
            ->take(20)
            ->values();

        return response()->json([
            'items' => $ranked->map(fn($row) => $this->serializeItem($row['item']))->values(),
            'match' => 'search',
        ]);
    }

    /**
     * Higher score = better match for POS ranking.
     */
    protected function scorePosMatch(string $term, array $tokens, array $fields): int
    {
        $termLower = mb_strtolower($term);
        $score = 0;

        foreach ($fields as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $valueLower = mb_strtolower((string) $value);

            if ($valueLower === $termLower) {
                $score += match ($key) {
                    'barcode' => 1000,
                    'sku', 'variant_sku' => 900,
                    'name' => 800,
                    default => 700,
                };
                continue;
            }

            if (str_starts_with($valueLower, $termLower)) {
                $score += match ($key) {
                    'sku', 'variant_sku', 'barcode' => 500,
                    'name' => 400,
                    default => 300,
                };
            } elseif (str_contains($valueLower, $termLower)) {
                $score += match ($key) {
                    'name' => 200,
                    'generic' => 180,
                    default => 120,
                };
            }

            foreach ($tokens as $token) {
                if (mb_strlen($token) < 2) {
                    continue;
                }
                if (str_contains($valueLower, $token)) {
                    $score += 40;
                }
            }
        }

        return $score;
    }

    protected function getMedicalItemsForPos($restaurant, $selectedCategory = null)
    {
        $query = Medicine::withoutGlobalScope('restaurant')->with(['category', 'batches' => function ($q) use ($restaurant) {
            $q->withoutGlobalScope('restaurant')->where(function ($sub) use ($restaurant) {
                $sub->whereNull('restaurant_id')->orWhere('restaurant_id', $restaurant->id);
            })->orderBy('expiry_date');
        }])
            ->where(function ($q) use ($restaurant) {
                $q->whereNull('restaurant_id')->orWhere('restaurant_id', $restaurant->id);
            });

        if ($selectedCategory && $selectedCategory !== 'all') {
            if ($selectedCategory === 'uncategorized') {
                $query->whereNull('category_id');
            } else {
                $query->where('category_id', (int) str_replace('cat-', '', $selectedCategory));
            }
        }

        return $query->orderBy('name')->get();
    }

    protected function resolveBranchInventoryForSale(int $restaurantId, int $branchId, string $itemType, int $itemId): ?BranchInventory
    {
        return BranchInventory::withoutGlobalScope('restaurant')
            ->where('restaurant_id', $restaurantId)
            ->where('branch_id', $branchId)
            ->where('item_type', $itemType)
            ->where('item_id', $itemId)
            ->lockForUpdate()
            ->first();
    }

    protected function serializeItem($item): array
    {
        // If a Medicine model is passed, serialize its batch data instead
        if ($item instanceof Medicine) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'generic_name' => $item->generic_name,
                'sku' => $item->sku,
                'barcode' => $item->barcode,
                'requires_prescription' => (bool) $item->requires_prescription,
                'track_stock' => (bool) $item->track_stock,
                'unit' => $item->unitLabel(),
                'allows_fractional' => $item->allowsFractionalQty(),
                'batches' => $item->batches->map(fn($b) => [
                    'id' => $b->id,
                    'batch_number' => $b->batch_number,
                    'expiry_date' => $b->expiry_date?->toDateString(),
                    'mfg_date' => $b->mfg_date?->toDateString(),
                    'price' => (float) $b->selling_price,
                    'purchase_price' => (float) $b->purchase_price,
                    'quantity' => (float) $b->quantity,
                ])->values()->toArray(),
            ];
        }

        return [
            'id' => $item->id,
            'name' => $item->name,
            'image' => $item->image,
            'sku' => $item->sku,
            'barcode' => $item->barcode,
            'price' => (float) $item->effectiveUnitPrice(),
            'price_per_unit' => (float) $item->effectiveUnitPrice(),
            'track_stock' => $item->track_stock,
            'stock_quantity' => (float) $item->stock_quantity,
            'unit' => $item->unitLabel(),
            'unit_label' => $item->unitLabel(),
            'allows_fractional' => $item->allowsFractionalQty(),
            'has_sizes' => $item->has_sizes,
            'sizes' => $item->has_sizes ? $item->sizes->map(fn($s) => [
                'label' => $s->size_label,
                'price' => (float) $s->price,
            ]) : [],
            'variants' => $item->variants->map(fn($v) => [
                'id' => $v->id,
                'sku' => $v->sku,
                'name' => $v->variant_name,
                'price' => (float) $v->getEffectivePrice(),
                'quantity_available' => $v->quantity_available,
            ]),
        ];
    }

    /**
     * Complete a sale. Always re-prices from the database (never trusts
     * client-sent prices), marks the order as "delivered" since a POS sale
     * is instant, deducts tracked stock, and logs the cash sale into the
     * cashbook — same auto-log behaviour as a normal order being marked
     * delivered.
     */
    public function checkout(Request $request)
    {
        $restaurant = Auth::user()->effectiveRestaurant();
        abort_unless($restaurant, 403);

        $cartPayload = $request->input('cart', []);

        // The POS screen posts the cart as a JSON string (built client-side
        // as items are scanned/tapped); decode it into an array before the
        // normal Laravel array-validation rules run against it.
        if ($request->has('cart') && is_string($request->input('cart'))) {
            $decoded = json_decode($request->input('cart'), true);
            $request->merge(['cart' => is_array($decoded) ? $decoded : []]);
            $cartPayload = is_array($decoded) ? $decoded : [];
        }

        $validated = $request->validate([
            'order_type' => 'nullable|in:dine_in,takeaway,delivery,online,table',
            'table_number' => 'nullable|string|max:50|required_if:order_type,table',
            'customer_id' => 'nullable|integer|exists:customers,id',
            'branch_id' => 'nullable|integer|exists:branches,id',
            'wholesale_price_list_id' => 'nullable|integer|exists:wholesale_price_lists,id',
            'customer_name' => 'nullable|string|max:100',
            'customer_phone' => 'nullable|string|max:20',
            'payment_method' => 'required|in:cash,online',
            'amount_received' => 'nullable|numeric|min:0',
            'accept_short_payment_without_debt' => 'nullable|boolean',
            'notes' => 'nullable|string|max:500',
            'prescription' => 'nullable|boolean',
            'prescription_doctor_id' => 'nullable|integer',
            'cart' => 'required|array|min:1',
            'cart.*.type' => 'required|in:menu_item,variant,deal,medicine_batch',
            'cart.*.id' => 'required|integer',
            // Allow fractional/weight quantities (e.g. 0.5 kg)
            'cart.*.quantity' => 'required|numeric|min:0.001|max:9999',
            'cart.*.price' => 'nullable|numeric|min:0',
            'cart.*.original_line_total' => 'nullable|numeric|min:0',
            'cart.*.size_label' => 'nullable|string',
            'cart.*.topping_ids' => 'nullable|array',
            'cart.*.line_discount_type' => 'nullable|in:percent,fixed',
            'cart.*.line_discount_value' => 'nullable|numeric|min:0',
            'bill_discount_type' => 'nullable|in:percent,fixed',
            'bill_discount_value' => 'nullable|numeric|min:0',
        ]);

        $customer = null;
        if (!empty($validated['customer_id'])) {
            $customer = Customer::where('restaurant_id', $restaurant->id)->find($validated['customer_id']);
        }

        if (! $customer && !empty($validated['customer_phone'])) {
            $customer = Customer::where('restaurant_id', $restaurant->id)
                ->where('phone', $validated['customer_phone'])
                ->first();

            if (! $customer) {
                $customer = Customer::create([
                    'restaurant_id' => $restaurant->id,
                    'name' => $validated['customer_name'] ?: 'Walk-in Customer',
                    'phone' => $validated['customer_phone'],
                    'password' => bcrypt(Str::random(16)),
                ]);
            }
        }

        $priceList = null;
        if (! empty($validated['wholesale_price_list_id'])) {
            $priceList = WholesalePriceList::with('items')
                ->where('restaurant_id', $restaurant->id)
                ->where('is_active', true)
                ->findOrFail($validated['wholesale_price_list_id']);
        }

        $branch = null;
        if (! empty($validated['branch_id'])) {
            $branch = Branch::where('restaurant_id', $restaurant->id)->where('is_active', true)->findOrFail($validated['branch_id']);
        }

        $highlightedLine = null;

        try {
            $order = DB::transaction(function () use ($validated, $restaurant, $customer, $priceList, $branch, $cartPayload, &$highlightedLine) {
                $subtotal = 0;
                $lineItems = [];
                $stockMoves = []; // ['menu_item'|'variant', model, qtySold]

                foreach ($validated['cart'] as $line) {
                    if ($line['type'] === 'menu_item') {
                        $menuItem = MenuItem::with('sizes')->where('restaurant_id', $restaurant->id)->findOrFail($line['id']);

                        if (! $menuItem->is_available) {
                            abort(422, "{$menuItem->name} is currently unavailable.");
                        }

                        $unitPrice = $menuItem->has_sizes
                            ? optional($menuItem->sizes->firstWhere('size_label', $line['size_label'] ?? null))->price
                            : $menuItem->price;

                        if ($priceList) {
                            $listedPrice = $priceList->items->first(fn($item) => (int) $item->menu_item_id === (int) $menuItem->id && $item->product_variant_id === null)?->price;
                            if ($listedPrice !== null) $unitPrice = $listedPrice;
                        }

                        // Allow cashier to override unit price for this bill (server trusts authenticated cashier)
                        if (! $priceList && isset($line['price']) && is_numeric($line['price'])) {
                            $unitPrice = max(0, (float) $line['price']);
                        }

                        if ($unitPrice === null) {
                            abort(422, "Invalid size for {$menuItem->name}.");
                        }

                        $toppingTotal = 0;
                        $toppings = [];
                        if (! empty($line['topping_ids'])) {
                            foreach (Topping::whereIn('id', $line['topping_ids'])->get() as $t) {
                                $toppingTotal += $t->price;
                                $toppings[] = $t;
                            }
                        }

                        $lineTotal = ($unitPrice + $toppingTotal) * $line['quantity'];
                        $lineDiscType = $line['line_discount_type'] ?? null;
                        $lineDiscValue = (float) ($line['line_discount_value'] ?? 0);
                        if ($lineDiscValue > 0 && in_array($lineDiscType, ['percent', 'fixed'], true)) {
                            if ($lineDiscType === 'percent') {
                                $lineTotal = (int) round(max(0, $lineTotal * (1 - min(100, $lineDiscValue) / 100)));
                            } else {
                                $lineTotal = (int) round(max(0, $lineTotal - min($lineTotal, $lineDiscValue)));
                            }
                            $unitPrice = $line['quantity'] > 0 ? (int) round($lineTotal / $line['quantity']) : ($unitPrice + $toppingTotal);
                        }

                        $subtotal += $lineTotal;

                        if ($menuItem->track_stock) {
                            $branchStock = $branch ? $this->resolveBranchInventoryForSale($restaurant->id, $branch->id, 'menu_item', $menuItem->id) : null;
                            $available = $branchStock ? (float) $branchStock->quantity : (float) $menuItem->stock_quantity;
                            if ($available < $line['quantity']) {
                                abort(422, "Not enough stock for {$menuItem->name} at " . ($branch?->name ?? 'the selected location') . " (only {$available} left).");
                            }
                            $stockMoves[] = ['menu_item', $menuItem, $line['quantity'], $branchStock];
                        }

                        $lineItems[] = [
                            'item_type' => 'menu_item',
                            'menu_item_id' => $menuItem->id,
                            'product_variant_id' => null,
                            'deal_id' => null,
                            'item_name' => $menuItem->name,
                            'size_label' => $line['size_label'] ?? null,
                            'quantity' => $line['quantity'],
                            'unit_price' => $unitPrice + $toppingTotal,
                            'total_price' => $lineTotal,
                            'special_request' => null,
                            'toppings' => $toppings,
                            'original_total' => isset($line['original_line_total']) ? round((float) $line['original_line_total'], 2) : null,
                        ];
                    } elseif ($line['type'] === 'variant') {
                        $variant = ProductVariant::with('menuItem')->where('restaurant_id', $restaurant->id)->findOrFail($line['id']);

                        if (! $variant->is_available) {
                            abort(422, "{$variant->variant_name} is currently unavailable.");
                        }

                        $branchStock = $branch ? $this->resolveBranchInventoryForSale($restaurant->id, $branch->id, 'variant', $variant->id) : null;
                        $available = $branchStock ? (float) $branchStock->quantity : (float) $variant->quantity_available;
                        if ($available < $line['quantity']) {
                            abort(422, "Not enough stock for {$variant->variant_name} at " . ($branch?->name ?? 'the selected location') . " (only {$available} left).");
                        }

                        $unitPrice = $variant->getEffectivePrice();
                        if ($priceList) {
                            $listedPrice = $priceList->items->first(fn($item) => (int) $item->product_variant_id === (int) $variant->id)?->price;
                            if ($listedPrice !== null) $unitPrice = $listedPrice;
                        }
                        if (! $priceList && isset($line['price']) && is_numeric($line['price'])) {
                            $unitPrice = max(0, (float) $line['price']);
                        }
                        $lineTotal = $unitPrice * $line['quantity'];
                        $lineDiscType = $line['line_discount_type'] ?? null;
                        $lineDiscValue = (float) ($line['line_discount_value'] ?? 0);
                        if ($lineDiscValue > 0 && in_array($lineDiscType, ['percent', 'fixed'], true)) {
                            if ($lineDiscType === 'percent') {
                                $lineTotal = (int) round(max(0, $lineTotal * (1 - min(100, $lineDiscValue) / 100)));
                            } else {
                                $lineTotal = (int) round(max(0, $lineTotal - min($lineTotal, $lineDiscValue)));
                            }
                            $unitPrice = $line['quantity'] > 0 ? (int) round($lineTotal / $line['quantity']) : $unitPrice;
                        }

                        $subtotal += $lineTotal;

                        $stockMoves[] = ['variant', $variant, $line['quantity'], $branchStock];

                        $lineItems[] = [
                            'item_type' => 'menu_item',
                            'menu_item_id' => $variant->menu_item_id,
                            'product_variant_id' => $variant->id,
                            'deal_id' => null,
                            'item_name' => $variant->menuItem->name . ' — ' . $variant->variant_name,
                            'size_label' => null,
                            'quantity' => $line['quantity'],
                            'unit_price' => $unitPrice,
                            'total_price' => $lineTotal,
                            'special_request' => null,
                            'toppings' => [],
                            'original_total' => isset($line['original_line_total']) ? round((float) $line['original_line_total'], 2) : null,
                        ];
                    } elseif ($line['type'] === 'deal') {
                        $deal = Deal::where('restaurant_id', $restaurant->id)->findOrFail($line['id']);

                        if (! $deal->is_active) {
                            abort(422, "{$deal->name} is currently unavailable.");
                        }

                        $unitPrice = $deal->price;
                        if (isset($line['price']) && is_numeric($line['price'])) {
                            $unitPrice = max(0, (float) $line['price']);
                        }
                        $lineTotal = $unitPrice * $line['quantity'];
                        $subtotal += $lineTotal;

                        $lineItems[] = [
                            'item_type' => 'deal',
                            'menu_item_id' => null,
                            'product_variant_id' => null,
                            'deal_id' => $deal->id,
                            'item_name' => $deal->name,
                            'size_label' => null,
                            'quantity' => $line['quantity'],
                            'unit_price' => $deal->price,
                            'total_price' => $lineTotal,
                            'special_request' => null,
                            'toppings' => [],
                            'original_total' => isset($line['original_line_total']) ? round((float) $line['original_line_total'], 2) : null,
                        ];
                    } elseif ($line['type'] === 'medicine_batch') {
                        $batch = MedicineBatch::with('medicine')->where('restaurant_id', $restaurant->id)->findOrFail($line['id']);

                        if ($batch->expiry_date && $batch->expiry_date->isPast()) {
                            $highlightedLine = ['type' => 'medicine_batch', 'id' => $line['id']];
                            abort(422, "Batch {$batch->batch_number} of {$batch->medicine->name} is expired and cannot be sold.");
                        }

                        $medicine = $batch->medicine;
                        if ($medicine->requires_prescription && empty($validated['prescription'])) {
                            abort(422, "{$medicine->name} requires a prescription to sell.");
                        }

                        if ($customer) {
                            $allergyWarnings = $customer->getAllergyWarningsForMedicine($medicine->id);
                            if ($allergyWarnings->isNotEmpty()) {
                                $names = $allergyWarnings->pluck('allergy_name')->implode(', ');
                                abort(422, "Customer allergy warning: {$names}. Please verify before completing the sale.");
                            }
                        }

                        if ($batch->quantity < $line['quantity']) {
                            abort(422, "Not enough stock for {$medicine->name} (batch {$batch->batch_number}) — only {$batch->quantity} left.");
                        }

                        $unitPrice = $batch->selling_price;
                        if (isset($line['price']) && is_numeric($line['price'])) {
                            $unitPrice = max(0, (float) $line['price']);
                        }
                        $lineTotal = $unitPrice * $line['quantity'];
                        $lineDiscType = $line['line_discount_type'] ?? null;
                        $lineDiscValue = (float) ($line['line_discount_value'] ?? 0);
                        if ($lineDiscValue > 0 && in_array($lineDiscType, ['percent', 'fixed'], true)) {
                            if ($lineDiscType === 'percent') {
                                $lineTotal = (int) round(max(0, $lineTotal * (1 - min(100, $lineDiscValue) / 100)));
                            } else {
                                $lineTotal = (int) round(max(0, $lineTotal - min($lineTotal, $lineDiscValue)));
                            }
                            $unitPrice = $line['quantity'] > 0 ? (int) round($lineTotal / $line['quantity']) : $unitPrice;
                        }

                        $subtotal += $lineTotal;

                        $stockMoves[] = ['medicine_batch', $batch, $line['quantity'], null];

                        $lineItems[] = [
                            'item_type' => 'medicine',
                            'menu_item_id' => null,
                            'product_variant_id' => null,
                            'deal_id' => null,
                            'medicine_batch_id' => $batch->id,
                            'item_name' => $medicine->name . ' — Batch ' . $batch->batch_number,
                            'size_label' => null,
                            'quantity' => $line['quantity'],
                            'unit_price' => $unitPrice,
                            'total_price' => $lineTotal,
                            'special_request' => null,
                            'toppings' => [],
                            'original_total' => isset($line['original_line_total']) ? round((float) $line['original_line_total'], 2) : null,
                        ];
                    }
                }

                $medicineIds = collect($lineItems)->filter(fn($item) => ($item['item_type'] ?? null) === 'medicine')->pluck('medicine_id')->filter()->values();
                $medicineWarnings = [];
                $medicineIds->each(function ($medicineId) use (&$medicineWarnings) {
                    $interactions = MedicineInteraction::getInteractionsFor($medicineId);
                    foreach ($interactions as $interaction) {
                        $other = $interaction->medicine_id_1 === $medicineId ? $interaction->medicineSecond : $interaction->medicineFirst;
                        $medicineWarnings[] = [
                            'medicine' => $other?->name ?? 'Unknown medicine',
                            'type' => $interaction->interaction_type,
                            'description' => $interaction->interaction_description,
                            'action' => $interaction->recommended_action,
                        ];
                    }
                });

                if (!empty($medicineWarnings)) {
                    $warningSummary = collect($medicineWarnings)->map(fn($warning) => "{$warning['medicine']} ({$warning['type']})")->implode(', ');
                    abort(422, "Drug interaction warning detected: {$warningSummary}. Please verify before completing the sale.");
                }

                // --- Discounts + PKR whole rupees (no decimals charged in PK) ---
                $pkr = static fn($n) => (int) max(0, (int) round((float) $n));

                $grossSubtotal = $pkr($subtotal);
                $billDiscountType = $validated['bill_discount_type'] ?? null;
                $billDiscountValue = (float) ($validated['bill_discount_value'] ?? 0);
                $billDiscountAmount = 0;
                if ($billDiscountValue > 0 && in_array($billDiscountType, ['percent', 'fixed'], true)) {
                    if ($billDiscountType === 'percent') {
                        $billDiscountAmount = $pkr(min($grossSubtotal, $grossSubtotal * min(100, $billDiscountValue) / 100));
                    } else {
                        $billDiscountAmount = $pkr(min($grossSubtotal, $billDiscountValue));
                    }
                }
                $subtotal = $pkr(max(0, $grossSubtotal - $billDiscountAmount));

                $discountNote = '';
                if ($billDiscountAmount > 0) {
                    $discountNote = $billDiscountType === 'percent'
                        ? sprintf('Bill discount %s%% (−Rs. %s). ', rtrim(rtrim(number_format($billDiscountValue, 2), '0'), '.'), number_format($billDiscountAmount, 0))
                        : sprintf('Bill discount −Rs. %s. ', number_format($billDiscountAmount, 0));
                }

                $amountReceived = $pkr($validated['amount_received'] ?? 0);
                $changeAmount = $pkr(max(0, $amountReceived - $subtotal));
                $balanceDue = $pkr(max(0, $subtotal - $amountReceived));

                if ($customer && $balanceDue > 0 && $customer->credit_limit !== null) {
                    $projectedBalance = round((float) $customer->balance + $balanceDue, 2);
                    if ($projectedBalance > (float) $customer->credit_limit) {
                        abort(422, "Credit limit exceeded for {$customer->name}. Available credit: Rs. " . number_format(max(0, (float) $customer->credit_limit - (float) $customer->balance), 2) . '.');
                    }
                }

                $posConfig = $restaurant->getPosConfigForRestaurant();
                $shortPaymentAllowed = $posConfig['allow_short_payment_without_debt'] ?? false;
                $shortPaymentThreshold = (int) ($posConfig['short_payment_threshold'] ?? 0);

                $order = Order::create([
                    'restaurant_id' => $restaurant->id,
                    'branch_id' => $branch?->id,
                    'customer_id' => $customer?->id,
                    'wholesale_price_list_id' => $priceList?->id,
                    'sales_representative_id' => $customer?->sales_representative_id,
                    'order_type' => $validated['order_type'] ?? 'takeaway',
                    'channel' => 'pos',
                    'cashier_id' => Auth::id(),
                    'table_number' => $validated['table_number'] ?? null,
                    'status' => 'delivered',
                    'customer_name' => $validated['customer_name'] ?? 'Walk-in Customer',
                    'customer_phone' => $validated['customer_phone'] ?? '0000000000',
                    'address' => null,
                    'subtotal' => $subtotal,
                    'delivery_fee' => 0,
                    'total' => $subtotal,
                    'amount_received' => $amountReceived,
                    'change_amount' => $changeAmount,
                    'payment_method' => $validated['payment_method'],
                    'notes' => trim(($discountNote ?? '') . ($validated['notes'] ?? '')) ?: null,
                    'estimated_minutes' => 0,
                    'confirmed_at' => now(),
                    'ready_at' => now(),
                    'delivered_at' => now(),
                ]);

                if ($balanceDue > 0) {
                    // If short payments are allowed and within threshold, require
                    // explicit cashier confirmation (frontend sets the flag).
                    if ($shortPaymentAllowed && $balanceDue <= $shortPaymentThreshold) {
                        if (empty($validated['accept_short_payment_without_debt'])) {
                            abort(422, 'Short payment detected. Confirm accepting the payment without recording customer debt.');
                        }

                        // If confirmed and no customer, it's acceptable: no debt created.
                    } else {
                        // Otherwise (not allowed or above threshold) we require a customer
                        // and automatically create a debt entry for them.
                        if (! $customer) {
                            abort(422, 'This sale has an unpaid balance. Select a customer on the bill, then confirm charging the remaining amount to their account.');
                        }

                        // Record customer debt automatically
                        if ($customer) {
                            $customer->recordBalanceChange($balanceDue, "Outstanding balance for POS sale {$order->order_number}", [
                                'restaurant_id' => $restaurant->id,
                                'order_id' => $order->id,
                                'created_by' => Auth::id(),
                                'source' => 'pos',
                                'type' => 'charge',
                            ]);
                        }
                    }
                }

                // Excess cash over the bill → apply to existing customer debt first, remainder is change
                if ($customer && $changeAmount > 0) {
                    $customer->refresh();
                    $existingDebt = (float) $customer->balance;
                    if ($existingDebt > 0) {
                        $debtPayment = round(min($changeAmount, $existingDebt), 2);
                        if ($debtPayment > 0) {
                            $customer->recordBalanceChange($debtPayment, "Debt payment from excess cash on POS sale {$order->order_number}", [
                                'restaurant_id' => $restaurant->id,
                                'order_id' => $order->id,
                                'created_by' => Auth::id(),
                                'source' => 'pos',
                                'type' => 'payment',
                            ]);
                            $changeAmount = round(max(0, $changeAmount - $debtPayment), 2);
                            $order->update(['change_amount' => $changeAmount]);
                        }
                    }
                }

                foreach ($lineItems as $line) {
                    $toppingsToAttach = $line['toppings'];
                    unset($line['toppings']);
                    $orderItem = $order->items()->create($line);

                    foreach ($toppingsToAttach as $t) {
                        OrderItemTopping::create([
                            'order_item_id' => $orderItem->id,
                            'topping_id' => $t->id,
                            'topping_name' => $t->name,
                            'price' => $t->price,
                        ]);
                    }
                }

                foreach ($stockMoves as [$kind, $model, $qty, $branchStock]) {
                    if ($branchStock) {
                        $before = (float) $branchStock->quantity;
                        $after = $before - (float) $qty;

                        $branchStock->refresh();
                        $branchStock->quantity = $after;
                        $branchStock->save();

                        StockAdjustment::create([
                            'restaurant_id' => $restaurant->id,
                            'product_variant_id' => $kind === 'variant' ? $model->id : null,
                            'menu_item_id' => $kind === 'menu_item' ? $model->id : null,
                            'user_id' => Auth::id(),
                            'quantity_before' => $before,
                            'quantity_after' => $after,
                            'change_quantity' => -$qty,
                            'reason' => 'sale',
                            'reference_id' => $order->id,
                            'notes' => "Branch POS sale at {$branchStock->branch_id} — order {$order->order_number}",
                        ]);
                        continue;
                    }
                    if ($kind === 'menu_item') {
                        $before = $model->stock_quantity;
                        $after = $before - $qty;
                        $model->update(['stock_quantity' => $after]);
                        $variantId = null;
                    } elseif ($kind === 'variant') {
                        $before = $model->quantity_available;
                        $after = $before - $qty;
                        $model->update(['quantity_available' => $after]);
                        $variantId = $model->id;
                    } elseif ($kind === 'medicine_batch') {
                        $before = $model->quantity;
                        $after = $before - $qty;
                        $model->update(['quantity' => $after]);
                        $variantId = null;
                    } else {
                        continue;
                    }

                    StockAdjustment::create([
                        'restaurant_id' => $restaurant->id,
                        'product_variant_id' => $variantId,
                        'user_id' => Auth::id(),
                        'quantity_before' => $before,
                        'quantity_after' => $after,
                        'change_quantity' => -$qty,
                        'reason' => 'sale',
                        'reference_id' => $order->id,
                        'notes' => isset($model->batch_number) ? "POS sale — order {$order->order_number} — batch {$model->batch_number}" : "POS sale — order {$order->order_number}",
                    ]);
                }

                // Same auto-cashbook logging the regular order flow does when a
                // cash order is marked delivered — a POS sale is delivered
                // instantly so we log it right here instead.
                if ($order->payment_method === 'cash') {
                    Cashbook::create([
                        'restaurant_id' => $restaurant->id,
                        'type' => 'in',
                        'amount' => $order->total,
                        'description' => "POS sale {$order->order_number} ({$order->customer_name})",
                        'source' => 'order',
                        'order_id' => $order->id,
                        'date' => now()->toDateString(),
                        'created_by' => Auth::id(),
                    ]);
                }

                return $order;
            });
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            if (! $highlightedLine && is_array($cartPayload)) {
                $highlightedLine = collect($cartPayload)->first(fn($line) => ($line['type'] ?? null) === 'medicine_batch') ?: null;
            }

            session()->flash('pos_last_cart', $cartPayload);
            session()->flash('pos_error_message', $e->getMessage());
            session()->flash('pos_error_highlight', $highlightedLine);

            return back()->withErrors(['cart' => $e->getMessage()])->withInput();
        }

        session()->forget(['pos_last_cart', 'pos_last_customer_id', 'pos_error_message', 'pos_error_highlight']);

        return redirect()->route('manager.pos.receipt', [
            'order' => $order->id,
            'print' => 1,
        ]);
    }

    /**
     * Printable receipt/invoice for a completed POS sale.
     */
    public function receipt(string $orderId)
    {
        $user = Auth::user();
        $order = Order::query()->findOrFail($orderId);

        if (! $user->isSuperAdmin() && $order->restaurant_id !== $user->effectiveRestaurantId()) {
            abort(403);
        }

        $order->load(['items.toppings', 'items.variant']);
        $restaurant = $user->effectiveRestaurant() ?: $order->restaurant;

        return view('manager.pos.receipt', compact('order', 'restaurant'));
    }

    /**
     * Sales history — every POS-rung sale (channel = 'pos'), distinct from
     * customer-placed online orders, with the cashier who rang each one up.
     * This is the actual "sale record" the register needs, since orders and
     * POS sales otherwise live in the same table with no way to tell them
     * apart or see who was on the till.
     */
    public function sales(Request $request)
    {
        $restaurant = Auth::user()->effectiveRestaurant();
        abort_unless($restaurant, 403);

        $query = Order::with('cashier')
            ->where('restaurant_id', $restaurant->id)
            ->where('channel', 'pos')
            ->latest();

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->input('to'));
        }
        if ($request->filled('cashier_id')) {
            $query->where('cashier_id', $request->input('cashier_id'));
        }

        $todaysSales = (clone $query)->whereDate('created_at', now()->toDateString())->get();
        $summary = [
            'today_count' => $todaysSales->count(),
            'today_total' => $todaysSales->sum('total'),
        ];

        $sales = $query->paginate(25)->withQueryString();

        $cashiers = \App\Models\User::where('restaurant_id', $restaurant->id)->orderBy('name')->get();

        return view('manager.pos.sales', compact('sales', 'summary', 'cashiers'));
    }
}
