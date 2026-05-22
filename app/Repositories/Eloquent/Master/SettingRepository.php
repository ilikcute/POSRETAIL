<?php

namespace App\Repositories\Eloquent\Master;

use App\Exceptions\SettingException;
use App\Models\Setting;
use App\Repositories\Contracts\Master\SettingRepositoryInterface;
use App\Repositories\Eloquent\BaseRepository;
use Illuminate\Support\Facades\DB;

class SettingRepository extends BaseRepository implements SettingRepositoryInterface
{
    /**
     * Create a new repository instance.
     */
    public function __construct(Setting $model)
    {
        parent::__construct($model);
    }

    /**
     * Get all settings grouped by their group name.
     */
    public function allGrouped(): array
    {
        return $this->model->all()->groupBy('group')->all();
    }

    /**
     * Update multiple settings in a single transaction.
     */
    public function updateBatch(array $settings): bool
    {
        return DB::transaction(function () use ($settings): bool {
            foreach ($settings as $key => $value) {
                /** @var Setting|null $setting */
                $setting = $this->model->where('key', $key)->first();
                if (! $setting) {
                    throw new SettingException("Setting key '{$key}' does not exist.", ['key' => $key]);
                }

                // Add explicit business checks
                if ($key === 'company_email' && $value !== null && ! filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    throw new SettingException('Invalid email format for company_email.', ['key' => $key, 'value' => $value]);
                }

                if ($key === 'password_min_length' && $value !== null && ((int) $value) < 4) {
                    throw new SettingException('Password minimum length must be at least 4.', ['key' => $key, 'value' => $value]);
                }

                $setting->value = $value;
                $setting->save();
            }

            return true;
        });
    }

    /**
     * Retrieve a setting value by its key.
     */
    public function getByKey(string $key, mixed $default = null): mixed
    {
        /** @var Setting|null $setting */
        $setting = $this->model->where('key', $key)->first();

        return $setting ? $setting->value : $default;
    }

    /**
     * Update or set a setting value by its key.
     */
    public function setByKey(string $key, mixed $value): bool
    {
        /** @var Setting|null $setting */
        $setting = $this->model->where('key', $key)->first();
        if ($setting) {
            $setting->value = $value;

            return $setting->save();
        }

        return false;
    }
}
