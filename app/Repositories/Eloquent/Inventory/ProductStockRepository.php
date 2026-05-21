<?php

namespace App\Repositories\Eloquent\Inventory;

use App\Models\Inventory\ProductStock;
use App\Repositories\Contracts\Inventory\ProductStockRepositoryInterface;
use App\Repositories\Eloquent\BaseRepository;

class ProductStockRepository extends BaseRepository implements ProductStockRepositoryInterface
{
    public function __construct(ProductStock $model)
    {
        parent::__construct($model);
    }
}
