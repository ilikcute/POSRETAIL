<?php

namespace App\Repositories\Eloquent\Master;

use App\Models\Master\Unit;
use App\Repositories\Contracts\Master\UnitRepositoryInterface;
use App\Repositories\Eloquent\BaseRepository;

class UnitRepository extends BaseRepository implements UnitRepositoryInterface
{
    public function __construct(Unit $model)
    {
        parent::__construct($model);
    }
}
