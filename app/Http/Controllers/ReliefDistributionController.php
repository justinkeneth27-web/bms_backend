<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Household;
use App\Models\ReliefDistribution;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReliefDistributionController extends Controller
{
    /**
     * List relief distributions.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ReliefDistribution::query();

        // Server-Side Jurisdiction Query Scoping
        $user = auth('sanctum')->user() ?: $request->user();
        if ($user && !empty($user->barangay)) {
            $query->where('barangay', $user->barangay);
        } elseif ($request->filled('barangay')) {
            $query->where('barangay', $request->query('barangay'));
        }

        if ($request->filled('household_id')) {
            $query->where('household_id', $request->query('household_id'));
        }

        $records = $query->orderBy('distributed_at', 'desc')->get();

        $transformed = $records->map(function ($r) {
            return [
                'id' => $r->id,
                'householdId' => $r->household_id,
                'recipientName' => $r->recipient_name,
                'barangay' => $r->barangay,
                'purok' => $r->purok,
                'item' => $r->item,
                'qty' => $r->qty,
                'location' => $r->location,
                'timestamp' => $r->distributed_at ? $r->distributed_at->toIso8601String() : null,
                'officerName' => $r->officer_name,
                'notes' => $r->notes,
            ];
        });

        return response()->json(['success' => true, 'data' => $transformed]);
    }

    /**
     * Record new distribution.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'householdId' => 'required|string|exists:households,id',
            'item' => 'required|string|max:150',
            'qty' => 'required|string|max:50',
            'location' => 'required|string|max:150',
            'officerName' => 'required|string|max:150',
            'notes' => 'nullable|string',
        ]);

        $household = Household::findOrFail($validated['householdId']);

        $id = 'rel-' . strtolower(substr($household->barangay, 0, 3)) . '-' . (ReliefDistribution::count() + 1);

        $record = ReliefDistribution::create([
            'id' => $id,
            'household_id' => $household->id,
            'recipient_name' => $household->head_of_family,
            'barangay' => $household->barangay,
            'purok' => $household->purok,
            'item' => $validated['item'],
            'qty' => $validated['qty'],
            'location' => $validated['location'],
            'distributed_at' => Carbon::now('Asia/Manila'),
            'officer_name' => $validated['officerName'],
            'notes' => $validated['notes'] ?? null,
        ]);

        ActivityLog::record(
            'RELIEF_DISTRIBUTED',
            $validated['officerName'],
            'Relief Operations Officer',
            $household->barangay,
            "{$household->id} ({$household->head_of_family})",
            "Dispatched {$validated['qty']} x {$validated['item']} to {$household->head_of_family} at {$validated['location']}."
        );

        return response()->json(['success' => true, 'data' => $record], 201);
    }
}
