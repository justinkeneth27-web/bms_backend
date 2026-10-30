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

        // Server-Side Jurisdiction Query Scoping
        $user = auth('sanctum')->user() ?: $request->user();
        if ($user && !empty($user->barangay)) {
            $query->where('barangay', $user->barangay);
        } elseif ($request->filled('barangay')) {
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

        $user = auth('sanctum')->user() ?: $request->user();
        if ($user && !empty($user->barangay) && $center->barangay !== $user->barangay) {
            return response()->json([
                'success' => false,
                'message' => "Unauthorized cross-jurisdiction action: this evacuation center belongs to Barangay {$center->barangay}.",
            ], 403);
        }

        $validated = $request->validate([
            'householdId' => 'required|string|exists:households,id',
            'officerName' => 'nullable|string',
            'officerRole' => 'nullable|string',
        ]);

        $household = Household::findOrFail($validated['householdId']);
        if ($household->barangay !== $center->barangay) {
            return response()->json([
                'success' => false,
                'message' => "Cannot assign household from Barangay {$household->barangay} to evacuation center in Barangay {$center->barangay}.",
            ], 422);
        }
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

    /**
     * Store or update an evacuation center.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => 'nullable|string',
            'name' => 'required|string|max:150',
            'barangay' => 'required|in:Buang,Nagsipit',
            'purok' => 'required|string',
            'capacityFamilies' => 'required|integer|min:0',
            'assignedCount' => 'nullable|integer',
            'location' => 'required|array',
            'managerName' => 'nullable|string',
            'managerContact' => 'nullable|string',
            'status' => 'required|in:Open,Full,Standby',
            'facilities' => 'nullable|array',
        ]);

        $id = $validated['id'] ?? ('center-' . strtolower(substr($validated['barangay'], 0, 3)) . '-' . time());

        $center = EvacuationCenter::updateOrCreate(
            ['id' => $id],
            [
                'name' => $validated['name'],
                'barangay' => $validated['barangay'],
                'purok' => $validated['purok'],
                'capacity_families' => $validated['capacityFamilies'],
                'assigned_count' => $validated['assignedCount'] ?? 0,
                'location' => $validated['location'],
                'manager_name' => $validated['managerName'] ?? 'DRRM Evacuation Lead',
                'manager_contact' => $validated['managerContact'] ?? '0917-000-0000',
                'status' => $validated['status'],
                'facilities' => $validated['facilities'] ?? [],
            ]
        );

        ActivityLog::record(
            'CREATE',
            $request->input('officerName', 'Barangay DRRM Desk'),
            $request->input('officerRole', 'DRRM Lead'),
            $center->barangay,
            "Evacuation Center: {$center->name}",
            "Registered/Updated evacuation center in {$center->purok} with capacity of {$center->capacity_families} families."
        );

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $center->id,
                'name' => $center->name,
                'barangay' => $center->barangay,
                'purok' => $center->purok,
                'capacityFamilies' => $center->capacity_families,
                'assignedCount' => $center->assigned_count,
                'location' => $center->location,
                'managerName' => $center->manager_name,
                'managerContact' => $center->manager_contact,
                'status' => $center->status,
                'facilities' => $center->facilities ?? [],
            ],
        ], 201);
    }

    /**
     * Delete an evacuation center.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $center = EvacuationCenter::find($id);
        if (!$center) {
            return response()->json(['success' => false, 'message' => 'Center not found'], 404);
        }

        $user = auth('sanctum')->user() ?: $request->user();
        if ($user && !empty($user->barangay) && $center->barangay !== $user->barangay) {
            return response()->json([
                'success' => false,
                'message' => "Unauthorized cross-jurisdiction action: this evacuation center belongs to Barangay {$center->barangay}.",
            ], 403);
        }

        // Unassign households from this center
        Household::where('evacuation_center_id', $id)->update(['evacuation_center_id' => null]);

        $name = $center->name;
        $brgy = $center->barangay;
        $center->delete();

        ActivityLog::record(
            'DELETE',
            $request->input('officerName', 'Barangay DRRM Desk'),
            $request->input('officerRole', 'DRRM Lead'),
            $brgy,
            "Evacuation Center: {$name}",
            "Deleted evacuation facility from database."
        );

        return response()->json(['success' => true, 'message' => 'Evacuation center deleted']);
    }
}
