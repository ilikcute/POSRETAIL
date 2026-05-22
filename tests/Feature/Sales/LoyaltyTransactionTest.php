<?php

namespace Tests\Feature\Sales;

use App\Models\Auth\User;
use App\Models\Master\Customer;
use App\Models\Sales\LoyaltyTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LoyaltyTransactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_loyalty_transactions(): void
    {
        $this->getJson('/api/loyalty-transactions')->assertStatus(401);
        $this->postJson('/api/loyalty-transactions', [])->assertStatus(401);
    }

    public function test_user_can_earn_loyalty_points_atomically(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $customer = Customer::factory()->create(['point_balance' => 150]);

        $response = $this->postJson('/api/loyalty-transactions', [
            'customer_id' => $customer->id,
            'type' => 'earn',
            'points' => 250,
            'description' => 'Bonus poin membership',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.customer.point_balance', 400)
            ->assertJsonPath('data.points', 250)
            ->assertJsonPath('data.created_by', $user->id);

        $this->assertSame(400, $customer->refresh()->point_balance);
        $this->assertDatabaseHas('loyalty_transactions', [
            'customer_id' => $customer->id,
            'type' => 'earn',
            'points' => 250,
        ]);
    }

    public function test_redeem_is_rejected_when_balance_is_insufficient(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $customer = Customer::factory()->create(['point_balance' => 100]);

        $response = $this->postJson('/api/loyalty-transactions', [
            'customer_id' => $customer->id,
            'type' => 'redeem',
            'points' => 150,
            'description' => 'Redeem belanja',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('status', 'error')
            ->assertJsonStructure(['errors' => ['points', 'loyalty']]);

        $this->assertSame(100, $customer->refresh()->point_balance);
        $this->assertSame(0, LoyaltyTransaction::query()->count());
    }

    public function test_update_only_changes_description_without_touching_balance(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $customer = Customer::factory()->create(['point_balance' => 500]);
        $transaction = LoyaltyTransaction::query()->create([
            'customer_id' => $customer->id,
            'type' => 'adjust',
            'points' => 100,
            'amount' => 0,
            'description' => 'Catatan awal',
            'created_by' => auth()->id(),
        ]);

        $response = $this->putJson('/api/loyalty-transactions/'.$transaction->id, [
            'description' => 'Catatan koreksi audit',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.description', 'Catatan koreksi audit')
            ->assertJsonPath('data.points', 100);

        $this->assertSame(500, $customer->refresh()->point_balance);
    }

    public function test_destroy_is_blocked_to_preserve_audit_trail(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $customer = Customer::factory()->create();
        $transaction = LoyaltyTransaction::query()->create([
            'customer_id' => $customer->id,
            'type' => 'earn',
            'points' => 25,
            'amount' => 0.25,
            'description' => 'Audit row',
            'created_by' => auth()->id(),
        ]);

        $this->deleteJson('/api/loyalty-transactions/'.$transaction->id)->assertStatus(409);

        $this->assertModelExists($transaction);
    }
}
