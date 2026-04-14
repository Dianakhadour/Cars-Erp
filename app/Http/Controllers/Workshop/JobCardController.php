<?php
 
namespace App\Http\Controllers\Workshop;

use App\Models\Tenant\Item;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\Tenant\JobCard;
use App\Models\Tenant\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class JobCardController extends Controller
{  protected function getCompanyId()
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
    public function index(Request $request): JsonResponse
    {
        try {
            $jobCards = JobCard::with([
                'vehicleItem',
                'customer',
                'technician',
                'serviceAdvisor',
                'jobParts.part'
            ])
            ->byCompany($this->getCompanyId())
            ->when($request->has('status'), function($query) use ($request) {
                return $query->where('status', $request->status);
            })
            ->when($request->has('technician_id'), function($query) use ($request) {
                return $query->where('assigned_technician_id', $request->technician_id);
            })
            ->when($request->has('date_from') && $request->has('date_to'), function($query) use ($request) {
                return $query->whereBetween('created_at', [
                    $request->date_from,
                    $request->date_to
                ]);
            })
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

            return response()->json([
                'success' => true,
                'data' => $jobCards
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch job cards',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                // Vehicle Information
                'vehicle_item_id' => 'nullable|exists:items,id',
                'customer_id' => 'nullable|exists:customers,id',
                'customer_name' => 'required|string|max:255',
                'customer_phone' => 'nullable|string|max:20',
                'customer_email' => 'nullable|email|max:255',
                
                // Vehicle Details
                'vehicle_license' => 'nullable|string|max:50',
                'vehicle_vin' => 'nullable|string|max:100',
                'vehicle_make' => 'nullable|string|max:100',
                'vehicle_model' => 'nullable|string|max:100',
                'vehicle_year' => 'nullable|integer|min:1900|max:' . (date('Y') + 1),
                'vehicle_color' => 'nullable|string|max:50',
                'vehicle_mileage' => 'nullable|integer|min:0',
                
                // Job Details
                'service_type' => 'required|in:maintenance,repair,inspection,warranty,diagnostic,recall',
                'work_description' => 'required|string|max:1000',
                'customer_complaint' => 'nullable|string|max:1000',
                
                // Scheduling
                'estimated_start' => 'nullable|date',
                'estimated_completion' => 'nullable|date|after_or_equal:estimated_start',
                
                // Cost Estimates
                'estimated_labor_cost' => 'nullable|numeric|min:0',
                'estimated_parts_cost' => 'nullable|numeric|min:0',
                
                // Assignment
                'assigned_technician_id' => 'nullable|exists:tenant_users,id',
                'service_advisor_id' => 'nullable|exists:tenant_users,id',
                
                'notes' => 'nullable|string|max:1000',
            ]);
    
            DB::beginTransaction();
    
            $companyId = $this->getCompanyId();
    
            // If vehicle_item_id is provided, populate vehicle details from items table
            if (!empty($validated['vehicle_item_id'])) {
                $vehicleItem = Item::where('company_id', $companyId)
                    ->where('item_type', 'vehicle')
                    ->find($validated['vehicle_item_id']);
                
                if ($vehicleItem) {
                    // Auto-populate vehicle details
                    $validated['vehicle_vin'] = $vehicleItem->vin ?? $validated['vehicle_vin'] ?? null;
                    $validated['vehicle_make'] = $vehicleItem->make ?? $validated['vehicle_make'] ?? null;
                    $validated['vehicle_model'] = $vehicleItem->model ?? $validated['vehicle_model'] ?? null;
                    $validated['vehicle_year'] = $vehicleItem->year ?? $validated['vehicle_year'] ?? null;
                    $validated['vehicle_color'] = $vehicleItem->color ?? $validated['vehicle_color'] ?? null;
                }
            }
    
            // If customer_id is provided, populate customer details
            if (!empty($validated['customer_id'])) {
                $customer = Customer::where('company_id', $companyId)
                    ->find($validated['customer_id']);
                
                if ($customer) {
                    $validated['customer_name'] = $customer->name;
                    $validated['customer_phone'] = $customer->phone ?? $validated['customer_phone'] ?? null;
                    $validated['customer_email'] = $customer->email ?? $validated['customer_email'] ?? null;
                }
            }
    
            // Calculate total estimated cost
            $validated['total_estimated_cost'] = 
                ($validated['estimated_labor_cost'] ?? 0) + 
                ($validated['estimated_parts_cost'] ?? 0);
    
            // FIX: Explicitly include vehicle_item_id even if null
            $jobCardData = [
                ...$validated,
                'company_id' => $companyId,
                'status' => 'pending',
                'vehicle_item_id' => $validated['vehicle_item_id'] ?? null, // EXPLICITLY SET
            ];
    
            $jobCard = JobCard::create($jobCardData);
    
            DB::commit();
    
            return response()->json([
                'success' => true,
                'message' => 'Job card created successfully',
                'data' => $jobCard->load(['vehicleItem', 'customer', 'technician', 'serviceAdvisor'])
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
                'message' => 'Failed to create job card',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id): JsonResponse
    {
        try {
            $jobCard = JobCard::with([
                'vehicleItem',
                'customer',
                'technician',
                'serviceAdvisor',
                'jobParts.part',
                'jobParts.warehouse',
                'jobParts.reservationMovement',
                'jobParts.issueMovement',
                'jobParts.returnMovement'
            ])
            ->byCompany($this->getCompanyId())
            ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $jobCard
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Job card not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        try {
            $jobCard = JobCard::byCompany($this->getCompanyId())->findOrFail($id);

            $validated = $request->validate([
                // Basic Information
                'customer_name' => 'sometimes|required|string|max:255',
                'customer_phone' => 'sometimes|nullable|string|max:20',
                'customer_email' => 'sometimes|nullable|email|max:255',
                
                // Vehicle Details
                'vehicle_license' => 'sometimes|nullable|string|max:50',
                'vehicle_vin' => 'sometimes|nullable|string|max:100',
                'vehicle_make' => 'sometimes|nullable|string|max:100',
                'vehicle_model' => 'sometimes|nullable|string|max:100',
                'vehicle_year' => 'sometimes|nullable|integer|min:1900|max:' . (date('Y') + 1),
                'vehicle_color' => 'sometimes|nullable|string|max:50',
                'vehicle_mileage' => 'sometimes|nullable|integer|min:0',
                
                // Job Details
                'service_type' => 'sometimes|required|in:maintenance,repair,inspection,warranty,diagnostic,recall',
                'work_description' => 'sometimes|required|string|max:1000',
                'customer_complaint' => 'sometimes|nullable|string|max:1000',
                'technician_findings' => 'sometimes|nullable|string|max:1000',
                
                // Status & Scheduling
                'status' => 'sometimes|in:pending,in_progress,waiting_parts,waiting_approval,completed,cancelled,on_hold',
                'estimated_start' => 'sometimes|nullable|date',
                'estimated_completion' => 'sometimes|nullable|date|after_or_equal:estimated_start',
                'actual_start' => 'sometimes|nullable|date',
                'actual_completion' => 'sometimes|nullable|date',
                
                // Costs
                'estimated_labor_cost' => 'sometimes|nullable|numeric|min:0',
                'estimated_parts_cost' => 'sometimes|nullable|numeric|min:0',
                'actual_labor_cost' => 'sometimes|nullable|numeric|min:0',
                'actual_parts_cost' => 'sometimes|nullable|numeric|min:0',
                
                // Assignment
                'assigned_technician_id' => 'nullable|exists:tenant_users,id',
                'service_advisor_id' => 'nullable|exists:tenant_users,id',
                
                // Notes
                'technician_notes' => 'sometimes|nullable|string|max:1000',
                'service_advisor_notes' => 'sometimes|nullable|string|max:1000',
                'customer_notes' => 'sometimes|nullable|string|max:1000',
            ]);

            DB::beginTransaction();

            // Update job card
            $jobCard->update($validated);

            // Recalculate total costs if cost fields were updated
            if (isset($validated['estimated_labor_cost']) || isset($validated['estimated_parts_cost'])) {
                $jobCard->total_estimated_cost = 
                    ($jobCard->estimated_labor_cost ?? 0) + 
                    ($jobCard->estimated_parts_cost ?? 0);
            }

            if (isset($validated['actual_labor_cost']) || isset($validated['actual_parts_cost'])) {
                $jobCard->total_actual_cost = 
                    ($jobCard->actual_labor_cost ?? 0) + 
                    ($jobCard->actual_parts_cost ?? 0);
            }

            $jobCard->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Job card updated successfully',
                'data' => $jobCard->load(['vehicleItem', 'customer', 'technician', 'serviceAdvisor'])
            ]);

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
                'message' => 'Failed to update job card',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id): JsonResponse
    {
        try {
            $jobCard = JobCard::byCompany($this->getCompanyId())->findOrFail($id);

            // Check if job card has parts issued or used
            if ($jobCard->jobParts()->whereIn('status', ['issued', 'used'])->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete job card that has parts issued or used'
                ], 422);
            }

            DB::beginTransaction();

            // Delete associated job parts
            $jobCard->jobParts()->delete();

            // Delete the job card
            $jobCard->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Job card deleted successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete job card',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updateStatus(Request $request, $id): JsonResponse
    {
        try {
            $jobCard = JobCard::byCompany($this->getCompanyId())->findOrFail($id);

            $validated = $request->validate([
                'status' => 'required|in:pending,in_progress,waiting_parts,waiting_approval,completed,cancelled,on_hold',
                'notes' => 'nullable|string|max:1000',
            ]);

            DB::beginTransaction();

            $oldStatus = $jobCard->status;
            $jobCard->update([
                'status' => $validated['status'],
            ]);

            // Set actual start time when moving to in_progress
            if ($validated['status'] === 'in_progress' && !$jobCard->actual_start) {
                $jobCard->actual_start = now();
            }

            // Set actual completion time when moving to completed
            if ($validated['status'] === 'completed' && !$jobCard->actual_completion) {
                $jobCard->actual_completion = now();
            }

            $jobCard->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Job card status updated successfully',
                'data' => [
                    'job_card' => $jobCard,
                    'old_status' => $oldStatus,
                    'new_status' => $jobCard->status,
                ]
            ]);

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
                'message' => 'Failed to update job card status',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}