# Medical Module Expansion Plan

## Purpose and scope

This document describes the existing medical/clinic module and the approved
two-track hospital roadmap. The two business types have different scopes:

- **Clinic Hospital**: the current outpatient/clinic workflow using doctors,
  patients, visits, queue, consultations, prescriptions, dispensing,
  notifications, and medical reporting.
- **Professional Hospital**: the future full hospital workflow adding inpatient
  admissions, wards, rooms, beds, nursing, laboratory, radiology, operating
  theatre, hospital billing, discharge, and related departments.

Full-hospital models remain design-only until their individual scope,
permissions, tenancy boundaries, and acceptance tests are approved. The
admission, nursing, vital-signs, admission clinical notes, laboratory,
radiology, operating-theatre, and hospital-billing slices below have now been
individually implemented and verified; the remaining hospital domains remain
planning-only.

The inpatient medication administration slice is also implemented behind the
dedicated `hospital-medication` module.

The inpatient staff duty-roster slice is also implemented behind the dedicated
`hospital-staff-rosters` module. It schedules tenant-scoped nurse and doctor
shifts, optionally associates them with a department or ward, prevents
overlapping active windows for a staff account, and supports manager
confirmation/cancellation. Active clinical staff can view only their own
rosters.

Discharge summaries now have an append-only tenant audit trail. Initial
discharge recording creates a `recorded` event; manager/owner corrections
require a reason and create an `edited` event containing previous and new
clinical snapshots. Corrections cannot alter admission/discharge timestamps,
cross tenant boundaries, or create a second summary for an admission.

The plan follows the existing repository conventions documented in
`docs/MEDICAL_MODULE_AUDIT.md`:

- tenant-owned records use `BelongsToRestaurant`;
- medical workflow states are plain strings validated with `Rule::in()` or
  equivalent request rules;
- Manager medical routes use the `manager.*` naming prefix;
- the existing `module:medical` gate is the default gate unless a dedicated
  module is explicitly approved.

## Convention cross-check

Before updating this plan, the following project references were re-read and
cross-checked:

- `docs/CODEIBEX_TENANCY_CODING_STANDARD.md`
- `doc.md`, including **Rules For Every Change** and **Definition Of Done**
- `docs/SUPER_ADMIN_VIEW_STRUCTURE.md`
- `docs/MANAGER_VIEW_STRUCTURE.md`
- `docs/CEO_VIEW_STRUCTURE.md`
- `docs/CUSTOMER_VIEW_STRUCTURE.md`

Any future entities proposed here are tenant-owned and must use the existing
`BelongsToRestaurant`/Tenancy helpers. Proposed workflow states remain plain
strings, not enums. Proposed Manager routes use `manager.<resource>.*`, and
future Manager pages belong under the feature-oriented
`resources/views/manager/<resource>/` structure, extending
`manager.layout.master` and defining `page-content`. No new tenancy, status,
route, or layout convention is introduced by this plan.

## Existing foundation

The current module already contains medicines, prescriptions, medical
records, appointments, allergies, interactions, purchases, medical reports,
insurance, and related workflows. Existing records commonly store
`restaurant_id` and use explicit tenant-aware validation.

## Business-type defaults audit

Clinic / Doctor registration defaults now cover the Phase 1–5 medical
workflow: Medical, Medical Records, Patient Records, Prescriptions, Allergies,
Pharmacy, Inventory, Stock, Item Sales, appointments, notifications, and
follow-up reminders. Restaurant-only modules such as kitchen display, recipes,
reservations, delivery zones, and dispatch are excluded.

Hospital was added through the existing persisted BusinessType/module-pivot
flow with the same defaults. Pharmacy was reviewed and remains focused on
medicine sales, inventory, prescriptions, and pharmacy controls without the
clinic patient/appointment workflow by default. This correction is
non-retroactive; existing tenants retain their current enabled modules.

## Approved two-track Hospital roadmap

### Track A: Clinic Hospital

Clinic Hospital is the immediately deliverable Hospital type. It shares the
existing OPD workflow with Clinic / Doctor and does not introduce inpatient
models. Its enabled modules are:

- Medical, Medical Records, Patient Records, Prescriptions, Allergies;
- Doctors, Patients, Visits, Queue, Consultations, and follow-up reminders;
- Pharmacy, Inventory, Stock, Item Sales, and dispensing;
- Notifications and Medical Reports;
- Staff, HR, Attendance, Salary, Cashbook, and Expenses where the plan allows.

Clinic Hospital access is granted at three layers:

1. **Business module gate**: the Hospital's enabled module list controls which
   feature routes exist.
2. **Account role**: the existing `User` role controls admin, manager, and
   staff authentication.
3. **Staff type and assignment**: medical staff types and future department
   assignments limit operational screens and actions.

### Track B: Professional Hospital

Professional Hospital is a separate future business type and must not silently
inherit all Clinic Hospital behavior. It will add dedicated module keys,
models, routes, and permissions for:

- Patient registration and inpatient admissions;
- Departments, wards, rooms, beds, transfers, and occupancy;
- Nursing assignments, rounds, and vital signs;
- Staff duty scheduling and shift rosters;
- Laboratory orders, samples, verification, and results;
- Radiology/imaging orders, reports, and result verification;
- Operating theatre scheduling and surgery records;
- Inpatient pharmacy charges and medication administration;
- Hospital billing, insurance, discharge summaries, and reconciliation;
- Ambulance, blood bank, equipment, compliance, and complaints only when
  explicitly selected for the hospital deployment.

Professional Hospital should be represented by a distinct persisted
`BusinessType` and a separate module bundle. Existing Clinic / Doctor and
Clinic Hospital tenants must not receive these modules automatically.

#### Inpatient medication administration slice (implemented)

