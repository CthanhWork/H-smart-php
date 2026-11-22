<?php

namespace App\Modules\Admin\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class MarketplaceSettings
{
    private const CONFIG_MAP = [
        'commission_rate' => 'services.marketplace.commission_rate',
        'shipping_fee' => 'services.marketplace.shipping_fee',
        'listing_free_monthly' => 'services.marketplace.listing_free_monthly',
        'listing_fee' => 'services.marketplace.listing_fee',
        'boost_price' => 'services.marketplace.boost_price',
        'boost_days' => 'services.marketplace.boost_days',
        'vip_price' => 'services.marketplace.vip_price',
        'vip_days' => 'services.marketplace.vip_days',
        'promotion_slots' => 'services.marketplace.promotion_slots',
        'cod_deposit_percent' => 'services.marketplace.cod_deposit_percent',
    ];

    public function get(string $key): int
    {
        if (! isset(self::CONFIG_MAP[$key])) {
            throw new \InvalidArgumentException('Unknown marketplace setting.');
        }

        return (int) Cache::remember('marketplace_setting:'.$key, 60, fn () =>
            DB::table('marketplace_settings')->where('key', $key)->value('value')
                ?? config(self::CONFIG_MAP[$key], 0)
        );
    }

    /** @return array<string, int> */
    public function all(): array
    {
        return array_combine(array_keys(self::CONFIG_MAP), array_map(fn (string $key) => $this->get($key), array_keys(self::CONFIG_MAP)));
    }

    /** @param array<string, int> $values */
    public function update(array $values, int $adminId): array
    {
        foreach ($values as $key => $value) {
            if (! isset(self::CONFIG_MAP[$key])) continue;
            DB::table('marketplace_settings')->updateOrInsert(['key' => $key], [
                'value' => $value,
                'updated_by' => $adminId,
                'updated_at' => now(),
                'created_at' => now(),
            ]);
            Cache::forget('marketplace_setting:'.$key);
        }

        return $this->all();
    }
}
