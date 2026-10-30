<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\BarangayConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BarangayConfigController extends Controller
{
    /**
     * Get configs for all barangays.
     */
    public function index(): JsonResponse
    {
        $configs = BarangayConfig::all();
        $transformed = [];
        foreach ($configs as $c) {
            $transformed[$c->name] = [
                'name' => $c->name,
                'center' => [
                    'lat' => (float) $c->center_lat,
                    'lng' => (float) $c->center_lng,
                ],
                'puroks' => $c->puroks ?? [],
                'hotline' => $c->hotline,
                'evacuationLead' => $c->evacuation_lead,
                'alternateHotline' => $c->alternate_hotline,
                'emergencyRadioChannel' => $c->emergency_radio_channel,
            ];
        }

        return response()->json(['success' => true, 'data' => $transformed]);
    }

    /**
     * Update or create barangay config.
     */
    public function update(Request $request, string $name): JsonResponse
    {
        $validated = $request->validate([
            'hotline' => 'nullable|string|max:100',
            'evacuationLead' => 'nullable|string|max:150',
            'alternateHotline' => 'nullable|string|max:100',
            'emergencyRadioChannel' => 'nullable|string|max:100',
            'center' => 'nullable|array',
            'center.lat' => 'nullable|numeric',
            'center.lng' => 'nullable|numeric',
            'puroks' => 'nullable|array',
        ]);

        $config = BarangayConfig::firstOrNew(['name' => $name]);
        if (!empty($validated['hotline'])) {
            $config->hotline = $validated['hotline'];
        }
        if (!empty($validated['evacuationLead'])) {
            $config->evacuation_lead = $validated['evacuationLead'];
        }
        if (array_key_exists('alternateHotline', $validated)) {
            $config->alternate_hotline = $validated['alternateHotline'];
        }
        if (array_key_exists('emergencyRadioChannel', $validated)) {
            $config->emergency_radio_channel = $validated['emergencyRadioChannel'];
        }
        if (!empty($validated['center']['lat']) && !empty($validated['center']['lng'])) {
            $config->center_lat = $validated['center']['lat'];
            $config->center_lng = $validated['center']['lng'];
        }
        if (!empty($validated['puroks'])) {
            $config->puroks = $validated['puroks'];
        }
        $config->save();

        ActivityLog::record(
            'CONFIG_UPDATE',
            $request->input('officerName', 'DRRM Administrator'),
            $request->input('officerRole', 'Command Administrator'),
            $name,
            "Barangay {$name} Settings",
            "Barangay configuration updated: Hotline {$config->hotline}, Lead: {$config->evacuation_lead}."
        );

        return response()->json([
            'success' => true,
            'message' => "Configuration for {$name} saved to database.",
            'data' => [
                'name' => $config->name,
                'center' => [
                    'lat' => (float) $config->center_lat,
                    'lng' => (float) $config->center_lng,
                ],
                'puroks' => $config->puroks ?? [],
                'hotline' => $config->hotline,
                'evacuationLead' => $config->evacuation_lead,
                'alternateHotline' => $config->alternate_hotline,
                'emergencyRadioChannel' => $config->emergency_radio_channel,
            ],
        ]);
    }
}
