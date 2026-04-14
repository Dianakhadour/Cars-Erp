<?php
 
namespace App\Http\Controllers\Finance;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Services\JournalEntryService;

class JournalEntriesController extends Controller
{
    public function __construct(
        private JournalEntryService $journalService
    ) {}

    /**
     * POST /api/finance/journal-entries
     */
    public function store(Request $request) : JsonResponse
    {
        $request->validate([
            'issue_date' => 'required|date',
            'posting_date' => 'required|date',
            'journal_entry_type' => 'required|in:manual,invoice,purchase,payroll,closing,adjustment,vehicle_sale,parts_sale,service_invoice,vehicle_purchase,parts_purchase',
            'journal_entry_by' => 'required|in:manual,automatic',
            'finance_year' => 'required|integer|min:2020|max:2030',
            'document_id' => 'nullable|uuid',
            'document_ref' => 'nullable|string|max:255',
            'narration' => 'nullable|string',
            'currency' => 'nullable|string|size:3',
            'exchange_rate' => 'nullable|numeric|min:0',
            'rows' => 'required|array|min:2',
            'rows.*.account_id' => 'required|uuid',
            'rows.*.debit' => 'required_without:rows.*.credit|numeric|min:0',
            'rows.*.credit' => 'required_without:rows.*.debit|numeric|min:0',
            'rows.*.currency_amount' => 'nullable|numeric|min:0',
            'rows.*.entry_type' => 'nullable|in:normal,adjustment,reversal,closing',
            'rows.*.reference' => 'nullable|string|max:255',
        ]);

        $companyId = $request->header('X-Company-ID');
        $branchId = $request->header('X-Branch-ID');
        $user = Auth::guard('tenant')->user();
       
        try {
            $entry = $this->journalService->createDraft(
                $request->all(),
                $companyId,
                $branchId,
                $user
            );

            return response()->json([
                'success' => true,
                'data' => $entry,
                'message' => 'Journal entry created as draft successfully'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * POST /api/finance/journal-entries/{id}/post
     */
    public function postEntry(Request $request, string $id): JsonResponse
    {
        $companyId = $request->header('X-Company-ID');
        $branchId = $request->header('X-Branch-ID');
        $user = Auth::guard('tenant')->user();

        try {
            $entry = $this->journalService->postEntry($id, $companyId, $branchId, $user);

            return response()->json([
                'success' => true,
                'data' => $entry,
                'message' => 'Journal entry posted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * POST /api/finance/journal-entries/{id}/reverse
     */
    public function reverseEntry(Request $request, string $id): JsonResponse
    {
        $companyId = $request->header('X-Company-ID');
        $branchId = $request->header('X-Branch-ID');
        $user = Auth::guard('tenant')->user();

        try {
            $reversalEntry = $this->journalService->reverseEntry($id, $companyId, $branchId, $user);

            return response()->json([
                'success' => true,
                'data' => $reversalEntry,
                'message' => 'Journal entry reversed successfully'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * GET /api/finance/journal-entries
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'status' => 'nullable|in:draft,posted,cancelled',
            'type' => 'nullable|in:manual,invoice,purchase,payroll,closing,adjustment,vehicle_sale,parts_sale,service_invoice,vehicle_purchase,parts_purchase',
        ]);

        $companyId = $request->header('X-Company-ID');
        $branchId = $request->header('X-Branch-ID');
        $from = $request->query('from');
        $to = $request->query('to');
        $status = $request->query('status');
        $type = $request->query('type');

        try {
            $entries = $this->journalService->listEntries(
                $from, $to, $branchId, $companyId, $status, $type
            );

            return response()->json([
                'success' => true,
                'data' => $entries
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * GET /api/finance/journal-entries/{id}
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $companyId = $request->header('X-Company-ID');
        $branchId = $request->header('X-Branch-ID');

        try {
            $result = $this->journalService->getEntryWithRows($id, $companyId, $branchId);

            return response()->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }
}