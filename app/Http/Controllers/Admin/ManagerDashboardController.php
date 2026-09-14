<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cashbook;
use App\Models\Expense;
use App\Models\Feedback;
use App\Models\Bed;
use App\Models\Department;
use App\Models\HospitalAdmission;
use App\Models\MenuItem;
use App\Models\NursingAssignment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Salary;
use App\Models\StockAdjustment;
use App\Models\User;
use App\Models\VitalSign;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ManagerDashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $canAccess = $user instanceof User
            && ($user->isRestaurantManager() || ($user->isSuperAdmin() && Tenancy::isImpersonating()));
        abort_unless($canAccess, 403);

        $restaurantId = $user->effectiveRestaurantId();
        $today = now()->toDateString();

        $todayOrders = Order::where('restaurant_id', $restaurantId)
            ->whereDate('created_at', $today)
            ->where('status', '!=', 'cancelled');

        $pendingOrders = Order::where('restaurant_id', $restaurantId)
            ->whereIn('status', ['pending', 'confirmed', 'preparing']);

        $expenseQuery = Expense::where('restaurant_id', $restaurantId)
            ->whereDate('date', $today);

        $todaySalary = Salary::where('restaurant_id', $restaurantId)
            ->whereDate('paid_at', $today);
        $todayCashbook = Cashbook::where('restaurant_id', $restaurantId)
            ->whereDate('date', $today);

        $bestSeller = OrderItem::select('item_name', DB::raw('SUM(quantity) as total_qty'))
            ->whereHas('order', fn ($query) => $query->where('restaurant_id', $restaurantId)
                ->whereDate('created_at', $today)
                ->where('status', '!=', 'cancelled'))
            ->groupBy('item_name')
            ->orderByDesc('total_qty')
            ->first();

        $recentOrders = Order::where('restaurant_id', $restaurantId)
            ->latest()
            ->limit(10)
            ->get();

        $weekSales = Order::where('restaurant_id', $restaurantId)
            ->where('status', '!=', 'cancelled')
            ->where('created_at', '>=', now()->subDays(7))
            ->selectRaw('DATE(created_at) as day, SUM(total) as total')
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        $growthStart = now()->subDays(29)->startOfDay();
        $growthByDay = Order::where('restaurant_id', $restaurantId)
            ->where('status', '!=', 'cancelled')
            ->where('created_at', '>=', $growthStart)
            ->selectRaw('DATE(created_at) as day, SUM(total) as revenue')
            ->groupBy('day')
            ->pluck('revenue', 'day');
        $growth = collect(range(29, 0))->map(function (int $daysAgo) use ($growthByDay) {
            $date = now()->subDays($daysAgo);
            return [
                'label' => $date->format('M j'),
                'revenue' => (float) ($growthByDay->get($date->toDateString()) ?? 0),
            ];
        })->values();

        $stockAdjustments = StockAdjustment::where('restaurant_id', $restaurantId);
        $stockReceived = (float) (clone $stockAdjustments)
            ->whereIn('reason', ['purchase', 'return'])
            ->where('change_quantity', '>', 0)
            ->sum('change_quantity');
        $stockSold = abs((float) (clone $stockAdjustments)
            ->where('reason', 'sale')
            ->sum('change_quantity'));
        $stockDamaged = abs((float) (clone $stockAdjustments)
            ->whereIn('reason', ['damage', 'expiry'])
            ->sum('change_quantity'));
        $stockValue = (float) MenuItem::where('restaurant_id', $restaurantId)
            ->where('track_stock', true)
            ->selectRaw('COALESCE(SUM(stock_quantity * COALESCE(cost_price, price, 0)), 0) as value')
            ->value('value');

        $stockMovements = (clone $stockAdjustments)
            ->with('menuItem:id,name')
            ->select('menu_item_id', DB::raw('SUM(CASE WHEN change_quantity < 0 THEN ABS(change_quantity) ELSE 0 END) as sold_qty'), DB::raw('SUM(CASE WHEN change_quantity > 0 THEN change_quantity ELSE 0 END) as received_qty'))
            ->whereNotNull('menu_item_id')
            ->groupBy('menu_item_id')
            ->orderByDesc('sold_qty')
            ->limit(5)
            ->get();

        // Low Stock Items — menu items (or products, for shop-type
        // businesses) that opted into stock tracking and have fallen at or
        // below their own low_stock_threshold.
        $lowStockCount = MenuItem::where('restaurant_id', $restaurantId)
            ->where('track_stock', true)
            ->whereColumn('stock_quantity', '<=', 'low_stock_threshold')
            ->count();

        // Monthly Net Profit — cashbook credits minus debits, current
        // calendar month.
        $monthCashbook = Cashbook::where('restaurant_id', $restaurantId)
            ->whereYear('date', now()->year)
            ->whereMonth('date', now()->month);
        $monthlyNetProfit = (clone $monthCashbook)->where('type', 'in')->sum('amount')
            - (clone $monthCashbook)->where('type', 'out')->sum('amount');

        // New Customer Feedback — open feedback submitted by a customer
        // (as opposed to a staff suggestion) for this restaurant.
        $newFeedbackCount = Feedback::where('restaurant_id', $restaurantId)
            ->whereNotNull('customer_id')
            ->where('status', 'open')
            ->count();

        $periods = [
            'day' => ['label' => 'Daily', 'start' => now()->startOfDay(), 'end' => now()->endOfDay()],
            'week' => ['label' => 'Weekly', 'start' => now()->startOfWeek(), 'end' => now()->endOfWeek()],
            'month' => ['label' => 'Monthly', 'start' => now()->startOfMonth(), 'end' => now()->endOfMonth()],
            '6months' => ['label' => '6 Months', 'start' => now()->subMonths(5)->startOfMonth(), 'end' => now()->endOfMonth()],
            'year' => ['label' => 'Yearly', 'start' => now()->startOfYear(), 'end' => now()->endOfYear()],
        ];

        $periodSummaries = [];
        foreach ($periods as $key => $period) {
            $ordersQuery = Order::where('restaurant_id', $restaurantId)
                ->where('status', '!=', 'cancelled')
                ->whereBetween('created_at', [$period['start'], $period['end']]);
            $expensesQuery = Expense::where('restaurant_id', $restaurantId)
                ->whereBetween('date', [$period['start']->toDateString(), $period['end']->toDateString()]);
            $salaryQuery = Salary::where('restaurant_id', $restaurantId)
                ->whereBetween('paid_at', [$period['start'], $period['end']]);
            $cashbookInQuery = Cashbook::where('restaurant_id', $restaurantId)
                ->where('type', 'in')
                ->whereNull('order_id')
                ->whereBetween('date', [$period['start']->toDateString(), $period['end']->toDateString()]);
            $cashbookOutQuery = Cashbook::where('restaurant_id', $restaurantId)
                ->where('type', 'out')
                ->whereBetween('date', [$period['start']->toDateString(), $period['end']->toDateString()]);

            $income = (float) $ordersQuery->sum('total');
            $otherIncome = (float) $cashbookInQuery->sum('amount');
            $operatingExpense = (float) $expensesQuery->sum('amount');
            $salary = (float) $salaryQuery->sum(DB::raw('COALESCE(net_paid, amount)'));
            $cashOut = (float) $cashbookOutQuery->sum('amount');
            $expense = $operatingExpense + $salary + $cashOut;

            $periodSummaries[] = [
                'key' => $key,
                'label' => $period['label'],
                'income' => $income + $otherIncome,
                'expense' => $expense,
                'sales' => $income,
                'other_income' => $otherIncome,
                'salary' => $salary,
                'cash_out' => $cashOut,
                'profit' => $income + $otherIncome - $expense,
            ];
        }

        $todayIncome = (float) $todayOrders->sum('total')
            + (float) (clone $todayCashbook)->where('type', 'in')->whereNull('order_id')->sum('amount');
        $todayExpense = (float) $expenseQuery->sum('amount')
            + (float) $todaySalary->sum(DB::raw('COALESCE(net_paid, amount)'))
            + (float) (clone $todayCashbook)->where('type', 'out')->sum('amount');

        $stats = [
            'orders_today' => $todayOrders->count(),
            'revenue_today' => $todayIncome,
            'pending_orders' => $pendingOrders->count(),
            'expenses_today' => $todayExpense,
            'low_stock_items' => $lowStockCount,
            'monthly_net_profit' => $monthlyNetProfit,
            'new_customer_feedback' => $newFeedbackCount,
            'period_summaries' => $periodSummaries,
            'growth' => $growth,
            'stock_received' => $stockReceived,
            'stock_sold' => $stockSold,
            'stock_damaged' => $stockDamaged,
            'stock_value' => $stockValue,
            'stock_movements' => $stockMovements,
        ];

        $restaurant = $user->effectiveRestaurant();
        $hospitalDashboard = null;

        if ($user->hasModuleAccess('hospital-admissions')) {
            $activeAdmissions = HospitalAdmission::where('restaurant_id', $restaurantId)
                ->whereIn('status', ['admitted', 'transferred']);
            $activeAdmissionIds = (clone $activeAdmissions)->pluck('id');
            $recentVitalAdmissionIds = $user->hasModuleAccess('hospital-vital-signs')
                ? VitalSign::where('restaurant_id', $restaurantId)
                    ->whereIn('hospital_admission_id', $activeAdmissionIds)
                    ->where('recorded_at', '>=', now()->subDay())
                    ->pluck('hospital_admission_id')
                    ->unique()
                : collect();

            $departmentOccupancy = Department::where('restaurant_id', $restaurantId)
                ->where('is_active', true)
                ->withCount(['admissions as active_admissions_count' => fn ($query) => $query->whereIn('status', ['admitted', 'transferred'])])
                ->orderByDesc('active_admissions_count')
                ->get();

            $hospitalDashboard = [
                'active_admissions' => $activeAdmissions->count(),
                'available_beds' => $user->hasModuleAccess('hospital-wards-beds')
                    ? Bed::where('restaurant_id', $restaurantId)->where('status', 'available')->count()
                    : null,
                'occupied_beds' => $user->hasModuleAccess('hospital-wards-beds')
                    ? Bed::where('restaurant_id', $restaurantId)->where('status', 'occupied')->count()
                    : null,
                'unassigned_admissions' => (clone $activeAdmissions)->whereDoesntHave('nursingAssignments', fn ($query) => $query->where('status', 'active'))->count(),
                'active_nursing_assignments' => $user->hasModuleAccess('hospital-nursing')
                    ? NursingAssignment::where('restaurant_id', $restaurantId)->where('status', 'active')->count()
                    : null,
                'without_recent_vitals' => $user->hasModuleAccess('hospital-vital-signs')
                    ? $activeAdmissionIds->diff($recentVitalAdmissionIds)->count()
                    : null,
                'discharges_today' => HospitalAdmission::where('restaurant_id', $restaurantId)
                    ->where('status', 'discharged')
                    ->whereDate('discharged_at', $today)
                    ->count(),
                'department_occupancy' => $departmentOccupancy,
            ];
        }

        return view('manager.manager-dashboard', compact('stats', 'bestSeller', 'recentOrders', 'weekSales', 'restaurant', 'hospitalDashboard'));
    }

    public function subscriptionExpired()
    {
        return view('manager.subscription-expired');
    }
}
