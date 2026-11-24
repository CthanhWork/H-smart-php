<?php

namespace App\Support\Reporting;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;

class RevenuePeriod
{
    public function resolve(Request $request): array
    {
        $data = $request->validate([
            'period' => ['sometimes', 'in:this_month,last_month,custom'],
            'from' => ['required_if:period,custom', 'nullable', 'date_format:Y-m-d'],
            'to' => ['required_if:period,custom', 'nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);
        $zone = 'Asia/Ho_Chi_Minh';
        $period = $data['period'] ?? 'this_month';
        if ($period === 'custom') {
            $start = CarbonImmutable::createFromFormat('!Y-m-d', $data['from'], $zone);
            $lastDay = CarbonImmutable::createFromFormat('!Y-m-d', $data['to'], $zone);
            if ($start->diffInDays($lastDay) > 366) {
                abort(422, 'Khoảng ngày tối đa là 366 ngày.');
            }
        } elseif ($period === 'last_month') {
            $start = CarbonImmutable::now($zone)->startOfMonth()->subMonth();
            $lastDay = $start->endOfMonth();
        } else {
            $start = CarbonImmutable::now($zone)->startOfMonth();
            $lastDay = $start->endOfMonth();
        }
        $days = $start->diffInDays($lastDay) + 1;

        return [
            'period' => $period,
            'from' => $start->toDateString(),
            'to' => $lastDay->toDateString(),
            'from_utc' => $start->startOfDay()->utc()->toDateTimeString(),
            'to_utc_exclusive' => $lastDay->addDay()->startOfDay()->utc()->toDateTimeString(),
            'grain' => $days > 93 ? 'month' : 'day',
            'timezone' => $zone,
            'page' => (int) ($data['page'] ?? 1),
        ];
    }

    public function bucketExpression(string $column, string $grain): string
    {
        $driver = \Illuminate\Support\Facades\DB::connection()->getDriverName();
        if ($grain === 'month') {
            return $driver === 'sqlite'
                ? "strftime('%Y-%m', {$column}, '+7 hours')"
                : "DATE_FORMAT(DATE_ADD({$column}, INTERVAL 7 HOUR), '%Y-%m')";
        }

        return $driver === 'sqlite'
            ? "date({$column}, '+7 hours')"
            : "DATE(DATE_ADD({$column}, INTERVAL 7 HOUR))";
    }

    public function fillSeries(array $range, array $values, string $name = 'revenue'): array
    {
        $result = [];
        $cursor = CarbonImmutable::createFromFormat('!Y-m-d', $range['from'], $range['timezone']);
        $last = CarbonImmutable::createFromFormat('!Y-m-d', $range['to'], $range['timezone']);
        while ($cursor <= $last) {
            $bucket = $range['grain'] === 'month' ? $cursor->format('Y-m') : $cursor->toDateString();
            $result[$bucket] ??= ['period' => $bucket, $name => 0];
            $cursor = $range['grain'] === 'month' ? $cursor->startOfMonth()->addMonth() : $cursor->addDay();
        }
        foreach ($values as $bucket => $amount) {
            $result[$bucket] ??= ['period' => $bucket, $name => 0];
            $result[$bucket][$name] = (int) $amount;
        }

        return array_values($result);
    }

    public function inRange(Builder $query, string $column, array $range): Builder
    {
        return $query->where($column, '>=', $range['from_utc'])->where($column, '<', $range['to_utc_exclusive']);
    }
}
