<?php

namespace App\Repositories\Eloquent\Master;

use App\Repositories\Eloquent\BaseRepository;

use App\Models\Master\Brand;
use App\Repositories\Contracts\Master\BrandRepositoryInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class BrandRepository extends BaseRepository implements BrandRepositoryInterface
{
    public function __construct(Brand $model)
    {
        parent::__construct($model);
    }

    public function create(array $attributes): Model
    {
        if (isset($attributes['logo']) && $attributes['logo'] instanceof \Illuminate\Http\UploadedFile) {
            $attributes['logo_path'] = $attributes['logo']->store('brands/logos', 'public');
            unset($attributes['logo']);
        }

        return parent::create($attributes);
    }

    public function update(int $id, array $attributes): Model
    {
        $brand = $this->findOrFail($id);

        if (isset($attributes['logo']) && $attributes['logo'] instanceof \Illuminate\Http\UploadedFile) {
            if ($brand->logo_path && Storage::disk('public')->exists($brand->logo_path)) {
                Storage::disk('public')->delete($brand->logo_path);
            }
            $attributes['logo_path'] = $attributes['logo']->store('brands/logos', 'public');
            unset($attributes['logo']);
        }

        return parent::update($id, $attributes);
    }

    public function delete(int $id): bool
    {
        $brand = $this->findOrFail($id);
        
        if ($brand->logo_path && Storage::disk('public')->exists($brand->logo_path)) {
            Storage::disk('public')->delete($brand->logo_path);
        }

        return parent::delete($id);
    }
}
