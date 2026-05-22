<?php

namespace App\Http\Controllers\Api\Finance;

use App\Exceptions\AccountException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreAccountRequest;
use App\Http\Requests\Finance\UpdateAccountRequest;
use App\Models\Finance\Account;
use App\Repositories\Contracts\Finance\AccountRepositoryInterface;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AccountController extends Controller
{
    use ApiResponseTrait;

    protected AccountRepositoryInterface $accountRepository;

    public function __construct(AccountRepositoryInterface $accountRepository)
    {
        $this->accountRepository = $accountRepository;
    }

    /**
     * Display a listing of the accounts.
     */
    public function index(): JsonResponse
    {
        $accounts = $this->accountRepository->all();

        return $this->successResponse($accounts, 'Accounts (Chart of Accounts) retrieved successfully');
    }

    /**
     * Store a newly created account in storage.
     */
    public function store(StoreAccountRequest $request): JsonResponse
    {
        try {
            $account = DB::transaction(function () use ($request) {
                return $this->accountRepository->create($request->validated());
            });

            return $this->successResponse($account, 'Account created successfully', 201);
        } catch (\Throwable $e) {
            Log::error('AccountController::store failed', [
                'error' => $e->getMessage(),
                'payload' => $request->validated(),
            ]);

            return $this->errorResponse('Failed to create account: '.$e->getMessage(), 500);
        }
    }

    /**
     * Display the specified account.
     */
    public function show($id): JsonResponse
    {
        $account = $this->accountRepository->findOrFail($id);

        return $this->successResponse($account, 'Account retrieved successfully');
    }

    /**
     * Update the specified account in storage.
     */
    public function update(UpdateAccountRequest $request, $id): JsonResponse
    {
        try {
            $account = DB::transaction(function () use ($request, $id) {
                // Find and lock the record
                /** @var Account $account */
                $account = Account::query()->lockForUpdate()->findOrFail($id);

                // If it is a system-critical account, prevent changing code and type
                if ($account->isSystemAccount()) {
                    $validated = $request->validated();

                    if (isset($validated['code']) && $validated['code'] !== $account->code) {
                        throw new AccountException('Protected system accounts cannot have their account code changed.', [
                            'account_id' => $account->id,
                            'current_code' => $account->code,
                            'proposed_code' => $validated['code'],
                        ]);
                    }

                    if (isset($validated['type']) && $validated['type'] !== $account->type) {
                        throw new AccountException('Protected system accounts cannot have their account type changed.', [
                            'account_id' => $account->id,
                            'current_type' => $account->type,
                            'proposed_type' => $validated['type'],
                        ]);
                    }
                }

                $account->update($request->validated());

                return $account->refresh();
            });

            return $this->successResponse($account, 'Account updated successfully');
        } catch (AccountException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->contextData());
        } catch (\Throwable $e) {
            Log::error('AccountController::update failed', [
                'id' => $id,
                'error' => $e->getMessage(),
                'payload' => $request->validated(),
            ]);

            return $this->errorResponse('Failed to update account: '.$e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified account from storage.
     */
    public function destroy($id): JsonResponse
    {
        try {
            DB::transaction(function () use ($id) {
                // Find and lock the record
                /** @var Account $account */
                $account = Account::query()->lockForUpdate()->findOrFail($id);

                if ($account->isSystemAccount()) {
                    throw new AccountException('Protected system accounts cannot be deleted.', [
                        'account_id' => $account->id,
                        'code' => $account->code,
                    ]);
                }

                // Verify that no journal entries are using this account
                if ($account->journalItems()->exists()) {
                    throw new AccountException('Cannot delete account with existing journal entry transactions.', [
                        'account_id' => $account->id,
                        'code' => $account->code,
                    ]);
                }

                $account->delete();
            });

            return $this->successResponse(null, 'Account deleted successfully');
        } catch (AccountException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->contextData());
        } catch (\Throwable $e) {
            Log::error('AccountController::destroy failed', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Failed to delete account: '.$e->getMessage(), 500);
        }
    }
}
