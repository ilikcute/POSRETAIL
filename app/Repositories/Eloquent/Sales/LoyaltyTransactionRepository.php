<?php

namespace App\Repositories\Eloquent\Sales;

use App\Repositories\Eloquent\BaseRepository;

use App\Models\Sales\LoyaltyTransaction;
use App\Models\Master\Customer;
use App\Repositories\Contracts\Sales\LoyaltyTransactionRepositoryInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class LoyaltyTransactionRepository extends BaseRepository implements LoyaltyTransactionRepositoryInterface
{
    public function __construct(LoyaltyTransaction $model)
    {
        parent::__construct($model);
    }

    public function create(array $attributes): Model
    {
        return DB::transaction(function () use ($attributes) {
            $attributes['created_by'] = auth()->id() ?? 1;
            
            $transaction = parent::create($attributes);

            // Sync ke table Customer
            $customer = Customer::findOrFail($transaction->customer_id);
            $customer->point_balance += $transaction->points;
            
            if ($customer->point_balance < 0) {
                $customer->point_balance = 0; // Poin tidak boleh negatif
            }
            
            $customer->save();

            return $transaction;
        });
    }
}
