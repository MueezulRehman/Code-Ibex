# Professional Hospital: optional module architecture

Status: **architecture only**. Blood Bank, Ambulance Dispatch, Biomedical
Equipment, Mortuary, Complaints, and Statutory Compliance are explicitly not
implemented in this slice.

## Boundaries and shared entities

Each optional module owns its workflow, status transitions, forms, reports and
audit events. Shared hospital entities remain canonical: `Restaurant`
(tenant), `User` (staff identity), `Patient`, `HospitalAdmission`, `Department`,
`Ward`, `Bed`, and `HospitalBilling`. Modules reference these entities by
foreign key and do not copy patient, admission, bed or staff records. A module
may publish domain events, but consumers must not write another module's tables.

* **Blood Bank** owns donors, units, compatibility, reservation, issue and
  transfusion traceability; it may link an admission, patient, surgery and
  billing charge.
* **Ambulance Dispatch** owns vehicles, crews, calls, dispatch and handover;
  it may create/link an admission but does not own admissions.
* **Biomedical Equipment** owns assets, calibration, maintenance and
  availability; clinical modules only reference an equipment asset.
* **Mortuary** owns decedent custody, releases and authorizations; it links a
  patient/admission and never changes clinical history directly.
* **Complaints** owns intake, assignment, response and resolution; it may
  reference a patient, admission and staff member with minimum necessary data.
* **Statutory Compliance** owns configurable obligations, submissions,
  evidence and sign-off; it consumes reporting/audit projections and does not
  mutate operational records.

## Tenancy, audit and security

Every module table must include `restaurant_id`, use the existing
`BelongsToRestaurant` scope, and have compound tenant indexes on foreign keys
and workflow status. Controllers must derive the tenant from the authenticated
manager context; request IDs must be validated against that tenant. There is
no cross-tenant lookup, export, queue job or notification.

Sensitive clinical, identity and statutory data is least-privilege: staff roles
receive only the module permissions needed for their workflow, while manager
reports are aggregate-first. Immutable audit events record actor, tenant,
timestamp, action, subject, old/new status and correlation ID; secrets and
credentials never enter audit payloads or exports. Exports require explicit
manager authorization and are logged.

## Module gates, dependencies and sequencing

Each module is gated by a business module key and subscription capability.
Routes, navigation, jobs and integrations must check the gate; migrations must
be safe for existing tenants and tenant provisioning must apply them
idempotently. Suggested sequencing:

1. Shared platform contract: permission catalogue, audit/event contract,
   retention policy and module health checks.
2. Biomedical Equipment (shared asset and maintenance primitives).
3. Blood Bank (patient/admission and surgery dependencies).
4. Ambulance Dispatch (patient/admission and location dependencies).
5. Mortuary (patient/admission, billing and authorization dependencies).
6. Complaints (patient/admission and staff dependencies).
7. Statutory Compliance last, consuming stable audit and report contracts.

Before each gate: migration rehearsal on a tenant fixture, tenant-isolation
tests, authorization tests, audit/retention review, focused browser flow and
rollback plan. No optional module is enabled by default; this document does
not authorize implementation or activation.
