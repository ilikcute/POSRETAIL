<?php

namespace App\Repositories\Eloquent\Finance;

use App\Models\Finance\Account;
use App\Repositories\Contracts\Finance\AccountRepositoryInterface;
use App\Repositories\Eloquent\BaseRepository;

class AccountRepository extends BaseRepository implements AccountRepositoryInterface
{
    public function __construct(Account $model)
    {
        parent::__construct($model);
    }
}
