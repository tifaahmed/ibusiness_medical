<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Governorate;
use App\Models\Service;
use App\Models\ServiceType;
use App\Models\User;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::first();
        $createdBy = $admin?->id ?? 1;

        // `name` is translatable (JSON), so it has to be matched via `name->en`
        // rather than a plain column comparison.
        $hospitalType = ServiceType::where('name->en', 'Hospital')->first();
        $clinicType = ServiceType::where('name->en', 'Clinic')->first();
        $doctorType = ServiceType::where('name->en', 'Doctor')->first();
        $pharmacyType = ServiceType::where('name->en', 'Pharmacy')->first();
        $dentalType = ServiceType::where('name->en', 'Dental')->first();
        $labType = ServiceType::where('name->en', 'Lab')->first();

        // category_id is a non-nullable FK; fall back to whatever type exists
        // instead of a hardcoded id that may not be present.
        $fallbackType = $hospitalType?->id ?? ServiceType::value('id');

        $cairo = Governorate::where('name->en', 'Cairo')->first();
        $alex = Governorate::where('name->en', 'Alexandria')->first();
        $giza = Governorate::where('name->en', 'Giza')->first();

        $cairoCity = City::where('governorate_id', $cairo?->id)->first();
        $alexCity = City::where('governorate_id', $alex?->id)->first();
        $gizaCity = City::where('governorate_id', $giza?->id)->first();

        $services = [
            [
                'category_id' => $hospitalType?->id ?? $fallbackType,
                'title' => 'Cairo General Hospital - Comprehensive Checkup',
                'short_subject' => 'Full medical checkup package including all major tests',
                'subject' => '<p>Our comprehensive health checkup package includes:</p><ul><li>Complete blood count (CBC)</li><li>Liver and kidney function tests</li><li>Lipid profile</li><li>Blood sugar levels (fasting & postprandial)</li><li>Chest X-ray</li><li>Abdominal ultrasound</li><li>Stress ECG</li><li>Consultation with internal medicine specialist</li></ul>',
                'old_price' => 5000.00,
                'new_price' => 2500.00,
                'governorate_id' => $cairo?->id,
                'city_id' => $cairoCity?->id,
            ],
            [
                'category_id' => $clinicType?->id ?? $fallbackType,
                'title' => 'Alexandria Dermatology Clinic - Skin Treatment',
                'short_subject' => 'Advanced dermatological treatments with modern equipment',
                'subject' => '<p>Specialized dermatology services:</p><ul><li>Acne treatment</li><li>Skin allergy testing</li><li>Laser hair removal</li><li>Scar revision</li><li>Melasma treatment</li><li>Skin cancer screening</li></ul>',
                'old_price' => 1500.00,
                'new_price' => 750.00,
                'governorate_id' => $alex?->id,
                'city_id' => $alexCity?->id,
            ],
            [
                'category_id' => $doctorType?->id ?? $fallbackType,
                'title' => 'Consultation with Prof. Ahmed - Cardiology Specialist',
                'short_subject' => 'Expert cardiac consultation with leading cardiologist',
                'subject' => '<p>Get expert cardiology consultation including:</p><ul><li>Heart disease risk assessment</li><li>ECG interpretation</li><li>Echocardiogram</li><li>Holter monitoring</li><li>Stress test supervision</li><li>Medication management</li></ul>',
                'old_price' => 800.00,
                'new_price' => 400.00,
                'governorate_id' => $giza?->id,
                'city_id' => $gizaCity?->id,
            ],
            [
                'category_id' => $pharmacyType?->id ?? $fallbackType,
                'title' => 'Al-Shifa Pharmacy - Monthly Medication Supply',
                'short_subject' => 'Discounted rates on chronic disease medications',
                'subject' => '<p>Monthly medication supply for chronic conditions:</p><ul><li>Diabetes medications</li><li>Blood pressure medications</li><li>Cholesterol medications</li><li>Thyroid medications</li><li>Asthma inhalers</li><li>Free home delivery</li></ul>',
                'old_price' => 3000.00,
                'new_price' => 2100.00,
                'governorate_id' => $cairo?->id,
                'city_id' => $cairoCity?->id,
            ],
            [
                'category_id' => $dentalType?->id ?? $fallbackType,
                'title' => 'Smile Dental Center - Teeth Whitening Package',
                'short_subject' => 'Professional teeth whitening with guaranteed results',
                'subject' => '<p>Complete teeth whitening package:</p><ul><li>Professional cleaning and scaling</li><li>Zoom whitening treatment</li><li>Take-home whitening kit</li><li>Sensitivity protection</li><li>Follow-up visit after 2 weeks</li><li>6-month warranty</li></ul>',
                'old_price' => 4000.00,
                'new_price' => 2000.00,
                'governorate_id' => $alex?->id,
                'city_id' => $alexCity?->id,
            ],
            [
                'category_id' => $labType?->id ?? $fallbackType,
                'title' => 'Al-Borg Lab - Premium Blood Analysis',
                'short_subject' => 'Accurate lab results with digital reporting',
                'subject' => '<p>Premium laboratory services:</p><ul><li>Comprehensive blood analysis</li><li>Hormone profiling</li><li>Vitamin deficiency testing</li><li>Food allergy testing</li><li>Genetic predisposition screening</li><li>Digital reports via email</li><li>Free sample collection from home</li></ul>',
                'old_price' => 2500.00,
                'new_price' => 1250.00,
                'governorate_id' => $giza?->id,
                'city_id' => $gizaCity?->id,
            ],
        ];

        foreach ($services as $service) {
            Service::updateOrCreate(
                ['title' => $service['title']],
                [
                    'category_id' => $service['category_id'],
                    'short_subject' => $service['short_subject'],
                    'subject' => $service['subject'],
                    'old_price' => $service['old_price'],
                    'new_price' => $service['new_price'],
                    'governorate_id' => $service['governorate_id'],
                    'city_id' => $service['city_id'],
                    'created_by' => $createdBy,
                ]
            );
        }
    }
}
