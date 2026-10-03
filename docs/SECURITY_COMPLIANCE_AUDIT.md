# Medical and Hospital Security & Compliance Audit

**Audit date:** 2026-09-17  
**Scope:** Medical and Professional Hospital functionality currently built,
including the Emergency & Trauma slice.  
**Mode:** Read-only audit. No runtime security finding was silently fixed.

## Executive result

The system is **not ready for a clean security sign-off**. The strongest
tenant-scoped hospital controllers generally protect their primary records,
but the audit found high-severity authorization and tenant-boundary gaps in
shared medical records and in the broad manager middleware. These gaps can
expose or mutate protected health information (PHI) for a low-privilege
operational account or another tenant.

The five pending hospital modules remain blocked. No ICU, OPD, pharmacy
deepening, or payroll implementation was started during this audit.

## Severity summary

| Severity | Count | Status |
|---|---:|---|
| Critical | 0 | No confirmed critical finding in this read-only pass |
| High | 4 | Must be fixed before production clinical use |
| Medium | 5 | Must be addressed before formal compliance sign-off |
| Low | 1 | Track and harden during the next security cycle |

## Findings requiring approval before remediation

### SEC-HIGH-001 — Clinical routes are reachable by non-clinical operational roles

**Severity:** High  
**Confidence:** 9/10  
**Evidence:** `app/Http/Middleware/EnsureRestaurantManager.php` allows
`staff`, `cashier`, `kitchen`, and `rider` accounts into the manager route
stack. `app/Http/Middleware/EnsureModuleEnabled.php` immediately passes every
authenticated non-manager account without checking the module. Medical routes
are mounted in `routes/web.php` under this stack.

Patient and admission controllers do not consistently add clinical-role
checks. This means a low-privilege operational account can reach medical
listing, create, edit, or mutation routes when it can authenticate into the
manager panel. Module enablement is not a substitute for clinical
authorization.

**Impact:** PHI disclosure and unauthorized clinical mutation.  
**Recommended fix order:** 1. Introduce an explicit clinical-role middleware or
policy layer, then apply it route-by-route and action-by-action. Preserve
manager/owner access while limiting Doctor, Nurse, Pharmacist, Receptionist,
and Guard to the documented operations.  
**Performance impact:** Negligible in-memory checks; no additional data scan is
required.

### SEC-HIGH-002 — `CustomerAllergy` is not tenant-owned

**Severity:** High  
**Confidence:** 9/10  
**Evidence:** `app/Models/CustomerAllergy.php` has no
`BelongsToRestaurant` trait and no `restaurant_id`. Its controller uses global
route-model binding and does not verify that the related customer or patient
belongs to the current restaurant.

**Impact:** Direct ID guessing can read, edit, or delete another tenant's
allergy record; creation can reference records outside the current tenant.  
**Recommended fix order:** 2. Add tenant ownership to the schema and model,
backfill safely, scope route binding and validation, then add cross-tenant
read/update/delete tests.  
**Performance impact:** One indexed `restaurant_id` predicate. No material
impact when indexed.

### SEC-HIGH-003 — `MedicineInteraction` is not tenant-owned

**Severity:** High  
**Confidence:** 9/10  
**Evidence:** `app/Models/MedicineInteraction.php` has no tenant scope or
restaurant identifier. The controller validates global medicine IDs and
accepts globally bound interaction records for edit and delete operations.

**Impact:** Cross-tenant disclosure or mutation of medication-safety rules and
creation of interactions between medicines owned by different tenants.  
**Recommended fix order:** 3. Decide whether interactions are tenant data or a
curated platform catalog. If tenant data, add indexed ownership and scope both
medicine references and route binding.  
**Performance impact:** Indexed tenant predicates are negligible. If converted
to a platform catalog, access checks remain in-memory after a single lookup.

### SEC-HIGH-004 — HR, attendance, and salary routes rely on bypassable module gating

**Severity:** High  
**Confidence:** 9/10  
**Evidence:** The Professional Hospital bundle enables `hr`, `staff`,
`attendance`, and `salary`. The attendance and salary controllers do not
consistently enforce manager/HR authorization, while the module middleware
allows non-manager roles through.

**Impact:** Staff, cashier, kitchen, or rider accounts may read or mutate
attendance and salary records.  
**Recommended fix order:** 4. Add explicit authorization policies to every HR,
attendance, and salary read/write route; test all clinical and operational
roles.  
**Performance impact:** Negligible in-memory authorization checks.

### SEC-MED-001 — OTP content and phone numbers are written to logs by default

**Severity:** Medium  
**Confidence:** 9/10  
**Evidence:** `config/services.php` defaults `SMS_DRIVER` to `log`.
`app/Services/SmsService.php` logs the phone number and complete OTP message.
The WhatsApp log path should be treated the same way until it is redacted.

