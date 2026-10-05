<?php

namespace Database\Seeders;

use App\Models\Manufacturer;
use App\Models\Medicine;
use App\Models\MedicineCategory;
use App\Models\MedicineUnit;
use App\Support\Tenancy;
use Illuminate\Database\Seeder;

class MedicineSeeder extends Seeder
{
    public function run(): void
    {
        $medicines = [
            ['name' => 'Panadol', 'generic' => 'Paracetamol', 'strength' => '500mg'],
            ['name' => 'Brufen', 'generic' => 'Ibuprofen', 'strength' => '400mg'],
            ['name' => 'Augmentin', 'generic' => 'Amoxicillin + Clavulanic Acid', 'strength' => '625mg'],
            ['name' => 'Amoxil', 'generic' => 'Amoxicillin', 'strength' => '500mg'],
            ['name' => 'Ponstan', 'generic' => 'Mefenamic Acid', 'strength' => '500mg'],
            ['name' => 'Disprin', 'generic' => 'Aspirin', 'strength' => '300mg'],
            ['name' => 'Calpol Syrup', 'generic' => 'Paracetamol', 'strength' => '120mg/5ml'],
            ['name' => 'Brufen Syrup', 'generic' => 'Ibuprofen', 'strength' => '100mg/5ml'],
            ['name' => 'Ventolin', 'generic' => 'Salbutamol', 'strength' => '100mcg'],
            ['name' => 'Zyrtec', 'generic' => 'Cetirizine', 'strength' => '10mg'],
            ['name' => 'Flagyl', 'generic' => 'Metronidazole', 'strength' => '400mg'],
            ['name' => 'Nexum', 'generic' => 'Esomeprazole', 'strength' => '40mg'],
            ['name' => 'ORS', 'generic' => 'Oral Rehydration Salt', 'strength' => 'Standard'],
            ['name' => 'Neurobion', 'generic' => 'Vitamin B Complex', 'strength' => 'Standard'],
            ['name' => 'Voltaren Gel', 'generic' => 'Diclofenac', 'strength' => '1%'],
            ['name' => 'Betnovate Cream', 'generic' => 'Betamethasone', 'strength' => '0.1%'],
            ['name' => 'Insulin Actrapid', 'generic' => 'Human Insulin', 'strength' => '100IU'],
        ];

        $defaultCategory = MedicineCategory::first() ?? MedicineCategory::create(['name' => 'Tablet', 'status' => 1]);
        $defaultUnit = MedicineUnit::first() ?? MedicineUnit::create(['name' => 'Tablet', 'status' => 1]);
        $defaultManufacturer = Manufacturer::first() ?? Manufacturer::create(['name' => 'Getz Pharma', 'status' => 1]);

        foreach ($medicines as $medicine) {
            Medicine::firstOrCreate([
                'name' => $medicine['name'],
            ], [
                'generic_name' => $medicine['generic'],
                'strength' => $medicine['strength'],
                'category_id' => $defaultCategory->id,
                'unit_id' => $defaultUnit->id,
                'manufacturer_id' => $defaultManufacturer->id,
                'dosage_form' => 'Tablet',
                'barcode' => rand(100000, 999999),
                'requires_prescription' => false,
                'track_stock' => true,
                'min_stock' => 10,
                'restaurant_id' => Tenancy::currentId(),
            ]);
        }
    }
}
