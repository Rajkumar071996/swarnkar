<?php

namespace Tests\Feature\Auth;

use App\Enums\UdhaarStatus;
use App\Models\Customer;
use App\Models\Store;
use App\Models\Udhaar;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;

    private User $owner;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = Store::factory()->create();
        $this->owner = User::factory()->owner()->create(['store_id' => $this->store->id]);
        $this->staff = User::factory()->staff()->create(['store_id' => $this->store->id]);
    }

    #[Test]
    public function guests_are_redirected_to_the_sign_in_screen(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    #[Test]
    public function a_deactivated_account_cannot_sign_in(): void
    {
        $this->staff->update(['is_active' => false]);

        $this->post(route('login.store'), [
            'company_name' => $this->staff->store->name,
            'phone' => $this->staff->phone,
            'password' => 'password',
        ])->assertSessionHasErrors('phone');

        $this->assertGuest();
    }

    #[Test]
    public function deactivating_a_signed_in_staff_member_ends_their_session(): void
    {
        $this->actingAs($this->staff)->get(route('dashboard'))->assertOk();

        $this->staff->update(['is_active' => false]);

        $this->actingAs($this->staff)->get(route('dashboard'))->assertRedirect(route('login'));
    }

    #[Test]
    public function only_owners_may_extend_store_credit(): void
    {
        $this->actingAs($this->staff)->get(route('udhaars.create'))->assertForbidden();
        $this->actingAs($this->owner)->get(route('udhaars.create'))->assertOk();
    }

    #[Test]
    public function staff_may_still_record_payments_against_existing_credit(): void
    {
        $udhaar = $this->udhaar();

        $this->actingAs($this->staff)
            ->post(route('udhaars.payments.store', $udhaar), [
                'amount' => 5000,
                'paid_on' => Carbon::today()->toDateString(),
                'method' => 'cash',
            ])
            ->assertRedirect();

        $this->assertSame(5000.0, (float) $udhaar->fresh()->amount_paid);
    }

    #[Test]
    public function only_owners_may_write_off_an_account(): void
    {
        $udhaar = $this->udhaar();

        $this->actingAs($this->staff)
            ->post(route('udhaars.write-off', $udhaar))
            ->assertForbidden();

        $this->assertSame(UdhaarStatus::Open, $udhaar->fresh()->status);

        $this->actingAs($this->owner)
            ->post(route('udhaars.write-off', $udhaar))
            ->assertRedirect();

        $this->assertSame(UdhaarStatus::WrittenOff, $udhaar->fresh()->status);
    }

    #[Test]
    public function only_owners_may_report_a_network_default(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($this->staff)
            ->post(route('flags.store', $customer), [
                'reason' => 'bounced_cheque',
                'occurred_on' => Carbon::today()->toDateString(),
                'narrative' => 'Cheque returned unpaid.',
            ])
            ->assertForbidden();
    }

    #[Test]
    public function only_owners_may_manage_staff(): void
    {
        $this->actingAs($this->staff)->get(route('staff.index'))->assertForbidden();
        $this->actingAs($this->owner)->get(route('staff.index'))->assertOk();
    }

    #[Test]
    public function an_owner_cannot_deactivate_their_own_account(): void
    {
        $this->actingAs($this->owner)
            ->delete(route('staff.destroy', $this->owner))
            ->assertForbidden();

        $this->assertTrue($this->owner->fresh()->is_active);
    }

    #[Test]
    public function all_roles_may_read_a_customer_score(): void
    {
        $this->actingAs($this->staff)->get(route('lookup.index'))->assertOk();
    }

    #[Test]
    public function a_store_cannot_open_another_stores_ledger_entry(): void
    {
        $rival = User::factory()->owner()->create(['store_id' => Store::factory()->create()->id]);
        $udhaar = $this->udhaar();

        // The store scope hides the row entirely rather than leaking its
        // existence through a 403.
        $this->actingAs($rival)->get(route('udhaars.show', $udhaar))->assertNotFound();
    }

    #[Test]
    public function the_udhaar_ledger_only_lists_the_signed_in_stores_rows(): void
    {
        $this->udhaar();

        $rivalStore = Store::factory()->create();
        Udhaar::factory()->create([
            'store_id' => $rivalStore->id,
            'item_description' => 'Rival store bangles',
        ]);

        $this->actingAs($this->owner)
            ->get(route('udhaars.index', ['filter' => 'all']))
            ->assertOk()
            ->assertDontSee('Rival store bangles');
    }

    private function udhaar(): Udhaar
    {
        return Udhaar::factory()
            ->issuedOn(Carbon::today()->subDays(10))
            ->create([
                'store_id' => $this->store->id,
                'customer_id' => Customer::factory()->create()->id,
                'principal_amount' => 40000,
            ]);
    }
}
