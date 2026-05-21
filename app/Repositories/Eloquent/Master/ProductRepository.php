<?php

namespace App\Repositories\Eloquent\Master;

use App\Models\Master\Product;
use App\Repositories\Contracts\Master\ProductRepositoryInterface;
use App\Repositories\Eloquent\BaseRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ProductRepository extends BaseRepository implements ProductRepositoryInterface
{
    public function __construct(Product $model)
    {
        parent::__construct($model);
    }

    public function create(array $attributes): Model
    {
        if (isset($attributes['image']) && $attributes['image'] instanceof UploadedFile) {
            $attributes['image_path'] = $attributes['image']->store('products/images', 'public');
            unset($attributes['image']);
        }

        return parent::create($attributes);
    }

    public function update(int $id, array $attributes): Model
    {
        $product = $this->findOrFail($id);

        if (isset($attributes['image']) && $attributes['image'] instanceof UploadedFile) {
            if ($product->image_path && Storage::disk('public')->exists($product->image_path)) {
                Storage::disk('public')->delete($product->image_path);
            }
            $attributes['image_path'] = $attributes['image']->store('products/images', 'public');
            unset($attributes['image']);
        }

        return parent::update($id, $attributes);
    }

    public function delete(int $id): bool
    {
        $product = $this->findOrFail($id);

        if ($product->image_path && Storage::disk('public')->exists($product->image_path)) {
            Storage::disk('public')->delete($product->image_path);
        }

        return parent::delete($id);
    }
}
