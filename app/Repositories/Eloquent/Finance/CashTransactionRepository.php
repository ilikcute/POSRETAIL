<?php

namespace App\Repositories\Eloquent\Finance;

use App\Exceptions\CashTransactionException;
use App\Models\Finance\Account;
use App\Models\Finance\CashTransaction;
use App\Models\Finance\JournalEntry;
use App\Models\Sales\Shift;
use App\Repositories\Contracts\Finance\CashTransactionRepositoryInterface;
use App\Repositories\Eloquent\BaseRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CashTransactionRepository extends BaseRepository implements CashTransactionRepositoryInterface
{
    public function __construct(CashTransaction $model)
    {
        parent::__construct($model);
    }

    /**
     * Create a new cash transaction and post the double-entry journal entry.
     *
     * @throws CashTransactionException
     */
    public function create(array $attributes): Model
    {
        return DB::transaction(function () use ($attributes) {
            // Check shift status if shift_id is provided
            if (! empty($attributes['shift_id'])) {
                $shift = Shift::find($attributes['shift_id']);
                if ($shift && $shift->status === 'closed') {
                    throw new CashTransactionException(
                        'Tidak dapat membuat transaksi kas pada shift yang sudah ditutup.',
                        ['shift_id' => $attributes['shift_id']]
                    );
                }
            }

            $type = $attributes['type'];
            $amount = $attributes['amount'];
            $paymentMethod = $attributes['payment_method'] ?? 'cash';

            // Lock accounts for update to prevent race conditions on balance updates
            $cashAccount = Account::where('code', '1101')->lockForUpdate()->first();
            $bankAccount = Account::where('code', '1102')->lockForUpdate()->first();

            $isSetorTengah = ($attributes['category'] ?? '') === 'setor_tengah';
            $otherRevenueAccount = null;
            $electricityAccount = null;

            if (! $isSetorTengah) {
                $otherRevenueAccount = Account::where('code', '4201')->lockForUpdate()->first();
                $electricityAccount = Account::where('code', '5201')->lockForUpdate()->first();
            }

            if (! $cashAccount || ! $bankAccount || (! $isSetorTengah && (! $otherRevenueAccount || ! $electricityAccount))) {
                $missingCodes = [];
                if (! $cashAccount) {
                    $missingCodes[] = '1101';
                }
                if (! $bankAccount) {
                    $missingCodes[] = '1102';
                }
                if (! $isSetorTengah) {
                    if (! $otherRevenueAccount) {
                        $missingCodes[] = '4201';
                    }
                    if (! $electricityAccount) {
                        $missingCodes[] = '5201';
                    }
                }
                throw new CashTransactionException(
                    'Akun keuangan sistem belum lengkap ('.implode(', ', $missingCodes).'). Silakan hubungi administrator.',
                    ['missing_accounts' => true]
                );
            }

            $mainAccount = ($paymentMethod === 'cash') ? $cashAccount : $bankAccount;

            // Verify if there is enough balance for cash-out transaction
            if ($type === 'out' && $mainAccount->balance < $amount) {
                throw new CashTransactionException(
                    "Saldo {$mainAccount->name} tidak mencukupi untuk melakukan transaksi keluar ini.",
                    [
                        'account_name' => $mainAccount->name,
                        'available_balance' => (float) $mainAccount->balance,
                        'required_amount' => (float) $amount,
                    ]
                );
            }

            $attributes['created_by'] = auth()->id() ?? 1;
            $transaction = parent::create($attributes);

            if (! $isSetorTengah) {
                $this->postCashJournalEntry($transaction, $cashAccount, $bankAccount, $otherRevenueAccount, $electricityAccount);
            }

            return $transaction;
        });
    }

    /**
     * Update an existing cash transaction and adjust journal entries.
     *
     * @throws CashTransactionException
     */
    public function update(int $id, array $attributes): Model
    {
        return DB::transaction(function () use ($id, $attributes) {
            $transaction = $this->findOrFail($id);

            // Revert old journal entry effects first
            $isSetorTengahBefore = $transaction->category === 'setor_tengah';
            if (! $isSetorTengahBefore) {
                $this->reverseCashJournalEntry($transaction);
            }

            // If shift_id is updated, check new shift status
            $newShiftId = $attributes['shift_id'] ?? $transaction->shift_id;
            if ($newShiftId) {
                $shift = Shift::find($newShiftId);
                if ($shift && $shift->status === 'closed') {
                    throw new CashTransactionException(
                        'Tidak dapat mengubah transaksi kas pada shift yang sudah ditutup.',
                        ['shift_id' => $newShiftId]
                    );
                }
            }

            // Update the record
            $transaction->update($attributes);
            $transaction = $transaction->refresh();

            // Load and lock accounts to post the updated journal entry
            $cashAccount = Account::where('code', '1101')->lockForUpdate()->first();
            $bankAccount = Account::where('code', '1102')->lockForUpdate()->first();

            $isSetorTengah = $transaction->category === 'setor_tengah';
            $otherRevenueAccount = null;
            $electricityAccount = null;

            if (! $isSetorTengah) {
                $otherRevenueAccount = Account::where('code', '4201')->lockForUpdate()->first();
                $electricityAccount = Account::where('code', '5201')->lockForUpdate()->first();
            }

            if (! $cashAccount || ! $bankAccount || (! $isSetorTengah && (! $otherRevenueAccount || ! $electricityAccount))) {
                throw new CashTransactionException(
                    'Akun keuangan sistem belum lengkap. Silakan hubungi administrator.',
                    ['missing_accounts' => true]
                );
            }

            $mainAccount = ($transaction->payment_method === 'cash') ? $cashAccount : $bankAccount;

            // Verify if there is enough balance for cash-out transaction after update
            if ($transaction->type === 'out' && $mainAccount->balance < $transaction->amount) {
                throw new CashTransactionException(
                    "Saldo {$mainAccount->name} tidak mencukupi setelah update transaksi keluar.",
                    [
                        'account_name' => $mainAccount->name,
                        'available_balance' => (float) $mainAccount->balance,
                        'required_amount' => (float) $transaction->amount,
                    ]
                );
            }

            // Post updated journal entry
            if (! $isSetorTengah) {
                $this->postCashJournalEntry($transaction, $cashAccount, $bankAccount, $otherRevenueAccount, $electricityAccount);
            }

            return $transaction;
        });
    }

    /**
     * Delete an existing cash transaction and its journal entries.
     */
    public function delete(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $transaction = $this->findOrFail($id);

            // Revert journal entry and account balances first
            if ($transaction->category !== 'setor_tengah') {
                $this->reverseCashJournalEntry($transaction);
            }

            // Delete the cash transaction
            return $transaction->delete();
        });
    }

    /**
     * Post double-entry journal entry for a cash transaction.
     */
    protected function postCashJournalEntry(
        CashTransaction $transaction,
        Account $cashAccount,
        Account $bankAccount,
        Account $otherRevenueAccount,
        Account $electricityAccount
    ): void {
        $mainAccount = ($transaction->payment_method === 'cash') ? $cashAccount : $bankAccount;
        $oppositeAccount = ($transaction->type === 'in') ? $otherRevenueAccount : $electricityAccount;

        $entry = JournalEntry::create([
            'reference_no' => 'JV-CASH-'.str_pad($transaction->id, 6, '0', STR_PAD_LEFT),
            'transaction_date' => now()->format('Y-m-d'),
            'description' => 'Jurnal Penyesuaian Kas Laci (POS Petty Cash) - Kategori: '.$transaction->category.' | Ket: '.$transaction->description,
            'created_by' => auth()->id() ?? 1,
        ]);

        if ($transaction->type === 'in') {
            // DEBET: Kas/Bank (Aset bertambah)
            $entry->items()->create([
                'account_id' => $mainAccount->id,
                'debit' => $transaction->amount,
                'credit' => 0,
            ]);
            $mainAccount->balance += $transaction->amount;
            $mainAccount->save();

            // KREDIT: Pendapatan Lain-lain (Revenue bertambah)
            $entry->items()->create([
                'account_id' => $oppositeAccount->id,
                'debit' => 0,
                'credit' => $transaction->amount,
            ]);
            $oppositeAccount->balance += $transaction->amount;
            $oppositeAccount->save();
        } else {
            // DEBET: Beban Listrik/Operasional (Beban bertambah)
            $entry->items()->create([
                'account_id' => $oppositeAccount->id,
                'debit' => $transaction->amount,
                'credit' => 0,
            ]);
            $oppositeAccount->balance += $transaction->amount;
            $oppositeAccount->save();

            // KREDIT: Kas/Bank (Aset berkurang)
            $entry->items()->create([
                'account_id' => $mainAccount->id,
                'debit' => 0,
                'credit' => $transaction->amount,
            ]);
            $mainAccount->balance -= $transaction->amount;
            $mainAccount->save();
        }
    }

    /**
     * Reverse a posted journal entry and restore account balances.
     */
    protected function reverseCashJournalEntry(CashTransaction $transaction): void
    {
        $referenceNo = 'JV-CASH-'.str_pad($transaction->id, 6, '0', STR_PAD_LEFT);
        $entry = JournalEntry::where('reference_no', $referenceNo)->first();

        if ($entry) {
            $cashAccount = Account::where('code', '1101')->lockForUpdate()->first();
            $bankAccount = Account::where('code', '1102')->lockForUpdate()->first();
            $otherRevenueAccount = Account::where('code', '4201')->lockForUpdate()->first();
            $electricityAccount = Account::where('code', '5201')->lockForUpdate()->first();

            if ($cashAccount && $bankAccount && $otherRevenueAccount && $electricityAccount) {
                $mainAccount = ($transaction->payment_method === 'cash') ? $cashAccount : $bankAccount;
                $oppositeAccount = ($transaction->type === 'in') ? $otherRevenueAccount : $electricityAccount;

                if ($transaction->type === 'in') {
                    // Revert DEBET (subtract balance)
                    $mainAccount->balance -= $transaction->amount;
                    $mainAccount->save();

                    // Revert KREDIT (subtract balance)
                    $oppositeAccount->balance -= $transaction->amount;
                    $oppositeAccount->save();
                } else {
                    // Revert DEBET (subtract balance)
                    $oppositeAccount->balance -= $transaction->amount;
                    $oppositeAccount->save();

                    // Revert KREDIT (add balance back)
                    $mainAccount->balance += $transaction->amount;
                    $mainAccount->save();
                }
            }

            // cascade delete deletes items
            $entry->delete();
        }
    }
}
