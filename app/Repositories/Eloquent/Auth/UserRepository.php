<?php

namespace App\Repositories\Eloquent\Auth;

use App\Repositories\Eloquent\BaseRepository;

use App\Models\Auth\User;
use App\Repositories\Contracts\Auth\UserRepositoryInterface;

class UserRepository extends BaseRepository implements UserRepositoryInterface
{
    public function __construct(User $model)
    {
        parent::__construct($model);
    }

    public function findByEmail(string $email)
    {
        return $this->model->where('email', $email)->first();
    }
}