The separately gated `hospital-medication` module adds tenant-scoped
`HospitalMedicationOrder` and `HospitalMedicationAdministration` records. An
order links a catalog `Medicine`, `Patient`, and active
`HospitalAdmission`; it does not copy or replace outpatient `Prescription` or
dispensing data. Owners/managers and doctors assigned directly to an admission
or its department may create orders. Owners/managers can administer for
operational correction, while a nurse must have an active
`NursingAssignment` for the admission to record or update a dose.

Orders and administration records capture dose, route, frequency, scheduled
and administered times, status, reason/notes, actor, and recorded timestamps.
Admission and order windows are validated server-side. Administered times
cannot be in the future or precede the scheduled dose. A tenant-scoped unique
scheduled-dose key plus optional idempotency key prevents unsafe duplicate
administration. No automatic stock decrement or billing charge is introduced:
existing medicine stock and the established manual `medication` hospital
billing charge type remain available without speculative coupling.

### Access model for both tracks

Every new feature must declare its access before implementation:

| Access layer | Example | Enforcement |
|---|---|---|
| Business type | Clinic Hospital vs Professional Hospital | Persisted BusinessType and default module bundle |
| Module | Laboratory, IPD, Radiology, OT, Billing | `module:<key>` middleware and navigation visibility |
| Account role | Admin, Manager, Staff | Existing authenticated User role/policies |
| Staff type | Doctor, Nurse, Pharmacist, Lab Technician | Validated `User.staff_type` plus policy/action checks |
| Department | Emergency, Pharmacy, Laboratory, Radiology | Tenant-scoped assignment and department authorization |
| Record ownership | Patient, admission, lab result | Tenant context and explicit restaurant-scoped queries |

No feature is complete until unauthorized users receive a consistent denial,
authorized users can complete the workflow, and cross-tenant access is tested.

The current Professional Hospital Vital Signs implementation applies this
model at record level: managers and owners can record tenant observations,
nurses can view and record only for active nursing assignments, doctors can
review observations without recording them, and receptionist, pharmacist,
guard, and other staff accounts are denied clinical access. Vital Sign rows
store both `patient_id` and `hospital_admission_id` so readmission history
remains explicit rather than relying only on a mutable admission lookup.

The Professional Hospital laboratory slice is implemented behind the
`hospital-laboratory` module gate. Tenant-scoped `LabOrder` and `LabResult`
records link both `Patient` and `HospitalAdmission`. Managers/owners can
create orders and manage results, doctors can create and view orders/results,
and nurses can view records for active assigned admissions. Receptionists,
pharmacists, guards, and other staff receive a server-side 403.

### Admission clinical notes / EMR foundation (implemented)

Professional Hospital now has a separately gated `hospital-clinical-notes`
module for the first admission-level EMR slice. `HospitalClinicalNote` rows
link a note to the tenant, the specific `HospitalAdmission`, and its author;
the note type is explicitly `clinical`, `progress`, or `nursing`. This is
deliberately separate from outpatient `MedicalRecord` and `Visit` data, so
readmission and inpatient documentation retain their encounter boundary.

Owners/managers can record all note types and correct or void notes. An
assigned doctor can create and view only clinical/progress notes for that
doctor's admissions. An active assigned nurse can create nursing notes and
view notes for the assigned active admission. Other staff receive a server-
side denial, and all list, admission, author, assignment, and write queries
remain tenant-scoped.

Recorded timestamps must fall within the admission window, including the
discharge boundary when present. Manager corrections require a reason and
preserve before/after content and timestamp snapshots in the append-only
`HospitalClinicalNoteEvent` audit trail; voids likewise require a reason.

### Structured inpatient care plans (implemented)

The separately gated `hospital-care-plans` module builds on the admission EMR
foundation with tenant-scoped `HospitalCarePlan` and append-only
`HospitalCarePlanEvent` records. Each plan links both `Patient` and the
specific `HospitalAdmission`, and stores a problem/diagnosis, goal,
interventions, optional responsible role/user, status, opened/review/resolved
timestamps, notes, and nursing progress fields. It intentionally does not
copy or replace outpatient `MedicalRecord`, `Visit`, prescription, or
dispensing records.

Owners/managers and doctors assigned directly to an admission or its
department can create and update plans. Nurses must have an active assignment
for the admission; they can view and update only nursing progress/status and
cannot change tenant, admission, patient, or clinical ownership fields.
Timestamps are checked against admission/discharge boundaries and
`review_due_at` cannot precede `opened_at`. Resolved plans require
`resolved_at`; active plans may resolve or cancel, while resolved/cancelled
plans are terminal and cannot be reopened. Corrections, status changes, and
nursing progress changes record before/after audit snapshots. Central and
tenant migrations use explicit short MySQL-safe FK/index names.

The Manager dashboard now includes a tenant-scoped Professional Hospital
operations panel. In addition to admissions, beds, nursing, vitals,
discharges, and department occupancy, it shows outstanding posted billing,
recent unpaid charges, today's and next-seven-day surgery counts, and pending
laboratory/radiology orders. Each enabled metric links to its operational
screen; queries are aggregate/eager-loaded and are not emitted for tenants
without the relevant hospital module.

The Professional Hospital Operating Theatre slice is implemented behind the
`hospital-operating-theatre` module gate. Tenant-scoped `OperatingRoom` and
`Surgery` records link to `Patient` and `HospitalAdmission`. Owners/managers
manage rooms and surgery records; doctors can schedule, update, and view
surgeries; nurses can view surgeries only for active assigned admissions; and
receptionists, pharmacists, and guards are denied. Surgery lifecycle records
also include optional anaesthetist/assistant-surgeon links, surgical team
details, consent status, a pre-operative checklist, post-operative notes, and
completion time. Managers/doctors can update the checklist, but consent and
all readiness items are required before starting or completing a case.
are `scheduled`, `in_progress`, `completed`, and `cancelled`. Admission-window
validation, end-after-start validation, tenant-safe room/admission validation,
and same-room overlap prevention are covered by focused tests.

