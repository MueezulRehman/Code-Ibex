<?php

namespace App\Services;

use App\Models\HospitalBilling;
use App\Models\HospitalMedicationFulfillment;
use App\Models\HospitalMedicationFulfillmentBatch;
use App\Models\HospitalMedicationOrder;
use App\Models\MedicineBatch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HospitalMedicationFulfillmentService
{
    public function fulfill(HospitalMedicationOrder $order, float $quantity, User $user, ?string $notes = null): HospitalMedicationFulfillment
    {
        if ($quantity <= 0) {
            throw ValidationException::withMessages(['quantity' => 'Dispensed quantity must be greater than zero.']);
        }

        return DB::transaction(function () use ($order, $quantity, $user, $notes): HospitalMedicationFulfillment {
            $existing = HospitalMedicationFulfillment::where('hospital_medication_order_id', $order->id)
                ->where('restaurant_id', $order->restaurant_id)
                ->lockForUpdate()->first();
            $alreadyDispensed = (float) ($existing?->quantity_dispensed ?? 0);
            $remaining = max($quantity - $alreadyDispensed, 0);

            if ($remaining <= 0) {
                throw ValidationException::withMessages(['quantity' => 'This order has already been fulfilled for that quantity.']);
            }

            $batches = MedicineBatch::where('restaurant_id', $order->restaurant_id)
                ->where('medicine_id', $order->medicine_id)
                ->where('quantity', '>', 0)
                ->where(function ($query): void {
                    $query->whereNull('expiry_date')->orWhereDate('expiry_date', '>=', today());
                })
                ->orderByRaw('expiry_date IS NULL')
                ->orderBy('expiry_date')
                ->lockForUpdate()->get();

            if ($batches->isEmpty()) {
                throw ValidationException::withMessages(['quantity' => 'No non-expired stock is available for this medicine.']);
            }

            $allocations = [];
            $remainingToDispense = $remaining;
            foreach ($batches as $batch) {
                if ($remainingToDispense <= 0) {
                    break;
                }
                $dispensed = min($remainingToDispense, (float) $batch->quantity);
                if ($dispensed <= 0) {
                    continue;
                }
                $batch->decrement('quantity', $dispensed);
                $allocations[] = [$batch, $dispensed];
                $remainingToDispense -= $dispensed;
            }

            $dispensedThisRequest = $remaining - $remainingToDispense;
            $totalDispensed = $alreadyDispensed + $dispensedThisRequest;
            $status = $totalDispensed >= $quantity ? 'dispensed' : 'partially_dispensed';

            $billing = HospitalBilling::create([
                'restaurant_id' => $order->restaurant_id,
                'patient_id' => $order->patient_id,
                'hospital_admission_id' => $order->hospital_admission_id,
                'charge_type' => 'pharmacy',
                'description' => 'Medication: '.$order->medicine->name,
                'quantity' => $dispensedThisRequest,
                'unit_amount' => $order->medicine->effectiveUnitPrice(),
                'status' => 'draft',
                'charged_at' => now(),
                'posted_by' => $user->id,
                'notes' => 'Hospital medication fulfillment.',
            ]);

            $fulfillment = HospitalMedicationFulfillment::updateOrCreate(
                ['restaurant_id' => $order->restaurant_id, 'hospital_medication_order_id' => $order->id],
                [
                    'medicine_id' => $order->medicine_id,
                    'medicine_batch_id' => $existing?->medicine_batch_id ?: $allocations[0][0]->id,
                    'fulfilled_by' => $user->id,
                    'quantity_requested' => $quantity,
                    'quantity_dispensed' => $totalDispensed,
                    'status' => $status,
                    'dispensed_at' => now(),
                    'notes' => $notes,
                    'hospital_billing_id' => $billing->id,
                ]
            );

            foreach ($allocations as [$batch, $dispensed]) {
                $allocation = HospitalMedicationFulfillmentBatch::firstOrNew([
                    'restaurant_id' => $order->restaurant_id,
                    'hospital_medication_fulfillment_id' => $fulfillment->id,
                    'medicine_batch_id' => $batch->id,
                ]);
                $allocation->quantity_dispensed = (float) $allocation->quantity_dispensed + $dispensed;
                $allocation->save();
            }

            return $fulfillment->load('batches.batch');
        });
    }
}
