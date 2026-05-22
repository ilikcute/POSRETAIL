<?php

namespace App\Repositories\Eloquent\Finance;

use App\Exceptions\JournalEntryException;
use App\Models\Finance\Account;
use App\Models\Finance\JournalEntry;
use App\Repositories\Contracts\Finance\JournalEntryRepositoryInterface;
use App\Repositories\Eloquent\BaseRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class JournalEntryRepository extends BaseRepository implements JournalEntryRepositoryInterface
{
    public function __construct(JournalEntry $model)
    {
        parent::__construct($model);
    }

    /**
     * Helper to adjust account balance based on double-entry principles.
     */
    protected function adjustAccountBalance(Account $account, float $debit, float $credit, bool $reverse = false): void
    {
        $netChange = $debit - $credit;
        if ($reverse) {
            $netChange = -$netChange;
        }

        switch ($account->type) {
            case 'asset':
            case 'expense':
                $account->balance += $netChange;
                break;
            case 'liability':
            case 'equity':
            case 'revenue':
                $account->balance -= $netChange;
                break;
        }
        $account->save();
    }

    /**
     * Create a new Journal Entry.
     */
    public function create(array $attributes): Model
    {
        return DB::transaction(function () use ($attributes) {
            $items = $attributes['items'] ?? [];
            unset($attributes['items']);

            if (count($items) < 2) {
                throw new JournalEntryException('Jurnal entry harus memiliki minimal 2 baris (double-entry).');
            }

            // 1. Validasi Balance (Debit = Credit)
            $totalDebit = 0.0;
            $totalCredit = 0.0;

            foreach ($items as $item) {
                $debit = (float) ($item['debit'] ?? 0.0);
                $credit = (float) ($item['credit'] ?? 0.0);

                if ($debit < 0 || $credit < 0) {
                    throw new JournalEntryException('Nilai debit atau credit tidak boleh negatif.');
                }

                if ($debit > 0 && $credit > 0) {
                    throw new JournalEntryException('Satu baris jurnal tidak boleh mengisi Debit dan Credit secara bersamaan.');
                }

                if ($debit == 0 && $credit == 0) {
                    throw new JournalEntryException('Setiap baris jurnal harus memiliki nilai Debit atau Credit yang lebih besar dari nol.');
                }

                $totalDebit += $debit;
                $totalCredit += $credit;
            }

            if (abs($totalDebit - $totalCredit) > 0.01) {
                throw new JournalEntryException('Total Debit (Rp '.number_format($totalDebit, 2).') harus sama dengan Total Credit (Rp '.number_format($totalCredit, 2).'). Selisih: Rp '.number_format(abs($totalDebit - $totalCredit), 2));
            }

            // 2. Lock & Validasi Akun
            $accountIds = collect($items)->pluck('account_id')->unique()->toArray();
            $accounts = Account::whereIn('id', $accountIds)->lockForUpdate()->get()->keyBy('id');

            foreach ($items as $item) {
                $account = $accounts->get($item['account_id']);
                if (! $account) {
                    throw new JournalEntryException("Akun dengan ID {$item['account_id']} tidak ditemukan.");
                }
                if (! $account->is_active) {
                    throw new JournalEntryException("Akun '{$account->name}' ({$account->code}) tidak aktif dan tidak dapat digunakan untuk transaksi.");
                }
            }

            // 3. Generate JV Ref & Simpan Journal Entry
            $attributes['reference_no'] = 'JV-'.date('Ym').'-'.str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
            $attributes['created_by'] = auth()->id() ?? 1;

            $entry = parent::create($attributes);

            // 4. Simpan Item & Update Saldo Akun
            foreach ($items as $item) {
                $entry->items()->create([
                    'account_id' => $item['account_id'],
                    'debit' => $item['debit'],
                    'credit' => $item['credit'],
                ]);

                $account = $accounts->get($item['account_id']);
                $this->adjustAccountBalance($account, (float) $item['debit'], (float) $item['credit'], false);
            }

            return $entry->load('items.account');
        });
    }

    /**
     * Update an existing Journal Entry.
     */
    public function update(int $id, array $attributes): Model
    {
        return DB::transaction(function () use ($id, $attributes) {
            $entry = JournalEntry::where('id', $id)->lockForUpdate()->firstOrFail();

            // Security: Prevent editing automated system-generated journal entries
            if (strpos($entry->reference_no, 'JV-') !== 0) {
                throw new JournalEntryException("Jurnal otomatis sistem ({$entry->reference_no}) tidak dapat diubah secara manual.");
            }

            if (isset($attributes['items'])) {
                $newItems = $attributes['items'];
                unset($attributes['items']);

                if (count($newItems) < 2) {
                    throw new JournalEntryException('Jurnal entry harus memiliki minimal 2 baris (double-entry).');
                }

                // 1. Validasi Balance Baris Baru
                $totalDebit = 0.0;
                $totalCredit = 0.0;

                foreach ($newItems as $item) {
                    $debit = (float) ($item['debit'] ?? 0.0);
                    $credit = (float) ($item['credit'] ?? 0.0);

                    if ($debit < 0 || $credit < 0) {
                        throw new JournalEntryException('Nilai debit atau credit tidak boleh negatif.');
                    }

                    if ($debit > 0 && $credit > 0) {
                        throw new JournalEntryException('Satu baris jurnal tidak boleh mengisi Debit dan Credit secara bersamaan.');
                    }

                    if ($debit == 0 && $credit == 0) {
                        throw new JournalEntryException('Setiap baris jurnal harus memiliki nilai Debit atau Credit yang lebih besar dari nol.');
                    }

                    $totalDebit += $debit;
                    $totalCredit += $credit;
                }

                if (abs($totalDebit - $totalCredit) > 0.01) {
                    throw new JournalEntryException('Total Debit (Rp '.number_format($totalDebit, 2).') harus sama dengan Total Credit (Rp '.number_format($totalCredit, 2).'). Selisih: Rp '.number_format(abs($totalDebit - $totalCredit), 2));
                }

                // 2. Lock & Validasi Akun Baru dan Lama
                $oldItems = $entry->items()->lockForUpdate()->get();
                $allAccountIds = collect($oldItems)->pluck('account_id')
                    ->concat(collect($newItems)->pluck('account_id'))
                    ->unique()
                    ->toArray();

                $accounts = Account::whereIn('id', $allAccountIds)->lockForUpdate()->get()->keyBy('id');

                foreach ($newItems as $item) {
                    $account = $accounts->get($item['account_id']);
                    if (! $account) {
                        throw new JournalEntryException("Akun dengan ID {$item['account_id']} tidak ditemukan.");
                    }
                    if (! $account->is_active) {
                        throw new JournalEntryException("Akun '{$account->name}' ({$account->code}) tidak aktif dan tidak dapat digunakan untuk transaksi.");
                    }
                }

                // 3. Revers saldo dari baris lama
                foreach ($oldItems as $oldItem) {
                    $account = $accounts->get($oldItem->account_id);
                    if ($account) {
                        $this->adjustAccountBalance($account, (float) $oldItem->debit, (float) $oldItem->credit, true);
                    }
                }

                // Hapus baris lama
                $entry->items()->delete();

                // 4. Simpan baris baru & update saldo baru
                foreach ($newItems as $newItem) {
                    $entry->items()->create([
                        'account_id' => $newItem['account_id'],
                        'debit' => $newItem['debit'],
                        'credit' => $newItem['credit'],
                    ]);

                    $account = $accounts->get($newItem['account_id']);
                    $this->adjustAccountBalance($account, (float) $newItem['debit'], (float) $newItem['credit'], false);
                }
            }

            // Update basic attributes
            $entry->update($attributes);

            return $entry->load('items.account');
        });
    }

    /**
     * Delete an existing Journal Entry (with ledger balance reversal).
     */
    public function delete(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $entry = JournalEntry::where('id', $id)->lockForUpdate()->firstOrFail();

            // Security: Prevent deleting automated system-generated journal entries
            if (strpos($entry->reference_no, 'JV-') !== 0) {
                throw new JournalEntryException("Jurnal otomatis sistem ({$entry->reference_no}) tidak dapat dihapus secara manual.");
            }

            // Lock items & involved accounts
            $items = $entry->items()->lockForUpdate()->get();
            $accountIds = $items->pluck('account_id')->unique()->toArray();
            $accounts = Account::whereIn('id', $accountIds)->lockForUpdate()->get()->keyBy('id');

            // Reverse balances
            foreach ($items as $item) {
                $account = $accounts->get($item->account_id);
                if ($account) {
                    $this->adjustAccountBalance($account, (float) $item->debit, (float) $item->credit, true);
                }
            }

            // Delete items and entry
            $entry->items()->delete();

            return (bool) $entry->delete();
        });
    }
}
