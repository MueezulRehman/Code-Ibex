<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Reservation;
use App\Models\Table;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class ReservationController extends Controller
{
    private function restaurantId(): int
    {
        $id = auth()->user()?->effectiveRestaurantId();
        abort_unless($id, 403);
        return (int) $id;
    }

    public function index(Request $request)
    {
        $id = $this->restaurantId();
        $reservations = Reservation::with(['customer', 'table'])->where('restaurant_id', $id)->orderBy('starts_at')->paginate(20)->withQueryString();
        return view('manager.reservations.index', compact('reservations'));
    }

    public function create()
    {
        $id = $this->restaurantId();
        $customers = Customer::where('restaurant_id', $id)->orderBy('name')->get();
        $tables = Table::where('restaurant_id', $id)->orderBy('number')->get();
        return view('manager.reservations.create', compact('customers', 'tables'));
    }

    public function store(Request $request)
    {
        $id = $this->restaurantId();
        $data = $request->validate([
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')->where(fn($q) => $q->where('restaurant_id', $id))],
            'table_id' => ['nullable', 'integer', Rule::exists('tables', 'id')->where(fn($q) => $q->where('restaurant_id', $id))],
            'guest_name' => 'required|string|max:150',
            'guest_phone' => 'nullable|string|max:40',
            'party_size' => 'required|integer|min:1|max:100',
            'starts_at' => 'required|date',
            'ends_at' => 'nullable|date|after:starts_at',
            'status' => ['required', Rule::in(['requested', 'confirmed', 'seated', 'completed', 'cancelled', 'no_show'])],
            'notes' => 'nullable|string|max:2000',
        ]);
        $start = Carbon::parse($data['starts_at']);
        $end = isset($data['ends_at']) ? Carbon::parse($data['ends_at']) : $start->copy()->addHours(2);
        if ($data['table_id'] && Reservation::where('restaurant_id', $id)->where('table_id', $data['table_id'])->whereNotIn('status', ['cancelled', 'completed', 'no_show'])->where('starts_at', '<', $end)->where(function ($q) use ($start) {
            $q->whereNull('ends_at')->orWhere('ends_at', '>', $start);
        })->exists()) {
            return back()->withInput()->withErrors(['table_id' => 'This table is already reserved for the selected time.']);
        }
        Reservation::create([...$data, 'restaurant_id' => $id, 'ends_at' => $end]);
        return redirect()->route('manager.reservations.index')->with('success', 'Reservation created successfully.');
    }

    public function updateStatus(Request $request, Reservation $reservation)
    {
        abort_unless($reservation->restaurant_id === $this->restaurantId(), 404);
        $data = $request->validate(['status' => ['required', Rule::in(['requested', 'confirmed', 'seated', 'completed', 'cancelled', 'no_show'])]]);
        $reservation->update($data);
        return back()->with('success', 'Reservation status updated.');
    }
}
