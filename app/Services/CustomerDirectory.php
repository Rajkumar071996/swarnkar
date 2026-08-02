<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Store;
use App\Support\BlindIndex;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Resolves the network-wide customer identity behind a phone number, PAN or
 * Aadhaar. Two stores serving the same person must land on the same row, or the
 * whole premise of a shared score falls apart.
 */
class CustomerDirectory
{
    public function findByIdentifier(string $term): ?Customer
    {
        $hashes = array_filter([
            BlindIndex::forMobile($term),
            BlindIndex::forPan($term),
            BlindIndex::forAadhaar($term),
        ]);

        if ($hashes === []) {
            return null;
        }

        return Customer::query()
            ->whereIn('mobile_hash', $hashes)
            ->orWhereIn('pan_hash', $hashes)
            ->orWhereIn('aadhaar_hash', $hashes)
            ->first();
    }

    /**
     * @return Collection<int, Customer>
     */
    public function search(string $term, int $limit = 25): Collection
    {
        $exact = $this->findByIdentifier($term);

        if ($exact) {
            return new Collection([$exact]);
        }

        return Customer::query()
            ->where('full_name', 'like', '%'.$term.'%')
            ->orderBy('full_name')
            ->limit($limit)
            ->get();
    }

    /**
     * Creates the customer if this is their first appearance anywhere on the
     * network, otherwise returns the existing identity and enriches any blanks.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function resolve(array $attributes, ?string $aadhaar = null): Customer
    {
        return DB::transaction(function () use ($attributes, $aadhaar) {
            $customer = $this->matchExisting($attributes['mobile'] ?? null, $attributes['pan'] ?? null, $aadhaar);

            if ($customer === null) {
                $customer = new Customer;
            }

            // Never blank out an identifier another store already captured.
            foreach ($attributes as $key => $value) {
                if (filled($value) || blank($customer->getAttribute($key))) {
                    $customer->setAttribute($key, $value);
                }
            }

            if (filled($aadhaar)) {
                $customer->setAadhaar($aadhaar);
            }

            $customer->save();

            return $customer;
        });
    }

    public function linkToStore(Customer $customer, Store|int $store, array $pivot = []): void
    {
        $storeId = $store instanceof Store ? $store->id : $store;

        if ($this->isKnownToStore($customer, $storeId)) {
            if ($pivot !== []) {
                $customer->stores()->updateExistingPivot($storeId, $pivot);
            }

            return;
        }

        $customer->stores()->attach($storeId, [...$pivot, 'first_seen_at' => Carbon::now()]);
    }

    public function isKnownToStore(Customer $customer, int $storeId): bool
    {
        return $customer->stores()->whereKey($storeId)->exists();
    }

    private function matchExisting(?string $mobile, ?string $pan, ?string $aadhaar): ?Customer
    {
        $hashes = array_filter([
            'mobile_hash' => BlindIndex::forMobile($mobile),
            'pan_hash' => BlindIndex::forPan($pan),
            'aadhaar_hash' => BlindIndex::forAadhaar($aadhaar),
        ]);

        if ($hashes === []) {
            return null;
        }

        $query = Customer::query();

        foreach ($hashes as $column => $hash) {
            $query->orWhere($column, $hash);
        }

        return $query->first();
    }
}
