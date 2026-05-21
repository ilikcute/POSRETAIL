<?php

namespace Tests\Feature\Sales;

use App\Models\Auth\User;
use App\Models\Sales\Promotion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PromotionTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Create an active user for Sanctum authentication
        $this->user = User::factory()->create();
    }

    public function test_guest_cannot_access_promotions_api(): void
    {
        $this->getJson('/api/promotions')->assertStatus(401);
        $this->postJson('/api/promotions', [])->assertStatus(401);
        $this->getJson('/api/promotions/1')->assertStatus(401);
        $this->putJson('/api/promotions/1', [])->assertStatus(401);
        $this->deleteJson('/api/promotions/1')->assertStatus(401);
    }

    public function test_authenticated_user_can_get_promotions_list(): void
    {
        Sanctum::actingAs($this->user);

        Promotion::factory()->count(3)->create();

        $response = $this->getJson('/api/promotions');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_user_can_create_promotion_with_valid_data(): void
    {
        Sanctum::actingAs($this->user);

        $payload = [
            'code' => 'TESTPROMO',
            'name' => 'Test Promo Code',
            'description' => 'Diskon akhir pekan',
            'type' => 'percentage',
            'value' => 15.00,
            'min_purchase_amount' => 50000.00,
            'max_discount_amount' => 20000.00,
            'start_date' => '2026-05-20 00:00:00',
            'end_date' => '2026-05-25 23:59:59',
            'is_active' => true,
        ];

        $response = $this->postJson('/api/promotions', $payload);

        $response->assertStatus(201);

        $this->assertDatabaseHas('promotions', [
            'code' => 'TESTPROMO',
            'name' => 'Test Promo Code',
            'type' => 'percentage',
            'value' => 15.00,
            'min_purchase_amount' => 50000.00,
            'max_discount_amount' => 20000.00,
            'is_active' => 1,
        ]);
    }

    public function test_user_cannot_create_promotion_with_invalid_data(): void
    {
        Sanctum::actingAs($this->user);

        // Missing required fields: code, name, type, value
        $payload = [
            'description' => 'Missing fields',
        ];

        $response = $this->postJson('/api/promotions', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code', 'name', 'type', 'value']);

        // Invalid: end_date before start_date
        $payloadDates = [
            'code' => 'PROMO1',
            'name' => 'Test',
            'type' => 'percentage',
            'value' => 10,
            'start_date' => '2026-05-25 00:00:00',
            'end_date' => '2026-05-20 00:00:00',
        ];

        $this->postJson('/api/promotions', $payloadDates)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['end_date']);
    }

    public function test_user_can_show_promotion(): void
    {
        Sanctum::actingAs($this->user);

        $promotion = Promotion::factory()->create();

        $response = $this->getJson("/api/promotions/{$promotion->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.code', $promotion->code);
    }

    public function test_user_can_update_promotion(): void
    {
        Sanctum::actingAs($this->user);

        $promotion = Promotion::factory()->create([
            'code' => 'OLDCODE',
            'name' => 'Old Name',
            'type' => 'fixed_amount',
            'value' => 10000.00,
        ]);

        $payload = [
            'code' => 'NEWCODE',
            'name' => 'New Name',
            'type' => 'percentage',
            'value' => 25.00,
        ];

        $response = $this->putJson("/api/promotions/{$promotion->id}", $payload);

        $response->assertStatus(200);

        $this->assertDatabaseHas('promotions', [
            'id' => $promotion->id,
            'code' => 'NEWCODE',
            'name' => 'New Name',
            'type' => 'percentage',
            'value' => 25.00,
        ]);
    }

    public function test_user_can_delete_promotion(): void
    {
        Sanctum::actingAs($this->user);

        $promotion = Promotion::factory()->create();

        $response = $this->deleteJson("/api/promotions/{$promotion->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('promotions', ['id' => $promotion->id]);
    }

    public function test_redirection_and_flash_messages_for_web_requests(): void
    {
        Sanctum::actingAs($this->user);

        // 1. Create redirect test
        $payloadStore = [
            'code' => 'REDIRECT1',
            'name' => 'Redirect Store',
            'type' => 'fixed_amount',
            'value' => 5000.00,
            'is_active' => true,
        ];

        $responseStore = $this->from('/promotions-page')
            ->post('/api/promotions', $payloadStore);

        $responseStore->assertRedirect('/promotions-page');
        $responseStore->assertSessionHas('success', 'Promotion created successfully');

        $promotion = Promotion::where('code', 'REDIRECT1')->first();
        $this->assertNotNull($promotion);

        // 2. Show redirect test
        $responseShow = $this->from('/promotions-page')
            ->get("/api/promotions/{$promotion->id}");
        $responseShow->assertRedirect('/promotions-page');

        // 3. Update redirect test
        $payloadUpdate = [
            'code' => 'REDIRECT1-EDITED',
            'name' => 'Redirect Store Edited',
            'type' => 'fixed_amount',
            'value' => 6000.00,
        ];

        $responseUpdate = $this->from('/promotions-page')
            ->put("/api/promotions/{$promotion->id}", $payloadUpdate);

        $responseUpdate->assertRedirect('/promotions-page');
        $responseUpdate->assertSessionHas('success', 'Promotion updated successfully');

        // 4. Delete redirect test
        $responseDelete = $this->from('/promotions-page')
            ->delete("/api/promotions/{$promotion->id}");

        $responseDelete->assertRedirect('/promotions-page');
        $responseDelete->assertSessionHas('success', 'Promotion deleted successfully');
        $this->assertSoftDeleted('promotions', ['id' => $promotion->id]);
    }
}
