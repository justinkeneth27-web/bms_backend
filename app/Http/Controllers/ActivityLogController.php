<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ActivityLogController extends Controller
{
    /**
     * List activity logs with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ActivityLog::query();

        if ($request->filled('barangay')) {
            $query->where('barangay', $request->query('barangay'));
        }

        if ($request->filled('action_type') && $request->query('action_type') !== 'ALL') {
            $query->where('action_type', $request->query('action_type'));
        }

        if ($request->filled('shift') && $request->query('shift') !== 'ALL') {
            $isWorking = ($request->query('shift') === 'WORKING');
            $query->where('is_working_hours', $isWorking);
        }

        $logs = $query->orderBy('timestamp', 'desc')->get();

        $transformed = $logs->map(function ($log) {
            return [
                'id' => $log->id,
                'timestamp' => $log->timestamp->format('Y-m-d h:i:s A'),
                'actionType' => $log->action_type,
                'actorName' => $log->actor_name,
                'actorRole' => $log->actor_role,
                'barangay' => $log->barangay,
                'target' => $log->target,
                'details' => $log->details,
                'isWorkingHours' => $log->is_working_hours,
                'workShiftTag' => $log->work_shift_tag,
                'terminal' => $log->terminal,
            ];
        });

        // Summary counts
        $total = $logs->count();
        $workingHours = $logs->where('is_working_hours', true)->count();
        $offHours = $total - $workingHours;
        $mutations = $logs->whereIn('action_type', ['EDIT', 'DELETE', 'STATUS_CHANGE'])->count();

        return response()->json([
            'success' => true,
            'summary' => [
                'total' => $total,
                'workingHours' => $workingHours,
                'offHours' => $offHours,
                'mutations' => $mutations,
            ],
            'data' => $transformed,
        ]);
    }

    /**
     * Log new manual or custom operational action.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'actionType' => 'required|string',
            'actorName' => 'required|string',
            'actorRole' => 'required|string',
            'barangay' => 'required|in:Buang,Nagsipit',
            'target' => 'required|string',
            'details' => 'required|string',
            'terminal' => 'nullable|string',
        ]);

        $log = ActivityLog::record(
            $validated['actionType'],
            $validated['actorName'],
            $validated['actorRole'],
            $validated['barangay'],
            $validated['target'],
            $validated['details'],
            $validated['terminal'] ?? null
        );

        return response()->json(['success' => true, 'data' => $log], 201);
    }

    /**
     * Export audit logs to formatted CSV with UTF-8 BOM.
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $barangay = $request->query('barangay', 'All');
        $query = ActivityLog::query();
        if ($barangay !== 'All') {
            $query->where('barangay', $barangay);
        }
        $logs = $query->orderBy('timestamp', 'desc')->get();

        $filename = sprintf(
            'barangay_%s_audit_logs_%s.csv',
            strtolower($barangay),
            Carbon::now('Asia/Manila')->format('Y_m_d_His')
        );

        return response()->streamDownload(function () use ($logs) {
            $handle = fopen('php://output', 'w');
            // Write UTF-8 BOM
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, [
                'Log ID',
                'Timestamp (Asia/Manila)',
                'Barangay',
                'Action Type',
                'Actor Name',
                'Actor Role',
                'Operational Shift',
                'Target Entity',
                'Details & Notes',
                'Terminal',
            ]);

            foreach ($logs as $log) {
                fputcsv($handle, [
                    $log->id,
                    '="' . $log->timestamp->format('Y-m-d h:i:s A') . '"',
                    $log->barangay,
                    $log->action_type,
                    $log->actor_name,
                    $log->actor_role,
                    $log->work_shift_tag,
                    $log->target,
                    $log->details,
                    $log->terminal,
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
