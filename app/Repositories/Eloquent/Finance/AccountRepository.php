<?php

namespace App\Repositories\Eloquent\Finance;

use App\Repositories\Eloquent\BaseRepository;

use App\Models\Finance\Account;
use App\Repositories\Contracts\Finance\AccountRepositoryInterface;

class AccountRepository extends BaseRepository implements AccountRepositoryInterface
{
    public function __construct(Account $model)
    {
        parent::__construct($model);
    }
}
