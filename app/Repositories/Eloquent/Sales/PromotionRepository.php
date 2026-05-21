<?php

namespace App\Repositories\Eloquent\Sales;

use App\Models\Sales\Promotion;
use App\Repositories\Contracts\Sales\PromotionRepositoryInterface;
use App\Repositories\Eloquent\BaseRepository;

class PromotionRepository extends BaseRepository implements PromotionRepositoryInterface
{
    public function __construct(Promotion $model)
    {
        parent::__construct($model);
    }
}
