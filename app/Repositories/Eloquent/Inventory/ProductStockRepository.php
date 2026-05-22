<?php

namespace App\Repositories\Eloquent\Inventory;

use App\Exceptions\InventoryException;
use App\Models\Inventory\ProductStock;
use App\Repositories\Contracts\Inventory\ProductStockRepositoryInterface;
use App\Repositories\Eloquent\BaseRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ProductStockRepository extends BaseRepository implements ProductStockRepositoryInterface
{
    public function __construct(ProductStock $model)
    {
        parent::__construct($model);
    }

    public function create(array $attributes): Model
    {
        return DB::transaction(function () use ($attributes) {
            $this->validateStockAttributes($attributes);
            $this->assertUniqueLocation($attributes);

            return parent::create($attributes);
        });
    }

    public function update(int $id, array $attributes): Model
    {
        return DB::transaction(function () use ($id, $attributes) {
            $this->validateStockAttributes($attributes, false);
            $stock = $this->findOrFail($id);
            $this->assertUniqueLocation($attributes, $stock->id);

            $stock->update($attributes);

            return $stock->refresh();
        });
    }

    public function delete(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $stock = $this->findOrFail($id);

            return $stock->delete();
        });
    }

    protected function validateStockAttributes(array $attributes, bool $isCreate = true): void
    {
        if (array_key_exists('qty', $attributes) && $attributes['qty'] < 0) {
            throw new InventoryException('Jumlah stok tidak boleh negatif.');
        }

        if (array_key_exists('min_qty', $attributes) && $attributes['min_qty'] < 0) {
            throw new InventoryException('Minimal stok tidak boleh negatif.');
        }

        if ($isCreate && empty($attributes['product_id'])) {
            throw new InventoryException('Product ID wajib diisi.');
        }

        if ($isCreate && empty($attributes['warehouse_id'])) {
            throw new InventoryException('Warehouse ID wajib diisi.');
        }
    }

    protected function assertUniqueLocation(array $attributes, ?int $exceptId = null): void
    {
        if (! isset($attributes['product_id']) || ! isset($attributes['warehouse_id'])) {
            return;
        }

        $query = $this->model
            ->where('product_id', $attributes['product_id'])
            ->where('warehouse_id', $attributes['warehouse_id']);

        if (array_key_exists('product_variant_id', $attributes)) {
            if ($attributes['product_variant_id'] === null) {
                $query->whereNull('product_variant_id');
            } else {
                $query->where('product_variant_id', $attributes['product_variant_id']);
            }
        }

        if (array_key_exists('rack_id', $attributes)) {
            if ($attributes['rack_id'] === null) {
                $query->whereNull('rack_id');
            } else {
                $query->where('rack_id', $attributes['rack_id']);
            }
        }

        if ($exceptId) {
            $query->where('id', '<>', $exceptId);
        }

        if ($query->exists()) {
            throw new InventoryException('Stok untuk produk, varian, warehouse, dan rak yang sama sudah ada. Gunakan update jika ingin menyesuaikan jumlah.');
        }
    }
}
