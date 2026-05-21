<?php

namespace App\Repositories\Eloquent\Master;

use App\Models\Master\Category;
use App\Repositories\Contracts\Master\CategoryRepositoryInterface;
use App\Repositories\Eloquent\BaseRepository;

class CategoryRepository extends BaseRepository implements CategoryRepositoryInterface
{
    public function __construct(Category $model)
    {
        parent::__construct($model);
    }
}
