<?php

namespace App\Http\Controllers\Api\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreJournalEntryRequest;
use App\Http\Requests\Finance\UpdateJournalEntryRequest;
use App\Repositories\Contracts\Finance\JournalEntryRepositoryInterface;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;

class JournalEntryController extends Controller
{
    use ApiResponseTrait;

    protected JournalEntryRepositoryInterface $journalEntryRepository;

    public function __construct(JournalEntryRepositoryInterface $journalEntryRepository)
    {
        $this->journalEntryRepository = $journalEntryRepository;
    }

    public function index(): JsonResponse
    {
        $entries = $this->journalEntryRepository->all();

        return $this->successResponse($entries, 'Journal entries retrieved successfully');
    }

    public function store(StoreJournalEntryRequest $request): JsonResponse
    {
        try {
            $entry = $this->journalEntryRepository->create($request->validated());

            return $this->successResponse($entry, 'Journal entry processed and balanced successfully', 201);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    public function show($id): JsonResponse
    {
        $entry = $this->journalEntryRepository->findOrFail($id);
        $entry->load(['items.account', 'creator']);

        return $this->successResponse($entry, 'Journal entry details retrieved successfully');
    }

    public function update(UpdateJournalEntryRequest $request, $id): JsonResponse
    {
        $entry = $this->journalEntryRepository->update($id, $request->validated());

        return $this->successResponse($entry, 'Journal entry updated successfully');
    }

    public function destroy($id): JsonResponse
    {
        $this->journalEntryRepository->delete($id);

        return $this->successResponse(null, 'Journal entry deleted successfully');
    }
}
