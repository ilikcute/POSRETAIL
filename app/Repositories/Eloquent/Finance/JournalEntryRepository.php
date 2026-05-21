<?php

namespace App\Repositories\Eloquent\Finance;

use App\Repositories\Eloquent\BaseRepository;

use App\Models\Finance\JournalEntry;
use App\Models\Finance\Account;
use App\Repositories\Contracts\Finance\JournalEntryRepositoryInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class JournalEntryRepository extends BaseRepository implements JournalEntryRepositoryInterface
{
    public function __construct(JournalEntry $model)
    {
        parent::__construct($model);
    }

    public function create(array $attributes): Model
    {
        return DB::transaction(function () use ($attributes) {
            $items = $attributes['items'];
            unset($attributes['items']);

            // 1. Validasi Balance (Debit = Credit)
            $totalDebit = 0;
            $totalCredit = 0;
            foreach ($items as $item) {
                $totalDebit += $item['debit'];
                $totalCredit += $item['credit'];
            }

            if (abs($totalDebit - $totalCredit) > 0.001) {
                throw new \Exception('Journal entries must balance. Total Debit must equal Total Credit.');
            }

            // 2. Generate JV Ref
            $attributes['reference_no'] = 'JV-' . date('Ym') . '-' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
            $attributes['created_by'] = auth()->id() ?? 1;

            $entry = parent::create($attributes);

            // 3. Simpan Item & Update Saldo Akun (Double Entry)
            foreach ($items as $item) {
                $entry->items()->create($item);

                $account = Account::findOrFail($item['account_id']);
                $debit = $item['debit'];
                $credit = $item['credit'];

                // Update saldo berdasarkan tipe akun
                switch ($account->type) {
                    case 'asset':
                    case 'expense':
                        $account->balance += ($debit - $credit);
                        break;
                    case 'liability':
                    case 'equity':
                    case 'revenue':
                        $account->balance += ($credit - $debit);
                        break;
                }

                $account->save();
            }

            return $entry->load('items.account');
        });
    }
}
