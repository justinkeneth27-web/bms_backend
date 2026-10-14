<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\EvacuationCenter;
use App\Models\Household;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EvacuationCenterController extends Controller
{
    /**
     * List evacuation centers.
     */
    public function index(Request $request): JsonResponse
    {
        $query = EvacuationCenter::query();

        if ($request->filled('barangay')) {
            $query->where('barangay', $request->query('barangay'));
        }

        $centers = $query->orderBy('name')->get();

        $transformed = $centers->map(function ($c) {
            return [
                'id' => $c->id,
                'name' => $c->name,
                'barangay' => $c->barangay,
                'purok' => $c->purok,
                'capacityFamilies' => $c->capacity_families,
                'assignedCount' => $c->assigned_count,
                'location' => $c->location,
                'managerName' => $c->manager_name,
                'managerContact' => $c->manager_contact,
                'status' => $c->status,
                'facilities' => $c->facilities ?? [],
            ];
        });

        return response()->json(['success' => true, 'data' => $transformed]);
    }

    /**
     * Assign a household to an evacuation center.
     */
    public function assignHousehold(Request $request, string $id): JsonResponse
    {
        $center = EvacuationCenter::findOrFail($id);

        $validated = $request->validate([
            'householdId' => 'required|string|exists:households,id',
            'officerName' => 'nullable|string',
            'officerRole' => 'nullable|string',
        ]);

        $household = Household::findOrFail($validated['householdId']);
        $household->evacuation_center_id = $center->id;
        $household->status = 'EMERGENCY';
        $household->save();

        $center->recalculateAssignedCount();

        ActivityLog::record(
            'CENTER_ASSIGNED',
            $validated['officerName'] ?? 'Evacuation Officer',
            $validated['officerRole'] ?? 'DRRM Lead',
            $center->barangay,
            "Household ID {$household->id} -> {$center->name}",
            "Assigned family to {$center->name} ({$center->purok}). Center occupancy: {$center->assigned_count}/{$center->capacity_families}."
        );

        return response()->json([
            'success' => true,
            'message' => "Household assigned to {$center->name}",
            'center' => $center,
        ]);
    }
}
