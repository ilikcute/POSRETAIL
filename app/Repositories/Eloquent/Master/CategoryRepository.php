<?php

namespace App\Repositories\Eloquent\Master;

use App\Repositories\Eloquent\BaseRepository;

use App\Models\Master\Category;
use App\Repositories\Contracts\Master\CategoryRepositoryInterface;

class CategoryRepository extends BaseRepository implements CategoryRepositoryInterface
{
    public function __construct(Category $model)
    {
        parent::__construct($model);
    }
}
