<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\EvacuationCenter;
use App\Models\Household;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class HouseholdController extends Controller
{
    /**
     * List households with filtering and search.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Household::with('evacuationCenter');

        if ($request->filled('barangay')) {
            $query->where('barangay', $request->query('barangay'));
        }

        if ($request->filled('purok') && $request->query('purok') !== 'All Puroks') {
            $query->where('purok', $request->query('purok'));
        }

        if ($request->filled('status') && $request->query('status') !== 'ALL') {
            $query->where('status', strtoupper($request->query('status')));
        }

        if ($request->filled('verification') && $request->query('verification') !== 'ALL') {
            $query->where('verification_status', $request->query('verification'));
        }

        if ($request->filled('search')) {
            $search = '%' . $request->query('search') . '%';
            $query->where(function ($q) use ($search) {
                $q->where('head_of_family', 'ILIKE', $search)
                  ->orWhere('id', 'ILIKE', $search)
                  ->orWhere('contact_number', 'ILIKE', $search)
                  ->orWhere('complete_address', 'ILIKE', $search);
            });
        }

        $households = $query->orderBy('created_at', 'desc')->get();

        // Transform to match React interface
        $transformed = $households->map(function ($h) {
            return [
                'id' => $h->id,
                'headOfFamily' => $h->head_of_family,
                'birthday' => $h->birthday ? $h->birthday->format('Y-m-d') : null,
                'age' => $h->age,
                'contactNumber' => $h->contact_number,
                'numberOfMembers' => $h->number_of_members,
                'houseNumber' => $h->house_number,
                'purok' => $h->purok,
                'barangay' => $h->barangay,
                'completeAddress' => $h->complete_address,
                'location' => $h->location ?? ['lat' => 13.3421, 'lng' => 123.6980],
                'status' => $h->status,
                'statusNote' => $h->status_note,
                'evacuationCenterId' => $h->evacuation_center_id,
                'verificationStatus' => $h->verification_status,
                'specialNeeds' => $h->special_needs ?? [],
                'familyMembers' => $h->family_members ?? [],
                'lastStatusUpdate' => $h->last_status_update ? $h->last_status_update->toIso8601String() : null,
                'createdAt' => $h->created_at->toIso8601String(),
            ];
        });

        return response()->json([
            'success' => true,
            'count' => $transformed->count(),
            'data' => $transformed,
        ]);
    }

    /**
     * Store new household.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'headOfFamily' => 'required|string|max:150',
            'birthday' => 'nullable|date',
            'age' => 'required|integer|min:1',
            'contactNumber' => 'required|string|max:50',
            'numberOfMembers' => 'required|integer|min:1',
            'houseNumber' => 'nullable|string|max:50',
            'purok' => 'required|string',
            'barangay' => 'required|in:Buang,Nagsipit',
            'completeAddress' => 'required|string',
            'location' => 'required|array',
            'location.lat' => 'required|numeric',
            'location.lng' => 'required|numeric',
            'status' => 'required|in:SAFE,MODERATE,EMERGENCY',
            'statusNote' => 'nullable|string',
            'evacuationCenterId' => 'nullable|string|exists:evacuation_centers,id',
            'verificationStatus' => 'required|in:Pending,Verified',
            'specialNeeds' => 'nullable|array',
            'familyMembers' => 'nullable|array',
        ]);

        $bShort = strtolower(substr($validated['barangay'], 0, 3));
        $id = $request->input('id') ?? 'hh-' . $bShort . '-' . (Household::where('barangay', $validated['barangay'])->count() + 1);

        $household = Household::create([
            'id' => $id,
            'head_of_family' => $validated['headOfFamily'],
            'birthday' => $validated['birthday'] ?? null,
            'age' => $validated['age'],
            'contact_number' => $validated['contactNumber'],
            'number_of_members' => $validated['numberOfMembers'],
            'house_number' => $validated['houseNumber'] ?? null,
            'purok' => $validated['purok'],
            'barangay' => $validated['barangay'],
            'complete_address' => $validated['completeAddress'],
            'location' => $validated['location'],
            'status' => $validated['status'],
            'statusNote' => $validated['statusNote'] ?? null,
            'evacuation_center_id' => $validated['evacuationCenterId'] ?? null,
            'verification_status' => $validated['verificationStatus'],
            'special_needs' => $validated['specialNeeds'] ?? [],
            'family_members' => $validated['familyMembers'] ?? [],
            'last_status_update' => Carbon::now('Asia/Manila'),
        ]);

        if ($household->evacuation_center_id) {
            EvacuationCenter::find($household->evacuation_center_id)?->recalculateAssignedCount();
        }

        ActivityLog::record(
            'CREATE',
            $request->input('officerName', 'Barangay DRRM Desk'),
            $request->input('officerRole', 'DRRM Officer'),
            $household->barangay,
            "Household ID {$household->id} ({$household->head_of_family})",
            "Registered new household in {$household->purok} with {$household->number_of_members} members. Status: {$household->status}."
        );

        return response()->json(['success' => true, 'data' => $household], 201);
    }

    /**
     * Update household alert status.
     */
    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $household = Household::findOrFail($id);
        $oldStatus = $household->status;

        $validated = $request->validate([
            'status' => 'required|in:SAFE,MODERATE,EMERGENCY',
            'statusNote' => 'nullable|string',
            'evacuationCenterId' => 'nullable|string',
            'officerName' => 'nullable|string',
            'officerRole' => 'nullable|string',
        ]);

        $household->status = $validated['status'];
        if (isset($validated['statusNote'])) {
            $household->status_note = $validated['statusNote'];
        }
        if (array_key_exists('evacuationCenterId', $validated)) {
            $oldCenter = $household->evacuation_center_id;
            $household->evacuation_center_id = $validated['evacuationCenterId'];
            if ($oldCenter) {
                EvacuationCenter::find($oldCenter)?->recalculateAssignedCount();
            }
            if ($household->evacuation_center_id) {
                EvacuationCenter::find($household->evacuation_center_id)?->recalculateAssignedCount();
            }
        }
        $household->last_status_update = Carbon::now('Asia/Manila');
        $household->save();

        ActivityLog::record(
            'STATUS_CHANGE',
            $validated['officerName'] ?? 'Barangay DRRM Desk',
            $validated['officerRole'] ?? 'Duty Officer',
            $household->barangay,
            "Household ID {$household->id} ({$household->head_of_family})",
            "Changed alert status from {$oldStatus} to {$household->status}."
        );

        return response()->json(['success' => true, 'data' => $household]);
    }

    /**
     * Delete household.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $household = Household::findOrFail($id);
        $b = $household->barangay;
        $name = $household->head_of_family;
        $evacId = $household->evacuation_center_id;

        $household->delete();

        if ($evacId) {
            EvacuationCenter::find($evacId)?->recalculateAssignedCount();
        }

        ActivityLog::record(
            'DELETE',
            $request->input('officerName', 'Barangay Administrator'),
            $request->input('officerRole', 'Super Admin'),
            $b,
            "Household ID {$id} ({$name})",
            "Record permanently purged from the official database registry."
        );

        return response()->json(['success' => true, 'message' => 'Household deleted successfully.']);
    }
}
