<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bed;
use App\Models\User;
use App\Models\Ward;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class WardController extends Controller
{
    private function restaurantId(): int
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);
        $id = $user->effectiveRestaurantId();
        abort_unless($id !== null, 403);
        return (int) $id;
    }

    public function index()
    {
        $wards = Ward::withCount('beds')->orderBy('name')->paginate(20);
        return view('manager.wards.index', compact('wards'));
    }

    public function store(Request $request)
    {
        $restaurantId = $this->restaurantId();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:30', 'alpha_dash', Rule::unique('wards', 'code')->where(fn ($q) => $q->where('restaurant_id', $restaurantId))],
            'description' => ['nullable', 'string', 'max:500'],
        ]);
        Ward::create([...$data, 'restaurant_id' => $restaurantId]);
        return back()->with('success', 'Ward created.');
    }

    public function storeBed(Request $request, Ward $ward)
    {
        $restaurantId = $this->restaurantId();
        abort_unless((int) $ward->restaurant_id === $restaurantId, 404);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:30', 'alpha_dash', Rule::unique('beds', 'code')->where(fn ($q) => $q->where('restaurant_id', $restaurantId))],
        ]);
        Bed::create([...$data, 'restaurant_id' => $restaurantId, 'ward_id' => $ward->id, 'status' => 'available']);
        return back()->with('success', 'Bed created.');
    }
}
