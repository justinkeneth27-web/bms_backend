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
     * Transform a Household Eloquent model to React camelCase interface.
     */
    public static function transformHousehold(Household $h): array
    {
        return [
            'id' => $h->id,
            'headOfFamily' => $h->head_of_family,
            'birthday' => $h->birthday ? (is_string($h->birthday) ? $h->birthday : $h->birthday->format('Y-m-d')) : '',
            'age' => (int) $h->age,
            'contactNumber' => $h->contact_number,
            'numberOfMembers' => (int) $h->number_of_members,
            'houseNumber' => $h->house_number ?? '',
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
            'lastStatusUpdate' => $h->last_status_update ? $h->last_status_update->toIso8601String() : Carbon::now('Asia/Manila')->toIso8601String(),
            'createdAt' => $h->created_at ? $h->created_at->toIso8601String() : Carbon::now('Asia/Manila')->toIso8601String(),
        ];
    }

    /**
     * List households with filtering and search.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Household::with('evacuationCenter');

        // Server-Side Jurisdiction Query Scoping (DPA / RA 10173 Compliance)
        $user = auth('sanctum')->user() ?: $request->user();
        if ($user && !empty($user->barangay)) {
            $query->where('barangay', $user->barangay);
        } elseif ($request->filled('barangay')) {
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

        $transformed = $households->map(function ($h) {
            return self::transformHousehold($h);
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
            'birthday' => 'nullable|string',
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
        $id = $request->input('id') ?? ('hh-' . $bShort . '-' . (Household::where('barangay', $validated['barangay'])->count() + 1));

        $household = Household::create([
            'id' => $id,
            'head_of_family' => $validated['headOfFamily'],
            'birthday' => !empty($validated['birthday']) ? $validated['birthday'] : null,
            'age' => $validated['age'],
            'contact_number' => $validated['contactNumber'],
            'number_of_members' => $validated['numberOfMembers'],
            'house_number' => $validated['houseNumber'] ?? null,
            'purok' => $validated['purok'],
            'barangay' => $validated['barangay'],
            'complete_address' => $validated['completeAddress'],
            'location' => $validated['location'],
            'status' => $validated['status'],
            'status_note' => $validated['statusNote'] ?? null,
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

        return response()->json(['success' => true, 'data' => self::transformHousehold($household)], 201);
    }

    /**
     * Update an existing household record.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $household = Household::findOrFail($id);

        // Security Guard: Prevent cross-jurisdiction updates
        $user = auth('sanctum')->user() ?: $request->user();
        if ($user && !empty($user->barangay) && $household->barangay !== $user->barangay) {
            return response()->json([
                'success' => false,
                'message' => "Unauthorized cross-jurisdiction action: this record belongs to Barangay {$household->barangay}.",
            ], 403);
        }

        $validated = $request->validate([
            'headOfFamily' => 'sometimes|required|string|max:150',
            'birthday' => 'nullable|string',
            'age' => 'sometimes|required|integer|min:1',
            'contactNumber' => 'sometimes|required|string|max:50',
            'numberOfMembers' => 'sometimes|required|integer|min:1',
            'houseNumber' => 'nullable|string|max:50',
            'purok' => 'sometimes|required|string',
            'barangay' => 'sometimes|required|in:Buang,Nagsipit',
            'completeAddress' => 'sometimes|required|string',
            'location' => 'nullable|array',
            'status' => 'sometimes|required|in:SAFE,MODERATE,EMERGENCY',
            'statusNote' => 'nullable|string',
            'evacuationCenterId' => 'nullable|string',
            'verificationStatus' => 'sometimes|required|in:Pending,Verified',
            'specialNeeds' => 'nullable|array',
            'familyMembers' => 'nullable|array',
        ]);

        if (isset($validated['headOfFamily'])) $household->head_of_family = $validated['headOfFamily'];
        if (array_key_exists('birthday', $validated)) $household->birthday = !empty($validated['birthday']) ? $validated['birthday'] : null;
        if (isset($validated['age'])) $household->age = $validated['age'];
        if (isset($validated['contactNumber'])) $household->contact_number = $validated['contactNumber'];
        if (isset($validated['numberOfMembers'])) $household->number_of_members = $validated['numberOfMembers'];
        if (array_key_exists('houseNumber', $validated)) $household->house_number = $validated['houseNumber'];
        if (isset($validated['purok'])) $household->purok = $validated['purok'];
        if (isset($validated['barangay'])) $household->barangay = $validated['barangay'];
        if (isset($validated['completeAddress'])) $household->complete_address = $validated['completeAddress'];
        if (isset($validated['location'])) $household->location = $validated['location'];
        if (isset($validated['status'])) $household->status = $validated['status'];
        if (array_key_exists('statusNote', $validated)) $household->status_note = $validated['statusNote'];
        if (array_key_exists('evacuationCenterId', $validated)) {
            $oldCenter = $household->evacuation_center_id;
            $household->evacuation_center_id = !empty($validated['evacuationCenterId']) ? $validated['evacuationCenterId'] : null;
            if ($oldCenter) EvacuationCenter::find($oldCenter)?->recalculateAssignedCount();
            if ($household->evacuation_center_id) EvacuationCenter::find($household->evacuation_center_id)?->recalculateAssignedCount();
        }
        if (isset($validated['verificationStatus'])) $household->verification_status = $validated['verificationStatus'];
        if (isset($validated['specialNeeds'])) $household->special_needs = $validated['specialNeeds'];
        if (isset($validated['familyMembers'])) $household->family_members = $validated['familyMembers'];
        $household->last_status_update = Carbon::now('Asia/Manila');
        $household->save();

        ActivityLog::record(
            'EDIT',
            $request->input('officerName', 'Barangay DRRM Desk'),
            $request->input('officerRole', 'DRRM Officer'),
            $household->barangay,
            "Household ID {$household->id} ({$household->head_of_family})",
            "Updated household record for {$household->head_of_family} in {$household->purok}."
        );

        return response()->json(['success' => true, 'data' => self::transformHousehold($household)]);
    }

    /**
     * Broadcast alert status to all households of a barangay.
     */
    public function broadcastStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'barangay' => 'required|in:Buang,Nagsipit',
            'status' => 'required|in:SAFE,MODERATE,EMERGENCY',
            'statusNote' => 'nullable|string',
            'officerName' => 'nullable|string',
            'officerRole' => 'nullable|string',
        ]);

        // Security Guard: Prevent broadcasting outside assigned jurisdiction
        $user = auth('sanctum')->user() ?: $request->user();
        if ($user && !empty($user->barangay) && $validated['barangay'] !== $user->barangay) {
            return response()->json([
                'success' => false,
                'message' => "Unauthorized: You can only broadcast status directives for your jurisdiction (Barangay {$user->barangay}).",
            ], 403);
        }

        Household::where('barangay', $validated['barangay'])->update([
            'status' => $validated['status'],
            'status_note' => $validated['statusNote'] ?? null,
            'last_status_update' => Carbon::now('Asia/Manila'),
        ]);

        ActivityLog::record(
            'STATUS_CHANGE',
            $validated['officerName'] ?? 'DRRM Operations Head',
            $validated['officerRole'] ?? 'Command Center',
            $validated['barangay'],
            "All Residents of Barangay {$validated['barangay']}",
            "Broadcasted status '{$validated['status']}' to all households. Directive: " . ($validated['statusNote'] ?? 'None')
        );

        return response()->json([
            'success' => true,
            'message' => "Successfully broadcasted status {$validated['status']} to Barangay {$validated['barangay']}.",
        ]);
    }

    /**
     * Update household alert status.
     */
    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $household = Household::findOrFail($id);
        $oldStatus = $household->status;

        // Security Guard: Prevent cross-jurisdiction status changes
        $user = auth('sanctum')->user() ?: $request->user();
        if ($user && !empty($user->barangay) && $household->barangay !== $user->barangay) {
            return response()->json([
                'success' => false,
                'message' => "Unauthorized cross-jurisdiction action: this record belongs to Barangay {$household->barangay}.",
            ], 403);
        }

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
            $household->evacuation_center_id = !empty($validated['evacuationCenterId']) ? $validated['evacuationCenterId'] : null;
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

        return response()->json(['success' => true, 'data' => self::transformHousehold($household)]);
    }

    /**
     * Delete household.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $household = Household::findOrFail($id);

        // Security Guard: Prevent cross-jurisdiction deletions
        $user = auth('sanctum')->user() ?: $request->user();
        if ($user && !empty($user->barangay) && $household->barangay !== $user->barangay) {
            return response()->json([
                'success' => false,
                'message' => "Unauthorized cross-jurisdiction action: this record belongs to Barangay {$household->barangay}.",
            ], 403);
        }

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