The slice intentionally does not yet model equipment/resource requirements or
calendar/drag-and-drop views. Anaesthetist and surgical-team assignments,
pre-operative checklists, post-operative notes, and the room/surgery update
workflow are implemented. Production-tenant migration rollout and
authenticated browser verification remain operational follow-up items.

Laboratory and Radiology result corrections now require manager/owner
authorization and a correction reason. Each initial result, verification, and
subsequent edit writes a tenant-scoped event containing the previous values,
new values, actor, timestamp, and notes. Silent no-op edits and cross-tenant
updates are rejected. Result audit history is visible to authorized managers
from the respective module pages.

### Proposed full-hospital model ownership

All records below are tenant-owned and must use the existing tenancy pattern.
They must link to the shared `Patient`, `Doctor`, `Visit`, and `User` records
where applicable; duplicate patient or outpatient models must not be created.

| Model | Track | Main access |
|---|---|---|
| `HospitalAdmission` | Professional Hospital | Admin, doctor, reception, authorized nursing |
| `Department` | Both, when enabled | Admin and department managers |
| `Ward`, `Room`, `Bed` | Professional Hospital | Admin, reception, nursing |
| `NursingAssignment` | Professional Hospital | Nursing lead and assigned nurses |
| `NursingAssignmentEvent` | Professional Hospital | Admin, nursing lead, audit readers |
| `VitalSign` | Professional Hospital | Nurses and treating doctors |
| `VitalSignEvent` | Professional Hospital | Admin, authorized clinical audit readers |
| `HospitalClinicalNote` | Professional Hospital | Admin, assigned doctors, assigned nurses |
| `HospitalClinicalNoteEvent` | Professional Hospital | Admin, authorized clinical audit readers |
| `HospitalDashboard` | Professional Hospital | Admin, manager, authorized clinical staff |
| `HospitalTransfer` | Professional Hospital | Admin, manager, authorized admissions staff |
| `LabOrder`, `LabResult` | Professional Hospital | Doctors, lab technicians, authorized records staff |
| `RadiologyOrder`, `RadiologyResult` | Professional Hospital | Doctors, radiology staff, authorized records staff |
| `LabResultEvent`, `RadiologyResultEvent` | Professional Hospital | Admin, authorized clinical audit readers |
| `OperatingRoom`, `Surgery` | Professional Hospital | Doctors, OT staff, scheduling/admin |
| `HospitalBilling` | Professional Hospital | Billing staff, admin, authorized managers |
| `DischargeSummary` | Professional Hospital | Treating doctor, nursing lead, admin |

Conditional models such as BloodBank, AmbulanceTrip, Equipment, Complaint, and
MortuaryRecord require a separate yes/no decision before being added.

### Delivery order

1. Finish Track A browser verification with a newly registered Clinic Hospital.
2. Add reusable staff profile and department foundations only after the fields
   and assignment rules are approved.
3. Add Professional Hospital as a separate business type with no enabled
   inpatient modules until its first module slice is implemented.
4. Implement admissions and departments before wards, beds, nursing, or
   laboratory workflows.
5. Implement each clinical department as an independently gated, tenant-safe
   slice with focused tests and browser verification.
6. Add consolidated billing and discharge only after departmental records are
   stable.

### Track A verification result

The dedicated `Clinic Hospital Demo` tenant was registered through the existing
Super Admin form. Its Hospital defaults persisted 20 medical modules, and
Manager login successfully loaded Doctors, Patients, Medical Queue,
Prescriptions, Queue Notifications, Medical Reports, and Staff creation.
Staff creation exposed Nurse, Guard, Receptionist, and Pharmacist. The existing
`test-restaurant` tenant was not recalculated or modified.

The current data model stores doctor information as text in places such as
`MedicalRecord.doctor_name` and `Prescription.doctor_name`. A future Doctor
directory can replace or complement those text fields, but relationship
migration is a separate decision and is not assumed by this plan.

## Planned phases

| Phase | Scope | Dependency |
|---|---|---|
| Phase 1 | Doctor directory and Patient entity foundation | Confirm Doctor contact fields and the complete Patient field list, including `cnic`, guardian fields, `date_of_birth`, `gender`, and `is_dependent`, before finalizing any migration |
| Phase 2 | Patient check-in and the per-doctor token queue described below | Phase 1 Doctor and patient identity |
| Phase 3 | Doctor consultation workflow, visit history, diagnosis, and prescriptions tied to a visit | Phase 2 queue entry and visit records |
| Phase 4 | Pharmacist dispensing, stock deduction, and prescription fulfillment | Phase 3 prescription-to-visit linkage |
| Phase 5 | Digital notifications, SMS delivery, kiosk/display polish, reporting, and operational analytics | Stable queue and dispensing workflows |

The queue is foundational to consultation and dispensing, but it should not be
implemented until the numbering policy and the Doctor foundation are approved.

### Phase 4 implementation scope

Phase 4 uses the existing tenant-scoped `MedicineBatch` inventory. A verified
prescription can be dispensed once from its medical prescription detail page.
Managers and admins are operational overrides; staff users must have
`staff_type` set to `pharmacist`. Dispensing consumes non-expired batches in
expiry order inside a transaction, rejects insufficient stock without changing
prescription status, and records the dispensing user and timestamp. The
existing `used` prescription status is the fulfillment state; no separate
dispensing table is introduced. Because authenticated users are central
records, tenant prescriptions retain `dispensed_by` as an ID without a
tenant-local users foreign key. Focused and full automated coverage is green,
and live stock-deduction verification has completed successfully. Dispensing
locks the prescription row inside the stock transaction to prevent concurrent
double fulfillment, ignores expired batches, and consumes valid batches in
expiry order.

