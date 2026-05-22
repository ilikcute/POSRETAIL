<?php

namespace Tests\Feature\Finance;

use App\Models\Auth\User;
use App\Models\Finance\Account;
use App\Models\Finance\JournalEntry;
use App\Models\Finance\JournalItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class JournalEntryControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Account $cashAccount;

    protected Account $bankAccount;

    protected Account $receivableAccount;

    protected Account $equityAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        // Setup accounts
        $this->cashAccount = Account::create([
            'code' => '1101',
            'name' => 'Kas Toko / Petty Cash',
            'type' => 'asset',
            'balance' => 1000000.00,
            'is_active' => true,
        ]);

        $this->bankAccount = Account::create([
            'code' => '1102',
            'name' => 'Bank Mandiri Toko',
            'type' => 'asset',
            'balance' => 5000000.00,
            'is_active' => true,
        ]);

        $this->receivableAccount = Account::create([
            'code' => '1103',
            'name' => 'Piutang Dagang',
            'type' => 'asset',
            'balance' => 0.00,
            'is_active' => true,
        ]);

        $this->equityAccount = Account::create([
            'code' => '3101',
            'name' => 'Modal Disetor',
            'type' => 'equity',
            'balance' => 6000000.00,
            'is_active' => true,
        ]);
    }

    public function test_guest_cannot_access_journal_entries_api(): void
    {
        $this->getJson('/api/journal-entries')->assertStatus(401);
        $this->postJson('/api/journal-entries', [])->assertStatus(401);
        $this->getJson('/api/journal-entries/1')->assertStatus(401);
        $this->putJson('/api/journal-entries/1', [])->assertStatus(401);
        $this->deleteJson('/api/journal-entries/1')->assertStatus(401);
    }

    public function test_can_get_paginated_journal_entries_with_filters(): void
    {
        Sanctum::actingAs($this->user);

        // Entry 1: JV-001 (2026-05-10)
        $entry1 = JournalEntry::create([
            'reference_no' => 'JV-202605-00001',
            'transaction_date' => '2026-05-10',
            'description' => 'Manual adj petty cash',
            'created_by' => $this->user->id,
        ]);
        JournalItem::create(['journal_entry_id' => $entry1->id, 'account_id' => $this->cashAccount->id, 'debit' => 20000.00, 'credit' => 0.00]);
        JournalItem::create(['journal_entry_id' => $entry1->id, 'account_id' => $this->equityAccount->id, 'debit' => 0.00, 'credit' => 20000.00]);

        // Entry 2: JV-002 (2026-05-15)
        $entry2 = JournalEntry::create([
            'reference_no' => 'JV-202605-00002',
            'transaction_date' => '2026-05-15',
            'description' => 'Deposit to Bank',
            'created_by' => $this->user->id,
        ]);
        JournalItem::create(['journal_entry_id' => $entry2->id, 'account_id' => $this->bankAccount->id, 'debit' => 150000.00, 'credit' => 0.00]);
        JournalItem::create(['journal_entry_id' => $entry2->id, 'account_id' => $this->cashAccount->id, 'debit' => 0.00, 'credit' => 150000.00]);

        // Test listing
        $response = $this->getJson('/api/journal-entries');
        $response->assertStatus(200)
            ->assertJsonStructure(['status', 'data' => ['data', 'current_page', 'total']]);

        $this->assertCount(2, $response->json('data.data'));

        // Filter search (description match)
        $responseSearch = $this->getJson('/api/journal-entries?search=petty');
        $responseSearch->assertStatus(200);
        $this->assertCount(1, $responseSearch->json('data.data'));
        $this->assertEquals('JV-202605-00001', $responseSearch->json('data.data.0.reference_no'));

        // Filter date range
        $responseDates = $this->getJson('/api/journal-entries?start_date=2026-05-12&end_date=2026-05-20');
        $responseDates->assertStatus(200);
        $this->assertCount(1, $responseDates->json('data.data'));
        $this->assertEquals('JV-202605-00002', $responseDates->json('data.data.0.reference_no'));

        // Filter account
        $responseAccount = $this->getJson('/api/journal-entries?account_id='.$this->bankAccount->id);
        $responseAccount->assertStatus(200);
        $this->assertCount(1, $responseAccount->json('data.data'));
        $this->assertEquals('JV-202605-00002', $responseAccount->json('data.data.0.reference_no'));
    }

    public function test_cannot_create_unbalanced_journal_entry(): void
    {
        Sanctum::actingAs($this->user);

        $payload = [
            'transaction_date' => '2026-05-20',
            'description' => 'Test unbalanced entry',
            'items' => [
                ['account_id' => $this->cashAccount->id, 'debit' => 100000.00, 'credit' => 0.00],
                ['account_id' => $this->equityAccount->id, 'debit' => 0.00, 'credit' => 99000.00], // Mismatch 1000
            ],
        ];

        $response = $this->postJson('/api/journal-entries', $payload);
        $response->assertStatus(422)
            ->assertJsonPath('status', 'error')
            ->assertJsonStructure(['message']);
    }

    public function test_cannot_create_journal_with_inactive_account(): void
    {
        Sanctum::actingAs($this->user);

        $this->bankAccount->update(['is_active' => false]);

        $payload = [
            'transaction_date' => '2026-05-20',
            'description' => 'Test inactive account posting',
            'items' => [
                ['account_id' => $this->cashAccount->id, 'debit' => 0.00, 'credit' => 100000.00],
                ['account_id' => $this->bankAccount->id, 'debit' => 100000.00, 'credit' => 0.00], // Inactive
            ],
        ];

        $response = $this->postJson('/api/journal-entries', $payload);
        $response->assertStatus(422)
            ->assertJsonPath('status', 'error')
            ->assertJsonFragment(['status' => 'error']);
    }

    public function test_can_create_balanced_journal_entry_and_updates_balances(): void
    {
        Sanctum::actingAs($this->user);

        // Current Balances: Cash = 1,000,000, Equity = 6,000,000
        // Posting: Debit Cash 500,000, Credit Equity 500,000
        $payload = [
            'transaction_date' => '2026-05-20',
            'description' => 'Posting manual modal tambahan',
            'items' => [
                ['account_id' => $this->cashAccount->id, 'debit' => 500000.00, 'credit' => 0.00],
                ['account_id' => $this->equityAccount->id, 'debit' => 0.00, 'credit' => 500000.00],
            ],
        ];

        $response = $this->postJson('/api/journal-entries', $payload);
        $response->assertStatus(201)
            ->assertJsonPath('status', 'success');

        // Check DB balances
        $this->assertEquals(1500000.00, $this->cashAccount->fresh()->balance);
        $this->assertEquals(6500000.00, $this->equityAccount->fresh()->balance);

        // Check stored items count
        $entry = JournalEntry::where('description', 'Posting manual modal tambahan')->first();
        $this->assertNotNull($entry);
        $this->assertCount(2, $entry->items);
    }

    public function test_cannot_edit_or_delete_system_generated_journal(): void
    {
        Sanctum::actingAs($this->user);

        // Create a system entry starting with non-JV prefix (e.g. SALE)
        $systemEntry = JournalEntry::create([
            'reference_no' => 'SALE-202605-00001',
            'transaction_date' => '2026-05-10',
            'description' => 'Automatic sales posting',
            'created_by' => $this->user->id,
        ]);
        JournalItem::create(['journal_entry_id' => $systemEntry->id, 'account_id' => $this->cashAccount->id, 'debit' => 10000.00, 'credit' => 0.00]);
        JournalItem::create(['journal_entry_id' => $systemEntry->id, 'account_id' => $this->equityAccount->id, 'debit' => 0.00, 'credit' => 10000.00]);

        // Attempt edit
        $responseEdit = $this->putJson('/api/journal-entries/'.$systemEntry->id, [
            'transaction_date' => '2026-05-11',
            'description' => 'Modified system posting',
        ]);
        $responseEdit->assertStatus(422)
            ->assertJsonPath('status', 'error');

        // Attempt delete
        $responseDelete = $this->deleteJson('/api/journal-entries/'.$systemEntry->id);
        $responseDelete->assertStatus(422)
            ->assertJsonPath('status', 'error');
    }

    public function test_can_delete_journal_entry_and_reverses_balances(): void
    {
        Sanctum::actingAs($this->user);

        // Set initial balance
        $this->cashAccount->update(['balance' => 1500000.00]);
        $this->equityAccount->update(['balance' => 6500000.00]);

        // Create a JV manual entry to delete
        $entry = JournalEntry::create([
            'reference_no' => 'JV-202605-00003',
            'transaction_date' => '2026-05-10',
            'description' => 'JV entry to delete',
            'created_by' => $this->user->id,
        ]);
        JournalItem::create(['journal_entry_id' => $entry->id, 'account_id' => $this->cashAccount->id, 'debit' => 500000.00, 'credit' => 0.00]);
        JournalItem::create(['journal_entry_id' => $entry->id, 'account_id' => $this->equityAccount->id, 'debit' => 0.00, 'credit' => 500000.00]);

        // Delete it
        $response = $this->deleteJson('/api/journal-entries/'.$entry->id);
        $response->assertStatus(200);

        // Verify balances are reversed: Cash decreased by 500k, Equity decreased by 500k
        $this->assertEquals(1000000.00, $this->cashAccount->fresh()->balance);
        $this->assertEquals(6000000.00, $this->equityAccount->fresh()->balance);

        // Verify entry and items are deleted
        $this->assertDatabaseMissing('journal_entries', ['id' => $entry->id]);
        $this->assertDatabaseMissing('journal_items', ['journal_entry_id' => $entry->id]);
    }

    public function test_can_update_journal_entry_items_and_recalculates_balances(): void
    {
        Sanctum::actingAs($this->user);

        // Setup initial balances
        $this->cashAccount->update(['balance' => 1200000.00]); // has +200k from original entry
        $this->bankAccount->update(['balance' => 5000000.00]);
        $this->equityAccount->update(['balance' => 6200000.00]); // has +200k from original entry

        // Create manual entry
        $entry = JournalEntry::create([
            'reference_no' => 'JV-202605-00004',
            'transaction_date' => '2026-05-12',
            'description' => 'JV to modify',
            'created_by' => $this->user->id,
        ]);
        JournalItem::create(['journal_entry_id' => $entry->id, 'account_id' => $this->cashAccount->id, 'debit' => 200000.00, 'credit' => 0.00]);
        JournalItem::create(['journal_entry_id' => $entry->id, 'account_id' => $this->equityAccount->id, 'debit' => 0.00, 'credit' => 200000.00]);

        // Send update replacing Cash item with Bank item and changing value to 300k
        $payload = [
            'transaction_date' => '2026-05-12',
            'description' => 'JV after modification',
            'items' => [
                ['account_id' => $this->bankAccount->id, 'debit' => 300000.00, 'credit' => 0.00], // changed Cash -> Bank, amount -> 300k
                ['account_id' => $this->equityAccount->id, 'debit' => 0.00, 'credit' => 300000.00], // amount -> 300k
            ],
        ];

        $response = $this->putJson('/api/journal-entries/'.$entry->id, $payload);
        $response->assertStatus(200);

        // Verify balances recalculation:
        // Cash should decrease by 200k (reversal) -> returns to 1,000,000.00
        $this->assertEquals(1000000.00, $this->cashAccount->fresh()->balance);

        // Bank should increase by 300k (new posting) -> 5,300,000.00
        $this->assertEquals(5300000.00, $this->bankAccount->fresh()->balance);

        // Equity should reverse -200k and add +300k -> net change +100k -> 6,300,000.00
        $this->assertEquals(6300000.00, $this->equityAccount->fresh()->balance);

        // Check that old items are deleted and new ones are inserted
        $this->assertCount(2, $entry->fresh()->items);
        $this->assertEquals('JV after modification', $entry->fresh()->description);
    }
}
