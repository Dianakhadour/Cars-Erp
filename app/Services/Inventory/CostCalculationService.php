<?php

namespace App\Services\Inventory;

use App\Models\Tenant\Part;
use App\Models\Tenant\PartStockMovement;
use Illuminate\Support\Facades\DB;

class CostCalculationService
{
    public function calculateWeightedAverageCost($partId, $warehouseId = null)
    {
        $query = PartStockMovement::where('part_id', $partId)
            ->where('status', 'completed')
            ->whereIn('movement_type', ['receipt', 'adjustment']);

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        $movements = $query->orderBy('movement_date')->get();

        $totalQuantity = 0;
        $totalValue = 0;

        foreach ($movements as $movement) {
            if (in_array($movement->movement_type, ['receipt', 'adjustment_in'])) {
                $totalQuantity += $movement->quantity;
                $totalValue += $movement->total_cost;
            } elseif (in_array($movement->movement_type, ['issue', 'adjustment_out'])) {
                $totalQuantity -= $movement->quantity;
                $totalValue -= $movement->quantity * ($totalValue / max($totalQuantity + $movement->quantity, 1));
            }
        }

        return $totalQuantity > 0 ? $totalValue / $totalQuantity : 0;
    }

    public function updatePartAverageCost($partId)
    {
        $part = Part::findOrFail($partId);
        $averageCost = $this->calculateWeightedAverageCost($partId);

        $part->update([
            'cost_price' => $averageCost,
            'last_purchase_cost' => $averageCost,
        ]);

        return $averageCost;
    }
}