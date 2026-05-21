<?php

namespace App\Repositories\Eloquent\Master;

use App\Models\Master\Rack;
use App\Repositories\Contracts\Master\RackRepositoryInterface;
use App\Repositories\Eloquent\BaseRepository;

class RackRepository extends BaseRepository implements RackRepositoryInterface
{
    public function __construct(Rack $model)
    {
        parent::__construct($model);
    }
}
