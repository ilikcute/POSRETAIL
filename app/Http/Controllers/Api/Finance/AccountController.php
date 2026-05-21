<?php

namespace App\Http\Controllers\Api\Finance;

use App\Http\Controllers\Controller;

use App\Http\Requests\Finance\StoreAccountRequest;
use App\Http\Requests\Finance\UpdateAccountRequest;
use App\Repositories\Contracts\Finance\AccountRepositoryInterface;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;

class AccountController extends Controller
{
    use ApiResponseTrait;

    protected AccountRepositoryInterface $accountRepository;

    public function __construct(AccountRepositoryInterface $accountRepository)
    {
        $this->accountRepository = $accountRepository;
    }

    public function index(): JsonResponse
    {
        $accounts = $this->accountRepository->all();
        return $this->successResponse($accounts, 'Accounts (Chart of Accounts) retrieved successfully');
    }

    public function store(StoreAccountRequest $request): JsonResponse
    {
        $account = $this->accountRepository->create($request->validated());
        return $this->successResponse($account, 'Account created successfully', 201);
    }

    public function show($id): JsonResponse
    {
        $account = $this->accountRepository->findOrFail($id);
        return $this->successResponse($account, 'Account retrieved successfully');
    }

    public function update(UpdateAccountRequest $request, $id): JsonResponse
    {
        $account = $this->accountRepository->update($id, $request->validated());
        return $this->successResponse($account, 'Account updated successfully');
    }

    public function destroy($id): JsonResponse
    {
        $this->accountRepository->delete($id);
        return $this->successResponse(null, 'Account deleted successfully');
    }
}
