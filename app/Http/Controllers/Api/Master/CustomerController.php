<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StoreCustomerRequest;
use App\Http\Requests\Master\UpdateCustomerRequest;
use App\Repositories\Contracts\Master\CustomerRepositoryInterface;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;

class CustomerController extends Controller
{
    use ApiResponseTrait;

    protected CustomerRepositoryInterface $customerRepository;

    public function __construct(CustomerRepositoryInterface $customerRepository)
    {
        $this->customerRepository = $customerRepository;
    }

    public function index(): JsonResponse
    {
        $customers = $this->customerRepository->all();

        return $this->successResponse($customers, 'Customers retrieved successfully');
    }

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $customer = $this->customerRepository->create($request->validated());

        return $this->successResponse($customer, 'Customer created successfully', 201);
    }

    public function show($id): JsonResponse
    {
        $customer = $this->customerRepository->findOrFail($id);

        return $this->successResponse($customer, 'Customer retrieved successfully');
    }

    public function update(UpdateCustomerRequest $request, $id): JsonResponse
    {
        $customer = $this->customerRepository->update($id, $request->validated());

        return $this->successResponse($customer, 'Customer updated successfully');
    }

    public function destroy($id): JsonResponse
    {
        $this->customerRepository->delete($id);

        return $this->successResponse(null, 'Customer deleted successfully');
    }
}
