<?php

namespace App\Services\Girvi;

use App\Models\MetalRate;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The per gram rates a shop is valuing metal at today, used to prefill the
 * girvi entry screen. A shop that has never set a rate falls back to the
 * config default rather than pricing everything at zero.
 */
class MetalRates
{
    /**
     * @return array<string, float>
     */
    public function current(int $storeId, ?Carbon $asOf = null): array
    {
        $asOf = $asOf ?? Carbon::today();
        $rates = [];

        foreach (array_keys(config('girvi.metal_types')) as $metal) {
            $rates[$metal] = round((float) (MetalRate::query()
                ->networkWide()
                ->where('store_id', $storeId)
                ->where('metal_type', $metal)
                ->whereDate('effective_on', '<=', $asOf)
                ->orderByDesc('effective_on')
                ->value('rate_per_gram')
                ?? config('girvi.rate_per_gram.'.$metal, 0)), 2);
        }

        return $rates;
    }

    /**
     * Rates are dated, so saving twice in a day corrects the day rather than
     * leaving two conflicting figures behind.
     */
    public function set(int $storeId, string $metal, float $rate, User $user, ?Carbon $effectiveOn = null): MetalRate
    {
        return MetalRate::query()->networkWide()->updateOrCreate(
            [
                'store_id' => $storeId,
                'metal_type' => $metal,
                'effective_on' => $effectiveOn ?? Carbon::today(),
            ],
            [
                'rate_per_gram' => $rate,
                'updated_by_user_id' => $user->id,
            ],
        );
    }

    /**
     * @return Collection<int, MetalRate>
     */
    public function history(int $storeId, int $limit = 12): Collection
    {
        return MetalRate::query()
            ->networkWide()
            ->where('store_id', $storeId)
            ->with('updatedBy')
            ->orderByDesc('effective_on')
            ->orderBy('metal_type')
            ->limit($limit)
            ->get();
    }
}
