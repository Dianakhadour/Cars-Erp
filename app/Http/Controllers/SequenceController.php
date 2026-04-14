<?php

namespace App\Http\Controllers;

use App\Models\Tenant\NumberSequence;
use App\Models\Tenant\Company;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class SequenceController extends Controller
{
    
    public function index(string $companyId): JsonResponse
    {
        $company = Company::find($companyId);

        if (!$company) {
            return response()->json([
                'error' => 'Company not found'
            ], 404);
        }

        $sequences = NumberSequence::where('company_id', $companyId)->get();

        return response()->json([
            'sequences' => $sequences
        ]);
    }

    /**
     * Create a new number sequence
     */
    public function store(Request $request, string $companyId): JsonResponse
    {
        $request->validate([
            'doc_type' => 'required|string|max:50',
            'prefix' => 'nullable|string|max:10',
            'next_number' => 'required|integer|min:1',
            'padding' => 'required|integer|min:1|max:10',
            'yearly_reset' => 'boolean',
        ]);

        $company = Company::find($companyId);

        if (!$company) {
            return response()->json([
                'error' => 'Company not found'
            ], 404);
        }

        // Check if sequence already exists for this doc type
        $existingSequence = NumberSequence::where('company_id', $companyId)
            ->where('doc_type', $request->doc_type)
            ->first();

        if ($existingSequence) {
            throw ValidationException::withMessages([
                'doc_type' => 'Number sequence already exists for this document type'
            ]);
        }

        $sequence = NumberSequence::create([
            'company_id' => $companyId,
            'doc_type' => $request->doc_type,
            'prefix' => $request->prefix,
            'next_number' => $request->next_number,
            'padding' => $request->padding,
            'yearly_reset' => $request->yearly_reset ?? false,
        ]);

        return response()->json([
            'message' => 'Number sequence created successfully',
            'sequence' => $sequence
        ], 201);
    }

    /**
     * Update number sequence
     */
    public function update(Request $request, string $companyId, string $sequenceId): JsonResponse
    {
        $request->validate([
            'prefix' => 'nullable|string|max:10',
            'next_number' => 'sometimes|integer|min:1',
            'padding' => 'sometimes|integer|min:1|max:10',
            'yearly_reset' => 'boolean',
        ]);

        $sequence = NumberSequence::where('company_id', $companyId)
            ->where('id', $sequenceId)
            ->first();

        if (!$sequence) {
            return response()->json([
                'error' => 'Number sequence not found'
            ], 404);
        }

        $sequence->update($request->all());

        return response()->json([
            'message' => 'Number sequence updated successfully',
            'sequence' => $sequence
        ]);
    }

    /**
     * Delete number sequence
     */
    public function destroy(string $companyId, string $sequenceId): JsonResponse
    {
        $sequence = NumberSequence::where('company_id', $companyId)
            ->where('id', $sequenceId)
            ->first();

        if (!$sequence) {
            return response()->json([
                'error' => 'Number sequence not found'
            ], 404);
        }

        $sequence->delete();

        return response()->json([
            'message' => 'Number sequence deleted successfully'
        ]);
    }

    /**
     * Generate next number from sequence
     */
    public function generateNext(Request $request, string $companyId, string $sequenceId): JsonResponse
    {
        $sequence = NumberSequence::where('company_id', $companyId)
            ->where('id', $sequenceId)
            ->first();

        if (!$sequence) {
            return response()->json([
                'error' => 'Number sequence not found'
            ], 404);
        }

        $nextNumber = $sequence->getNextNumber();

        return response()->json([
            'next_number' => $nextNumber,
            'sequence' => $sequence
        ]);
    }
}