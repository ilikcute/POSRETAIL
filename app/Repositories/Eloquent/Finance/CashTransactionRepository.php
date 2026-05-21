<?php

namespace App\Repositories\Eloquent\Finance;

use App\Repositories\Eloquent\BaseRepository;

use App\Models\Finance\CashTransaction;
use App\Models\Finance\Account;
use App\Models\Finance\JournalEntry;
use App\Repositories\Contracts\Finance\CashTransactionRepositoryInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CashTransactionRepository extends BaseRepository implements CashTransactionRepositoryInterface
{
    public function __construct(CashTransaction $model)
    {
        parent::__construct($model);
    }

    public function create(array $attributes): Model
    {
        return DB::transaction(function () use ($attributes) {
            $attributes['created_by'] = auth()->id() ?? 1;
            $transaction = parent::create($attributes);

            $this->postCashJournalEntry($transaction);

            return $transaction;
        });
    }

    protected function postCashJournalEntry(CashTransaction $transaction)
    {
        $cashAccount = Account::where('code', '1101')->first();
        $bankAccount = Account::where('code', '1102')->first();
        $otherRevenueAccount = Account::where('code', '4201')->first();
        $electricityAccount = Account::where('code', '5201')->first();

        if (!$cashAccount || !$bankAccount || !$otherRevenueAccount || !$electricityAccount) {
            return;
        }

        // Tentukan akun kas utama yang terpengaruh
        $mainAccount = ($transaction->payment_method === 'cash') ? $cashAccount : $bankAccount;

        // Tentukan akun lawan berdasarkan tipe transaksi
        if ($transaction->type === 'in') {
            // UANG MASUK (Penerimaan)
            // Lawan: Pendapatan Lain-lain (Revenue)
            $oppositeAccount = $otherRevenueAccount;
        } else {
            // UANG KELUAR (Pengeluaran)
            // Lawan: Beban Listrik / Beban Operasional Lainnya (Expense)
            $oppositeAccount = $electricityAccount;
        }

        $entry = JournalEntry::create([
            'reference_no' => 'JV-CASH-' . str_pad($transaction->id, 6, '0', STR_PAD_LEFT),
            'transaction_date' => now()->format('Y-m-d'),
            'description' => 'Jurnal Penyesuaian Kas Laci (POS Petty Cash) - Kategori: ' . $transaction->category . ' | Ket: ' . $transaction->description,
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
}
