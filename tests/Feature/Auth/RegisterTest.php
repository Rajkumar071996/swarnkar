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
        $response = $this->post(route('register.store'), [
            'store_name' => 'Laxmi Jewellers',
            'city' => 'Jaipur',
            'state' => 'Rajasthan',
            'name' => 'Suresh Agarwal',
            'phone' => '9829012345',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();

        $user = User::where('phone', '9829012345')->first();

        $this->assertNotNull($user);
        $this->assertSame(UserRole::Owner, $user->role);
        $this->assertTrue($user->is_active);
        $this->assertSame('Laxmi Jewellers', $user->store->name);
        $this->assertSame('Jaipur', $user->store->city);
        $this->assertSame('9829012345', $user->store->phone);
        $this->assertSame(1, Store::count());
    }

    #[Test]
    public function a_mobile_already_on_the_network_cannot_register_again(): void
    {
        User::factory()->owner()->create(['phone' => '9829011223']);

        $this->post(route('register.store'), [
            'store_name' => 'Another Shop',
            'city' => 'Ajmer',
            'state' => 'Rajasthan',
            'name' => 'Someone Else',
            'phone' => '9829011223',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors('phone');

        $this->assertGuest();
        $this->assertSame(1, Store::count());
    }

    #[Test]
    public function country_code_prefixes_are_stripped_before_saving(): void
    {
        $this->post(route('register.store'), [
            'store_name' => 'Laxmi Jewellers',
            'city' => 'Jaipur',
            'state' => 'Rajasthan',
            'name' => 'Suresh Agarwal',
            'phone' => '+91 98290 12345',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('users', ['phone' => '9829012345']);
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
