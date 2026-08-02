<?php

namespace Database\Seeders;

use App\Enums\DefaultFlagReason;
use App\Enums\DefaultFlagStatus;
use App\Enums\GoldLoanStatus;
use App\Enums\UdhaarStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\DefaultFlag;
use App\Models\GoldLoan;
use App\Models\Store;
use App\Models\Udhaar;
use App\Models\UdhaarPayment;
use App\Models\User;
use App\Services\Scoring\ScoreService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Builds a demo network with real transaction histories rather than pre-set
 * scores, so every number on screen is one the engine actually derived.
 */
class DemoDataSeeder extends Seeder
{
    private Store $store;

    private Store $rivalStore;

    private Carbon $today;

    public function run(): void
    {
        $this->today = Carbon::today();

        $this->store = Store::create([
            'name' => 'Swarnkar Jewellers',
            'legal_name' => 'Swarnkar Jewellers Pvt Ltd',
            'gstin' => '08AABCS1429B1ZP',
            'phone' => '9829011223',
            'email' => 'owner@swarnkar.test',
            'address_line' => 'Shop 14, Johari Bazaar',
            'city' => 'Jaipur',
            'state' => 'Rajasthan',
            'pincode' => '302003',
        ]);

        // A second store exists so cross-store scoring and merchant
        // anonymisation are visible in the demo rather than theoretical.
        $this->rivalStore = Store::create([
            'name' => 'Mahalaxmi Jewellers',
            'city' => 'Ajmer',
            'state' => 'Rajasthan',
            'phone' => '9829099887',
        ]);

        $this->createUsers();
        $this->seedCustomers();

        // Scores are derived at the end, from the ledger rows above.
        $service = app(ScoreService::class);
        Customer::query()->each(fn (Customer $customer) => $service->refresh($customer));
    }

