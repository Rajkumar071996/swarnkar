<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Store;
use App\Models\User;
use App\Services\CustomerDirectory;
use App\Support\BlindIndex;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CustomerIdentityTest extends TestCase
{
    use RefreshDatabase;

    private CustomerDirectory $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = app(CustomerDirectory::class);
    }

    #[Test]
    public function identifiers_are_encrypted_at_rest(): void
    {
        $customer = Customer::factory()->create(['mobile' => '9876543210', 'pan' => 'ABCDE1234F']);

        $raw = DB::table('customers')->where('id', $customer->id)->first();

        $this->assertNotSame('9876543210', $raw->mobile);
        $this->assertStringNotContainsString('9876543210', $raw->mobile);
        $this->assertStringNotContainsString('ABCDE1234F', $raw->pan);

        // Still readable through the model.
        $this->assertSame('9876543210', $customer->fresh()->mobile);
        $this->assertSame('ABCDE1234F', $customer->fresh()->pan);
    }

    #[Test]
    public function an_encrypted_identifier_is_still_searchable_via_its_blind_index(): void
    {
        $customer = Customer::factory()->create(['mobile' => '9876543210']);

        $this->assertTrue($this->directory->findByIdentifier('9876543210')?->is($customer));
    }

    #[Test]
    public function the_same_number_is_matched_however_it_is_typed(): void
    {
        $customer = Customer::factory()->create(['mobile' => '9876543210']);

        foreach (['+919876543210', '09876543210', '98765 43210', '+91 98765-43210'] as $variant) {
            $this->assertTrue(
                $this->directory->findByIdentifier($variant)?->is($customer),
                "Failed to match [{$variant}] to the stored number."
            );
        }
    }

    #[Test]
    public function pan_lookup_is_case_insensitive(): void
    {
        $customer = Customer::factory()->create(['pan' => 'ABCDE1234F']);

        $this->assertTrue($this->directory->findByIdentifier('abcde1234f')?->is($customer));
    }

    #[Test]
    public function a_full_aadhaar_number_is_never_persisted(): void
    {
        $customer = new Customer(['full_name' => 'Test Person', 'mobile' => '9876543210']);
        $customer->setAadhaar('234567890124');
        $customer->save();

        $raw = (array) DB::table('customers')->where('id', $customer->id)->first();

        foreach ($raw as $value) {
            $this->assertStringNotContainsString('234567890124', (string) $value);
        }

        $this->assertSame('0124', $customer->aadhaar_last4);
        $this->assertSame(BlindIndex::forAadhaar('234567890124'), $customer->aadhaar_hash);
    }

    #[Test]
    public function an_aadhaar_lookup_matches_on_the_hash_alone(): void
    {
        $customer = Customer::factory()->create();
        $customer->setAadhaar('234567890124');
        $customer->save();

        $this->assertTrue($this->directory->findByIdentifier('2345 6789 0124')?->is($customer));
    }

    #[Test]
    public function two_stores_serving_the_same_person_resolve_to_one_identity(): void
    {
        $storeA = Store::factory()->create();
        $storeB = Store::factory()->create();

        $first = $this->directory->resolve([
            'full_name' => 'Rajesh Kumar',
            'mobile' => '9876543210',
            'created_by_store_id' => $storeA->id,
        ]);
        $this->directory->linkToStore($first, $storeA);

        $second = $this->directory->resolve([
            'full_name' => 'Rajesh Kumar',
            'mobile' => '9876543210',
            'created_by_store_id' => $storeB->id,
        ]);
        $this->directory->linkToStore($second, $storeB);

        $this->assertTrue($first->is($second));
        $this->assertSame(1, Customer::count());
        $this->assertSame(2, $second->stores()->count());
    }

    #[Test]
    public function resolving_an_existing_customer_fills_gaps_without_wiping_known_data(): void
    {
        $existing = $this->directory->resolve([
            'full_name' => 'Anita Agarwal',
            'mobile' => '9876543210',
            'city' => 'Jaipur',
        ]);

        $updated = $this->directory->resolve([
            'full_name' => 'Anita Agarwal',
            'mobile' => '9876543210',
            'city' => null,
            'state' => 'Rajasthan',
        ]);

        $this->assertTrue($existing->is($updated));
        $this->assertSame('Jaipur', $updated->city, 'A blank must not overwrite a known value.');
        $this->assertSame('Rajasthan', $updated->state);
    }

    #[Test]
    public function a_search_term_that_is_not_an_identifier_falls_back_to_the_name(): void
    {
        Customer::factory()->named('Rajesh Kumar')->create();

        $this->assertCount(1, $this->directory->search('Rajesh'));
        $this->assertCount(0, $this->directory->search('Someone Else'));
    }

    #[Test]
    public function the_customer_list_only_shows_people_linked_to_the_signed_in_store(): void
    {
        $user = User::factory()->owner()->create();

        $mine = Customer::factory()->named('Mine Customer')->create();
        $mine->stores()->attach($user->store_id, ['first_seen_at' => now()]);

        Customer::factory()->named('Stranger Person')->create();

        $this->actingAs($user)
            ->get(route('customers.index'))
            ->assertOk()
            ->assertSee('Mine Customer')
            ->assertDontSee('Stranger Person');
    }

    #[Test]
    public function creating_a_customer_who_already_exists_links_them_instead_of_duplicating(): void
    {
        $user = User::factory()->owner()->create();
        Customer::factory()->named('Rajesh Kumar')->withMobile('9876543210')->create();

        $this->actingAs($user)->post(route('customers.store'), [
            'full_name' => 'Rajesh Kumar',
            'mobile' => '+91 98765 43210',
        ])->assertRedirect();

        $this->assertSame(1, Customer::count());
        $this->assertSame(1, Customer::first()->stores()->count());
    }

    #[Test]
    public function an_aadhaar_that_fails_its_checksum_is_rejected(): void
    {
        $user = User::factory()->owner()->create();

        $this->actingAs($user)->post(route('customers.store'), [
            'full_name' => 'Typo Person',
            'mobile' => '9876543211',
            'aadhaar' => '234567890123',
        ])->assertSessionHasErrors('aadhaar');

        $this->assertSame(0, Customer::count());
    }
}
