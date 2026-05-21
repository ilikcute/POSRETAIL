<?php

namespace Tests\Feature\Master;

use App\Models\Auth\User;
use App\Models\Master\Brand;
use App\Models\Master\Category;
use App\Models\Master\Product;
use App\Models\Master\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Category $category;

    protected Brand $brand;

    protected Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->category = Category::factory()->create();
        $this->brand = Brand::factory()->create();
        $this->unit = Unit::factory()->create();
    }

    public function test_guest_cannot_access_products_api(): void
    {
        $this->getJson('/api/products')->assertStatus(401);
        $this->postJson('/api/products', [])->assertStatus(401);
        $this->getJson('/api/products/1')->assertStatus(401);
        $this->putJson('/api/products/1', [])->assertStatus(401);
        $this->deleteJson('/api/products/1')->assertStatus(401);
    }

    public function test_authenticated_user_can_get_products_list(): void
    {
        Sanctum::actingAs($this->user);

        Product::factory()->count(4)->create([
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'unit_id' => $this->unit->id,
        ]);

        $response = $this->getJson('/api/products');

        $response->assertStatus(200)
            ->assertJsonCount(4, 'data');
    }

    public function test_user_can_create_product_with_valid_data(): void
    {
        Sanctum::actingAs($this->user);

        $payload = [
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'unit_id' => $this->unit->id,
            'code' => 'PRD-ALPHA-01',
            'name' => 'Indomie Goreng Original',
            'sku' => 'SKU-ALPHA-01',
            'barcode' => '8992388123456',
            'cost_price' => 2500,
            'price' => 3500,
            'wholesale_price' => 3200,
            'description' => 'Classic fried noodle Indonesian product.',
            'safety_stock' => 10,
            'reorder_point' => 5,
            'lead_time' => 3,
            'purchase_type' => 'outright',
            'consignment_commission_fee' => 0,
            'min_margin_percentage' => 15,
            'is_taxable' => true,
            'is_consignment' => false,
            'is_active' => true,
        ];

        $response = $this->postJson('/api/products', $payload);

        $response->assertStatus(201);

        $this->assertDatabaseHas('products', [
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'code' => 'PRD-ALPHA-01',
            'name' => 'Indomie Goreng Original',
            'sku' => 'SKU-ALPHA-01',
            'barcode' => '8992388123456',
            'is_active' => 1,
            'purchase_type' => 'outright',
        ]);
    }

    public function test_user_can_create_consignment_product(): void
    {
        Sanctum::actingAs($this->user);

        $payload = [
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'code' => 'PRD-CONSIGN-01',
            'name' => 'Consignment Product Alpha',
            'cost_price' => 10000,
            'price' => 15000,
            'purchase_type' => 'consignment',
            'consignment_commission_fee' => 20.00,
            'min_margin_percentage' => 10.00,
            'is_taxable' => false,
            'is_consignment' => true,
            'is_active' => true,
        ];

        $response = $this->postJson('/api/products', $payload);

        $response->assertStatus(201);

        $this->assertDatabaseHas('products', [
            'code' => 'PRD-CONSIGN-01',
            'purchase_type' => 'consignment',
            'is_consignment' => 1,
        ]);
    }

    public function test_user_cannot_create_product_with_invalid_data(): void
    {
        Sanctum::actingAs($this->user);

        // Missing required fields: category_id, unit_id, code, name, cost_price, price
        $response = $this->postJson('/api/products', [
            'description' => 'No required fields',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['category_id', 'unit_id', 'code', 'name', 'cost_price', 'price']);

        // Non-existing category
        $this->postJson('/api/products', [
            'category_id' => 9999,
            'unit_id' => $this->unit->id,
            'code' => 'PRD-BAD-01',
            'name' => 'Bad Category Product',
            'cost_price' => 1000,
            'price' => 2000,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['category_id']);

        // Code too long (max 50)
        $this->postJson('/api/products', [
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'code' => str_repeat('X', 51),
            'name' => 'Long Code Product',
            'cost_price' => 1000,
            'price' => 2000,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['code']);

        // Invalid purchase_type value
        $this->postJson('/api/products', [
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'code' => 'PRD-INVTYPE',
            'name' => 'Bad Type Product',
            'cost_price' => 1000,
            'price' => 2000,
            'purchase_type' => 'invalid_type',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['purchase_type']);

        // Commission fee out of range
        $this->postJson('/api/products', [
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'code' => 'PRD-INVFEE',
            'name' => 'Bad Fee Product',
            'cost_price' => 1000,
            'price' => 2000,
            'consignment_commission_fee' => 150,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['consignment_commission_fee']);
    }

    public function test_user_cannot_create_product_with_duplicate_code(): void
    {
        Sanctum::actingAs($this->user);

        Product::factory()->create([
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'code' => 'PRD-DUPLICATE',
        ]);

        $response = $this->postJson('/api/products', [
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'code' => 'PRD-DUPLICATE',
            'name' => 'Another Product Same Code',
            'cost_price' => 1000,
            'price' => 2000,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    public function test_user_cannot_create_product_with_duplicate_sku(): void
    {
        Sanctum::actingAs($this->user);

        Product::factory()->create([
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'sku' => 'SKU-DUPLICATE',
        ]);

        $response = $this->postJson('/api/products', [
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'code' => 'PRD-NEW-001',
            'name' => 'Another Product Same SKU',
            'sku' => 'SKU-DUPLICATE',
            'cost_price' => 1000,
            'price' => 2000,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['sku']);
    }

    public function test_user_can_show_product(): void
    {
        Sanctum::actingAs($this->user);

        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'unit_id' => $this->unit->id,
            'name' => 'Show Test Product',
        ]);

        $response = $this->getJson("/api/products/{$product->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Show Test Product')
            ->assertJsonPath('data.category.id', $this->category->id)
            ->assertJsonPath('data.unit.id', $this->unit->id);
    }

    public function test_user_can_update_product(): void
    {
        Sanctum::actingAs($this->user);

        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'name' => 'Old Product Name',
            'price' => 5000,
            'is_active' => false,
            'purchase_type' => 'outright',
        ]);

        $payload = [
            'name' => 'New Product Name Updated',
            'price' => 7500,
            'is_active' => true,
            'purchase_type' => 'consignment',
            'consignment_commission_fee' => 25.00,
            'min_margin_percentage' => 20.00,
        ];

        $response = $this->putJson("/api/products/{$product->id}", $payload);

        $response->assertStatus(200);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'New Product Name Updated',
            'is_active' => 1,
            'purchase_type' => 'consignment',
        ]);
    }

    public function test_user_can_upload_product_image(): void
    {
        Storage::fake('public');

        Sanctum::actingAs($this->user);

        $fakeImage = UploadedFile::fake()->image('product.jpg', 200, 200);

        $payload = [
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'code' => 'PRD-IMG-01',
            'name' => 'Product With Image',
            'cost_price' => 2000,
            'price' => 3000,
            'image' => $fakeImage,
        ];

        $response = $this->postJson('/api/products', $payload);

        $response->assertStatus(201);

        $product = Product::where('code', 'PRD-IMG-01')->first();
        $this->assertNotNull($product->image_path);
        Storage::disk('public')->assertExists($product->image_path);
    }

    public function test_user_can_delete_product(): void
    {
        Sanctum::actingAs($this->user);

        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
        ]);

        $response = $this->deleteJson("/api/products/{$product->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }

    public function test_user_can_export_products_to_excel(): void
    {
        Sanctum::actingAs($this->user);

        Product::factory()->count(3)->create([
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'unit_id' => $this->unit->id,
        ]);

        $response = $this->get('/api/products/export');

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_user_can_download_import_template(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->get('/api/products/import-template');

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_user_can_import_products_from_valid_excel(): void
    {
        Sanctum::actingAs($this->user);

        // Use explicit names to avoid Faker generating names with commas that break CSV
        $importCategory = Category::factory()->create(['name' => 'Beverages']);
        $importBrand = Brand::factory()->create(['name' => 'AquaBrand']);
        $importUnit = Unit::factory()->create(['name' => 'Bottle']);

        $csvContent = implode("\n", [
            'Code,Name,SKU,Barcode,Category,Brand,Unit,Cost Price,Selling Price,Wholesale Price,Purchase Type,Commission Fee (%),Min Margin (%),Safety Stock,Reorder Point,Lead Time (days),Is Taxable,Is Consignment,Is Active,Description',
            'PRD-IMP-001,Import Product One,SKU-IMP-001,,Beverages,AquaBrand,Bottle,5000,8000,7500,outright,0,10,20,10,2,Yes,No,Yes,Imported product one',
            'PRD-IMP-002,Import Product Two,SKU-IMP-002,,Beverages,,Bottle,3000,5000,,consignment,20,15,10,5,1,No,Yes,Yes,Imported product two',
        ]);

        $file = UploadedFile::fake()->createWithContent('products.csv', $csvContent);

        $response = $this->post('/api/products/import', ['file' => $file]);

        $response->assertStatus(200)
            ->assertJsonPath('data.success_count', 2)
            ->assertJsonPath('data.failure_count', 0);

        $this->assertDatabaseHas('products', ['code' => 'PRD-IMP-001', 'name' => 'Import Product One']);
        $this->assertDatabaseHas('products', ['code' => 'PRD-IMP-002', 'name' => 'Import Product Two', 'purchase_type' => 'consignment']);
    }

    public function test_guest_cannot_access_excel_endpoints(): void
    {
        $this->getJson('/api/products/export')->assertStatus(401);
        $this->getJson('/api/products/import-template')->assertStatus(401);
        $this->postJson('/api/products/import', [])->assertStatus(401);
    }

    public function test_import_rejects_non_excel_file(): void
    {
        Sanctum::actingAs($this->user);

        $textFile = UploadedFile::fake()->create('products.pdf', 100, 'application/pdf');

        $response = $this->postJson('/api/products/import', ['file' => $textFile]);

        $response->assertStatus(422);
    }
}