### Phase 5 initial slice

The first Phase 5 change hardens the existing public queue-token and kiosk
display pages. They continue to expose only doctor, token, queue date/status,
and no patient or medical information. Responses now use `no-store` cache
headers so shared waiting-area screens do not display stale queue state.
Live browser verification confirmed both pages return HTTP 200 and show no
patient identity or medical information.
The individual token page also refreshes every 10 seconds, matching the kiosk
display behavior.
Both screens also provide an explicit manual refresh control and show the
local last-checked time without exposing patient identity.
The notification foundation now includes a provider-independent queue message
builder. External SMS/WhatsApp delivery remains separate and must use tenant
opt-in/preferences, queued delivery, retries, and privacy-safe templates.
The notification preference UI should live in the Manager medical/business
area. Super Admin should manage provider/platform configuration only, not
tenant recipient consent or patient-facing message content.

### Phase 5 notification delivery foundation

The first delivery slice is now implemented without selecting an external
provider. Manager business settings store tenant opt-in plus enabled channels,
while patient records store explicit phone notification consent captured during
check-in. A `TenantAwareJob` dispatches one job per enabled channel and uses
Laravel's standard retry/backoff configuration. The default
`NotificationProvider` binding is a log-only driver, so local and automated
verification exercise the full pipeline without sending real messages.

External delivery remains blocked until a provider, credentials, operational
consent policy, and failure monitoring are approved and configured.
The current configuration exposes `MEDICAL_QUEUE_NOTIFICATION_DRIVER=log` as
the safe default; unsupported or real-provider drivers must not be enabled
until their implementation and credentials are reviewed.

The existing Manager medical reports landing page now provides
tenant-scoped, date-filtered workflow KPIs for patients, active doctors,
visits, completed visits, prescriptions, dispensing, and the current queue.
Detailed medicine and inventory reports remain separate and read-only.

## Doctor foundation (Phase 1)

The planned Doctor feature is a tenant-scoped CRUD directory under
`manager.doctors.*`, using the existing `module:medical` gate unless a new
module key is approved.

Phase 1 is intentionally narrow and surgical: it delivers only the Doctor
model directory and the Patient foundation. It does not include the queue,
token, visit, or check-in flow. This keeps the first milestone small,
shippable, and testable before any queue logic is added.

## Approved Decisions

1. **Token numbering:** daily reset per doctor.
2. **Patient identity:** a separate `Patient` entity is the primary identity for
   clinical check-in; it is tenant-scoped and follows the existing
   `BelongsToRestaurant` convention. If the same person is both a paying
   customer and a patient, they are linked by a separate identity relation, not
   merged into one record by default.
3. **Allergy handling:** keep `CustomerAllergy` for general retail/customer use,
   and add a separate `PatientAllergy` model for medical check-in and clinical
   safety. This avoids mixing retail purchase/loyalty data with actual clinical
   allergy history, and it keeps the medical model aligned with patient-centric
   workflows.
4. **SMS delivery:** deferred to Phase 5. The first release uses link-only token
   access for patients; SMS can be added later with a provider integration.
5. **Doctor status list:** `pending`, `active`, `declined` only. No extra
   statuses are added in the first implementation.
6. **Record-sharing scope:** a patient's clinical history is visible only to the
   doctors who have personally treated them, plus designated Manager/admin users
   with appropriate operational access. It is not visible to all doctors in the
   clinic by default.
7. **Patient identity matching:** `cnic` is the strongest identifier when
   present and is unique per tenant. If CNIC is provided, duplicate detection
   matches by CNIC only; a match blocks creation, shows a clear “Patient
   already registered” message, and redirects or opens the existing Patient
   record. If no CNIC is provided, matching falls back to the combined
   `name` + `phone` + `date_of_birth` values. A guardian's phone alone must
   never identify a Patient: a guardian's own Patient record and a dependent's
   Patient record remain distinct even when they share a phone number.
8. **Queue and visit references:** `Visit`, `QueueEntry`, and `QueueCounter`
   reference `patient_id`; the old `customer_id` field is not used in the medical
   queue model.

This Patient identity decision began as a documentation-only design revision.
The approved follow-up implementation now adds the new columns and
tenant-scoped CNIC uniqueness through a separate migration; the Patient model,
CRUD validation, duplicate handling, and registration form must remain aligned
with the rules above. Queue, visit, billing, and other Phase 2+ workflows
remain outside this implementation.

## Patient Check-In & Token Queue System (Phase 2)

### Goals

Reception must be able to register a visit quickly, assign it to a specific
Doctor, and give the patient both a physical and digital way to follow the
queue. Doctors must be able to advance their own queue manually and consult
the patient's clinic history before recording the visit outcome.

There is no real-time auto-polling requirement. The doctor or assistant
explicitly presses **Next** when ready; the counter changes as a result of that
staff action and/or the next page reload.

### Reception check-in

1. Front desk staff searches for an existing `Patient` record or creates a new
   one.
2. Staff selects the Doctor for this visit.
3. Staff optionally records a reason or purpose. The field may be skipped.
4. The system creates a Visit and one QueueEntry in that Doctor's queue.
5. The system assigns the next token number for that Doctor and the current
   daily numbering period.
6. Reception prints a paper slip and exposes the same token through the
   digital-token flow.

The check-in operation must be transactional so a visit cannot be created
without its queue entry and token number, and a retry cannot accidentally
assign two tokens to one visit.

### Independent queue numbering

Each Doctor has an independent sequence. A token belongs to exactly one
Doctor and is never compared with another Doctor's sequence. For example,
Doctor A may issue Token 1 while Doctor B is already serving Token 32.

The recommended numbering design is a `QueueCounter` keyed by:

- `restaurant_id`
- `doctor_id`
- `queue_date`

