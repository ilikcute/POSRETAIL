<?php

namespace App\Repositories\Eloquent\Master;

use App\Repositories\Eloquent\BaseRepository;

use App\Models\Master\Store;
use App\Repositories\Contracts\Master\StoreRepositoryInterface;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Model;

class StoreRepository extends BaseRepository implements StoreRepositoryInterface
{
    public function __construct(Store $model)
    {
        parent::__construct($model);
    }

    /**
     * Override create untuk handle file upload
     */
    public function create(array $attributes): Model
    {
        if (isset($attributes['logo']) && $attributes['logo'] instanceof \Illuminate\Http\UploadedFile) {
            $attributes['logo_path'] = $attributes['logo']->store('stores/logos', 'public');
            unset($attributes['logo']);
        }

        return parent::create($attributes);
    }

    /**
     * Override update untuk handle penghapusan file lama dan upload file baru
     */
    public function update(int $id, array $attributes): Model
    {
        $store = $this->findOrFail($id);

        if (isset($attributes['logo']) && $attributes['logo'] instanceof \Illuminate\Http\UploadedFile) {
            // Hapus logo lama jika ada
            if ($store->logo_path && Storage::disk('public')->exists($store->logo_path)) {
                Storage::disk('public')->delete($store->logo_path);
            }
            
            $attributes['logo_path'] = $attributes['logo']->store('stores/logos', 'public');
            unset($attributes['logo']);
        }

        return parent::update($id, $attributes);
    }

    /**
     * Override delete untuk menghapus file logo terkait
     */
    public function delete(int $id): bool
    {
        $store = $this->findOrFail($id);
        
        // Hapus logo jika ada
        if ($store->logo_path && Storage::disk('public')->exists($store->logo_path)) {
            Storage::disk('public')->delete($store->logo_path);
        }

        return parent::delete($id);
    }
}
