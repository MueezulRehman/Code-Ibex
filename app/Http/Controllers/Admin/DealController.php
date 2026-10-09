<?php

namespace App\Http\Controllers\Admin;

use App\Models\Deal;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DealController extends Controller
{
    public function index()
    {
        $deals = Deal::orderBy('created_at', 'desc')->paginate(15);
        return view('manager.deals.index', compact('deals'));
    }

    public function create()
    {
        return view('manager.deals.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'price' => 'required|numeric|min:0',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'active' => 'boolean',
            'image' => 'nullable|file|mimes:jpg,jpeg,png,gif,webp|max:2048',
        ]);

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('deals', 'public');
        }

        $validated['is_active'] = $validated['active'] ?? false;
        unset($validated['active']);

        Deal::create($validated);

        return redirect()->route('manager.deals.index')
            ->with('success', 'Deal created successfully.');
    }

    public function edit(Deal $deal)
    {
        return view('manager.deals.edit', compact('deal'));
    }

    public function update(Request $request, Deal $deal)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'price' => 'required|numeric|min:0',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'active' => 'boolean',
            'image' => 'nullable|file|mimes:jpg,jpeg,png,gif,webp|max:2048',
        ]);

        if ($request->hasFile('image')) {
            if ($deal->image) {
                Storage::disk('public')->delete($deal->image);
            }
            $validated['image'] = $request->file('image')->store('deals', 'public');
        }

        $validated['is_active'] = $validated['active'] ?? false;
        unset($validated['active']);

        $deal->update($validated);

        return redirect()->route('manager.deals.index')
            ->with('success', 'Deal updated successfully.');
    }

    public function destroy(Deal $deal)
    {
        $deal->delete();
        return redirect()->route('manager.deals.index')
            ->with('success', 'Deal deleted successfully.');
    }
}