The counter stores the last issued number and is incremented inside a
transaction with a row lock. This is preferred over a
`current_token_number` field on `doctors` because it supports independent
queues, resets, historical reporting, and concurrent reception terminals
without overwriting the Doctor's profile.

The selected policy must be encoded in the unique key, counter logic, display,
paper slip, digital link, and tests. Daily reset per doctor is approved.

### Proposed entities

All proposed entities are tenant-scoped with `restaurant_id`, indexes, and
tenant-safe route binding. The approved naming and identity direction is to use
`Patient` rather than `Customer` for medical queue and visit workflows.

#### `Doctor`

Phase 1 entity. Planned fields include:

- `restaurant_id`
- `name`
- `specialty`
- approved contact fields (at minimum the fields confirmed for the Doctor
  directory)
- `status`

#### `Patient`

The primary identity model for all clinic check-in activity. It is separate from
`Customer` and is intended for medical identity, visit history, allergies,
prescriptions, and patient-level clinical records.

The approved Patient field list is:

**Core fields**

- `cnic` — string, nullable, unique per tenant; the strongest unique identifier
  when present;
- `date_of_birth` — date, nullable;
- `gender` — string, nullable; allowed values are `male`, `female`, and
  `other`;
- `phone` — string, nullable; contact information only and never the primary
  identity.

The existing tenant-owned Patient identifier and name remain part of the
foundation. The same physical person may also be a retail customer, but the
medical record remains Patient-scoped and linked only when needed by explicit
business rules.

**Dependent / Guardian block**

These fields are shown only when `is_dependent` is `true`:

- `is_dependent` — boolean, default `false`;
- `guardian_name` — string, nullable;
- `guardian_cnic` — string, nullable;
- `guardian_phone` — string, nullable;
- `relationship` — string validated with `Rule::in()`, using exactly:
  `self`, `father`, `mother`, `son`, `daughter`, `spouse`, `brother`,
  `sister`, `guardian`, `other`.

At registration, reception must explicitly choose who the visit is for:
the person themselves or a dependent such as a child or elderly parent. When
dependent is selected, the guardian fields become visible and required.
`relationship` remains a plain string, not an enum or separate table.

CNIC remains optional, but the registration form should show a soft
recommendation warning when it is empty for an adult patient. Future Patient
search must prioritize CNIC first, then name/phone, while applying the
identity rules above rather than treating a shared guardian phone as a match.

The complete field list above, including `cnic`, guardian fields,
`date_of_birth`, `gender`, and `is_dependent`, must be confirmed before any
Patient migration is finalized. Because the Phase 1 table already exists,
implementing this approved design will require a separate follow-up migration;
this document revision does not create or run one.

#### `Visit` or `Admission`

Represents the clinical encounter created during check-in. Likely fields:

- `restaurant_id`
- `doctor_id`
- `patient_id`
- optional `reason`
- visit status
- timestamps for check-in, start, and completion
- diagnosis and doctor notes, or a relationship to the existing medical-record
  structure

The final relationship to `MedicalRecord` must avoid duplicating the same
diagnosis and notes in two sources of truth.

#### `QueueCounter`

One row per restaurant, Doctor, and queue date. Likely fields:

- `restaurant_id`
- `doctor_id`
- `queue_date`
- `last_issued_number`
- timestamps

The row must have a uniqueness constraint matching the numbering policy.

#### `QueueEntry` or `Token`

Represents the patient's place in one Doctor's queue. Likely fields:

- `restaurant_id`
- `doctor_id`
- `visit_id`
- `patient_id`
- `token_number`
- queue status
- optional reason snapshot
- `called_at`, `started_at`, `completed_at`, and `no_show_at`
- timestamps

The token number is unique within its restaurant, Doctor, and queue date, not
globally across all Doctors.

#### `TokenAccess` (optional)

An opaque, revocable public token may be needed for a patient link or kiosk
lookup. It must not expose sequential database IDs or unrestricted medical
history. This entity is optional if a signed, expiring URL can provide the
same guarantees without storing a separate record.

### Allergy and patient history model

The project should not reuse `CustomerAllergy` for clinical medical records.
`CustomerAllergy` remains valid for retail customer-related allergy or dietary
preferences, but the medical funnel must operate on a separate
`PatientAllergy` model. This keeps patient history, allergy warnings, and drug
interaction checks tied to the clinical identity, not the billing/customer
profile.

This matters because the allergy/interaction check is explicitly moving into
Phase 3 of the doctor-prescribing workflow. Before a prescription is saved,
the system should check the patient's `PatientAllergy` records and relevant
medicine interactions. The rule is attached to the patient encounter rather
than a retail ordering profile.

## Token & Attendance Record-Keeping

Every `QueueEntry`/token is permanently retained as a historical record. It
must not be deleted or inferred only from `QueueCounter.last_issued_number`.
Daily and Doctor-level reporting derives its totals from `QueueEntry` rows:

- total tokens issued: all entries created for the Doctor and queue date;
- total patients seen: entries with status `completed`;
- total no-shows: entries with status `no_show`;
- cancelled entries: reported separately from no-shows.

### Required Phase 2 no-show policy

This is required queue behavior, not an optional extension. The first release
uses an explicit manual policy: a Doctor or authorized assistant marks a
called patient `no_show` after the clinic's configured waiting window has
elapsed and the patient has not presented. No background timeout job is
required for the first release, so the action is visible and auditable.

The planning decision is a configurable 10-minute waiting window after
`called_at`; the clinic may change that setting before launch without changing
the workflow or report semantics.

A same-day no-show may be re-queued only by reception or a Manager, and it
receives a new QueueEntry and new token so the original attendance record
remains immutable. Re-calling the old token is not allowed. `no_show` and
`cancelled` remain separate statuses and separate report counts.

### Queue status workflow

