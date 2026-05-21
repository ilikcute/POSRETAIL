<?php

namespace App\Repositories\Contracts\Sales;

use App\Repositories\Contracts\BaseRepositoryInterface;

interface ShiftRepositoryInterface extends BaseRepositoryInterface
{
    public function closeShift(int $id, array $data);
}
