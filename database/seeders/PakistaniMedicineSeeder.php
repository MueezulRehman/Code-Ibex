<?php

namespace Database\Seeders;

use App\Models\Category as MenuCategory;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\MedicineCategory;
use App\Models\Restaurant;
use App\Services\TenantProvisioner;
use App\Support\Tenancy;
use Illuminate\Database\Seeder;

class PakistaniMedicineSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $restaurant = Restaurant::where('slug', 'city-pharmacy')->first();

        if (! $restaurant) {
            throw new \RuntimeException('Create the City Pharmacy demo business before seeding its medicine catalog.');
        }

        if (! $restaurant->hasTenantDatabase()) {
            app(TenantProvisioner::class)->provision($restaurant, false);
            $restaurant->refresh();
        }

        Tenancy::runFor($restaurant, function () use ($restaurant): void {
            $categories = [
                'Pain Relief' => ['icon' => '💊', 'sort_order' => 1],
                'Antibiotics' => ['icon' => '🧪', 'sort_order' => 2],
                'Cold & Flu' => ['icon' => '🌡️', 'sort_order' => 3],
                'Vitamins' => ['icon' => '🧬', 'sort_order' => 4],
                'Digestive Health' => ['icon' => '🫙', 'sort_order' => 5],
                'Homeopathic' => ['icon' => '🌿', 'sort_order' => 6],
            ];

            foreach ($categories as $name => $config) {
                MedicineCategory::firstOrCreate([
                    'name' => $name,
                ], [
                    'status' => true,
                ]);
            }

            $medicines = [
                // ---------------- Pain Relief ----------------
                ['name' => 'Panadol Extra', 'generic_name' => 'Paracetamol', 'category' => 'Pain Relief', 'dosage_form' => 'Tablet', 'strength' => '500mg', 'sku' => 'PA-001', 'requires_prescription' => false, 'track_stock' => true, 'min_stock' => 20, 'description' => 'Common pain relief medicine widely used in Pakistan.', 'tax' => 5],
                ['name' => 'Panadol Cold & Flu', 'generic_name' => 'Paracetamol/Phenylephrine', 'category' => 'Pain Relief', 'dosage_form' => 'Tablet', 'strength' => '500mg', 'sku' => 'PA-002', 'requires_prescription' => false, 'track_stock' => true, 'min_stock' => 15, 'description' => 'Relieves pain with added decongestant.', 'tax' => 5],
                ['name' => 'Brufen', 'generic_name' => 'Ibuprofen', 'category' => 'Pain Relief', 'dosage_form' => 'Tablet', 'strength' => '400mg', 'sku' => 'PA-003', 'requires_prescription' => false, 'track_stock' => true, 'min_stock' => 20, 'description' => 'Fast relief for fever and body pain.', 'tax' => 5],
                ['name' => 'Brufen 600', 'generic_name' => 'Ibuprofen', 'category' => 'Pain Relief', 'dosage_form' => 'Tablet', 'strength' => '600mg', 'sku' => 'PA-004', 'requires_prescription' => true, 'track_stock' => true, 'min_stock' => 15, 'description' => 'Higher strength ibuprofen for stronger pain relief.', 'tax' => 5],
                ['name' => 'Disprin', 'generic_name' => 'Aspirin', 'category' => 'Pain Relief', 'dosage_form' => 'Tablet', 'strength' => '300mg', 'sku' => 'PA-005', 'requires_prescription' => false, 'track_stock' => true, 'min_stock' => 25, 'description' => 'Soluble aspirin for headache and minor aches.', 'tax' => 5],
                ['name' => 'Ponstan Forte', 'generic_name' => 'Mefenamic Acid', 'category' => 'Pain Relief', 'dosage_form' => 'Capsule', 'strength' => '500mg', 'sku' => 'PA-006', 'requires_prescription' => true, 'track_stock' => true, 'min_stock' => 15, 'description' => 'Used for menstrual and moderate pain.', 'tax' => 5],
                ['name' => 'Voltral SR', 'generic_name' => 'Diclofenac Sodium', 'category' => 'Pain Relief', 'dosage_form' => 'Tablet', 'strength' => '100mg', 'sku' => 'PA-007', 'requires_prescription' => true, 'track_stock' => true, 'min_stock' => 12, 'description' => 'Extended release anti-inflammatory for joint pain.', 'tax' => 5],
                ['name' => 'Katoflam', 'generic_name' => 'Diclofenac Potassium', 'category' => 'Pain Relief', 'dosage_form' => 'Tablet', 'strength' => '50mg', 'sku' => 'PA-008', 'requires_prescription' => true, 'track_stock' => true, 'min_stock' => 12, 'description' => 'Fast acting anti-inflammatory pain reliever.', 'tax' => 5],
                ['name' => 'Synflex', 'generic_name' => 'Naproxen Sodium', 'category' => 'Pain Relief', 'dosage_form' => 'Tablet', 'strength' => '550mg', 'sku' => 'PA-009', 'requires_prescription' => true, 'track_stock' => true, 'min_stock' => 10, 'description' => 'Long acting pain and inflammation relief.', 'tax' => 5],
                ['name' => 'Tramal', 'generic_name' => 'Tramadol', 'category' => 'Pain Relief', 'dosage_form' => 'Capsule', 'strength' => '50mg', 'sku' => 'PA-010', 'requires_prescription' => true, 'track_stock' => true, 'min_stock' => 10, 'description' => 'Moderate to severe pain management, prescription only.', 'tax' => 5],
                ['name' => 'Feldene', 'generic_name' => 'Piroxicam', 'category' => 'Pain Relief', 'dosage_form' => 'Capsule', 'strength' => '20mg', 'sku' => 'PA-011', 'requires_prescription' => true, 'track_stock' => true, 'min_stock' => 10, 'description' => 'Long duration anti-inflammatory for arthritis pain.', 'tax' => 5],
                ['name' => 'Arinac', 'generic_name' => 'Paracetamol/Chlorpheniramine', 'category' => 'Pain Relief', 'dosage_form' => 'Tablet', 'strength' => '500mg', 'sku' => 'PA-012', 'requires_prescription' => false, 'track_stock' => true, 'min_stock' => 15, 'description' => 'Pain relief combined with antihistamine.', 'tax' => 5],
                ['name' => 'Panadol Night', 'generic_name' => 'Paracetamol/Diphenhydramine', 'category' => 'Pain Relief', 'dosage_form' => 'Tablet', 'strength' => '500mg', 'sku' => 'PA-013', 'requires_prescription' => false, 'track_stock' => true, 'min_stock' => 12, 'description' => 'Night-time pain relief with mild sedative.', 'tax' => 5],
                ['name' => 'Flexi-tab', 'generic_name' => 'Diclofenac/Paracetamol', 'category' => 'Pain Relief', 'dosage_form' => 'Tablet', 'strength' => '50mg/500mg', 'sku' => 'PA-014', 'requires_prescription' => true, 'track_stock' => true, 'min_stock' => 10, 'description' => 'Combination tablet for stronger pain relief.', 'tax' => 5],
                ['name' => 'Calpol Syrup', 'generic_name' => 'Paracetamol Suspension', 'category' => 'Pain Relief', 'dosage_form' => 'Syrup', 'strength' => '120mg/5ml', 'sku' => 'PA-015', 'requires_prescription' => false, 'track_stock' => true, 'min_stock' => 20, 'description' => 'Children\'s fever and pain relief syrup.', 'tax' => 5],
                ['name' => 'Junior Disprin', 'generic_name' => 'Aspirin', 'category' => 'Pain Relief', 'dosage_form' => 'Tablet', 'strength' => '75mg', 'sku' => 'PA-016', 'requires_prescription' => false, 'track_stock' => true, 'min_stock' => 15, 'description' => 'Low dose aspirin for children under guidance.', 'tax' => 5],
                ['name' => 'Novalgin', 'generic_name' => 'Metamizole', 'category' => 'Pain Relief', 'dosage_form' => 'Tablet', 'strength' => '500mg', 'sku' => 'PA-017', 'requires_prescription' => true, 'track_stock' => true, 'min_stock' => 10, 'description' => 'Strong analgesic for severe pain and fever.', 'tax' => 5],

                // ---------------- Antibiotics ----------------
                ['name' => 'Augmentin 625', 'generic_name' => 'Amoxicillin/Clavulanic Acid', 'category' => 'Antibiotics', 'dosage_form' => 'Tablet', 'strength' => '625mg', 'sku' => 'AB-001', 'requires_prescription' => true, 'track_stock' => true, 'min_stock' => 15, 'description' => 'Widely prescribed antibiotic for bacterial infections.', 'tax' => 7],
                ['name' => 'Augmentin 1g', 'generic_name' => 'Amoxicillin/Clavulanic Acid', 'category' => 'Antibiotics', 'dosage_form' => 'Tablet', 'strength' => '1g', 'sku' => 'AB-002', 'requires_prescription' => true, 'track_stock' => true, 'min_stock' => 12, 'description' => 'Higher dose broad-spectrum antibiotic.', 'tax' => 7],
                ['name' => 'Cefixime', 'generic_name' => 'Cefixime', 'category' => 'Antibiotics', 'dosage_form' => 'Tablet', 'strength' => '200mg', 'sku' => 'AB-003', 'requires_prescription' => true, 'track_stock' => true, 'min_stock' => 10, 'description' => 'Common oral antibiotic used in many clinics.', 'tax' => 7],
                ['name' => 'Cefspan', 'generic_name' => 'Cefixime', 'category' => 'Antibiotics', 'dosage_form' => 'Capsule', 'strength' => '100mg', 'sku' => 'AB-004', 'requires_prescription' => true, 'track_stock' => true, 'min_stock' => 10, 'description' => 'Third generation cephalosporin antibiotic.', 'tax' => 7],
                ['name' => 'Ceclor', 'generic_name' => 'Cefaclor', 'category' => 'Antibiotics', 'dosage_form' => 'Capsule', 'strength' => '250mg', 'sku' => 'AB-005', 'requires_prescription' => true, 'track_stock' => true, 'min_stock' => 10, 'description' => 'Used for respiratory and ear infections.', 'tax' => 7],
                ['name' => 'Klaricid', 'generic_name' => 'Clarithromycin', 'category' => 'Antibiotics', 'dosage_form' => 'Tablet', 'strength' => '500mg', 'sku' => 'AB-006', 'requires_prescription' => true, 'track_stock' => true, 'min_stock' => 10, 'description' => 'Macrolide antibiotic for respiratory infections.', 'tax' => 7],
                ['name' => 'Zimax', 'generic_name' => 'Azithromycin', 'category' => 'Antibiotics', 'dosage_form' => 'Tablet', 'strength' => '250mg', 'sku' => 'AB-007', 'requires_prescription' => true, 'track_stock' => true, 'min_stock' => 12, 'description' => 'Short course antibiotic for common infections.', 'tax' => 7],
                ['name' => 'Azomax', 'generic_name' => 'Azithromycin', 'category' => 'Antibiotics', 'dosage_form' => 'Tablet', 'strength' => '500mg', 'sku' => 'AB-008', 'requires_prescription' => true, 'track_stock' => true, 'min_stock' => 12, 'description' => 'Single daily dose antibiotic, 3-5 day course.', 'tax' => 7],
                ['name' => 'Amoxil', 'generic_name' => 'Amoxicillin', 'category' => 'Antibiotics', 'dosage_form' => 'Capsule', 'strength' => '500mg', 'sku' => 'AB-009', 'requires_prescription' => true, 'track_stock' => true, 'min_stock' => 15, 'description' => 'Standard penicillin-class antibiotic.', 'tax' => 7],
                ['name' => 'Flagyl', 'generic_name' => 'Metronidazole', 'category' => 'Antibiotics', 'dosage_form' => 'Tablet', 'strength' => '400mg', 'sku' => 'AB-010', 'requires_prescription' => true, 'track_stock' => true, 'min_stock' => 12, 'description' => 'Used for anaerobic and parasitic infections.', 'tax' => 7],
                ['name' => 'Cifran', 'generic_name' => 'Ciprofloxacin', 'category' => 'Antibiotics', 'dosage_form' => 'Tablet', 'strength' => '500mg', 'sku' => 'AB-011', 'requires_prescription' => true, 'track_stock' => true, 'min_stock' => 10, 'description' => 'Fluoroquinolone antibiotic for urinary infections.', 'tax' => 7],
                ['name' => 'Ciproxin', 'generic_name' => 'Ciprofloxacin', 'category' => 'Antibiotics', 'dosage_form' => 'Tablet', 'strength' => '250mg', 'sku' => 'AB-012', 'requires_prescription' => true, 'track_stock' => true, 'min_stock' => 10, 'description' => 'Lower dose fluoroquinolone antibiotic.', 'tax' => 7],
                ['name' => 'Rifadin', 'generic_name' => 'Rifampicin', 'category' => 'Antibiotics', 'dosage_form' => 'Capsule', 'strength' => '300mg', 'sku' => 'AB-013', 'requires_prescription' => true, 'track_stock' => true, 'min_stock' => 8, 'description' => 'Used in combination TB therapy regimens.', 'tax' => 7],
                ['name' => 'Vibramycin', 'generic_name' => 'Doxycycline', 'category' => 'Antibiotics', 'dosage_form' => 'Capsule', 'strength' => '100mg', 'sku' => 'AB-014', 'requires_prescription' => true, 'track_stock' => true, 'min_stock' => 10, 'description' => 'Broad-spectrum tetracycline antibiotic.', 'tax' => 7],
                ['name' => 'Zinnat', 'generic_name' => 'Cefuroxime', 'category' => 'Antibiotics', 'dosage_form' => 'Tablet', 'strength' => '250mg', 'sku' => 'AB-015', 'requires_prescription' => true, 'track_stock' => true, 'min_stock' => 10, 'description' => 'Second generation cephalosporin antibiotic.', 'tax' => 7],
                ['name' => 'Moxatag', 'generic_name' => 'Amoxicillin ER', 'category' => 'Antibiotics', 'dosage_form' => 'Tablet', 'strength' => '775mg', 'sku' => 'AB-016', 'requires_prescription' => true, 'track_stock' => true, 'min_stock' => 8, 'description' => 'Extended release amoxicillin, once daily.', 'tax' => 7],
                ['name' => 'Septran', 'generic_name' => 'Cotrimoxazole', 'category' => 'Antibiotics', 'dosage_form' => 'Tablet', 'strength' => '480mg', 'sku' => 'AB-017', 'requires_prescription' => true, 'track_stock' => true, 'min_stock' => 12, 'description' => 'Combination antibiotic for common infections.', 'tax' => 7],

                // ---------------- Cold & Flu ----------------
                ['name' => 'Benylin', 'generic_name' => 'Diphenhydramine', 'category' => 'Cold & Flu', 'dosage_form' => 'Syrup', 'strength' => '100ml', 'sku' => 'CF-001', 'requires_prescription' => false, 'track_stock' => true, 'min_stock' => 12, 'description' => 'Popular cold and cough relief syrup.', 'tax' => 5],
                ['name' => 'ORS Sachet', 'generic_name' => 'Oral Rehydration Salts', 'category' => 'Cold & Flu', 'dosage_form' => 'Powder', 'strength' => '20.5g', 'sku' => 'CF-002', 'requires_prescription' => false, 'track_stock' => true, 'min_stock' => 30, 'description' => 'Essential rehydration solution for dehydration.', 'tax' => 0],
                ['name' => 'Panadol Cold & Flu Tab', 'generic_name' => 'Paracetamol/Phenylephrine', 'category' => 'Cold & Flu', 'dosage_form' => 'Tablet', 'strength' => '500mg', 'sku' => 'CF-003', 'requires_prescription' => false, 'track_stock' => true, 'min_stock' => 15, 'description' => 'Relieves flu symptoms and body ache.', 'tax' => 5],
                ['name' => 'Actifed Syrup', 'generic_name' => 'Triprolidine/Pseudoephedrine', 'category' => 'Cold & Flu', 'dosage_form' => 'Syrup', 'strength' => '100ml', 'sku' => 'CF-004', 'requires_prescription' => false, 'track_stock' => true, 'min_stock' => 12, 'description' => 'Decongestant syrup for cold symptoms.', 'tax' => 5],
                ['name' => 'Arinac Forte', 'generic_name' => 'Paracetamol/Phenylephrine/Chlorpheniramine', 'category' => 'Cold & Flu', 'dosage_form' => 'Tablet', 'strength' => '500mg', 'sku' => 'CF-005', 'requires_prescription' => false, 'track_stock' => true, 'min_stock' => 15, 'description' => 'Triple action flu and congestion relief.', 'tax' => 5],
                ['name' => 'Disprin CV', 'generic_name' => 'Aspirin/Vitamin C', 'category' => 'Cold & Flu', 'dosage_form' => 'Tablet', 'strength' => '300mg', 'sku' => 'CF-006', 'requires_prescription' => false, 'track_stock' => true, 'min_stock' => 15, 'description' => 'Soluble tablet for cold and body ache.', 'tax' => 5],
                ['name' => 'Tuseran Forte', 'generic_name' => 'Cough Suppressant Combination', 'category' => 'Cold & Flu', 'dosage_form' => 'Syrup', 'strength' => '100ml', 'sku' => 'CF-007', 'requires_prescription' => false, 'track_stock' => true, 'min_stock' => 10, 'description' => 'Cough suppressant syrup for dry cough.', 'tax' => 5],
                ['name' => 'Grippex', 'generic_name' => 'Paracetamol/Pseudoephedrine/Chlorpheniramine', 'category' => 'Cold & Flu', 'dosage_form' => 'Tablet', 'strength' => '500mg', 'sku' => 'CF-008', 'requires_prescription' => false, 'track_stock' => true, 'min_stock' => 15, 'description' => 'Multi-symptom flu relief tablet.', 'tax' => 5],
                ['name' => 'Decolgen', 'generic_name' => 'Paracetamol/Phenylephrine/Chlorpheniramine', 'category' => 'Cold & Flu', 'dosage_form' => 'Tablet', 'strength' => '500mg', 'sku' => 'CF-009', 'requires_prescription' => false, 'track_stock' => true, 'min_stock' => 15, 'description' => 'Popular over-the-counter flu remedy.', 'tax' => 5],
                ['name' => 'Panadol Extra Cold', 'generic_name' => 'Paracetamol/Caffeine', 'category' => 'Cold & Flu', 'dosage_form' => 'Tablet', 'strength' => '500mg', 'sku' => 'CF-010', 'requires_prescription' => false, 'track_stock' => true, 'min_stock' => 15, 'description' => 'Extra strength cold relief with caffeine.', 'tax' => 5],
                ['name' => 'Coldact', 'generic_name' => 'Phenylpropanolamine/Chlorpheniramine', 'category' => 'Cold & Flu', 'dosage_form' => 'Capsule', 'strength' => '500mg', 'sku' => 'CF-011', 'requires_prescription' => false, 'track_stock' => true, 'min_stock' => 12, 'description' => 'Sustained release cold and allergy relief.', 'tax' => 5],
                ['name' => 'Terflu', 'generic_name' => 'Paracetamol/Phenylephrine/Cetirizine', 'category' => 'Cold & Flu', 'dosage_form' => 'Tablet', 'strength' => '500mg', 'sku' => 'CF-012', 'requires_prescription' => false, 'track_stock' => true, 'min_stock' => 12, 'description' => 'Flu relief with antihistamine.', 'tax' => 5],
                ['name' => 'Flutex', 'generic_name' => 'Combination Cold Remedy', 'category' => 'Cold & Flu', 'dosage_form' => 'Tablet', 'strength' => '500mg', 'sku' => 'CF-013', 'requires_prescription' => false, 'track_stock' => true, 'min_stock' => 12, 'description' => 'General flu and congestion tablet.', 'tax' => 5],
                ['name' => 'Rigix', 'generic_name' => 'Guaifenesin', 'category' => 'Cold & Flu', 'dosage_form' => 'Syrup', 'strength' => '100ml', 'sku' => 'CF-014', 'requires_prescription' => false, 'track_stock' => true, 'min_stock' => 10, 'description' => 'Expectorant syrup for chesty cough.', 'tax' => 5],
                ['name' => 'Sinutab', 'generic_name' => 'Paracetamol/Pseudoephedrine', 'category' => 'Cold & Flu', 'dosage_form' => 'Tablet', 'strength' => '500mg', 'sku' => 'CF-015', 'requires_prescription' => false, 'track_stock' => true, 'min_stock' => 12, 'description' => 'Sinus congestion and headache relief.', 'tax' => 5],
                ['name' => 'Nasomist Nasal Spray', 'generic_name' => 'Xylometazoline', 'category' => 'Cold & Flu', 'dosage_form' => 'Spray', 'strength' => '15ml', 'sku' => 'CF-016', 'requires_prescription' => false, 'track_stock' => true, 'min_stock' => 15, 'description' => 'Fast acting nasal decongestant spray.', 'tax' => 5],
                ['name' => 'Vicks Vaporub', 'generic_name' => 'Menthol/Camphor', 'category' => 'Cold & Flu', 'dosage_form' => 'Ointment', 'strength' => '50g', 'sku' => 'CF-017', 'requires_prescription' => false, 'track_stock' => true, 'min_stock' => 20, 'description' => 'Topical rub for cold symptom relief.', 'tax' => 5],

                // ---------------- Vitamins ----------------
                ['name' => 'Vitamin C', 'generic_name' => 'Ascorbic Acid', 'category' => 'Vitamins', 'dosage_form' => 'Tablet', 'strength' => '1000mg', 'sku' => 'VT-001', 'requires_prescription' => false, 'track_stock' => true, 'min_stock' => 20, 'description' => 'Daily vitamin supplement.', 'tax' => 5],
                ['name' => '100 Plus', 'generic_name' => 'Electrolyte Drink', 'category' => 'Vitamins', 'dosage_form' => 'Drink', 'strength' => '250ml', 'sku' => 'VT-002', 'requires_prescription' => false, 'track_stock' => true, 'min_stock' => 20, 'description' => 'Refreshing electrolyte drink for hydration and energy support.', 'tax' => 5],
                ['name' => 'Surbex Z', 'generic_name' => 'B-Complex/Zinc', 'category' => 'Vitamins', 'dosage_form' => 'Tablet', 'strength' => '1 tab', 'sku' => 'VT-003', 'requires_prescription' => false, 'track_stock' => true, 'min_stock' => 15, 'description' => 'Popular multivitamin with zinc.', 'tax' => 5],
                ['name' => 'Centrum', 'generic_name' => 'Multivitamin/Mineral', 'category' => 'Vitamins', 'dosage_form' => 'Tablet', 'strength' => '1 tab', 'sku' => 'VT-004', 'requires_prescription' => false, 'track_stock' => true, 'min_stock' => 15, 'description' => 'Complete daily multivitamin supplement.', 'tax' => 5],
                ['name' => 'Neurobion Forte', 'generic_name' => 'Vitamin B1/B6/B12', 'category' => 'Vitamins', 'dosage_form' => 'Tablet', 'strength' => '1 tab', 'sku' => 'VT-005', 'requires_prescription' => false, 'track_stock' => true, 'min_stock' => 15, 'description' => 'Nerve health B-vitamin supplement.', 'tax' => 5],
                ['name' => 'Calcium Sandoz', 'generic_name' => 'Calcium Carbonate', 'category' => 'Vitamins', 'dosage_form' => 'Effervescent Tablet', 'strength' => '500mg', 'sku' => 'VT-006', 'requires_prescription' => false, 'track_stock' => true, 'min_stock' => 15, 'description' => 'Bone health calcium supplement.', 'tax' => 5],
                ['name' => 'Ferrograd Iron', 'generic_name' => 'Ferrous Sulphate', 'category' => 'Vitamins', 'dosage_form' => 'Tablet', 'strength' => '325mg', 'sku' => 'VT-007', 'requires_prescription' => false, 'track_stock' => true, 'min_stock' => 15, 'description' => 'Iron supplement for anemia prevention.', 'tax' => 5],
                ['name' => 'Folic Acid', 'generic_name' => 'Folic Acid', 'category' => 'Vitamins', 'dosage_form' => 'Tablet', 'strength' => '5mg', 'sku' => 'VT-008', 'requires_prescription' => false, 'track_stock' => true, 'min_stock' => 20, 'description' => 'Commonly used during pregnancy and for anemia.', 'tax' => 5],
                ['name' => 'Vitamin D3 Sachet', 'generic_name' => 'Cholecalciferol', 'category' => 'Vitamins', 'dosage_form' => 'Sachet', 'strength' => '200,000 IU', 'sku' => 'VT-009', 'requires_prescription' => false, 'track_stock' => true, 'min_stock' => 15, 'description' => 'High dose vitamin D supplement.', 'tax' => 5],
                ['name' => 'Multivitamin Syrup', 'generic_name' => 'Multivitamin', 'category' => 'Vitamins', 'dosage_form' => 'Syrup', 'strength' => '120ml', 'sku' => 'VT-010', 'requires_prescription' => false, 'track_stock' => true, 'min_stock' => 15, 'description' => 'Children\'s multivitamin syrup.', 'tax' => 5],
                ['name' => 'Zincovit', 'generic_name' => 'Zinc/Multivitamin', 'category' => 'Vitamins', 'dosage_form' => 'Tablet', 'strength' => '1 tab', 'sku' => 'VT-011', 'requires_prescription' => false, 'track_stock' => true, 'min_stock' => 15, 'description' => 'Zinc-fortified multivitamin supplement.', 'tax' => 5],
                ['name' => 'B-Complex Forte', 'generic_name' => 'Vitamin B Complex', 'category' => 'Vitamins', 'dosage_form' => 'Capsule', 'strength' => '1 cap', 'sku' => 'VT-012', 'requires_prescription' => false, 'track_stock' => true, 'min_stock' => 15, 'description' => 'General wellness B vitamin capsule.', 'tax' => 5],
                ['name' => 'Evion 400', 'generic_name' => 'Vitamin E', 'category' => 'Vitamins', 'dosage_form' => 'Capsule', 'strength' => '400mg', 'sku' => 'VT-013', 'requires_prescription' => false, 'track_stock' => true, 'min_stock' => 15, 'description' => 'Vitamin E supplement for skin and hair.', 'tax' => 5],
                ['name' => 'Osteocare', 'generic_name' => 'Calcium/Vitamin D/Zinc', 'category' => 'Vitamins', 'dosage_form' => 'Tablet', 'strength' => '1 tab', 'sku' => 'VT-014', 'requires_prescription' => false, 'track_stock' => true, 'min_stock' => 12, 'description' => 'Bone strengthening supplement.', 'tax' => 5],
                ['name' => 'Biotin Plus', 'generic_name' => 'Biotin', 'category' => 'Vitamins', 'dosage_form' => 'Tablet', 'strength' => '10mg', 'sku' => 'VT-015', 'requires_prescription' => false, 'track_stock' => true, 'min_stock' => 12, 'description' => 'Supports healthy hair, skin, and nails.', 'tax' => 5],
                ['name' => 'Omega 3 Fish Oil', 'generic_name' => 'Fish Oil', 'category' => 'Vitamins', 'dosage_form' => 'Softgel', 'strength' => '1000mg', 'sku' => 'VT-016', 'requires_prescription' => false, 'track_stock' => true, 'min_stock' => 15, 'description' => 'Heart and brain health supplement.', 'tax' => 5],
                ['name' => 'Glucosamine MSM', 'generic_name' => 'Glucosamine Sulfate/MSM', 'category' => 'Vitamins', 'dosage_form' => 'Tablet', 'strength' => '750mg', 'sku' => 'VT-017', 'requires_prescription' => false, 'track_stock' => true, 'min_stock' => 10, 'description' => 'Joint health supplement.', 'tax' => 5],

                // ---------------- Digestive Health ----------------
                ['name' => 'Omeprazole', 'generic_name' => 'Omeprazole', 'category' => 'Digestive Health', 'dosage_form' => 'Capsule', 'strength' => '20mg', 'sku' => 'DG-001', 'requires_prescription' => false, 'track_stock' => true, 'min_stock' => 15, 'description' => 'Common medicine for acidity and heartburn.', 'tax' => 5],
                ['name' => 'Risek', 'generic_name' => 'Omeprazole', 'category' => 'Digestive Health', 'dosage_form' => 'Capsule', 'strength' => '40mg', 'sku' => 'DG-002', 'requires_prescription' => true, 'track_stock' => true, 'min_stock' => 12, 'description' => 'Higher strength acid reducer.', 'tax' => 5],
                ['name' => 'Gaviscon', 'generic_name' => 'Alginate/Antacid', 'category' => 'Digestive Health', 'dosage_form' => 'Suspension', 'strength' => '150ml', 'sku' => 'DG-003', 'requires_prescription' => false, 'track_stock' => true, 'min_stock' => 15, 'description' => 'Fast relief for heartburn and reflux.', 'tax' => 5],
                ['name' => 'Buscopan', 'generic_name' => 'Hyoscine Butylbromide', 'category' => 'Digestive Health', 'dosage_form' => 'Tablet', 'strength' => '10mg', 'sku' => 'DG-004', 'requires_prescription' => false, 'track_stock' => true, 'min_stock' => 15, 'description' => 'Relieves stomach cramps and spasms.', 'tax' => 5],
                ['name' => 'Domperidone', 'generic_name' => 'Domperidone', 'category' => 'Digestive Health', 'dosage_form' => 'Tablet', 'strength' => '10mg', 'sku' => 'DG-005', 'requires_prescription' => true, 'track_stock' => true, 'min_stock' => 12, 'description' => 'Used for nausea and bloating.', 'tax' => 5],
                ['name' => 'Eno', 'generic_name' => 'Sodium Bicarbonate/Citric Acid', 'category' => 'Digestive Health', 'dosage_form' => 'Powder', 'strength' => '5g Sachet', 'sku' => 'DG-006', 'requires_prescription' => false, 'track_stock' => true, 'min_stock' => 25, 'description' => 'Quick relief antacid powder.', 'tax' => 0],
                ['name' => 'Duphalac', 'generic_name' => 'Lactulose', 'category' => 'Digestive Health', 'dosage_form' => 'Syrup', 'strength' => '200ml', 'sku' => 'DG-007', 'requires_prescription' => false, 'track_stock' => true, 'min_stock' => 12, 'description' => 'Mild laxative for constipation relief.', 'tax' => 5],
                ['name' => 'Motilium', 'generic_name' => 'Domperidone', 'category' => 'Digestive Health', 'dosage_form' => 'Tablet', 'strength' => '10mg', 'sku' => 'DG-008', 'requires_prescription' => true, 'track_stock' => true, 'min_stock' => 12, 'description' => 'Relieves nausea and speeds digestion.', 'tax' => 5],
                ['name' => 'Entrogermina', 'generic_name' => 'Bacillus Clausii', 'category' => 'Digestive Health', 'dosage_form' => 'Vial', 'strength' => '5ml', 'sku' => 'DG-009', 'requires_prescription' => false, 'track_stock' => true, 'min_stock' => 15, 'description' => 'Probiotic for digestive balance.', 'tax' => 5],
                ['name' => 'Practin', 'generic_name' => 'Cyproheptadine', 'category' => 'Digestive Health', 'dosage_form' => 'Syrup', 'strength' => '100ml', 'sku' => 'DG-010', 'requires_prescription' => false, 'track_stock' => true, 'min_stock' => 10, 'description' => 'Appetite stimulant syrup.', 'tax' => 5],
                ['name' => 'Digene', 'generic_name' => 'Antacid Combination', 'category' => 'Digestive Health', 'dosage_form' => 'Tablet', 'strength' => '1 tab', 'sku' => 'DG-011', 'requires_prescription' => false, 'track_stock' => true, 'min_stock' => 20, 'description' => 'Chewable antacid for acidity relief.', 'tax' => 5],
                ['name' => 'Rablet', 'generic_name' => 'Rabeprazole', 'category' => 'Digestive Health', 'dosage_form' => 'Tablet', 'strength' => '20mg', 'sku' => 'DG-012', 'requires_prescription' => true, 'track_stock' => true, 'min_stock' => 10, 'description' => 'Proton pump inhibitor for ulcers and reflux.', 'tax' => 5],
                ['name' => 'Cinolan', 'generic_name' => 'Simethicone', 'category' => 'Digestive Health', 'dosage_form' => 'Tablet', 'strength' => '80mg', 'sku' => 'DG-013', 'requires_prescription' => false, 'track_stock' => true, 'min_stock' => 15, 'description' => 'Relieves gas and bloating.', 'tax' => 5],
                ['name' => 'Lactulose Syrup', 'generic_name' => 'Lactulose', 'category' => 'Digestive Health', 'dosage_form' => 'Syrup', 'strength' => '120ml', 'sku' => 'DG-014', 'requires_prescription' => false, 'track_stock' => true, 'min_stock' => 12, 'description' => 'Gentle laxative syrup.', 'tax' => 5],
                ['name' => 'Simethicone Drops', 'generic_name' => 'Simethicone', 'category' => 'Digestive Health', 'dosage_form' => 'Drops', 'strength' => '30ml', 'sku' => 'DG-015', 'requires_prescription' => false, 'track_stock' => true, 'min_stock' => 12, 'description' => 'Infant colic and gas relief drops.', 'tax' => 5],
                ['name' => 'Antacid Plus', 'generic_name' => 'Aluminum/Magnesium Hydroxide', 'category' => 'Digestive Health', 'dosage_form' => 'Suspension', 'strength' => '170ml', 'sku' => 'DG-016', 'requires_prescription' => false, 'track_stock' => true, 'min_stock' => 15, 'description' => 'Everyday antacid suspension.', 'tax' => 5],
                ['name' => 'Enzar Digestive Enzyme', 'generic_name' => 'Pancreatin Enzyme Blend', 'category' => 'Digestive Health', 'dosage_form' => 'Tablet', 'strength' => '1 tab', 'sku' => 'DG-017', 'requires_prescription' => false, 'track_stock' => true, 'min_stock' => 12, 'description' => 'Aids digestion of fats, protein, and starch.', 'tax' => 5],

                // ---------------- Homeopathic ----------------
                ['name' => 'Arnica Montana 30C', 'generic_name' => 'Arnica Montana', 'category' => 'Homeopathic', 'dosage_form' => 'Pills', 'strength' => '30C', 'sku' => 'HM-001', 'requires_prescription' => false, 'track_stock' => true, 'min_stock' => 10, 'description' => 'Homeopathic remedy for bruising, trauma, and muscle soreness. Dr. Reckeweg/SBL brand commonly stocked in Pakistan.', 'tax' => 0],
                ['name' => 'Nux Vomica 30C', 'generic_name' => 'Nux Vomica', 'category' => 'Homeopathic', 'dosage_form' => 'Pills', 'strength' => '30C', 'sku' => 'HM-002', 'requires_prescription' => false, 'track_stock' => true, 'min_stock' => 10, 'description' => 'Homeopathic remedy for indigestion, acidity, and overindulgence.', 'tax' => 0],
                ['name' => 'Rhus Toxicodendron 30C', 'generic_name' => 'Rhus Tox', 'category' => 'Homeopathic', 'dosage_form' => 'Pills', 'strength' => '30C', 'sku' => 'HM-003', 'requires_prescription' => false, 'track_stock' => true, 'min_stock' => 10, 'description' => 'Homeopathic remedy for joint stiffness and sprains.', 'tax' => 0],
                ['name' => 'Calcarea Carbonica 30C', 'generic_name' => 'Calcarea Carb', 'category' => 'Homeopathic', 'dosage_form' => 'Pills', 'strength' => '30C', 'sku' => 'HM-004', 'requires_prescription' => false, 'track_stock' => true, 'min_stock' => 8, 'description' => 'Homeopathic constitutional remedy, general wellness.', 'tax' => 0],
                ['name' => 'SBL R89 Cold Drops', 'generic_name' => 'Combination Remedy', 'category' => 'Homeopathic', 'dosage_form' => 'Drops', 'strength' => '30ml', 'sku' => 'HM-005', 'requires_prescription' => false, 'track_stock' => true, 'min_stock' => 12, 'description' => 'Popular SBL homeopathic drops for cold and flu relief.', 'tax' => 0],
                ['name' => 'Belladonna 30C', 'generic_name' => 'Belladonna', 'category' => 'Homeopathic', 'dosage_form' => 'Pills', 'strength' => '30C', 'sku' => 'HM-006', 'requires_prescription' => false, 'track_stock' => true, 'min_stock' => 8, 'description' => 'Homeopathic remedy for fever and throbbing headaches.', 'tax' => 0],
                ['name' => 'Chamomilla 30C', 'generic_name' => 'Chamomilla', 'category' => 'Homeopathic', 'dosage_form' => 'Pills', 'strength' => '30C', 'sku' => 'HM-007', 'requires_prescription' => false, 'track_stock' => true, 'min_stock' => 8, 'description' => 'Homeopathic remedy for teething and irritability.', 'tax' => 0],
                ['name' => 'Pulsatilla 30C', 'generic_name' => 'Pulsatilla', 'category' => 'Homeopathic', 'dosage_form' => 'Pills', 'strength' => '30C', 'sku' => 'HM-008', 'requires_prescription' => false, 'track_stock' => true, 'min_stock' => 8, 'description' => 'Homeopathic remedy for colds and emotional sensitivity.', 'tax' => 0],
                ['name' => 'Sulphur 30C', 'generic_name' => 'Sulphur', 'category' => 'Homeopathic', 'dosage_form' => 'Pills', 'strength' => '30C', 'sku' => 'HM-009', 'requires_prescription' => false, 'track_stock' => true, 'min_stock' => 8, 'description' => 'Homeopathic constitutional remedy for skin conditions.', 'tax' => 0],
                ['name' => 'Sepia 30C', 'generic_name' => 'Sepia', 'category' => 'Homeopathic', 'dosage_form' => 'Pills', 'strength' => '30C', 'sku' => 'HM-010', 'requires_prescription' => false, 'track_stock' => true, 'min_stock' => 8, 'description' => 'Homeopathic remedy for hormonal and fatigue complaints.', 'tax' => 0],
                ['name' => 'Bryonia Alba 30C', 'generic_name' => 'Bryonia', 'category' => 'Homeopathic', 'dosage_form' => 'Pills', 'strength' => '30C', 'sku' => 'HM-011', 'requires_prescription' => false, 'track_stock' => true, 'min_stock' => 8, 'description' => 'Homeopathic remedy for dry cough and joint pain.', 'tax' => 0],
                ['name' => 'Ignatia Amara 30C', 'generic_name' => 'Ignatia', 'category' => 'Homeopathic', 'dosage_form' => 'Pills', 'strength' => '30C', 'sku' => 'HM-012', 'requires_prescription' => false, 'track_stock' => true, 'min_stock' => 8, 'description' => 'Homeopathic remedy for grief and emotional stress.', 'tax' => 0],
                ['name' => 'Lycopodium 30C', 'generic_name' => 'Lycopodium', 'category' => 'Homeopathic', 'dosage_form' => 'Pills', 'strength' => '30C', 'sku' => 'HM-013', 'requires_prescription' => false, 'track_stock' => true, 'min_stock' => 8, 'description' => 'Homeopathic remedy for digestive and liver complaints.', 'tax' => 0],
                ['name' => 'Natrum Muriaticum 30C', 'generic_name' => 'Natrum Mur', 'category' => 'Homeopathic', 'dosage_form' => 'Pills', 'strength' => '30C', 'sku' => 'HM-014', 'requires_prescription' => false, 'track_stock' => true, 'min_stock' => 8, 'description' => 'Homeopathic remedy for colds and emotional withdrawal.', 'tax' => 0],
                ['name' => 'Aconitum Napellus 30C', 'generic_name' => 'Aconite', 'category' => 'Homeopathic', 'dosage_form' => 'Pills', 'strength' => '30C', 'sku' => 'HM-015', 'requires_prescription' => false, 'track_stock' => true, 'min_stock' => 8, 'description' => 'Homeopathic remedy for sudden fever and anxiety.', 'tax' => 0],
                ['name' => 'Gelsemium 30C', 'generic_name' => 'Gelsemium', 'category' => 'Homeopathic', 'dosage_form' => 'Pills', 'strength' => '30C', 'sku' => 'HM-016', 'requires_prescription' => false, 'track_stock' => true, 'min_stock' => 8, 'description' => 'Homeopathic remedy for flu and performance anxiety.', 'tax' => 0],
                ['name' => 'SBL R42 Cold & Cough Drops', 'generic_name' => 'Combination Remedy', 'category' => 'Homeopathic', 'dosage_form' => 'Drops', 'strength' => '30ml', 'sku' => 'HM-017', 'requires_prescription' => false, 'track_stock' => true, 'min_stock' => 10, 'description' => 'SBL homeopathic drops for cough and throat irritation.', 'tax' => 0],
            ];

            foreach ($medicines as $medicineData) {
                $category = MedicineCategory::where('name', $medicineData['category'])->first();

                $medicine = Medicine::firstOrCreate([
                    'restaurant_id' => $restaurant->id,
                    'sku' => $medicineData['sku'],
                ], [
                    'name' => $medicineData['name'],
                    'generic_name' => $medicineData['generic_name'],
                    'category_id' => $category?->id,
                    'dosage_form' => $medicineData['dosage_form'],
                    'strength' => $medicineData['strength'],
                    'barcode' => null,
                    'requires_prescription' => $medicineData['requires_prescription'],
                    'track_stock' => $medicineData['track_stock'],
                    'min_stock' => $medicineData['min_stock'],
                    'description' => $medicineData['description'],
                    'tax' => $medicineData['tax'],
                ]);

                $resolvedCategoryId = $this->resolveMedicineCategoryId($medicine->category_id);
                if ($resolvedCategoryId && $medicine->category_id !== $resolvedCategoryId) {
                    $medicine->category_id = $resolvedCategoryId;
                    $medicine->save();
                }

                MedicineBatch::firstOrCreate([
                    'medicine_id' => $medicine->id,
                    'restaurant_id' => $restaurant->id,
                    'batch_number' => 'BATCH-'.$medicine->id,
                ], [
                    'mfg_date' => now()->subMonths(2)->toDateString(),
                    'expiry_date' => now()->addMonths(12)->toDateString(),
                    'purchase_price' => 80,
                    'selling_price' => 120,
                    'wholesale_price' => 100,
                    'quantity' => 100,
                    'rack_number' => 'R1',
                    'storage_location' => 'Main Shelf',
                ]);
            }
        });
    }

    protected function resolveMedicineCategoryId(?int $categoryId): ?int
    {
        if (! $categoryId) {
            return null;
        }

        $medicineCategory = MedicineCategory::find($categoryId);
        if ($medicineCategory) {
            return $medicineCategory->id;
        }

        $legacyCategory = MenuCategory::find($categoryId);
        if (! $legacyCategory) {
            return null;
        }

        $mappedCategory = MedicineCategory::firstOrCreate([
            'name' => $legacyCategory->name,
        ], [
            'status' => true,
        ]);

        return $mappedCategory->id;
    }
}
