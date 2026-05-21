<?php

namespace App\Repositories\Contracts\Sales;

use App\Models\Sales\SuspendedCart;
use App\Repositories\Contracts\BaseRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

interface SuspendedCartRepositoryInterface extends BaseRepositoryInterface
{
    public function getPendingCartsWithRelations(): Collection;

    public function findPendingByQueueCode(string $queueCode): ?SuspendedCart;
}
