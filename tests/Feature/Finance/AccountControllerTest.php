<?php

namespace Tests\Feature\Finance;

use App\Models\Auth\User;
use App\Models\Finance\Account;
use App\Models\Finance\JournalEntry;
use App\Models\Finance\JournalItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Create user for authentication
        $this->user = User::factory()->create();
    }

    public function test_guest_cannot_access_accounts_api(): void
    {
        $this->getJson('/api/accounts')->assertStatus(401);
        $this->postJson('/api/accounts', [])->assertStatus(401);
        $this->getJson('/api/accounts/1')->assertStatus(401);
        $this->putJson('/api/accounts/1', [])->assertStatus(401);
        $this->deleteJson('/api/accounts/1')->assertStatus(401);
    }

    public function test_authenticated_user_can_get_accounts_list(): void
    {
        Sanctum::actingAs($this->user);

        Account::factory()->count(3)->create();

        $response = $this->getJson('/api/accounts');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_user_can_create_account_with_valid_data(): void
    {
        Sanctum::actingAs($this->user);

        $payload = [
            'code' => '9999',
            'name' => 'Biaya Test Akun',
            'type' => 'expense',
            'balance' => 250000.50,
            'description' => 'Test account creation',
            'is_active' => true,
        ];

        $response = $this->postJson('/api/accounts', $payload);

        $response->assertStatus(201);

        $this->assertDatabaseHas('accounts', [
            'code' => '9999',
            'name' => 'Biaya Test Akun',
            'type' => 'expense',
            'balance' => 250000.50,
            'is_active' => 1,
        ]);
    }

    public function test_user_cannot_create_account_with_invalid_data(): void
    {
        Sanctum::actingAs($this->user);

        // Code and name missing, type is invalid
        $payload = [
            'type' => 'invalid_type',
        ];

        $response = $this->postJson('/api/accounts', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code', 'name', 'type']);
    }

    public function test_user_cannot_create_account_with_duplicate_code(): void
    {
        Sanctum::actingAs($this->user);

        Account::factory()->create(['code' => '1101']);

        $payload = [
            'code' => '1101',
            'name' => 'Another cash account',
            'type' => 'asset',
        ];

        $response = $this->postJson('/api/accounts', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    public function test_user_can_show_account(): void
    {
        Sanctum::actingAs($this->user);

        $account = Account::factory()->create();

        $response = $this->getJson("/api/accounts/{$account->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.code', $account->code);
    }

    public function test_user_can_update_regular_account(): void
    {
        Sanctum::actingAs($this->user);

        $account = Account::factory()->create([
            'code' => '8888',
            'name' => 'Old Name',
            'type' => 'expense',
        ]);

        $payload = [
            'code' => '8889',
            'name' => 'New Name',
            'type' => 'asset',
            'description' => 'Updated desc',
        ];

        $response = $this->putJson("/api/accounts/{$account->id}", $payload);

        $response->assertStatus(200);

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'code' => '8889',
            'name' => 'New Name',
            'type' => 'asset',
            'description' => 'Updated desc',
        ]);
    }

    public function test_user_cannot_update_code_or_type_of_protected_system_account(): void
    {
        Sanctum::actingAs($this->user);

        // 1101 is Kas Toko / Petty Cash (protected)
        $account = Account::factory()->create([
            'code' => '1101',
            'name' => 'Kas Toko / Petty Cash',
            'type' => 'asset',
        ]);

        // Attempting to change code
        $payloadCodeChange = [
            'code' => '1199',
            'name' => 'Modified Name',
        ];

        $responseCode = $this->putJson("/api/accounts/{$account->id}", $payloadCodeChange);
        $responseCode->assertStatus(422)
            ->assertJsonPath('message', 'Protected system accounts cannot have their account code changed.');

        // Attempting to change type
        $payloadTypeChange = [
            'type' => 'revenue',
            'name' => 'Modified Name',
        ];

        $responseType = $this->putJson("/api/accounts/{$account->id}", $payloadTypeChange);
        $responseType->assertStatus(422)
            ->assertJsonPath('message', 'Protected system accounts cannot have their account type changed.');

        // Updating only name/description is allowed
        $payloadValid = [
            'name' => 'Kas Toko Baru',
            'description' => 'Updated system desc',
        ];

        $responseValid = $this->putJson("/api/accounts/{$account->id}", $payloadValid);
        $responseValid->assertStatus(200);

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'code' => '1101', // code unchanged
            'name' => 'Kas Toko Baru', // name updated
        ]);
    }

    public function test_user_cannot_delete_protected_system_account(): void
    {
        Sanctum::actingAs($this->user);

        // 1201 is Persediaan Barang Dagang (protected)
        $account = Account::factory()->create([
            'code' => '1201',
            'name' => 'Persediaan Barang Dagang',
            'type' => 'asset',
        ]);

        $response = $this->deleteJson("/api/accounts/{$account->id}");

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Protected system accounts cannot be deleted.');

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'deleted_at' => null,
        ]);
    }

    public function test_user_cannot_delete_account_with_journal_entries(): void
    {
        Sanctum::actingAs($this->user);

        $account = Account::factory()->create([
            'code' => '7777',
            'name' => 'General Account',
        ]);

        // Attach a journal entry
        $entry = JournalEntry::create([
            'transaction_date' => now()->toDateString(),
            'reference_no' => 'JV-TEST',
            'created_by' => $this->user->id,
        ]);

        JournalItem::create([
            'journal_entry_id' => $entry->id,
            'account_id' => $account->id,
            'debit' => 50000.00,
            'credit' => 0.00,
        ]);

        $response = $this->deleteJson("/api/accounts/{$account->id}");

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Cannot delete account with existing journal entry transactions.');

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'deleted_at' => null,
        ]);
    }

    public function test_user_can_delete_regular_account(): void
    {
        Sanctum::actingAs($this->user);

        $account = Account::factory()->create([
            'code' => '7777',
            'name' => 'General Account',
        ]);

        $response = $this->deleteJson("/api/accounts/{$account->id}");

        $response->assertStatus(200);

        $this->assertSoftDeleted('accounts', [
            'id' => $account->id,
        ]);
    }
}
