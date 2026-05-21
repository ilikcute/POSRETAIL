<?php

namespace App\Repositories\Eloquent\Master;

use App\Repositories\Eloquent\BaseRepository;

use App\Models\Master\Unit;
use App\Repositories\Contracts\Master\UnitRepositoryInterface;

class UnitRepository extends BaseRepository implements UnitRepositoryInterface
{
    public function __construct(Unit $model)
    {
        parent::__construct($model);
    }
}
