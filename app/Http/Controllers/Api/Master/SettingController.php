<?php

namespace App\Http\Controllers\Api\Master;

use App\Exceptions\SettingException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Master\UpdateSettingsRequest;
use App\Models\Setting;
use App\Repositories\Contracts\Master\SettingRepositoryInterface;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    use ApiResponseTrait;

    protected SettingRepositoryInterface $settingRepository;

    /**
     * Create a new controller instance.
     */
    public function __construct(SettingRepositoryInterface $settingRepository)
    {
        $this->settingRepository = $settingRepository;
    }

    /**
     * Display a listing of all settings grouped by group name.
     */
    public function index(): JsonResponse
    {
        $grouped = $this->settingRepository->allGrouped();

        return $this->successResponse($grouped, 'Settings retrieved successfully');
    }

    /**
     * Update multiple settings in a batch transaction.
     */
    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        try {
            $this->settingRepository->updateBatch($request->input('settings'));

            return $this->successResponse(null, 'Settings updated successfully');
        } catch (SettingException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->contextData());
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update settings: '.$e->getMessage(), 500);
        }
    }

    /**
     * Display a single setting.
     */
    public function show(string $key): JsonResponse
    {
        /** @var Setting|null $setting */
        $setting = Setting::where('key', $key)->first();
        if (! $setting) {
            return $this->errorResponse("Setting key '{$key}' not found", 404);
        }

        return $this->successResponse($setting, 'Setting retrieved successfully');
    }

    /**
     * Update a single setting.
     */
    public function updateSingle(Request $request, string $key): JsonResponse
    {
        // Simple manual validation to ensure setting key exists first
        /** @var Setting|null $setting */
        $setting = Setting::where('key', $key)->first();
        if (! $setting) {
            return $this->errorResponse("Setting key '{$key}' not found", 404);
        }

        // Validate value based on type
        $rules = [
            'value' => 'present',
        ];

        if ($setting->type === 'integer') {
            $rules['value'] = 'present|integer';
        } elseif ($setting->type === 'double') {
            $rules['value'] = 'present|numeric';
        } elseif ($setting->type === 'boolean') {
            $rules['value'] = 'present|boolean';
        }

        $validated = $request->validate($rules);

        try {
            $this->settingRepository->setByKey($key, $validated['value']);

            return $this->successResponse(null, 'Setting updated successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update setting: '.$e->getMessage(), 500);
        }
    }
}
