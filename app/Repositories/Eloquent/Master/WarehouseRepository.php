<?php

namespace App\Repositories\Eloquent\Master;

use App\Repositories\Eloquent\BaseRepository;

use App\Models\Master\Warehouse;
use App\Repositories\Contracts\Master\WarehouseRepositoryInterface;
use Illuminate\Database\Eloquent\Model;

class WarehouseRepository extends BaseRepository implements WarehouseRepositoryInterface
{
    public function __construct(Warehouse $model)
    {
        parent::__construct($model);
    }

    /**
     * Override create untuk memastikan hanya ada 1 main warehouse
     */
    public function create(array $attributes): Model
    {
        if (isset($attributes['is_main']) && $attributes['is_main'] == true) {
            // Nonaktifkan is_main di gudang lain
            $this->model->where('is_main', true)->update(['is_main' => false]);
        }

        return parent::create($attributes);
    }

    /**
     * Override update untuk memastikan hanya ada 1 main warehouse
     */
    public function update(int $id, array $attributes): Model
    {
        if (isset($attributes['is_main']) && $attributes['is_main'] == true) {
            // Nonaktifkan is_main di gudang lain (selain yang sedang diupdate)
            $this->model->where('id', '!=', $id)->where('is_main', true)->update(['is_main' => false]);
        }

        return parent::update($id, $attributes);
    }
}
