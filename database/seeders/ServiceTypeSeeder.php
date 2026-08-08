<?php

namespace Database\Seeders;

use App\Models\ServiceType;
use App\Models\User;
use Illuminate\Database\Seeder;

class ServiceTypeSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::first();
        $createdBy = $admin?->id ?? 1;

        $types = [
            [
                'name' => 'Hospital',
                'description' => 'Full-service hospitals offering inpatient and outpatient care across all medical specialties.',
                'icon' => '🏥',
                'color' => '#EF4444',
                'is_active' => true,
            ],
            [
                'name' => 'Clinic',
                'description' => 'Specialized medical clinics providing outpatient consultations and treatments.',
                'icon' => '🏨',
                'color' => '#3B82F6',
                'is_active' => true,
            ],
            [
                'name' => 'Doctor',
                'description' => 'Individual medical practitioners offering specialized healthcare services.',
                'icon' => '👨‍⚕️',
                'color' => '#10B981',
                'is_active' => true,
            ],
            [
                'name' => 'Pharmacy',
                'description' => 'Pharmacies providing prescription medications and healthcare products.',
                'icon' => '💊',
                'color' => '#8B5CF6',
                'is_active' => true,
            ],
            [
                'name' => 'Dental',
                'description' => 'Dental clinics and practitioners offering oral healthcare services.',
                'icon' => '🦷',
                'color' => '#F59E0B',
                'is_active' => true,
            ],
            [
                'name' => 'Lab',
                'description' => 'Medical laboratories providing diagnostic testing and analysis services.',
                'icon' => '🔬',
                'color' => '#06B6D4',
                'is_active' => true,
            ],
            [
                'name' => 'Other',
                'description' => 'Other healthcare-related services and providers.',
                'icon' => '📋',
                'color' => '#6B7280',
                'is_active' => true,
            ],
        ];

        foreach ($types as $type) {
            ServiceType::updateOrCreate(
                ['name->en' => $type['name']],
                [
                    'name' => ['en' => $type['name'], 'ar' => $type['name']],
                    'description' => $type['description'],
                    'icon' => $type['icon'],
                    'color' => $type['color'],
                    'is_active' => $type['is_active'],
                    'created_by' => $createdBy,
                ]
            );
        }
    }
}