Use plain strings, consistent with the existing medical module. The proposed
baseline is:

- `waiting`: checked in and waiting to be called;
- `called`: Doctor has selected the next token;
- `in_progress`: consultation has started;
- `completed`: visit is finished;
- `no_show`: patient did not attend when called;
- `cancelled`: visit was cancelled before completion.

Allowed transitions should be explicit:

`waiting -> called -> in_progress -> completed`

`waiting -> cancelled`, `called -> no_show`, and
`in_progress -> cancelled` may be allowed with appropriate permissions and an
audit trail. Reopening or moving backwards is not assumed.

### Current token and manual Doctor counter

The Doctor's door display resolves the current queue state from the active
Doctor-specific `QueueEntry` records and the approved numbering period. It
must not read a single global clinic counter.

Recommended behavior for **Next**:

1. lock the Doctor's queue for the active period;
2. finalize or retain the currently called/in-progress entry according to its
   explicit action;
3. select the next `waiting` entry in token order;
4. mark it `called` and set `called_at`;
5. return the newly called token to the Doctor view and door display.

The action must be permission-protected and idempotent enough that a browser
retry cannot skip a patient. The UI should show the current token, the next
waiting token, and clear actions for **Next**, **No-show**, and completion as
approved.

No background polling, websocket, or automatic token advancement is planned.
A shared screen can refresh on a staff-triggered reload or use a later,
explicitly approved refresh mechanism.

### Token delivery and displays

Both delivery modes are always available:

#### Paper slip

Reception prints the token number, Doctor name, clinic/business name, and the
queue date or period. It may include a short digital link or QR code, but the
paper flow must remain useful without a phone.

#### Digital token

The patient receives a link. SMS is deferred to Phase 5. The link shows only
safe queue information, such as:

- Doctor name;
- token number;
- current token being served;
- approximate queue position if approved;
- waiting/called/in-progress/completed state;
- clinic instructions.

It must not reveal diagnosis, prescriptions, prior visits, or another
patient's identity. Links should use opaque/signed identifiers and expiry or
revocation rules.

#### Shared clinic screen or kiosk

The kiosk/clinic screen shows the current called token per Doctor and the
Doctor name, with no medical details. It must be usable by patients without
working phones. The display should be scoped to the clinic and should not
require patient authentication for the public queue view beyond an approved
screen access mechanism.

### Doctor queue and clinical history

The Doctor queue view opens the selected queue entry and indicates whether the
patient is new or returning for that clinic.

For a returning patient, authorized clinical staff may view the patient's
prior clinic history, including previous diagnoses, prescriptions, and Doctor
notes. Access must remain tenant-scoped and role-authorized, but the visibility
rule is intentionally limited: only the treating doctor(s) and designated
Manager/admin users may access prior medical history, not every doctor at the
clinic by default.

The Doctor records the diagnosis and notes against the specific Visit/queue
entry and prescribes medicine through the existing prescription workflow.
Whether existing text fields such as `doctor_name` are migrated to
`doctor_id` is a separate Phase 3 data-model decision.

### Pharmacist workflow

The Pharmacist view lists prescriptions tied to a Visit/queue entry. Dispensing
against a prescription must:

1. verify the prescription belongs to the current tenant;
2. verify it is in a dispensable status;
3. deduct stock using the existing inventory conventions;
4. record the dispenser, quantity, and timestamp;
5. transition the prescription/dispense status consistently;
6. prevent duplicate dispensing or overdrawing stock.

The exact dispense entity and status transitions should reuse the earlier
prescription-to-dispense design where possible rather than creating a second
stock deduction path.

### Billing and financial integration

A `Visit` should carry a consultation fee when the clinic charges for the
consultation itself. The fee should be recorded as a visit-level charge, with
posting to the existing `cashbook`/finance module when the visit is completed
or settled. The system should not silently merge a consultation charge with an
entire patient basket unless a checkout workflow explicitly chooses that
behavior.

For the first release, the recommended approach is:

- consultation fee = visit-level charge, captured on the visit or invoice line;
- medicine dispensed from the pharmacy = separate dispense/stock flow, with its
  own billing at the pharmacy counter or as a separate invoice line depending on
  the existing POS invoice conventions;
- no automatic nested invoice merge between consultation and dispensary unless a
  later business rule approves it.

This keeps financial posting simple, preserves the current cashbook structure,
and avoids mixing patient care with retail checkout logic in a way that could
confuse the accounting flow.

### Walk-in vs. appointment reconciliation

The queue system must work alongside the existing `Appointment` model without
creating a second conflicting scheduling mechanism.

Recommended rule:

- a scheduled appointment may optionally create a related `Visit` when the
  patient checks in and is assigned a token;
- walk-ins still create a `Visit` and a `QueueEntry` directly without a prior
  appointment record;
- the token system is therefore the operational queue layer, while
  `Appointment` remains the booking/scheduling layer.

This avoids conflicting state where the same patient is seen twice in the queue
(or a queued token is created without a real arrival). A scheduled appointment
should be considered a specialized visit type, not a separate queue
implementation.

### Permissions and auditability

Reception can create check-ins and print tokens. Doctors or authorized
assistants can advance their own queue and update visits. Pharmacists can
dispense approved prescriptions. Managers/admins can correct or cancel
entries according to approved policy.

Queue advancement, no-shows, cancellations, status changes, and clinical
updates should be auditable with actor and timestamp information. Cross-tenant
access must be covered by feature tests before browser verification.

## Recommended Extensions

These are optional future-phase additions. They are not required for the Phase
1–5 launch scope and should not be treated as launch blockers.

### Clinical safety

- **Extension:** vitals capture (blood pressure, temperature, weight) recorded
  pre-visit by a nurse/assistant and attached to the visit.
- **Natural phase:** Phase 3.

### Operational

