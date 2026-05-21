<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;

use App\Http\Requests\Master\StoreRackRequest;
use App\Http\Requests\Master\UpdateRackRequest;
use App\Models\Master\Rack;
use App\Repositories\Contracts\Master\RackRepositoryInterface;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RackController extends Controller
{
    use ApiResponseTrait;

    protected RackRepositoryInterface $rackRepository;

    public function __construct(RackRepositoryInterface $rackRepository)
    {
        $this->rackRepository = $rackRepository;
    }

    public function index(): JsonResponse
    {
        $racks = $this->rackRepository->all();
        $racks->load('warehouse');
        return $this->successResponse($racks, 'Racks retrieved successfully');
    }

    public function store(StoreRackRequest $request): JsonResponse
    {
        $rack = $this->rackRepository->create($request->validated());
        return $this->successResponse($rack, 'Rack created successfully', 201);
    }

    public function show($id): JsonResponse
    {
        $rack = $this->rackRepository->findOrFail($id);
        $rack->load(['warehouse', 'products']);
        return $this->successResponse($rack, 'Rack retrieved successfully');
    }

    public function update(UpdateRackRequest $request, $id): JsonResponse
    {
        $rack = $this->rackRepository->update($id, $request->validated());
        return $this->successResponse($rack, 'Rack updated successfully');
    }

    public function destroy($id): JsonResponse
    {
        $this->rackRepository->delete($id);
        return $this->successResponse(null, 'Rack deleted successfully');
    }

    /**
     * Dapatkan layout visual Planogram Rak (dikelompokkan per Shelf Level)
     */
    public function planogram($id): JsonResponse
    {
        $rack = Rack::with(['warehouse', 'products' => function ($query) {
            $query->orderBy('shelf_level', 'desc')
                  ->orderBy('position_order', 'asc');
        }])->findOrFail($id);

        $shelves = [];
        
        // Kelompokkan produk berdasarkan tingkat rak (shelf_level)
        foreach ($rack->products as $product) {
            $level = $product->pivot->shelf_level;
            
            if (!isset($shelves[$level])) {
                $shelves[$level] = [
                    'shelf_level' => $level,
                    'level_name' => 'Shelf Level ' . $level,
                    'products' => []
                ];
            }

            // Tambahkan detail planogram produk
            $shelves[$level]['products'][] = [
                'id' => $product->id,
                'code' => $product->code,
                'name' => $product->name,
                'image_path' => $product->image_path,
                'price' => round($product->price, 2),
                'cost_price' => round($product->cost_price, 2),
                'planogram' => [
                    'position_order' => $product->pivot->position_order,
                    'facing' => $product->pivot->facing,
                    'max_capacity' => $product->pivot->max_capacity,
                ]
            ];
        }

        // Urutkan shelfs dari atas (level tertinggi) ke bawah (level 1)
        krsort($shelves);

        $layout = [
            'rack' => [
                'id' => $rack->id,
                'code' => $rack->code,
                'name' => $rack->name,
                'warehouse_name' => $rack->warehouse->name,
            ],
            'shelves' => array_values($shelves)
        ];

        return $this->successResponse($layout, 'Rack planogram layout retrieved successfully');
    }

    /**
     * Simpan / Perbarui layout Planogram Rak secara massal
     */
    public function updatePlanogram(Request $request, $id): JsonResponse
    {
        $rack = Rack::findOrFail($id);

        $request->validate([
            'items' => 'required|array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.shelf_level' => 'required|integer|min:1',
            'items.*.position_order' => 'required|integer|min:1',
            'items.*.facing' => 'required|integer|min:1',
            'items.*.max_capacity' => 'required|integer|min:1',
        ]);

        DB::transaction(function () use ($rack, $request) {
            $syncData = [];
            foreach ($request->input('items') as $item) {
                $syncData[$item['product_id']] = [
                    'shelf_level' => $item['shelf_level'],
                    'position_order' => $item['position_order'],
                    'facing' => $item['facing'],
                    'max_capacity' => $item['max_capacity'],
                ];
            }

            // Sync pivot table data
            $rack->products()->sync($syncData);
        });

        return $this->successResponse(null, 'Rack planogram layout updated successfully');
    }
}
