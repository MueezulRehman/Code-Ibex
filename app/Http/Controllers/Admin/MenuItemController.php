<?php

namespace App\Http\Controllers\Admin;

use App\Models\MenuItem;
use App\Models\Category;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Storage;

class MenuItemController extends Controller
{
    public function index(Request $request)
    {
        $restaurant = auth()->user()->effectiveRestaurant();
        abort_unless($restaurant, 403, 'No restaurant is linked to this account.');
        Tenancy::configureTenantConnection($restaurant);

        $query = MenuItem::with(['category', 'sizes', 'variants.attributeValues.attribute'])->orderBy('created_at', 'desc');

        if ($q = trim((string) $request->input('q', ''))) {
            $query->where(function ($builder) use ($q) {
                $builder->where('name', 'like', "%{$q}%")
                    ->orWhere('sku', 'like', "%{$q}%")
                    ->orWhere('barcode', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%");
            });
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        if ($request->filled('season')) {
            $query->where('season', $request->string('season')->toString());
        }

        $items = $query->paginate(20)->withQueryString();
        $categories = Category::orderBy('name')->get();

        return view('manager.menu-items.index', compact('items', 'categories'));
    }

    public function create(Request $request)
    {
        $restaurant = auth()->user()->effectiveRestaurant();
        abort_unless($restaurant, 403, 'No restaurant is linked to this account.');
        Tenancy::configureTenantConnection($restaurant);
        $categories = Category::orderBy('name')->get();
        $selectedCategoryId = (int) $request->query('category_id', 0);
        return view('manager.menu-items.create', compact('categories', 'selectedCategoryId'));
    }

    public function store(Request $request)
    {
        $restaurantId = auth()->user()->effectiveRestaurantId();
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:100',
            'barcode' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:500',
            'season' => 'nullable|in:all-season,summer,winter',
            'price' => 'required|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'unit' => 'nullable|string|max:50',
            'unit_type' => 'nullable|string|max:30',
            'price_per_unit' => 'nullable|numeric|min:0',
            'allow_fractional_qty' => 'boolean',
            'pos_show_line_edit' => 'boolean',
            'category_id' => ['required', Rule::exists('categories', 'id')->where(fn($query) => $query->where('restaurant_id', $restaurantId))],
            'available' => 'boolean',
            'has_variants' => 'boolean',
            'track_stock' => 'boolean',
            'stock_quantity' => 'nullable|numeric|min:0',
            'low_stock_threshold' => 'nullable|numeric|min:0',
            'image' => 'nullable|file|mimes:jpg,jpeg,png,gif,webp|max:2048',
        ]);
        $category = Category::where('restaurant_id', $restaurantId)->findOrFail($validated['category_id']);

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('menu-items', 'public');
        }

        $validated['is_available'] = $validated['available'] ?? false;
        $validated['has_variants'] = $validated['has_variants'] ?? false;
        $validated['track_stock'] = $validated['track_stock'] ?? false;
        $validated['allow_fractional_qty'] = $validated['allow_fractional_qty'] ?? false;
        $validated['stock_quantity'] = $validated['stock_quantity'] ?? 0;
        $validated['low_stock_threshold'] = $validated['low_stock_threshold'] ?? 5;
        $validated['cost_price'] = $validated['cost_price'] ?? 0;
        $validated['unit_type'] = $validated['unit_type'] ?? ($validated['unit'] ?? 'piece');
        $validated['price_per_unit'] = $validated['price_per_unit'] ?? $validated['price'];
        $validated['season'] = $validated['season'] ?? 'all-season';
        unset($validated['available']);
        $validated['pos_show_line_edit'] = (bool) $category->pos_show_line_edit;

        MenuItem::create($validated);

        return redirect()->route('manager.menu-items.index')
            ->with('success', 'Menu item created successfully.');
    }

    public function edit(string $item)
    {
        $restaurant = auth()->user()->effectiveRestaurant();
        abort_unless($restaurant, 403, 'No restaurant is linked to this account.');
        Tenancy::configureTenantConnection($restaurant);
        $item = $this->resolveMenuItem($item);
        $categories = Category::orderBy('name')->get();
        $sizes = $item->sizes()->orderBy('sort_order')->get();
        return view('manager.menu-items.edit', compact('item', 'categories', 'sizes'));
    }

    public function update(Request $request, string $item)
    {
        $item = $this->resolveMenuItem($item);
        $restaurantId = auth()->user()->effectiveRestaurantId();
        $hasSizes = $item->sizes()->exists();
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:100',
            'barcode' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:500',
            'season' => 'nullable|in:all-season,summer,winter',
            'price' => $hasSizes ? 'nullable|numeric|min:0' : 'required|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'unit' => 'nullable|string|max:50',
            'unit_type' => 'nullable|string|max:30',
            'price_per_unit' => 'nullable|numeric|min:0',
            'allow_fractional_qty' => 'boolean',
            'pos_show_line_edit' => 'boolean',
            'category_id' => ['required', Rule::exists('categories', 'id')->where(fn($query) => $query->where('restaurant_id', $restaurantId))],
            'available' => 'boolean',
            'has_variants' => 'boolean',
            'track_stock' => 'boolean',
            'stock_quantity' => 'nullable|numeric|min:0',
            'low_stock_threshold' => 'nullable|numeric|min:0',
            'image' => 'nullable|file|mimes:jpg,jpeg,png,gif,webp|max:2048',
        ]);

        if ($request->hasFile('image')) {
            if ($item->image) {
                Storage::disk('public')->delete($item->image);
            }
            $validated['image'] = $request->file('image')->store('menu-items', 'public');
        } else {
            unset($validated['image']);
        }

        $updateData = [
            'name' => $validated['name'],
            'category_id' => (int) $validated['category_id'],
            'barcode' => $validated['barcode'] ?? null,
            'sku' => $validated['sku'] ?? null,
            'description' => $validated['description'] ?? null,
            'season' => $validated['season'] ?? 'all-season',
            'price' => $validated['price'] ?? $item->price,
            'price_per_unit' => $validated['price_per_unit'] ?? $validated['price'] ?? $item->price,
            'cost_price' => $validated['cost_price'] ?? ($item->cost_price ?? 0),
            'unit' => $validated['unit'] ?? null,
            'unit_type' => $validated['unit_type'] ?? ($validated['unit'] ?? 'piece'),
            'is_available' => $request->boolean('available'),
            'has_variants' => $request->boolean('has_variants'),
            'track_stock' => $request->boolean('track_stock'),
            'allow_fractional_qty' => $request->boolean('allow_fractional_qty'),
            'stock_quantity' => $validated['stock_quantity'] ?? 0,
            'low_stock_threshold' => $validated['low_stock_threshold'] ?? 5,
            'pos_show_line_edit' => Category::where('restaurant_id', $restaurantId)
                ->whereKey($validated['category_id'])
                ->value('pos_show_line_edit') ?? false,
        ];
        if (array_key_exists('image', $validated)) {
            $updateData['image'] = $validated['image'];
        }

        $item->fill($updateData);
        $item->save();

        return redirect()->route('manager.menu-items.index')
            ->with('success', 'Menu item updated successfully.');
    }

    public function destroy(string $item)
    {
        $item = $this->resolveMenuItem($item);
        $item->delete();
        return redirect()->route('manager.menu-items.index')
            ->with('success', 'Menu item deleted successfully.');
    }

    private function resolveMenuItem(string $item): MenuItem
    {
        return MenuItem::query()->findOrFail($item);
    }
}
