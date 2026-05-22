<?php

namespace App\Http\Controllers\Api\Finance;

use App\Exceptions\JournalEntryException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreJournalEntryRequest;
use App\Http\Requests\Finance\UpdateJournalEntryRequest;
use App\Models\Finance\JournalEntry;
use App\Repositories\Contracts\Finance\JournalEntryRepositoryInterface;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class JournalEntryController extends Controller
{
    use ApiResponseTrait;

    protected JournalEntryRepositoryInterface $journalEntryRepository;

    public function __construct(JournalEntryRepositoryInterface $journalEntryRepository)
    {
        $this->journalEntryRepository = $journalEntryRepository;
    }

    /**
     * Display a listing of journal entries with filters and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = JournalEntry::with(['items.account', 'creator']);

            // Filter by search (reference_no or description)
            if ($request->has('search') && $request->input('search') !== '') {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('reference_no', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            }

            // Filter by date range (start_date & end_date)
            if ($request->has('start_date') && $request->input('start_date') !== '') {
                $query->where('transaction_date', '>=', $request->input('start_date'));
            }
            if ($request->has('end_date') && $request->input('end_date') !== '') {
                $query->where('transaction_date', '<=', $request->input('end_date'));
            }

            // Filter by specific account used in journal items
            if ($request->has('account_id') && $request->input('account_id') !== '') {
                $accountId = $request->input('account_id');
                $query->whereHas('items', function ($q) use ($accountId) {
                    $q->where('account_id', $accountId);
                });
            }

            // Order by date and id descending (latest first)
            $entries = $query->orderBy('transaction_date', 'desc')
                ->orderBy('id', 'desc')
                ->paginate($request->input('per_page', 15));

            return $this->successResponse($entries, 'Data jurnal berhasil diambil');
        } catch (\Throwable $e) {
            Log::error('JournalEntryController::index failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->errorResponse('Gagal mengambil data jurnal: '.$e->getMessage(), 500);
        }
    }

    /**
     * Store a newly created journal entry in storage.
     */
    public function store(StoreJournalEntryRequest $request): JsonResponse
    {
        try {
            $entry = $this->journalEntryRepository->create($request->validated());

            return $this->successResponse($entry, 'Jurnal berhasil disimpan dan diposting ke buku besar', 201);
        } catch (JournalEntryException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->contextData());
        } catch (\Throwable $e) {
            Log::error('JournalEntryController::store failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payload' => $request->validated(),
            ]);

            return $this->errorResponse('Gagal menyimpan jurnal: '.$e->getMessage(), 500);
        }
    }

    /**
     * Display the specified journal entry.
     *
     * @param  int  $id
     */
    public function show($id): JsonResponse
    {
        try {
            $entry = $this->journalEntryRepository->findOrFail($id);
            $entry->load(['items.account', 'creator']);

            return $this->successResponse($entry, 'Detail jurnal berhasil diambil');
        } catch (\Throwable $e) {
            return $this->errorResponse('Jurnal tidak ditemukan.', 404);
        }
    }

    /**
     * Update the specified journal entry in storage.
     *
     * @param  int  $id
     */
    public function update(UpdateJournalEntryRequest $request, $id): JsonResponse
    {
        try {
            $entry = $this->journalEntryRepository->update($id, $request->validated());

            return $this->successResponse($entry, 'Jurnal berhasil diperbarui');
        } catch (JournalEntryException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->contextData());
        } catch (\Throwable $e) {
            Log::error('JournalEntryController::update failed', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payload' => $request->validated(),
            ]);

            return $this->errorResponse('Gagal memperbarui jurnal: '.$e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified journal entry from storage.
     *
     * @param  int  $id
     */
    public function destroy($id): JsonResponse
    {
        try {
            $this->journalEntryRepository->delete($id);

            return $this->successResponse(null, 'Jurnal berhasil dihapus dan saldo dibalik');
        } catch (JournalEntryException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->contextData());
        } catch (\Throwable $e) {
            Log::error('JournalEntryController::destroy failed', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->errorResponse('Gagal menghapus jurnal: '.$e->getMessage(), 500);
        }
    }
}
