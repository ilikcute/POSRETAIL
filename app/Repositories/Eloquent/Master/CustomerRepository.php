<?php

namespace App\Repositories\Eloquent\Master;

use App\Models\Master\Customer;
use App\Repositories\Contracts\Master\CustomerRepositoryInterface;
use App\Repositories\Eloquent\BaseRepository;

class CustomerRepository extends BaseRepository implements CustomerRepositoryInterface
{
    public function __construct(Customer $model)
    {
        parent::__construct($model);
    }
}
