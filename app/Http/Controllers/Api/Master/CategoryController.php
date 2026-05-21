<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StoreCategoryRequest;
use App\Http\Requests\Master\UpdateCategoryRequest;
use App\Repositories\Contracts\Master\CategoryRepositoryInterface;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    use ApiResponseTrait;

    protected CategoryRepositoryInterface $categoryRepository;

    public function __construct(CategoryRepositoryInterface $categoryRepository)
    {
        $this->categoryRepository = $categoryRepository;
    }

    public function index(): JsonResponse
    {
        $categories = $this->categoryRepository->all();

        return $this->successResponse($categories, 'Categories retrieved successfully');
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = $this->categoryRepository->create($request->validated());

        return $this->successResponse($category, 'Category created successfully', 201);
    }

    public function show($id): JsonResponse
    {
        $category = $this->categoryRepository->findOrFail($id);

        return $this->successResponse($category, 'Category retrieved successfully');
    }

    public function update(UpdateCategoryRequest $request, $id): JsonResponse
    {
        $category = $this->categoryRepository->update($id, $request->validated());

        return $this->successResponse($category, 'Category updated successfully');
    }

    public function destroy($id): JsonResponse
    {
        $this->categoryRepository->delete($id);

        return $this->successResponse(null, 'Category deleted successfully');
    }
}
