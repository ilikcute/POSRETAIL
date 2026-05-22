<?php

namespace App\Repositories\Contracts\Finance;

use App\Repositories\Contracts\BaseRepositoryInterface;

interface MonthEndRepositoryInterface extends BaseRepositoryInterface
{
    public function preview(array $attributes): array;
}
