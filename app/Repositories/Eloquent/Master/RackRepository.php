<?php

namespace App\Repositories\Eloquent\Master;

use App\Repositories\Eloquent\BaseRepository;

use App\Models\Master\Rack;
use App\Repositories\Contracts\Master\RackRepositoryInterface;

class RackRepository extends BaseRepository implements RackRepositoryInterface
{
    public function __construct(Rack $model)
    {
        parent::__construct($model);
    }
}
