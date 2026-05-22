<?php

namespace Tests\Feature\Finance;

use App\Models\Auth\User;
use App\Models\Finance\Account;
use App\Models\Finance\JournalEntry;
use App\Models\Finance\JournalItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FinancialReportControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Account $cashAccount;

    protected Account $bankAccount;

    protected Account $receivableAccount;

    protected Account $payableAccount;

    protected Account $equityAccount;

    protected Account $revenueAccount;

    protected Account $expenseAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        // Setup chart of accounts
        $this->cashAccount = Account::create([
            'code' => '1101',
            'name' => 'Kas Toko / Petty Cash',
            'type' => 'asset',
            'balance' => 0.00,
            'is_active' => true,
        ]);

        $this->bankAccount = Account::create([
            'code' => '1102',
            'name' => 'Bank Mandiri Toko',
            'type' => 'asset',
            'balance' => 0.00,
            'is_active' => true,
        ]);

        $this->receivableAccount = Account::create([
            'code' => '1103',
            'name' => 'Piutang Dagang',
            'type' => 'asset',
            'balance' => 0.00,
            'is_active' => true,
        ]);

        $this->payableAccount = Account::create([
            'code' => '2101',
            'name' => 'Utang Dagang',
            'type' => 'liability',
            'balance' => 0.00,
            'is_active' => true,
        ]);

        $this->equityAccount = Account::create([
            'code' => '3101',
            'name' => 'Modal Disetor',
            'type' => 'equity',
            'balance' => 0.00,
            'is_active' => true,
        ]);

        $this->revenueAccount = Account::create([
            'code' => '4101',
            'name' => 'Pendapatan Penjualan Retail',
            'type' => 'revenue',
            'balance' => 0.00,
            'is_active' => true,
        ]);

        $this->expenseAccount = Account::create([
            'code' => '5201',
            'name' => 'Beban Listrik Toko',
            'type' => 'expense',
            'balance' => 0.00,
            'is_active' => true,
        ]);
    }

    public function test_guest_cannot_access_reports_api(): void
    {
        $this->getJson('/api/reports/balance-sheet')->assertStatus(401);
        $this->getJson('/api/reports/profit-loss')->assertStatus(401);
        $this->getJson('/api/reports/cash-flow')->assertStatus(401);
    }

    public function test_invalid_date_validation_fails(): void
    {
        Sanctum::actingAs($this->user);

        // Invalid format
        $response = $this->getJson('/api/reports/profit-loss?start_date=2026/05/01');
        $response->assertStatus(422)
            ->assertJsonPath('status', 'error')
            ->assertJsonStructure(['message', 'errors']);

        // end_date before start_date
        $response2 = $this->getJson('/api/reports/cash-flow?start_date=2026-05-20&end_date=2026-05-10');
        $response2->assertStatus(422);
    }

    public function test_balance_sheet_calculates_retrospective_balances_correctly(): void
    {
        Sanctum::actingAs($this->user);

        // Transaction 1: Capital set-up on 2026-05-10
        // Debit Cash 1,000,000, Credit Equity 1,000,000
        $entry1 = JournalEntry::create([
            'transaction_date' => '2026-05-10',
            'reference_no' => 'JV-001',
            'created_by' => $this->user->id,
        ]);
        JournalItem::create(['journal_entry_id' => $entry1->id, 'account_id' => $this->cashAccount->id, 'debit' => 1000000.00, 'credit' => 0.00]);
        JournalItem::create(['journal_entry_id' => $entry1->id, 'account_id' => $this->equityAccount->id, 'debit' => 0.00, 'credit' => 1000000.00]);
        $this->cashAccount->increment('balance', 1000000.00);
        $this->equityAccount->increment('balance', 1000000.00);

        // Transaction 2: Revenue earned on 2026-05-15
        // Debit Cash 500,000, Credit Revenue 500,000
        $entry2 = JournalEntry::create([
            'transaction_date' => '2026-05-15',
            'reference_no' => 'JV-002',
            'created_by' => $this->user->id,
        ]);
        JournalItem::create(['journal_entry_id' => $entry2->id, 'account_id' => $this->cashAccount->id, 'debit' => 500000.00, 'credit' => 0.00]);
        JournalItem::create(['journal_entry_id' => $entry2->id, 'account_id' => $this->revenueAccount->id, 'debit' => 0.00, 'credit' => 500000.00]);
        $this->cashAccount->increment('balance', 500000.00);
        $this->revenueAccount->increment('balance', 500000.00);

        // Transaction 3: Expense paid on 2026-05-25 (after our target end_date 2026-05-20)
        // Debit Expense 200,000, Credit Cash 200,000
        $entry3 = JournalEntry::create([
            'transaction_date' => '2026-05-25',
            'reference_no' => 'JV-003',
            'created_by' => $this->user->id,
        ]);
        JournalItem::create(['journal_entry_id' => $entry3->id, 'account_id' => $this->expenseAccount->id, 'debit' => 200000.00, 'credit' => 0.00]);
        JournalItem::create(['journal_entry_id' => $entry3->id, 'account_id' => $this->cashAccount->id, 'debit' => 0.00, 'credit' => 200000.00]);
        $this->expenseAccount->increment('balance', 200000.00);
        $this->cashAccount->decrement('balance', 200000.00);

        // Fetch Balance Sheet as of 2026-05-20 (should exclude Transaction 3)
        // Current balances: Cash=1,300,000, Equity=1,000,000, Revenue=500,000, Expense=200,000
        // Expected retrospective balances:
        // Cash as of 2026-05-20: 1,300,000 - (0 debit after - 200,000 credit after) = 1,500,000
        // Expense as of 2026-05-20: 200,000 - (200,000 debit after - 0 credit after) = 0
        // Revenue as of 2026-05-20: 500,000 - (0 credit after - 0 debit after) = 500,000
        // Equity as of 2026-05-20: 1,000,000
        // Net Income (Netto) as of 2026-05-20: 500,000 - 0 = 500,000
        // Assets = 1,500,000
        // Liabilities = 0
        // Equity = 1,000,000 + 500,000 (net income) = 1,500,000
        $response = $this->getJson('/api/reports/balance-sheet?end_date=2026-05-20');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertTrue($data['metadata']['is_balanced']);
        $this->assertEquals(1500000.00, $data['summary']['total_assets']);
        $this->assertEquals(1500000.00, $data['summary']['total_liabilities_and_equity']);
    }

    public function test_profit_loss_calculates_period_revenues_and_expenses_correctly(): void
    {
        Sanctum::actingAs($this->user);

        // Transaction 1: Revenue earned within period (2026-05-10)
        $entry1 = JournalEntry::create(['transaction_date' => '2026-05-10', 'reference_no' => 'PL-001', 'created_by' => $this->user->id]);
        JournalItem::create(['journal_entry_id' => $entry1->id, 'account_id' => $this->cashAccount->id, 'debit' => 600000.00, 'credit' => 0.00]);
        JournalItem::create(['journal_entry_id' => $entry1->id, 'account_id' => $this->revenueAccount->id, 'debit' => 0.00, 'credit' => 600000.00]);

        // Transaction 2: Expense paid within period (2026-05-12)
        $entry2 = JournalEntry::create(['transaction_date' => '2026-05-12', 'reference_no' => 'PL-002', 'created_by' => $this->user->id]);
        JournalItem::create(['journal_entry_id' => $entry2->id, 'account_id' => $this->expenseAccount->id, 'debit' => 150000.00, 'credit' => 0.00]);
        JournalItem::create(['journal_entry_id' => $entry2->id, 'account_id' => $this->cashAccount->id, 'debit' => 0.00, 'credit' => 150000.00]);

        // Transaction 3: Revenue earned OUTSIDE period (2026-05-25)
        $entry3 = JournalEntry::create(['transaction_date' => '2026-05-25', 'reference_no' => 'PL-003', 'created_by' => $this->user->id]);
        JournalItem::create(['journal_entry_id' => $entry3->id, 'account_id' => $this->cashAccount->id, 'debit' => 200000.00, 'credit' => 0.00]);
        JournalItem::create(['journal_entry_id' => $entry3->id, 'account_id' => $this->revenueAccount->id, 'debit' => 0.00, 'credit' => 200000.00]);

        // Query P&L for period 2026-05-01 to 2026-05-20
        $response = $this->getJson('/api/reports/profit-loss?start_date=2026-05-01&end_date=2026-05-20');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertEquals(600000.00, $data['summary']['total_revenue']);
        $this->assertEquals(150000.00, $data['summary']['total_expense']);
        $this->assertEquals(450000.00, $data['summary']['net_profit']);
    }

    public function test_cash_flow_classifies_and_excludes_transfers_correctly(): void
    {
        Sanctum::actingAs($this->user);

        // Transaction 1: Capital Set-up (Financing Inflow)
        $entry1 = JournalEntry::create(['transaction_date' => '2026-05-05', 'reference_no' => 'CF-001', 'created_by' => $this->user->id]);
        JournalItem::create(['journal_entry_id' => $entry1->id, 'account_id' => $this->cashAccount->id, 'debit' => 1000000.00, 'credit' => 0.00]);
        JournalItem::create(['journal_entry_id' => $entry1->id, 'account_id' => $this->equityAccount->id, 'debit' => 0.00, 'credit' => 1000000.00]);
        $this->cashAccount->increment('balance', 1000000.00);

        // Transaction 2: Revenue inflow (Operating Inflow)
        $entry2 = JournalEntry::create(['transaction_date' => '2026-05-10', 'reference_no' => 'CF-002', 'created_by' => $this->user->id]);
        JournalItem::create(['journal_entry_id' => $entry2->id, 'account_id' => $this->cashAccount->id, 'debit' => 400000.00, 'credit' => 0.00]);
        JournalItem::create(['journal_entry_id' => $entry2->id, 'account_id' => $this->revenueAccount->id, 'debit' => 0.00, 'credit' => 400000.00]);
        $this->cashAccount->increment('balance', 400000.00);

        // Transaction 3: Cash Transfer to Bank (Should be completely ignored)
        $entry3 = JournalEntry::create(['transaction_date' => '2026-05-12', 'reference_no' => 'CF-003', 'created_by' => $this->user->id]);
        JournalItem::create(['journal_entry_id' => $entry3->id, 'account_id' => $this->bankAccount->id, 'debit' => 300000.00, 'credit' => 0.00]);
        JournalItem::create(['journal_entry_id' => $entry3->id, 'account_id' => $this->cashAccount->id, 'debit' => 0.00, 'credit' => 300000.00]);
        $this->bankAccount->increment('balance', 300000.00);
        $this->cashAccount->decrement('balance', 300000.00);

        // Transaction 4: Expense outflow (Operating Outflow)
        $entry4 = JournalEntry::create(['transaction_date' => '2026-05-15', 'reference_no' => 'CF-004', 'created_by' => $this->user->id]);
        JournalItem::create(['journal_entry_id' => $entry4->id, 'account_id' => $this->expenseAccount->id, 'debit' => 100000.00, 'credit' => 0.00]);
        JournalItem::create(['journal_entry_id' => $entry4->id, 'account_id' => $this->cashAccount->id, 'debit' => 0.00, 'credit' => 100000.00]);
        $this->cashAccount->decrement('balance', 100000.00);

        // Fetch Cash Flow Report
        // Total cash accounts balance: Cash (1M) + Bank (300k) = 1.3M
        // Financing Inflow = 1M
        // Operating Inflow = 400k
        // Operating Outflow = 100k
        // Transfer = 0
        // Net Cash Increase = 1M + 400k - 100k = 1.3M
        $response = $this->getJson('/api/reports/cash-flow?start_date=2026-05-01&end_date=2026-05-20');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertEquals(400000.00, $data['operating_activities']['cash_inflow_customers']);
        $this->assertEquals(100000.00, $data['operating_activities']['cash_outflow_expenses']);
        $this->assertEquals(1000000.00, $data['financing_activities']['cash_inflow_owners']);
        $this->assertEquals(1300000.00, $data['summary']['net_cash_increase']);
        $this->assertEquals(0.00, $data['summary']['beginning_cash_balance']);
        $this->assertEquals(1300000.00, $data['summary']['ending_cash_balance']);
    }
}
