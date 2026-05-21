<?php

namespace App\Repositories\Eloquent\Master;

use App\Repositories\Eloquent\BaseRepository;

use App\Models\Master\Supplier;
use App\Repositories\Contracts\Master\SupplierRepositoryInterface;

class SupplierRepository extends BaseRepository implements SupplierRepositoryInterface
{
    public function __construct(Supplier $model)
    {
        parent::__construct($model);
    }
}
