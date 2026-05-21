<?php

namespace App\Repositories\Eloquent\Master;

use App\Models\Master\ProductVariant;
use App\Repositories\Contracts\Master\ProductVariantRepositoryInterface;
use App\Repositories\Eloquent\BaseRepository;

class ProductVariantRepository extends BaseRepository implements ProductVariantRepositoryInterface
{
    public function __construct(ProductVariant $model)
    {
        parent::__construct($model);
    }
}
