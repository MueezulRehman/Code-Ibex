<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bed;
use App\Models\User;
use App\Models\Ward;
use App\Models\WardLocation;
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
        $wards = Ward::withCount('beds')
            ->with(['locations.beds', 'beds'])
            ->orderBy('name')
            ->paginate(20);
        return view('manager.wards.index', compact('wards'));
    }

    public function store(Request $request)
    {
        $restaurantId = $this->restaurantId();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:30', 'alpha_dash', Rule::unique('wards', 'code')->where(fn ($q) => $q->where('restaurant_id', $restaurantId))],
            'description' => ['nullable', 'string', 'max:500'],
            'room_category' => ['nullable', 'string', 'max:100'],
            'daily_rate' => ['nullable', 'numeric', 'min:0'],
        ]);
        Ward::create([...$data, 'restaurant_id' => $restaurantId]);
        return back()->with('success', 'Ward created.');
    }

    public function storeLocation(Request $request, Ward $ward)
    {
        $restaurantId = $this->restaurantId();
        abort_unless((int) $ward->restaurant_id === $restaurantId, 404);

        $data = $request->validate([
            'type' => ['required', 'string', Rule::in(['room', 'hall', 'bay'])],
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:30',
                'alpha_dash',
                Rule::unique('ward_locations', 'code')->where(fn ($q) => $q->where('restaurant_id', $restaurantId)),
            ],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        WardLocation::create([...$data, 'restaurant_id' => $restaurantId, 'ward_id' => $ward->id]);

        return back()->with('success', 'Ward location created.');
    }

    public function updateLocation(Request $request, WardLocation $location)
    {
        $restaurantId = $this->restaurantId();
        abort_unless((int) $location->restaurant_id === $restaurantId, 404);

        $data = $request->validate([
            'type' => ['required', 'string', Rule::in(['room', 'hall', 'bay'])],
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:30',
                'alpha_dash',
                Rule::unique('ward_locations', 'code')
                    ->ignore($location->id)
                    ->where(fn ($q) => $q->where('restaurant_id', $restaurantId)),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $location->update($data);

        return back()->with('success', 'Ward location updated.');
    }

    public function destroyLocation(WardLocation $location)
    {
        $restaurantId = $this->restaurantId();
        abort_unless((int) $location->restaurant_id === $restaurantId, 404);

        if ($location->beds()->exists()) {
            return back()->withErrors(['location' => 'Move or remove the beds from this location before deleting it.']);
        }

        $location->delete();

        return back()->with('success', 'Ward location deleted.');
    }

    public function storeBed(Request $request, Ward $ward)
    {
        $restaurantId = $this->restaurantId();
        abort_unless((int) $ward->restaurant_id === $restaurantId, 404);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:30', 'alpha_dash', Rule::unique('beds', 'code')->where(fn ($q) => $q->where('restaurant_id', $restaurantId))],
            'location_id' => [
                'nullable',
                'integer',
                Rule::exists('ward_locations', 'id')->where(fn ($q) => $q
                    ->where('restaurant_id', $restaurantId)
                    ->where('ward_id', $ward->id)
                    ->where('is_active', true)),
            ],
        ]);

        if (! empty($data['location_id'])) {
            $location = WardLocation::whereKey($data['location_id'])->first();
            abort_unless(
                $location
                && (int) $location->restaurant_id === $restaurantId
                && (int) $location->ward_id === (int) $ward->id,
                404
            );
        }

        Bed::create([
            ...$data,
            'restaurant_id' => $restaurantId,
            'ward_id' => $ward->id,
            'status' => 'available',
        ]);

        return back()->with('success', 'Bed created.');
    }
}
