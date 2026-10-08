<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Support\Orders\OrderStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        // 730 days (2 years) so the frontend can compare the largest preset
        // (1 year) against an equally long preceding period for the trend
        // footer, without a second request. Daily aggregates, not raw
        // orders, so the payload stays tiny even at this range.
        $days = 730;
        $from = Carbon::today()->subDays($days - 1)->startOfDay();

        $rows = Order::query()
            ->toBase()
            ->select(
                DB::raw('date(created_at) as date'),
                DB::raw('COUNT(*) as orders'),
                DB::raw('SUM(total_amount) as revenue'),
            )
            ->where('created_at', '>=', $from)
            ->where('status', OrderStatus::Paid)
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $chartData = collect(range(0, $days - 1))
            ->map(function (int $offset) use ($from, $rows): array {
                $date = $from->copy()->addDays($offset)->toDateString();
                $row = $rows->get($date);

                return [
                    'date' => $date,
                    'orders' => (int) ($row->orders ?? 0),
                    'revenue' => (float) ($row->revenue ?? 0),
                ];
            });

        $totals = [
            'revenue' => (float) Order::query()->paid()->sum('total_amount'),
            'orders' => Order::query()->count(),
        ];

        return Inertia::render('Admin/Dashboard', [
            'chartData' => $chartData->values(),
            'totals' => $totals,
        ]);
    }
}
