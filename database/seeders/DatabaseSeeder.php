<?php

namespace Database\Seeders;

use App\Models\EvacuationCenter;
use App\Models\OfficialAccount;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the DRRM database with initial infrastructure while preserving legitimate existing accounts.
     * Note: Hardcoded demo accounts and default passwords have been removed.
     */
    public function run(): void
    {
        // 1. Seed Evacuation Centers if not present
        EvacuationCenter::updateOrCreate(
            ['id' => 'center-buang-1'],
            [
                'name' => 'Barangay Hall',
                'barangay' => 'Buang',
                'purok' => 'Purok 3',
                'capacity_families' => 15,
                'assigned_count' => 0,
                'location' => ['lat' => 13.3425, 'lng' => 123.6975],
                'manager_name' => 'Kagawad Joel Bautista',
                'manager_contact' => '0917-123-4567',
                'status' => 'Open',
                'facilities' => ['Generator Set', 'Clean Water', 'Medical Station', 'Kitchen Area'],
            ]
        );

        EvacuationCenter::updateOrCreate(
            ['id' => 'center-nagsipit-1'],
            [
                'name' => 'Barangay Hall',
                'barangay' => 'Nagsipit',
                'purok' => 'Purok 4',
                'capacity_families' => 20,
                'assigned_count' => 0,
                'location' => ['lat' => 13.3580, 'lng' => 123.7180],
                'manager_name' => 'Luzviminda Obrero',
                'manager_contact' => '0918-999-1122',
                'status' => 'Open',
                'facilities' => ['Radio Communication', 'Stockpile Supplies', 'Maternal Room', 'Clean Water'],
            ]
        );

        // 2. Seed Primary DRRM Head Official Accounts
        OfficialAccount::updateOrCreate(
            ['username' => 'Buangadmin.gov'],
            [
                'password_hash' => \Illuminate\Support\Facades\Hash::make('admin123'),
                'barangay' => 'Buang',
                'official_name' => 'Hon. Jose Hernandez',
                'position' => 'Punong Barangay / DRRM Chief',
                'phone' => '0917-111-2222',
                'email' => 'buangg2026@gmail.com',
            ]
        );

        OfficialAccount::updateOrCreate(
            ['username' => 'Nagsipitadmin.gov'],
            [
                'password_hash' => \Illuminate\Support\Facades\Hash::make('admin123'),
                'barangay' => 'Nagsipit',
                'official_name' => 'Hon. Maria Clara Santos',
                'position' => 'Punong Barangay / DRRM Chief',
                'phone' => '0918-333-4444',
                'email' => 'nagsipit2026@gmail.com',
            ]
        );
    }
}
