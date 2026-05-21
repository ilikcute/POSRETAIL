<?php

namespace App\Repositories\Eloquent\Master;

use App\Repositories\Eloquent\BaseRepository;

use App\Models\Master\Customer;
use App\Repositories\Contracts\Master\CustomerRepositoryInterface;

class CustomerRepository extends BaseRepository implements CustomerRepositoryInterface
{
    public function __construct(Customer $model)
    {
        parent::__construct($model);
    }
}