**Impact:** Anyone with application-log access can obtain valid OTPs during
their lifetime. This is authentication material, not clinical data, but it is
a sensitive-data exposure.  
**Recommended fix order:** 5. Never log OTP values; redact phone numbers; fail
closed when no delivery provider is configured in production.  
**Performance impact:** None beyond removing a log payload.

### SEC-MED-002 — Patient PII is stored as plaintext

**Severity:** Medium  
**Confidence:** 9/10  
**Evidence:** Patient `name`, `phone`, `email`, CNIC/guardian fields, address,
and emergency-contact fields are ordinary database columns. No application
encryption cast or searchable hash design was found.

**Impact:** Database readers, backups, and exports can see direct identifiers.  
**Recommendation:** Do not encrypt silently. Obtain product and compliance
approval first. A safe design is encrypted value columns plus normalized,
indexed HMAC/hash lookup columns for fields that must remain searchable.
Encryption should be introduced with a staged migration, key management,
backfill, dual-read period, and export review.  
**Performance impact:** Encryption/decryption adds CPU per read/write; indexed
blind-index lookup preserves search performance. Large list screens should
select only required fields and avoid decrypting unused columns.

### SEC-MED-003 — Production debug and secure-cookie defaults are unsafe

**Severity:** Medium  
**Confidence:** 9/10  
**Evidence:** `.env.example` contains `APP_DEBUG=true`; session secure-cookie
behavior is environment-controlled and has no secure production default.

**Impact:** Misconfigured deployments may expose exception details or transmit
session cookies without the `Secure` attribute.  
**Recommendation:** Production deployment validation must require
`APP_DEBUG=false`, HTTPS, secure and HTTP-only cookies, an appropriate
SameSite policy, and trusted proxy configuration.

### SEC-MED-004 — No MFA enforcement was found for clinical or manager accounts

**Severity:** Medium  
**Confidence:** 8/10  
**Impact:** Password compromise gives direct access to PHI and clinical
operations.  
**Recommendation:** Require MFA for manager, owner, Doctor, Nurse,
Pharmacist, and other privileged clinical accounts, with recovery and
tenant-safe enrollment flows.

### SEC-MED-005 — Password policy is inconsistent

**Severity:** Medium  
**Confidence:** 8/10  
**Evidence:** Some password flows use only an eight-character minimum while
other account creation paths use different validation conventions.

**Recommendation:** Centralize password rules, require confirmation, use the
configured bcrypt/argon work factor, and add breached-password screening if
approved.

### SEC-LOW-001 — Sensitive exception text may be propagated without a redaction policy

**Severity:** Low  
**Confidence:** 7/10  
**Evidence:** Operational logging includes exception messages in some generic
failure paths. The audit did not confirm a centralized PHI redaction processor.

**Recommendation:** Add a logging processor/redaction policy and prohibit
patient identifiers, diagnoses, prescriptions, CNIC, and clinical note bodies
from structured context.

## Tenant isolation audit

### Passed or substantially covered

The following areas have tenant-aware models/controllers or focused tests:

- Patient, Doctor, Visit, Prescription, Patient medical records
- Hospital Admission, Department, Ward, Bed, Bed Transfer
- Nursing assignments and staff rosters
- Vital signs and vital-sign event history
- Hospital clinical notes and note events
- Hospital care plans and care-plan events
- Laboratory orders/results and Radiology orders/results
- Surgery and operating-theatre records
- Hospital medication orders/administration
- Hospital billing, billing payments, insurance, invoices, receipts, and reports
- Discharge summaries and discharge-summary events
- Emergency cases and emergency route actions

Focused tests already cover several direct-ID and cross-tenant mutation paths,
including admissions, beds, laboratory, radiology, billing, clinical notes,
insurance, invoices, receipts, and Emergency.

### Not clean

- `CustomerAllergy` is missing tenant ownership and route scope.
- `MedicineInteraction` is missing tenant ownership and route scope.
- There is no single exhaustive cross-tenant sweep covering every listed
  model, every show/edit/print route, and every JSON/API response.
- The current test suite is fragmented by module and therefore cannot be
  treated as proof that every direct route-model binding is covered.

**Required test work before sign-off:** Add one exhaustive matrix test that
creates two tenants, creates representative records for every listed medical
model and audit/event model, and asserts cross-tenant list, direct ID,
route-binding, mutation, print/export, and JSON responses are rejected or
empty as appropriate. This test was not added during this report-only pass so
that the authorization and data-model changes remain separately reviewable.

## Authorization matrix audit

