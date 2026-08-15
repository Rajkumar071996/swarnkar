<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_login_page_offers_a_sign_up_link(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee(route('register'), false)
            ->assertSee('Create a store account');
    }

    #[Test]
    public function a_jeweller_can_open_a_store_and_land_on_the_dashboard(): void
    {
        $response = $this->post(route('register.store'), $this->validPayload());

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();

        $user = User::where('phone', '9829012345')->first();

        $this->assertNotNull($user);
        $this->assertSame(UserRole::Owner, $user->role);
        $this->assertTrue($user->is_active);
        $this->assertSame('Laxmi Jewellers', $user->store->name);
        $this->assertSame('Shop 14, Johari Bazaar', $user->store->address_line);
        $this->assertSame('Jaipur', $user->store->city);
        $this->assertSame('Rajasthan', $user->store->state);
        $this->assertSame('302003', $user->store->pincode);
        $this->assertSame('9829012345', $user->store->phone);
        $this->assertSame('1000000.00', $user->store->opening_capital);
        $this->assertSame('400000.00', $user->store->cash_in_hand);
        $this->assertSame('600000.00', $user->store->bank_balance);
        $this->assertSame(1, Store::count());

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Capital')
            ->assertSee(money(1000000), false)
            ->assertSee('Cash in hand')
            ->assertSee(money(400000), false)
            ->assertSee('Bank')
            ->assertSee(money(600000), false)
            ->assertSee('Expenses');
    }

    #[Test]
    public function the_register_form_asks_for_the_full_shop_address(): void
    {
        $this->get(route('register'))
            ->assertOk()
            ->assertSee('Shop address')
            ->assertSee('name="address_line"', false)
            ->assertSee('name="pincode"', false)
            ->assertSee('Opening books')
            ->assertSee('name="opening_capital"', false)
            ->assertSee('name="cash_in_hand"', false)
            ->assertSee('name="bank_balance"', false);
    }

    #[Test]
    public function registration_requires_the_shop_address(): void
    {
        $this->post(route('register.store'), $this->validPayload([
            'address_line' => '',
            'pincode' => '',
        ]))->assertSessionHasErrors(['address_line', 'pincode']);

        $this->assertGuest();
    }

    #[Test]
    public function registration_requires_opening_books_that_add_up(): void
    {
        $this->post(route('register.store'), $this->validPayload([
            'opening_capital' => '',
            'cash_in_hand' => '',
            'bank_balance' => '',
        ]))->assertSessionHasErrors(['opening_capital', 'cash_in_hand', 'bank_balance']);

        $this->post(route('register.store'), $this->validPayload([
            'opening_capital' => 1000000,
            'cash_in_hand' => 400000,
            'bank_balance' => 500000,
        ]))->assertSessionHasErrors('opening_capital');

        $this->assertGuest();
    }

    #[Test]
    public function a_mobile_already_on_the_network_cannot_register_again(): void
    {
        User::factory()->owner()->create(['phone' => '9829011223']);

        $this->post(route('register.store'), $this->validPayload([
            'phone' => '9829011223',
        ]))->assertSessionHasErrors('phone');

        $this->assertGuest();
        $this->assertSame(1, Store::count());
    }

    #[Test]
    public function country_code_prefixes_are_stripped_before_saving(): void
    {
        $this->post(route('register.store'), $this->validPayload([
            'phone' => '+91 98290 12345',
        ]))->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('users', ['phone' => '9829012345']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'store_name' => 'Laxmi Jewellers',
            'address_line' => 'Shop 14, Johari Bazaar',
            'city' => 'Jaipur',
            'state' => 'Rajasthan',
            'pincode' => '302003',
            'name' => 'Suresh Agarwal',
            'phone' => '9829012345',
            'password' => 'password',
            'password_confirmation' => 'password',
            'opening_capital' => 1000000,
            'cash_in_hand' => 400000,
            'bank_balance' => 600000,
        ], $overrides);
    }

    #[Test]
    public function signed_in_users_are_sent_away_from_the_register_form(): void
    {
        $user = User::factory()->owner()->create();

        $this->actingAs($user)
            ->get(route('register'))
            ->assertRedirect(route('dashboard'));
    }
}
