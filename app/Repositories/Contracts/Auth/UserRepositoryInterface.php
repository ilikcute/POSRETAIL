<?php

namespace App\Repositories\Contracts\Auth;

use App\Repositories\Contracts\BaseRepositoryInterface;

interface UserRepositoryInterface extends BaseRepositoryInterface
{
    public function findByEmail(string $email);
}