| Role | Current conclusion |
|---|---|
| Manager/owner | Broad access is present, subject to module enablement |
| Doctor | Explicit checks exist in several clinical controllers, but not uniformly across all routes |
| Nurse | Explicit checks exist for vitals and some nursing flows, but not uniformly |
| Pharmacist | No complete route-by-route matrix was found |
| Receptionist | Emergency intake is explicitly allowed; broader medical access is not consistently constrained |
| Guard | No explicit clinical denial matrix was found |
| Staff/cashier/kitchen/rider | Broad manager middleware admits them; this is a high-severity gap |

Routes protected only by `module:<key>` are not sufficient authorization for
clinical data. Every medical route needs a second role/staff-type/assignment
check appropriate to its operation.

## Sensitive data and notification audit

- No confirmed code path intentionally sends diagnosis, prescription, or
  clinical-note content through the OTP SMS builder.
- OTP SMS logging currently exposes the OTP and phone number by default.
- Medical queue notification code uses a log driver by default and requires a
  separate redaction review before production use.
- Patient names, phones, email, CNIC/guardian data, address, and emergency
  contact data are stored as plaintext. No encryption-at-rest change was made.
- Demo account passwords are generally passed through Laravel hashing helpers
  or model casts in seeders/tests; a complete account-by-account proof should
  be added before production data seeding. No plaintext seeded production
  credential was confirmed in this audit.

## Audit-trail completeness

### Covered

- Vital-sign create/correct actions create vital-sign events.
- Clinical-note create/correct actions create note events.
- Care-plan changes create care-plan events.
- Laboratory and radiology result changes have audit history.
- Discharge summary recording/correction has append-only events.
- Emergency routing creates the admission and billing records atomically, but
  it does not yet create a dedicated emergency audit event.

### Gaps

The following clinical/financial writes do not have a consistently demonstrated
immutable actor/timestamp audit event in the current code:

- Patient and Doctor corrections
- Prescription create/correct/void
- Medication dispensing/fulfillment and administration corrections
- Hospital admission create/update/transfer lifecycle
- Bed assignment and bed release
- Surgery lifecycle changes
- Hospital billing create, post, void, insurance-link, payment, and refund
- Hospital insurance policy changes
- Emergency intake, triage, and routing
- Attendance and salary mutations

Model timestamps are not a substitute for an immutable audit record containing
actor, action, before/after values, and reason.

Audit writes currently occur synchronously in the request transaction. This
provides consistency but adds one database insert to the request. The measured
design impact is expected to be small; moving legally required audit events to
an asynchronous job would risk losing the event unless an outbox/transactional
queue is used. No queue migration is recommended until durability
requirements are approved.

## Authentication and session security

- Manager, admin, CEO, and customer login POST routes use `throttle:5,10`.
- The audit did not run a fresh 429 browser test in this report-only pass;
  existing route declarations show throttling is present.
- Laravel authentication middleware protects manager clinical writes, so a
  de-authenticated request should be rejected server-side. A dedicated test
  should be added for every clinical write group to prove a stale session
  cannot submit a write.
- Session lifetime is configurable and database-backed by default.
- Session encryption and secure-cookie behavior are environment-controlled,
  not enforced by safe production defaults.
- MFA was not found.
- Password validation is inconsistent as described above.

## Performance baseline

The requested Admissions, Vital Signs, and Billing baseline could not be
reliably measured in this audit environment. The available active dataset did
not contain a representative volume, and an additional bootstrap/probing run
encountered a MySQL deadlock during module seeding. No fabricated latency or
query-count numbers are reported.

Required follow-up baseline:

1. Use a production-like tenant snapshot or generated fixture volume.
2. Measure p50/p95 latency and query count for admissions list, vitals list,
   billing list/report, invoice, and export.
3. Capture indexed query plans before remediation.
4. Repeat after tenant predicates, audit events, and any PII lookup design are
   approved.

## Recommended fix order

1. Close the broad operational-role bypass on medical and HR routes.
2. Make `CustomerAllergy` tenant-owned and scope all related validation/binding.
3. Decide and implement the tenant/platform ownership model for
   `MedicineInteraction`.
4. Add the exhaustive cross-tenant route and model matrix test.
5. Redact OTP/notification logs and fail closed for unconfigured production
   delivery.
6. Add immutable audit events for billing, dispensing, admission/bed, surgery,
   prescription, insurance, and emergency writes.
7. Enforce production session/debug settings and add stale-session write tests.
8. Approve an MFA and centralized password-policy design.
9. Decide on PII encryption plus searchable HMAC columns; do not migrate
   plaintext fields before that approval.
10. Establish the representative performance baseline and repeat it after
    approved remediations.

## Sign-off status

**Not approved for a clean production security sign-off.** The high-severity
authorization and tenant-boundary findings must be fixed and retested first.
The five pending hospital modules remain blocked until this audit is reviewed,
the remediation order is approved, and the required baseline tests pass.
