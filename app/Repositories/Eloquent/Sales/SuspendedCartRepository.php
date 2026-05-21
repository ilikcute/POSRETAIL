<?php

namespace App\Repositories\Eloquent\Sales;

use App\Repositories\Eloquent\BaseRepository;

use App\Models\Sales\SuspendedCart;
use App\Repositories\Contracts\Sales\SuspendedCartRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class SuspendedCartRepository extends BaseRepository implements SuspendedCartRepositoryInterface
{
    public function __construct(SuspendedCart $model)
    {
        parent::__construct($model);
    }

    public function getPendingCartsWithRelations(): Collection
    {
        return $this->model->where('status', 'pending')
            ->with(['items.product', 'customer', 'station', 'cashier'])
            ->orderBy('id', 'desc')
            ->get();
    }

    public function findPendingByQueueCode(string $queueCode): ?SuspendedCart
    {
        return $this->model->where('queue_code', $queueCode)
            ->where('status', 'pending')
            ->with(['items.product', 'customer', 'station'])
            ->first();
    }
}