    private function createUsers(): void
    {
        User::create([
            'store_id' => $this->store->id,
            'name' => 'Mahesh Soni',
            'email' => 'owner@swarnkar.test',
            'role' => UserRole::Owner,
            'phone' => '9829011223',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);

        User::create([
            'store_id' => $this->store->id,
            'name' => 'Priya Sharma',
            'email' => 'staff@swarnkar.test',
            'role' => UserRole::Staff,
            'phone' => '9829044556',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);

        User::create([
            'store_id' => $this->store->id,
            'name' => 'Bhanwar Lal',
            'email' => 'karigar@swarnkar.test',
            'role' => UserRole::GoldsmithManager,
            'phone' => '9829077889',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);

        // A second store's owner, for checking that one shop cannot see
        // another's ledger.
        User::create([
            'store_id' => $this->rivalStore->id,
            'name' => 'Ramesh Verma',
            'email' => 'owner@mahalaxmi.test',
            'role' => UserRole::Owner,
            'phone' => '9829099887',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
    }

    private function seedCustomers(): void
    {
        // The profile from the product mock: a long run of cleared credit with
        // one slip, which keeps him a strong green rather than a perfect 900.
        $rajesh = $this->customer('Rajesh Kumar', '9829100001', 'Jaipur');
        $this->settledUdhaar($rajesh, 145000, monthsAgo: 8, daysLate: 0);
        $this->settledUdhaar($rajesh, 90000, monthsAgo: 14, daysLate: 0);
        $this->settledUdhaar($rajesh, 80000, monthsAgo: 3, daysLate: 38);

        $anita = $this->customer('Anita Agarwal', '9829100002', 'Jaipur');
        $this->settledUdhaar($anita, 240000, monthsAgo: 5, daysLate: 0);
        $this->settledUdhaar($anita, 110000, monthsAgo: 16, daysLate: 0);
        $this->closedGoldLoan($anita, 180000, monthsAgo: 14);

        $vikram = $this->customer('Vikram Singh Rathore', '9829100003', 'Ajmer');
        $this->settledUdhaar($vikram, 95000, monthsAgo: 4, daysLate: 0, store: $this->rivalStore);
        $this->settledUdhaar($vikram, 60000, monthsAgo: 11, daysLate: 0);

        // The scenario the whole product exists for. Spotless in Swarnkar's own
        // book, but carrying 50,000 at the other store that has not fallen due
        // yet, so nothing in the score itself gives it away. Only the network
        // exposure panel catches it.
        $suresh = $this->customer('Suresh Agarwal', '9829100021', 'Jaipur');
        $this->settledUdhaar($suresh, 40000, monthsAgo: 7, daysLate: 0);
        $this->openUdhaar($suresh, 50000, dueInDays: 15, store: $this->rivalStore);

        // Yellow: pays, but consistently behind.
        $sunita = $this->customer('Sunita Devi', '9829100004', 'Jaipur');
        $this->settledUdhaar($sunita, 48000, monthsAgo: 6, daysLate: 25);
        $this->settledUdhaar($sunita, 36000, monthsAgo: 13, daysLate: 22);

        $mohan = $this->customer('Mohanlal Gupta', '9829100005', 'Kota');
        $this->settledUdhaar($mohan, 120000, monthsAgo: 7, daysLate: 28);
        $this->partiallyPaidUdhaar($mohan, 70000, paid: 30000, dueDaysAgo: 20);

        $kavita = $this->customer('Kavita Jain', '9829100006', 'Jaipur');
        $this->settledUdhaar($kavita, 55000, monthsAgo: 9, daysLate: 22);
        $this->renewedGoldLoan($kavita, 90000, monthsAgo: 10);

        // Red: long-overdue credit, write-offs and verified fraud reports.
        $prakash = $this->customer('Prakash Meena', '9829100007', 'Jaipur');
        $this->overdueUdhaar($prakash, 85000, dueDaysAgo: 140);
        $this->flag($prakash, DefaultFlagReason::BouncedCheque, monthsAgo: 5, amount: 85000, store: $this->store);

        $dinesh = $this->customer('Dinesh Chandra', '9829100008', 'Ajmer');
        $this->overdueUdhaar($dinesh, 210000, dueDaysAgo: 200, store: $this->rivalStore);
        $this->flag($dinesh, DefaultFlagReason::Absconded, monthsAgo: 4, amount: 210000, store: $this->rivalStore);
        $this->auctionedGoldLoan($dinesh, 150000, monthsAgo: 18);

        $ramesh = $this->customer('Ramesh Bhai Patel', '9829100009', 'Jaipur');
        $this->writtenOffUdhaar($ramesh, 55000, monthsAgo: 9);
        $this->overdueUdhaar($ramesh, 40000, dueDaysAgo: 95);

        // Unscored: on the books but with no completed obligations yet.
        $this->customer('Neha Saxena', '9829100010', 'Jaipur');
        $this->customer('Arjun Malhotra', '9829100011', 'Jaipur');

        $newcomer = $this->customer('Pooja Rani', '9829100012', 'Jaipur');
        $this->openUdhaar($newcomer, 30000, dueInDays: 20);

        foreach ($this->extraCustomers() as [$name, $mobile, $profile]) {
            $customer = $this->customer($name, $mobile, 'Jaipur');

            match ($profile) {
                'good' => $this->goodHistory($customer),
                'fair' => $this->fairHistory($customer),
                default => $this->poorHistory($customer),
            };
        }
    }

    /**
     * @return array<int, array{0: string, 1: string, 2: string}>
     */
    private function extraCustomers(): array
    {
        return [
            ['Sanjay Bansal', '9829100013', 'good'],
            ['Meera Tiwari', '9829100014', 'good'],
            ['Harish Chandani', '9829100015', 'good'],
            ['Lalita Kumari', '9829100016', 'fair'],
            ['Naveen Choudhary', '9829100017', 'fair'],
            ['Deepak Ranka', '9829100018', 'fair'],
            ['Sarita Yadav', '9829100019', 'poor'],
            ['Girdhari Lal', '9829100020', 'poor'],
        ];
    }

    private function goodHistory(Customer $customer): void
    {
        // One slip apiece, so the top of the demo range is not a wall of
        // identical perfect 900s.
        $this->settledUdhaar($customer, random_int(60, 180) * 1000, monthsAgo: random_int(3, 9), daysLate: 0);
        $this->settledUdhaar($customer, random_int(20, 50) * 1000, monthsAgo: random_int(12, 18), daysLate: 6);
    }

    /**
     * Pays, but always a few weeks behind. The recent account is deliberately
     * the larger one so the profile lands in yellow rather than tipping into
     * red on the roll of the dice.
     */
    private function fairHistory(Customer $customer): void
    {
        $this->settledUdhaar($customer, random_int(80, 140) * 1000, monthsAgo: random_int(3, 8), daysLate: random_int(15, 29));
        $this->settledUdhaar($customer, random_int(20, 35) * 1000, monthsAgo: random_int(14, 20), daysLate: random_int(31, 55));
    }

    private function poorHistory(Customer $customer): void
    {
        $this->overdueUdhaar($customer, random_int(50, 150) * 1000, dueDaysAgo: random_int(90, 220));
        $this->settledUdhaar($customer, random_int(20, 60) * 1000, monthsAgo: random_int(14, 22), daysLate: random_int(70, 120));
    }

    private function customer(string $name, string $mobile, string $city): Customer
    {
        $customer = new Customer([
            'full_name' => $name,
            'mobile' => $mobile,
            'city' => $city,
            'state' => 'Rajasthan',
            'created_by_store_id' => $this->store->id,
        ]);

        $customer->pan = strtoupper(Str::random(5)).random_int(1000, 9999).strtoupper(Str::random(1));
        $customer->save();

        $customer->stores()->attach($this->store->id, ['first_seen_at' => now()]);

        return $customer;
    }

    private function settledUdhaar(Customer $customer, float $amount, int $monthsAgo, int $daysLate, ?Store $store = null): void
    {
        $issuedOn = $this->today->copy()->subMonthsNoOverflow($monthsAgo);
        $dueOn = $issuedOn->copy()->addDays(30);
        // Fixed rather than jittered: recency weighting reads the settlement
        // date, so a random offset would move the headline demo scores by a
        // point or two on every reseed.
        $settledOn = $daysLate > 0 ? $dueOn->copy()->addDays($daysLate) : $dueOn->copy()->subDays(5);

        $udhaar = Udhaar::create([
            'store_id' => ($store ?? $this->store)->id,
            'customer_id' => $customer->id,
            'invoice_no' => 'INV-'.random_int(1000, 9999),
            'item_description' => $this->item(),
            'principal_amount' => $amount,
            'amount_paid' => $amount,
            'issued_on' => $issuedOn,
            'due_on' => $dueOn,
            'settled_on' => $settledOn,
            'status' => UdhaarStatus::Settled,
        ]);

        UdhaarPayment::create([
            'udhaar_id' => $udhaar->id,
            'amount' => $amount,
            'paid_on' => $settledOn,
            'method' => 'cash',
        ]);
    }

    private function partiallyPaidUdhaar(Customer $customer, float $amount, float $paid, int $dueDaysAgo): void
    {
        $dueOn = $this->today->copy()->subDays($dueDaysAgo);

        $udhaar = Udhaar::create([
            'store_id' => $this->store->id,
            'customer_id' => $customer->id,
            'invoice_no' => 'INV-'.random_int(1000, 9999),
            'item_description' => $this->item(),
            'principal_amount' => $amount,
            'amount_paid' => $paid,
            'issued_on' => $dueOn->copy()->subDays(30),
            'due_on' => $dueOn,
            'status' => UdhaarStatus::PartiallyPaid,
        ]);

        UdhaarPayment::create([
            'udhaar_id' => $udhaar->id,
            'amount' => $paid,
            'paid_on' => $dueOn->copy()->subDays(5),
            'method' => 'upi',
        ]);
    }

    private function openUdhaar(Customer $customer, float $amount, int $dueInDays, ?Store $store = null): void
    {
        Udhaar::create([
            'store_id' => ($store ?? $this->store)->id,
            'customer_id' => $customer->id,
            'invoice_no' => 'INV-'.random_int(1000, 9999),
            'item_description' => $this->item(),
            'principal_amount' => $amount,
            'issued_on' => $this->today->copy()->subDays(5),
            'due_on' => $this->today->copy()->addDays($dueInDays),
            'status' => UdhaarStatus::Open,
        ]);
    }

    private function overdueUdhaar(Customer $customer, float $amount, int $dueDaysAgo, ?Store $store = null): void
    {
        $dueOn = $this->today->copy()->subDays($dueDaysAgo);

        Udhaar::create([
            'store_id' => ($store ?? $this->store)->id,
            'customer_id' => $customer->id,
            'invoice_no' => 'INV-'.random_int(1000, 9999),
            'item_description' => $this->item(),
            'principal_amount' => $amount,
            'issued_on' => $dueOn->copy()->subDays(30),
            'due_on' => $dueOn,
            'status' => UdhaarStatus::Defaulted,
        ]);
    }

    private function writtenOffUdhaar(Customer $customer, float $amount, int $monthsAgo): void
    {
        $issuedOn = $this->today->copy()->subMonthsNoOverflow($monthsAgo);

        Udhaar::create([
            'store_id' => $this->store->id,
            'customer_id' => $customer->id,
            'item_description' => $this->item(),
            'principal_amount' => $amount,
            'issued_on' => $issuedOn,
            'due_on' => $issuedOn->copy()->addDays(30),
            'status' => UdhaarStatus::WrittenOff,
            'notes' => 'Unrecoverable after repeated follow-ups.',
        ]);
    }

    private function closedGoldLoan(Customer $customer, float $amount, int $monthsAgo): void
    {
        $disbursedOn = $this->today->copy()->subMonthsNoOverflow($monthsAgo);

        GoldLoan::create([
            'store_id' => $this->store->id,
            'customer_id' => $customer->id,
            'loan_no' => 'GL-'.Str::upper(Str::random(8)),
            'principal_amount' => $amount,
            'interest_rate' => 11.0,
            'pledged_weight_grams' => round($amount / 5200, 3),
            'disbursed_on' => $disbursedOn,
            'due_on' => $disbursedOn->copy()->addMonths(6),
            'closed_on' => $disbursedOn->copy()->addMonths(5),
            'status' => GoldLoanStatus::Closed,
        ]);
    }

    private function renewedGoldLoan(Customer $customer, float $amount, int $monthsAgo): void
    {
        $disbursedOn = $this->today->copy()->subMonthsNoOverflow($monthsAgo);

        GoldLoan::create([
            'store_id' => $this->store->id,
            'customer_id' => $customer->id,
            'loan_no' => 'GL-'.Str::upper(Str::random(8)),
            'principal_amount' => $amount,
            'interest_rate' => 12.5,
            'pledged_weight_grams' => round($amount / 5200, 3),
            'disbursed_on' => $disbursedOn,
            'due_on' => $disbursedOn->copy()->addMonths(6),
            'status' => GoldLoanStatus::Renewed,
        ]);
    }

    private function auctionedGoldLoan(Customer $customer, float $amount, int $monthsAgo): void
    {
        $disbursedOn = $this->today->copy()->subMonthsNoOverflow($monthsAgo);

        GoldLoan::create([
            'store_id' => $this->rivalStore->id,
            'customer_id' => $customer->id,
            'loan_no' => 'GL-'.Str::upper(Str::random(8)),
            'principal_amount' => $amount,
            'interest_rate' => 12.5,
            'pledged_weight_grams' => round($amount / 5200, 3),
            'disbursed_on' => $disbursedOn,
            'due_on' => $disbursedOn->copy()->addMonths(6),
            'closed_on' => $disbursedOn->copy()->addMonths(9),
            'status' => GoldLoanStatus::Auctioned,
        ]);
    }

    private function flag(Customer $customer, DefaultFlagReason $reason, int $monthsAgo, float $amount, Store $store): void
    {
        DefaultFlag::create([
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'reason' => $reason,
            'status' => DefaultFlagStatus::Verified,
            'amount_involved' => $amount,
            'narrative' => 'Verified by the merchant network after invoice review.',
            'evidence_path' => 'evidence/demo-invoice.pdf',
            'occurred_on' => $this->today->copy()->subMonthsNoOverflow($monthsAgo),
            'verified_at' => now(),
        ]);
    }

    private function item(): string
    {
        return collect([
            '22K gold chain, 18.4 g',
            'Bridal bangles pair, 42.7 g',
            'Diamond pendant set',
            '18K gold ring, 6.2 g',
            'Temple jewellery necklace',
            'Silver pooja thali set',
        ])->random();
    }
}
