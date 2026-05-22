<?php

namespace Tests\Feature\Master;

use App\Models\Auth\User;
use App\Models\Setting;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SettingControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Create an active user for Sanctum authentication
        $this->user = User::factory()->create();

        // Run the seeder to populate default settings
        $this->seed(SettingSeeder::class);
    }

    public function test_guest_cannot_access_settings_api(): void
    {
        $this->getJson('/api/settings')->assertStatus(401);
        $this->postJson('/api/settings', [])->assertStatus(401);
        $this->getJson('/api/settings/company_name')->assertStatus(401);
        $this->putJson('/api/settings/company_name', [])->assertStatus(401);
    }

    public function test_authenticated_user_can_get_grouped_settings(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/settings');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure([
                'data' => [
                    'company',
                    'region',
                    'theme',
                    'loyalty',
                    'security',
                    'invoice',
                ],
            ]);
    }

    public function test_user_can_batch_update_settings(): void
    {
        Sanctum::actingAs($this->user);

        $payload = [
            'settings' => [
                'company_name' => 'Updated Retail Corp',
                'company_tax_rate' => 12.5,
                'theme_mode' => 'light',
                'drawer_safety_limit' => 6000000.0,
            ],
        ];

        $response = $this->postJson('/api/settings', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('message', 'Settings updated successfully');

        $this->assertDatabaseHas('settings', [
            'key' => 'company_name',
            'value' => 'Updated Retail Corp',
        ]);

        $this->assertDatabaseHas('settings', [
            'key' => 'company_tax_rate',
            'value' => '12.5',
        ]);

        $this->assertDatabaseHas('settings', [
            'key' => 'theme_mode',
            'value' => 'light',
        ]);

        $this->assertDatabaseHas('settings', [
            'key' => 'drawer_safety_limit',
            'value' => '6000000',
        ]);
    }

    public function test_batch_update_fails_on_validation_errors(): void
    {
        Sanctum::actingAs($this->user);

        // Invalid: email format incorrect, tax rate > 100
        $payload = [
            'settings' => [
                'company_email' => 'not-an-email',
                'company_tax_rate' => 105,
            ],
        ];

        $response = $this->postJson('/api/settings', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'settings.company_email',
                'settings.company_tax_rate',
            ]);
    }

    public function test_batch_update_rolls_back_entire_transaction_on_failure(): void
    {
        Sanctum::actingAs($this->user);

        $originalName = Setting::where('key', 'company_name')->first()?->value;

        // Try to update one valid key and one non-existent key
        $payload = [
            'settings' => [
                'company_name' => 'Should Not Save This Name',
                'invalid_key_name' => 'Some Value',
            ],
        ];

        $response = $this->postJson('/api/settings', $payload);

        $response->assertStatus(422);

        // Verify company name was rolled back and is still the original name
        $this->assertDatabaseHas('settings', [
            'key' => 'company_name',
            'value' => $originalName,
        ]);
    }

    public function test_user_can_retrieve_single_setting(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/settings/company_name');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.key', 'company_name')
            ->assertJsonPath('data.value', 'POS Retail Store');
    }

    public function test_user_can_update_single_setting(): void
    {
        Sanctum::actingAs($this->user);

        // Test update of an integer value
        $payload = [
            'value' => 12,
        ];

        $response = $this->putJson('/api/settings/password_min_length', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('settings', [
            'key' => 'password_min_length',
            'value' => '12',
        ]);
    }

    public function test_single_setting_update_fails_if_key_not_found(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->putJson('/api/settings/non_existent_key_xyz', [
            'value' => 'value',
        ]);

        $response->assertStatus(404);
    }
}
