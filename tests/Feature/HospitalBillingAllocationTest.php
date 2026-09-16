<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HospitalBillingAllocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_partial_insurer_and_patient_split_allocation_is_deterministic(): void
    {
        $this->markTestIncomplete('Implement allocation engine tests: insurer + patient partial splits, discount precedence, and idempotency.');
    }

    public function test_refund_allocation_across_multiple_payments_is_deterministic(): void
    {
        $this->markTestIncomplete('Implement refund allocation determinism tests.');
    }

    public function test_idempotent_payment_handling_prevents_duplicate_charges_on_retries(): void
    {
        $this->markTestIncomplete('Implement idempotent payment and retry tests.');
    }
}