- **Extension:** priority/emergency queue insert that does not disturb the normal
  sequential numbering.
- **Natural phase:** Phase 2.
- **Extension:** doctor availability/schedule gating so reception cannot issue
  tokens for a doctor who is not marked "in" for that day.
- **Natural phase:** Phase 2.

### Compliance & trust

- **Extension:** audit trail for medical record views and edits, including who
  accessed which record and when; this is a stricter requirement than general
  business records and must be treated as a medical-data compliance need.
- **Natural phase:** Phase 3 or 5.
- **Extension:** printable/downloadable prescription slip for the patient,
  separate from the internal clinical record.
- **Natural phase:** Phase 3.

### Patient experience

- **Extension:** an "almost your turn" notification tied to the digital token,
  using a threshold-triggered nudge rather than full real-time polling.
- **Natural phase:** Phase 5.

### Multi-branch support

- **Extension:** decide whether token queues and patient histories need to be
  branch-scoped given the existing `branches` and branch inventory system.
- **Natural phase:** Phase 2 or 5, depending on the rollout model.

### Reporting

- **Extension:** doctor/clinic analytics such as patients seen per day, average
  wait time, and no-show rate, especially relevant for Hospital business types.
- **Natural phase:** Phase 5.

- **Extension:** estimated wait time shown to waiting patients, calculated from
  each Doctor's average consultation time using completed visit timestamps.
- **Natural phase:** Phase 5.

- **Extension:** a daily Doctor summary report that is printable/exportable and
  includes patients seen, no-shows, average consultation time, and revenue
  when billing is enabled.
- **Natural phase:** Phase 5.

### Data governance

- **Extension:** a medical-data retention and export policy defining how long
  Patient, Visit, QueueEntry, prescription, and related clinical records are
  retained; whether a Patient or business can export the records; whether
  deletion is allowed or must be restricted to anonymization; and which
  compliance standard or jurisdiction the clinic targets.
- **Natural phase:** Phase 1 for policy definition, before medical data
  accumulates; implementation enforcement may continue through later phases.

### Billing and insurance

- **Extension:** direct Visit-level linkage to the existing `InsuranceClaim`
  model, allowing a consultation or dispensed treatment to generate or link
  to a claim instead of requiring a fully separate manual process.
- **Natural phase:** Phase 4.

### Localization

- **Extension:** structural language/label localization for the printed slip
  and kiosk display, including local languages such as Urdu even if English is
  the only shipped translation initially.
- **Natural phase:** Phase 2 design, with the first additional language rollout
  in Phase 5.

## Definition of done for the queue phase

Implementation may begin only after the approved decisions above are locked and
the schema is finalized. The Phase 2 queue phase is complete when:

- reception can create a tenant-scoped visit and Doctor-specific token;
- numbering is independent per Doctor and follows the approved daily reset rule;
- paper and digital token paths both work;
- the clinic screen and Doctor door display show safe queue state;
- Doctor/assistant manual **Next** advances exactly one queue;
- returning-patient history is protected and visible only to authorized staff;
- diagnoses and prescriptions attach to the correct visit;
- Pharmacist dispensing safely deducts stock;
- no-show, cancellation, and completion transitions are tested;
- tenant isolation, authorization, duplicate-submit, and stock-safety tests
  pass;
- the relevant Manager pages are browser-verified and this plan is updated
  with the final decisions.

## Hospital-Specific Extensions (Full Hospital Management Scope — Future Initiative)

**Documentation-only scope notice:** this section maps a possible full hospital
management initiative for future scoping. None of the items below is part of
the approved Phase 1–5 Medical Module Expansion Plan, and none is authorized
for implementation. It requires its own separate approval process before any
models, migrations, routes, permissions, or views are created, the same way the
OPD plan was approval-gated.

### Additional hospital staff roles

Hospital operations may require staff types beyond the current generic Staff
account, including Lab Technician, Radiologist/Imaging Technician,
Physiotherapist, Billing/Cashier staff, Housekeeping/cleaning staff, and
Ambulance driver/dispatcher. These would be a field-level extension of the
existing `User`-backed Manager staff records, not a new permission system.
Surgeon and Anesthesiologist are likely specialties of the separate `Doctor`
directory rather than staff roles, but that boundary is a future decision.

### Patient registration and admission

This would define a formal inpatient admission flow, including registration,
admission authorization, responsible department, and an admission lifecycle.
It is genuinely new hospital functionality and is distinct from the approved
OPD walk-in check-in and token flow.

### OPD (Outpatient)

OPD is already represented by the approved Doctor, Patient, queue, visit, and
dispensing plan. The future hospital initiative would link to those existing
records rather than create a second outpatient workflow.

### IPD (Inpatient) and ward/bed management

This would track inpatient occupancy, room and bed assignment, ward transfers,
admission duration, and length of stay. It is genuinely new functionality,
although it would link to the future admission and shared Patient records.

### Professional Hospital physical bed locations (implemented)

The inpatient capacity slice now supports an optional physical hierarchy:
`Ward -> Room/Hall/Bay -> Bed`. `WardLocation` is tenant-scoped and stores a
validated location type (`room`, `hall`, or `bay`), while `beds.location_id`
is nullable. This preserves existing ward-level beds and the existing
`HospitalAdmission.bed_id` contract for deployments that do not need room
detail.

Managers can create locations and assign beds to them through the existing
`hospital-wards-beds` screen. Server-side validation requires the location to
belong to the selected ward and active tenant; route/model authorization also
rejects cross-tenant wards. Admission creation and subsequent bed assignment
continue to use the same tenant-scoped available-bed checks and occupancy
transaction, with the optional location shown in bed selections.

### Operation Theatre (OT) scheduling

