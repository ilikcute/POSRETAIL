<?php

namespace App\Repositories\Eloquent\Master;

use App\Repositories\Eloquent\BaseRepository;

use App\Models\Master\ProductVariant;
use App\Repositories\Contracts\Master\ProductVariantRepositoryInterface;

class ProductVariantRepository extends BaseRepository implements ProductVariantRepositoryInterface
{
    public function __construct(ProductVariant $model)
    {
        parent::__construct($model);
    }
}
