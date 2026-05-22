<?php

namespace App\Repositories\Contracts\Master;

use App\Exceptions\SettingException;
use App\Repositories\Contracts\BaseRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

interface SettingRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Get all settings grouped by their group name.
     *
     * @return array<string, Collection>
     */
    public function allGrouped(): array;

    /**
     * Update multiple settings in a single transaction.
     *
     * @param  array<string, mixed>  $settings  Key-value pairs of settings to update
     *
     * @throws SettingException
     */
    public function updateBatch(array $settings): bool;

    /**
     * Retrieve a setting value by its key.
     *
     * @param  mixed  $default  Default value if the key does not exist
     */
    public function getByKey(string $key, mixed $default = null): mixed;

    /**
     * Update or set a setting value by its key.
     */
    public function setByKey(string $key, mixed $value): bool;
}
