<?php
 
namespace App\Http\Controllers\Inventory;

use App\Models\Tenant\Part;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\Tenant\JobCard;
use App\Models\Tenant\JobPart;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Models\Tenant\PartStockMovement;
use App\Models\Tenant\PartStockValuation;

class WorkshopIntegrationController extends Controller
{
    protected function getCompanyId()
    {
        $user = Auth::guard('tenant')->user();
        
        if ($user && $user->company_id) {
            return $user->company_id;
        }
        
        if (function_exists('tenant') && tenant('id')) {
            return tenant('id');
        }
        
        if (request()->header('X-Company-ID')) {
            return request()->header('X-Company-ID');
        }
        
        if (session('company_id')) {
            return session('company_id');
        }
        
        throw new \Exception('Company context not found');
    }
    public function reserveForJob(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'job_card_id' => 'required|exists:job_cards,id',
                'warehouse_id' => 'required|exists:warehouses,id',
                'parts' => 'required|array|min:1',
                'parts.*.part_id' => 'required|exists:parts,id',
                'parts.*.quantity' => 'required|numeric|min:0.001',
                'parts.*.estimated_unit_cost' => 'nullable|numeric|min:0',
                'notes' => 'nullable|string',
            ]);

            DB::beginTransaction();

            $jobCard = JobCard::byCompany($this->getCompanyId())
                ->findOrFail($validated['job_card_id']);

            $reservedParts = [];
            $failedReservations = [];

            foreach ($validated['parts'] as $partData) {
                $part = Part::find($partData['part_id']);
                
                // Check stock availability
                $stock = PartStockValuation::where([
                    'part_id' => $part->id,
                    'warehouse_id' => $validated['warehouse_id'],
                    'company_id' => $this->getCompanyId(),
                ])->first();

                $availableQuantity = $stock ? $stock->quantity_available : 0;
                
                if ($availableQuantity < $partData['quantity']) {
                    $failedReservations[] = [
                        'part_sku' => $part->sku,
                        'part_name' => $part->name,
                        'required' => $partData['quantity'],
                        'available' => $availableQuantity,
                        'shortage' => $partData['quantity'] - $availableQuantity,
                    ];
                    continue;
                }

                // Create or update job part
                $jobPart = JobPart::updateOrCreate([
                    'job_card_id' => $jobCard->id,
                    'part_id' => $part->id,
                    'company_id' => $this->getCompanyId(),
                ], [
                    'warehouse_id' => $validated['warehouse_id'],
                    'quantity_required' => $partData['quantity'],
                    'estimated_unit_cost' => $partData['estimated_unit_cost'] ?? $part->cost_price,
                    'selling_price' => $part->selling_price,
                    'status' => 'reserved',
                    'reserved_at' => now(),
                    'notes' => $validated['notes'] ?? null,
                ]);

                // Create reservation movement
                $movement = PartStockMovement::create([
                    'company_id' => $this->getCompanyId(),
                    'part_id' => $part->id,
                    'warehouse_id' => $validated['warehouse_id'],
                    'movement_type' => 'reservation',
                    'quantity' => $partData['quantity'],
                    'unit_cost' => $part->cost_price,
                    'total_cost' => $partData['quantity'] * $part->cost_price,
                    'reference_type' => 'job_card',
                    'reference_id' => $jobCard->id,
                    'document_number' => 'RES-' . $jobCard->job_number . '-' . Str::random(6),
                    'status' => 'completed',
                    'notes' => "Reserved for job: {$jobCard->job_number}. " . ($validated['notes'] ?? ''),
                    'created_by' => auth()->id(),
                    'movement_date' => now(),
                ]);

                // Update job part with movement reference
                $jobPart->update([
                    'reservation_movement_id' => $movement->id,
                ]);

                // Update stock valuation (reserve quantity)
                if ($stock) {
                    $stock->quantity_reserved += $partData['quantity'];
                    $stock->updateStockStatus();
                    $stock->save();
                }

                $reservedParts[] = $jobPart->load(['part', 'warehouse']);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Parts reservation completed',
                'data' => [
                    'job_card' => $jobCard,
                    'reserved_parts' => $reservedParts,
                    'failed_reservations' => $failedReservations,
                    'summary' => [
                        'successful' => count($reservedParts),
                        'failed' => count($failedReservations),
                    ]
                ]
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to reserve parts for job',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function issueToJob(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'job_card_id' => 'required|exists:job_cards,id',
                'warehouse_id' => 'required|exists:warehouses,id',
                'parts' => 'required|array|min:1',
                'parts.*.job_part_id' => 'required|exists:job_parts,id',
                'parts.*.quantity' => 'required|numeric|min:0.001',
                'notes' => 'nullable|string',
            ]);

            DB::beginTransaction();

            $jobCard = JobCard::byCompany($this->getCompanyId())
                ->findOrFail($validated['job_card_id']);

            $issuedParts = [];
            $failedIssues = [];

            foreach ($validated['parts'] as $partData) {
                $jobPart = JobPart::where('id', $partData['job_part_id'])
                    ->where('job_card_id', $jobCard->id)
                    ->where('company_id', $this->getCompanyId())
                    ->firstOrFail();

                // Check if part is reserved or pending
                if (!in_array($jobPart->status, ['reserved', 'pending'])) {
                    $failedIssues[] = [
                        'part_sku' => $jobPart->part->sku,
                        'part_name' => $jobPart->part->name,
                        'reason' => "Part status is '{$jobPart->status}', cannot issue",
                    ];
                    continue;
                }

                // Check stock availability
                $stock = PartStockValuation::where([
                    'part_id' => $jobPart->part_id,
                    'warehouse_id' => $validated['warehouse_id'],
                    'company_id' => $this->getCompanyId(),
                ])->first();

                $availableQuantity = $stock ? $stock->quantity_available : 0;
                
                if ($availableQuantity < $partData['quantity']) {
                    $failedIssues[] = [
                        'part_sku' => $jobPart->part->sku,
                        'part_name' => $jobPart->part->name,
                        'required' => $partData['quantity'],
                        'available' => $availableQuantity,
                        'shortage' => $partData['quantity'] - $availableQuantity,
                    ];
                    continue;
                }

                // Update job part
                $jobPart->update([
                    'quantity_issued' => $partData['quantity'],
                    'actual_unit_cost' => $stock ? $stock->average_cost : $jobPart->part->cost_price,
                    'status' => 'issued',
                    'issued_at' => now(),
                ]);

                // Create issue movement
                $movement = PartStockMovement::create([
                    'company_id' => $this->getCompanyId(),
                    'part_id' => $jobPart->part_id,
                    'warehouse_id' => $validated['warehouse_id'],
                    'movement_type' => 'issue',
                    'quantity' => $partData['quantity'],
                    'unit_cost' => $jobPart->actual_unit_cost,
                    'total_cost' => $partData['quantity'] * $jobPart->actual_unit_cost,
                    'reference_type' => 'job_card',
                    'reference_id' => $jobCard->id,
                    'document_number' => 'ISS-' . $jobCard->job_number . '-' . Str::random(6),
                    'status' => 'completed',
                    'notes' => "Issued to job: {$jobCard->job_number}. " . ($validated['notes'] ?? ''),
                    'created_by' => auth()->id(),
                    'movement_date' => now(),
                ]);

                // Update job part with movement reference
                $jobPart->update([
                    'issue_movement_id' => $movement->id,
                ]);

                // Remove reservation if exists
                if ($jobPart->reservation_movement_id) {
                    $reservationMovement = PartStockMovement::find($jobPart->reservation_movement_id);
                    if ($reservationMovement) {
                        // Update stock valuation (remove reservation, reduce on-hand)
                        if ($stock) {
                            $stock->quantity_reserved -= $partData['quantity'];
                            $stock->quantity_on_hand -= $partData['quantity'];
                            $stock->updateStockStatus();
                            $stock->save();
                        }
                    }
                }

                $issuedParts[] = $jobPart->load(['part', 'warehouse']);
            }

            // Update job card parts cost
            $jobCard->calculatePartsCost();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Parts issued to job successfully',
                'data' => [
                    'job_card' => $jobCard,
                    'issued_parts' => $issuedParts,
                    'failed_issues' => $failedIssues,
                    'summary' => [
                        'successful' => count($issuedParts),
                        'failed' => count($failedIssues),
                    ]
                ]
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to issue parts to job',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getJobUsage($jobId): JsonResponse
    {
        try {
            $jobCard = JobCard::byCompany($this->getCompanyId())
                ->with([
                    'jobParts.part.category',
                    'jobParts.warehouse',
                    'jobParts.reservationMovement',
                    'jobParts.issueMovement',
                    'jobParts.returnMovement'
                ])
                ->findOrFail($jobId);

            $partsSummary = $jobCard->jobParts->groupBy('status')->map(function($parts, $status) {
                return [
                    'count' => $parts->count(),
                    'total_quantity' => $parts->sum('quantity_required'),
                    'total_cost' => $parts->sum(function($part) {
                        return $part->quantity_required * $part->estimated_unit_cost;
                    }),
                ];
            });

            $costBreakdown = [
                'estimated_parts_cost' => $jobCard->estimated_parts_cost,
                'actual_parts_cost' => $jobCard->actual_parts_cost,
                'total_issued_cost' => $jobCard->jobParts->where('status', 'issued')->sum(function($part) {
                    return $part->quantity_issued * $part->actual_unit_cost;
                }),
                'total_used_cost' => $jobCard->jobParts->where('status', 'used')->sum(function($part) {
                    return $part->quantity_used * $part->actual_unit_cost;
                }),
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'job_card' => $jobCard,
                    'parts_usage' => $jobCard->jobParts,
                    'summary' => $partsSummary,
                    'cost_breakdown' => $costBreakdown,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch job parts usage',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function returnFromJob(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'job_card_id' => 'required|exists:job_cards,id',
                'warehouse_id' => 'required|exists:warehouses,id',
                'returns' => 'required|array|min:1',
                'returns.*.job_part_id' => 'required|exists:job_parts,id',
                'returns.*.quantity' => 'required|numeric|min:0.001',
                'reason' => 'required|string|max:500',
                'notes' => 'nullable|string',
            ]);
    
            DB::beginTransaction();
    
            $companyId = $this->getCompanyId();
            $jobCard = JobCard::byCompany($companyId)->findOrFail($validated['job_card_id']);
    
            $returnedParts = [];
            $failedReturns = [];
    
            foreach ($validated['returns'] as $returnData) {
                try {
                    // FIXED: Better error handling for job part lookup
                    $jobPart = JobPart::where('id', $returnData['job_part_id'])
                        ->where('job_card_id', $jobCard->id)
                        ->where('company_id', $companyId)
                        ->first();
    
                    if (!$jobPart) {
                        $failedReturns[] = [
                            'job_part_id' => $returnData['job_part_id'],
                            'reason' => 'Job part not found or does not belong to this job card',
                        ];
                        continue;
                    }
    
                    // Check if part can be returned
                    $availableForReturn = $jobPart->getQuantityAvailableForReturn();
                    
                    if ($availableForReturn < $returnData['quantity']) {
                        $failedReturns[] = [
                            'job_part_id' => $jobPart->id,
                            'part_sku' => $jobPart->part->sku ?? 'Unknown',
                            'part_name' => $jobPart->part->name ?? 'Unknown',
                            'requested' => $returnData['quantity'],
                            'available' => $availableForReturn,
                            'reason' => 'Insufficient quantity available for return',
                        ];
                        continue;
                    }
    
                    // Update job part
                    $jobPart->quantity_returned += $returnData['quantity'];
                    
                    // Update status if all issued quantity is returned
                    if ($jobPart->quantity_returned >= $jobPart->quantity_issued) {
                        $jobPart->status = 'returned';
                    }
                    
                    $jobPart->returned_at = now();
                    $jobPart->save();
    
                    // Create return movement (receipt back to stock)
                    $movement = PartStockMovement::create([
                        'company_id' => $companyId,
                        'part_id' => $jobPart->part_id,
                        'warehouse_id' => $validated['warehouse_id'],
                        'movement_type' => 'receipt',
                        'quantity' => $returnData['quantity'],
                        'unit_cost' => $jobPart->actual_unit_cost,
                        'total_cost' => $returnData['quantity'] * $jobPart->actual_unit_cost,
                        'reference_type' => 'job_return',
                        'reference_id' => $jobCard->id,
                        'document_number' => 'RET-' . $jobCard->job_number . '-' . Str::random(6),
                        'status' => 'completed',
                        'notes' => "Return from job: {$jobCard->job_number}. Reason: {$validated['reason']}. " . ($validated['notes'] ?? ''),
                        'created_by' => auth()->id(),
                        'movement_date' => now(),
                    ]);
    
                    // Update job part with movement reference
                    $jobPart->update([
                        'return_movement_id' => $movement->id,
                    ]);
    
                    $returnedParts[] = $jobPart->load(['part', 'warehouse']);
    
                } catch (\Exception $e) {
                    $failedReturns[] = [
                        'job_part_id' => $returnData['job_part_id'],
                        'reason' => 'Error processing return: ' . $e->getMessage(),
                    ];
                    continue;
                }
            }
    
            // Update job card parts cost
            $jobCard->calculatePartsCost();
    
            DB::commit();
    
            return response()->json([
                'success' => true,
                'message' => 'Parts return processed',
                'data' => [
                    'job_card' => $jobCard,
                    'returned_parts' => $returnedParts,
                    'failed_returns' => $failedReturns,
                    'summary' => [
                        'successful' => count($returnedParts),
                        'failed' => count($failedReturns),
                        'total_quantity_returned' => collect($returnedParts)->sum('quantity_returned'),
                    ]
                ]
            ], 201);
    
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to return parts from job',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}