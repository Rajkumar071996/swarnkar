<?php

namespace Tests\Feature\Books;

use App\Models\StoreExpense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ShopBooksTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_dashboard_shows_the_shops_capital_cash_bank_and_expenses(): void
    {
        $user = User::factory()->owner()->create();
        $user->store->forceFill([
            'opening_capital' => 1500000,
            'cash_in_hand' => 500000,
            'bank_balance' => 1000000,
        ])->save();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Capital')
            ->assertSee(money(1500000), false)
            ->assertSee('Cash in hand')
            ->assertSee(money(500000), false)
            ->assertSee('Bank')
            ->assertSee(money(1000000), false)
            ->assertSee('Expenses')
            ->assertSee(money(0), false);

        $this->actingAs($user)
            ->get(route('girvi.dashboard'))
            ->assertOk()
            ->assertSee('Capital')
            ->assertSee(money(1500000), false)
            ->assertSee(money(500000), false)
            ->assertSee(money(1000000), false);
    }

    #[Test]
    public function an_expense_comes_out_of_cash_and_shows_on_the_dashboard(): void
    {
        $user = User::factory()->owner()->create();

        $this->actingAs($user)
            ->post(route('books.expenses.store'), [
                'amount' => 25000,
                'paid_from' => 'cash',
                'paid_on' => now()->toDateString(),
                'narration' => 'Shop rent',
            ])
            ->assertRedirect();

        $user->store->refresh();

        $this->assertSame('375000.00', $user->store->cash_in_hand);
        $this->assertSame('600000.00', $user->store->bank_balance);
        $this->assertSame('1000000.00', $user->store->opening_capital);
        $this->assertSame(1, StoreExpense::query()->count());

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(money(25000), false)
            ->assertSee(money(375000), false);

        $this->actingAs($user)
            ->get(route('books.index'))
            ->assertOk()
            ->assertSee('Shop rent')
            ->assertSee(money(25000), false);
    }

    #[Test]
    public function an_expense_cannot_take_more_than_the_wallet_holds(): void
    {
        $user = User::factory()->owner()->create();

        $this->actingAs($user)
            ->post(route('books.expenses.store'), [
                'amount' => 700000,
                'paid_from' => 'bank',
                'paid_on' => now()->toDateString(),
                'narration' => 'Stock purchase',
            ])
            ->assertSessionHasErrors('amount');

        $this->assertSame('600000.00', $user->store->fresh()->bank_balance);
        $this->assertSame(0, StoreExpense::query()->count());
    }

    #[Test]
    public function the_owner_can_correct_the_opening_books(): void
    {
        $user = User::factory()->owner()->create();

        $this->actingAs($user)
            ->put(route('books.update'), [
                'opening_capital' => 2000000,
                'cash_in_hand' => 750000,
                'bank_balance' => 1250000,
            ])
            ->assertRedirect();

        $user->store->refresh();

        $this->assertSame('2000000.00', $user->store->opening_capital);
        $this->assertSame('750000.00', $user->store->cash_in_hand);
        $this->assertSame('1250000.00', $user->store->bank_balance);
    }

    #[Test]
    public function staff_cannot_change_the_books(): void
    {
        $owner = User::factory()->owner()->create();
        $staff = User::factory()->staff()->create(['store_id' => $owner->store_id]);

        $this->actingAs($staff)->get(route('books.index'))->assertForbidden();

        $this->actingAs($staff)
            ->post(route('books.expenses.store'), [
                'amount' => 100,
                'paid_from' => 'cash',
                'paid_on' => now()->toDateString(),
                'narration' => 'Tea',
            ])
            ->assertForbidden();
    }

    #[Test]
    public function one_shops_expense_does_not_show_in_another(): void
    {
        $owner = User::factory()->owner()->create();
        $other = User::factory()->owner()->create();

        $this->actingAs($owner)->post(route('books.expenses.store'), [
            'amount' => 1000,
            'paid_from' => 'cash',
            'paid_on' => now()->toDateString(),
            'narration' => 'Electricity',
        ]);

        $this->actingAs($other)
            ->get(route('books.index'))
            ->assertOk()
            ->assertDontSee('Electricity');

        $this->assertSame(0, StoreExpense::query()->count());
        $this->assertSame(1, StoreExpense::query()->networkWide()->where('store_id', $owner->store_id)->count());
    }
}
