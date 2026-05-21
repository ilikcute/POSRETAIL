<?php

namespace App\Repositories\Eloquent\Master;

use App\Models\Master\Station;
use App\Repositories\Contracts\Master\StationRepositoryInterface;
use App\Repositories\Eloquent\BaseRepository;

class StationRepository extends BaseRepository implements StationRepositoryInterface
{
    public function __construct(Station $model)
    {
        parent::__construct($model);
    }
}
