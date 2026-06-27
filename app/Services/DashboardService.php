<?php

namespace App\Services;

use App\Models\DailyReport;
use App\Models\Investment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    private const CACHE_TTL_SECONDS = 120;

    public function getStats(): array
    {
        $today = Carbon::today()->toDateString();

        return Cache::remember("dashboard.stats.{$today}", self::CACHE_TTL_SECONDS, function () use ($today): array {
            $userStats = DB::table('users')
                ->selectRaw('COUNT(*) as total_users')
                ->selectRaw('SUM(CASE WHEN DATE(created_at) = ? THEN 1 ELSE 0 END) as today_users', [$today])
                ->selectRaw('COALESCE(SUM(gold_holdings), 0) as total_gold_holdings')
                ->selectRaw('COALESCE(SUM(silver_holdings), 0) as total_silver_holdings')
                ->selectRaw('SUM(CASE WHEN role = ? OR gold_holdings > 0 OR silver_holdings > 0 THEN 1 ELSE 0 END) as active_investors', ['investor'])
                ->first();

            $investmentStats = DB::table('investments')
                ->selectRaw('COUNT(*) as total_transactions')
                ->selectRaw('SUM(CASE WHEN DATE(created_at) = ? THEN 1 ELSE 0 END) as today_transactions', [$today])
                ->selectRaw('COALESCE(SUM(CASE WHEN status = ? THEN total_amount ELSE 0 END), 0) as total_revenue', ['completed'])
                ->selectRaw('COALESCE(SUM(CASE WHEN status = ? AND DATE(created_at) = ? THEN total_amount ELSE 0 END), 0) as today_revenue', ['completed', $today])
                ->selectRaw('COALESCE(SUM(CASE WHEN metal_type = ? AND type = ? AND status = ? AND DATE(created_at) = ? THEN quantity_grams ELSE 0 END), 0) as today_gold_holdings', ['gold', 'buy', 'completed', $today])
                ->selectRaw('COALESCE(SUM(CASE WHEN metal_type = ? AND type = ? AND status = ? AND DATE(created_at) = ? THEN quantity_grams ELSE 0 END), 0) as today_silver_holdings', ['silver', 'buy', 'completed', $today])
                ->first();

            return [
                'total_users' => (int) $userStats->total_users,
                'today_users' => (int) $userStats->today_users,
                'active_investors' => (int) $userStats->active_investors,
                'total_gold_holdings' => (float) $userStats->total_gold_holdings,
                'total_silver_holdings' => (float) $userStats->total_silver_holdings,
                'today_gold_holdings' => (float) $investmentStats->today_gold_holdings,
                'today_silver_holdings' => (float) $investmentStats->today_silver_holdings,
                'total_revenue' => (float) $investmentStats->total_revenue,
                'today_revenue' => (float) $investmentStats->today_revenue,
                'total_transactions' => (int) $investmentStats->total_transactions,
                'today_transactions' => (int) $investmentStats->today_transactions,
            ];
        });
    }

    public function getRevenueChartData(int $days = 30): array
    {
        $cacheKey = "dashboard.revenue_chart.{$days}.".Carbon::today()->toDateString();

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, fn (): array => $this->buildRevenueChartData($days));
    }

    public function getTransactionChartData(int $days = 30): array
    {
        $cacheKey = "dashboard.transaction_chart.{$days}.".Carbon::today()->toDateString();

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, fn (): array => $this->buildTransactionChartData($days));
    }

    public function generateDailyReport(?Carbon $date = null): DailyReport
    {
        $date = $date ?? Carbon::today();
        $dateString = $date->toDateString();

        $report = DailyReport::updateOrCreate(
            ['report_date' => $dateString],
            [
                'new_users' => User::whereDate('created_at', $date)->count(),
                'active_investors' => User::where('role', 'investor')->count(),
                'gold_holdings_total' => User::sum('gold_holdings'),
                'silver_holdings_total' => User::sum('silver_holdings'),
                'revenue_total' => Investment::where('status', 'completed')
                    ->whereDate('created_at', $date)
                    ->sum('total_amount'),
                'transaction_count' => Investment::whereDate('created_at', $date)->count(),
                'gst_collected' => Investment::where('status', 'completed')
                    ->whereDate('created_at', $date)
                    ->sum('gst_amount'),
            ]
        );

        $this->clearCache();

        return $report;
    }

    public function clearCache(): void
    {
        $today = Carbon::today()->toDateString();

        Cache::forget("dashboard.stats.{$today}");
        Cache::forget("dashboard.revenue_chart.30.{$today}");
        Cache::forget("dashboard.transaction_chart.30.{$today}");
    }

    private function buildRevenueChartData(int $days): array
    {
        $startDate = Carbon::today()->subDays($days - 1);

        $data = Investment::query()
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('SUM(total_amount) as revenue'))
            ->where('status', 'completed')
            ->where('created_at', '>=', $startDate)
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('revenue', 'date');

        return $this->fillDailySeries($startDate, $days, $data);
    }

    private function buildTransactionChartData(int $days): array
    {
        $startDate = Carbon::today()->subDays($days - 1);

        $rows = Investment::query()
            ->select(DB::raw('DATE(created_at) as date'))
            ->selectRaw('SUM(CASE WHEN type = ? THEN 1 ELSE 0 END) as buy_count', ['buy'])
            ->selectRaw('SUM(CASE WHEN type = ? THEN 1 ELSE 0 END) as sell_count', ['sell'])
            ->where('created_at', '>=', $startDate)
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $labels = [];
        $buyValues = [];
        $sellValues = [];

        for ($i = 0; $i < $days; $i++) {
            $date = $startDate->copy()->addDays($i)->format('Y-m-d');
            $labels[] = Carbon::parse($date)->format('M d');
            $row = $rows->get($date);
            $buyValues[] = (int) ($row->buy_count ?? 0);
            $sellValues[] = (int) ($row->sell_count ?? 0);
        }

        return [
            'labels' => $labels,
            'buy' => $buyValues,
            'sell' => $sellValues,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<string, mixed>  $data
     * @return array{labels: array<int, string>, values: array<int, float>}
     */
    private function fillDailySeries(Carbon $startDate, int $days, $data): array
    {
        $labels = [];
        $values = [];

        for ($i = 0; $i < $days; $i++) {
            $date = $startDate->copy()->addDays($i)->format('Y-m-d');
            $labels[] = Carbon::parse($date)->format('M d');
            $values[] = (float) ($data[$date] ?? 0);
        }

        return ['labels' => $labels, 'values' => $values];
    }
}
