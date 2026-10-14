<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\EvacuationCenter;
use App\Models\Household;
use App\Models\OfficialAccount;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the DRRM database with Barangay Buang and Nagsipit data.
     */
    public function run(): void
    {
        // 1. Seed Official Accounts
        OfficialAccount::updateOrCreate(
            ['username' => 'Buangadmin.gov'],
            [
                'password_hash' => Hash::make('buang123'),
                'barangay' => 'Buang',
                'official_name' => 'Hon. Rolando M. Santos',
                'position' => 'Punong Barangay / DRRM Chief',
                'phone' => '0917-555-0101',
                'security_question' => 'What is the official municipality or city of your barangay jurisdiction?',
                'security_answer' => 'Tabaco City',
            ]
        );

        OfficialAccount::updateOrCreate(
            ['username' => 'Nagsipitadmin.gov'],
            [
                'password_hash' => Hash::make('Nagsipit123'),
                'barangay' => 'Nagsipit',
                'official_name' => 'Hon. Cynthia P. Ramos',
                'position' => 'Disaster Risk Reduction Officer',
                'phone' => '0918-777-0202',
                'security_question' => 'What is the official municipality or city of your barangay jurisdiction?',
                'security_answer' => 'Tabaco City',
            ]
        );

        // 2. Seed Evacuation Centers
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

        // 3. Seed Initial Households
        Household::updateOrCreate(
            ['id' => 'hh-buang-1'],
            [
                'head_of_family' => 'Pedro M. Penduko',
                'birthday' => '1982-05-14',
                'age' => 44,
                'contact_number' => '0917-882-3491',
                'number_of_members' => 5,
                'house_number' => '124-A',
                'purok' => 'Purok 1',
                'barangay' => 'Buang',
                'complete_address' => '124-A Riverside, Purok 1, Buang, Albay',
                'location' => ['lat' => 13.3412, 'lng' => 123.6965],
                'status' => 'EMERGENCY',
                'status_note' => 'River floodwater rising rapidly to waist level! Urgent rescue & evacuation required.',
                'evacuation_center_id' => null,
                'verification_status' => 'Pending',
                'special_needs' => ['Senior', 'Infant'],
                'family_members' => [
                    ['id' => 'fm-1', 'name' => 'Pedro M. Penduko', 'age' => 44, 'relationship' => 'Head of Family', 'specialCategory' => 'None'],
                    ['id' => 'fm-2', 'name' => 'Rosalina B. Penduko', 'age' => 42, 'relationship' => 'Spouse', 'specialCategory' => 'None'],
                    ['id' => 'fm-3', 'name' => 'Lola Mercedes Penduko', 'age' => 76, 'relationship' => 'Grandmother', 'specialCategory' => 'Senior'],
                    ['id' => 'fm-4', 'name' => 'Baby Jayce Penduko', 'age' => 1, 'relationship' => 'Child', 'specialCategory' => 'Infant'],
                    ['id' => 'fm-5', 'name' => 'Mark Penduko', 'age' => 14, 'relationship' => 'Child', 'specialCategory' => 'None'],
                ],
                'last_status_update' => Carbon::now('Asia/Manila')->subHours(2),
            ]
        );

        // 4. Seed Initial Activity Logs
        ActivityLog::record(
            'LOGIN',
            'Hon. Rolando M. Santos',
            'Punong Barangay',
            'Buang',
            'Authentication System',
            'Authenticated successfully into Buang Disaster Operations console.'
        );
    }
}