This coordinates surgery bookings across surgeon, OT room, date, and time slot,
with conflict checks and an operational schedule. The first implementation
slice is complete as described above. Anaesthesiologist/team assignment,
resource requirements, peri-operative documentation, and richer schedule
views remain future work and may reference Doctor specialties and admission
records.

### Laboratory

This would cover test ordering, sample collection and tracking, result entry,
verification, and linkage of results to the patient's longitudinal record.
It is genuinely new functionality, with a future link to Patient and admission
or visit records.

### Radiology and imaging

This would cover imaging orders and the order-to-result flow for X-ray,
ultrasound, CT, MRI, and similar services. It is genuinely new functionality,
with references to Patient, the ordering clinician, and the relevant encounter.

**Implemented slice (2026-09-14):** Professional Hospital radiology is now
available behind the `hospital-radiology` module gate. Tenant-scoped
`RadiologyOrder` and `RadiologyResult` records link to Patient and
HospitalAdmission, with manager/owner result management, doctor ordering and
view access, assigned-nurse admission visibility, and timestamp validation.

### Pharmacy and dispensing

Outpatient pharmacy and prescription dispensing remain covered by the approved
Phase 4 plan. The inpatient medication slice now adds admission-linked orders
and administration records behind `hospital-medication`, reusing the
tenant-scoped Medicine catalog without copying outpatient prescriptions.
Medication stock decrement, fulfillment, and automatic billing remain
intentionally out of scope; the existing manual hospital `medication` charge
type can be used when a hospital chooses to bill a dose.

### Blood bank

If the target hospital maintains a blood bank, this would cover inventory,
donor records, blood-group compatibility and cross-matching, reservation, and
issue tracking. It is genuinely new functionality and should remain optional
until the hospital scope confirms that a blood bank is operated.

### Ambulance dispatch and tracking

This would cover transport requests, dispatcher assignment, driver and vehicle
allocation, trip status, and handoff information. It is genuinely new
functionality, with a possible link to Patient registration and admission.

### Billing and insurance

Hospital billing would consolidate room and bed charges, OT services,
laboratory, radiology, pharmacy, and other departmental charges into one
inpatient patient invoice. Insurance processing would build on the existing
`InsuranceClaim` model and the approved future Visit-level linkage, but the
multi-department inpatient invoice is a new billing workflow.

The initial hospital billing slice is implemented as tenant-scoped admission
charges with draft, posted, paid, and void states. Admission discharge now
reconciles these states: posted charges remain outstanding until paid and block
discharge; draft, paid, and void charges are excluded from the block. The
admissions screen exposes total, paid, and outstanding amounts before a
manager submits discharge.

### HR and staff scheduling

This would provide duty rosters, shifts, availability, and attendance planning
for doctors, nurses, and other hospital staff. It is separate from the existing
generic Staff/Attendance module: the existing records may be referenced, but
hospital scheduling rules are new functionality.

### Biomedical equipment and inventory

This would track hospital equipment, maintenance, calibration, assignment,
and lifecycle separately from retail and pharmacy stock. It is genuinely new
inventory functionality and must not be folded into medicine or retail stock
tables without a separate design.

### Discharge workflow

This would coordinate discharge authorization, discharge-summary generation,
follow-up scheduling, medication instructions, and final billing
reconciliation. It is genuinely new functionality that would link admission,
clinical records, billing, and pharmacy.

The implemented discharge slice records a discharge summary and performs the
first billing reconciliation gate. The server rechecks posted unpaid charges
inside the discharge transaction, preserving the tenant boundary and preventing
discharge until those charges are settled or voided.

### Medical records and EMR

This would provide a longitudinal electronic medical record spanning the
departments and services a patient receives during one admission, rather than
only a single OPD visit note. It is a new cross-department record boundary
that must be reconciled with the approved Patient, Visit, prescription, and
future laboratory/imaging records.

### Mortuary management

If required by the target hospital type, this would cover decedent intake,
identity and release tracking, authorized handover, and related operational
records. It is genuinely new functionality and remains conditional on the
hospital's scope and jurisdiction.

### Patient feedback and complaints

Hospital-specific feedback would cover complaints, service areas, escalation,
resolution, and response tracking. It may link to the existing general
`feedback` module, but hospital complaint workflows may need a distinct scope
and stricter medical-record access boundaries.

### Compliance and reporting

This would define hospital-specific regulatory reports, audit requirements,
retention rules, and operational/statutory exports. It is a new compliance
scope; the applicable jurisdiction and standard must be requested and
confirmed before any assumptions are made.

**Future-initiative boundary:** the full-hospital list above is now an approved
planning target, not blanket authorization to build every model at once.
Implementation must proceed one module slice at a time. The optional
WardLocation hierarchy is now implemented as a backward-compatible
Ward -> Room/Hall/Bay -> Bed structure. The immediate follow-up is
authenticated production-tenant rollout and browser verification, followed by
a reviewed
hospital billing/discharge charge model that integrates admission, theatre,
laboratory, radiology, and pharmacy services.
# Phase 3 status

The consultation workflow is implemented on the existing Visit/QueueEntry
records. Managers can record diagnosis and notes, update consultation status,
review authorized returning-patient history, and print a consultation or
prescription slip. Prescriptions carry Visit/Patient/Doctor links and are
checked against active patient allergy triggers and existing medicine
interactions before saving. Pharmacist dispensing, stock deduction, billing,
SMS, and hospital scope are intentionally deferred.

### Professional Hospital bed transfer history (implemented)

Bed movements now persist as explicit tenant-scoped `BedTransfer` records with patient/admission links, previous and new bed/ward/department references, manager request and approval references, transfer timestamps, reasons, and notes. The transfer transaction updates the target bed availability, keeps `HospitalTransfer` records in sync, blocks no-op and cross-tenant requests, and rejects timestamps that fall before admission or after the current time. The Manager admissions UI shows the recent bed-movement history directly on each admission.
