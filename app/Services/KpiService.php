<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Carbon;

class KpiService
{
    private function kpiKey(string $metric): string
    {
        return 'kpi:' . Carbon::now()->format('Y-m-d') . ':' . $metric;
    }

    private function leaderboardKey(): string
    {
        return 'leaderboard:customers';
    }

    /** Record successful payment */
    public function recordSuccess(int $customerId, int $amountCents): void
    {
        Redis::incrby($this->kpiKey('revenue_cents'), $amountCents);
        Redis::incr($this->kpiKey('order_count'));

        $revenue = (int) Redis::get($this->kpiKey('revenue_cents'));
        $count = (int) Redis::get($this->kpiKey('order_count'));

        if ($count > 0) {
            Redis::set($this->kpiKey('avg_order_value_cents'), (int) floor($revenue / $count));
        }

        Redis::zincrby($this->leaderboardKey(), $amountCents, (string) $customerId);
    }

    /** Record failure/refund adjustments */
    public function recordFailure(int $customerId, int $amountCents): void
    {
        Redis::decrby($this->kpiKey('revenue_cents'), $amountCents);
        Redis::zincrby($this->leaderboardKey(), -$amountCents, (string) $customerId);
    }
}
