# CodeIbex Development Focus

## Purpose

This document is the working priority list for the project. Before starting new
features, use this file to decide what must be completed first and to record
whether the current foundation is stable.

## Current Focus

**Stabilize and verify the existing application before adding new features.**

The immediate goal is not to expand the product. The immediate goal is to make
the current admin, manager, authentication, tenancy, and core business flows
reliable enough that future changes can be made safely.

### Professional Hospital module access and demo tenant

- Professional Hospital has one centralized implemented-module bundle in
  `ModuleService::professionalHospitalModuleKeys()`. Super Admin business
  management displays the bundle and the locked roadmap modules
  (`hospital-emergency`, `hospital-blood-bank`, and `hospital-dialysis`) without
  enabling them.
- Manager hospital navigation is grouped under Pharmacy, Laboratory &
  Diagnostics, Inpatient Care, Operating Theatre, Billing & Insurance, and
  Reports. Existing route names and `module:<key>` middleware remain the
  authorization source of truth.
- The explicit tenant-safe demo command is
  `php artisan hospital:seed-demo --restaurant=18 --dry-run` followed by
  `php artisan hospital:seed-demo --restaurant=18`. It refuses non-Professional
  Hospital tenants, tenants without a dedicated database, and implicit tenant
  selection. The seeder uses stable business keys and can be run repeatedly.
- Demo coverage includes departments, wards/locations/beds, patients,
  admissions, doctors, nursing assignments/rosters, vitals, laboratory,
  radiology, surgery, medication, clinical notes, care plans, billing,
  payments, insurance, and report-compatible records.

### Hospital operational reporting and exports validation

- The manager operational reports surface admission census, ward/location/bed
  occupancy, bed turnover and length of stay, nursing coverage, medication
  administration status, pending laboratory/radiology work, surgery schedule,
  billing/payments/refunds/outstanding balances, and discharges/readmissions.
- Reports remain manager-only and tenant-scoped, with ward/location filters and
  section-specific or complete CSV export.
- Focused regression coverage now verifies both report aggregation/isolation and
  the billing CSV export response. Authenticated browser verification confirmed
  all report sections and styled filters render for Professional Hospital Demo.

### Clinical result audit history

Laboratory and Radiology result creation, verification, and correction now
record tenant-scoped audit events with before/after snapshots, actor,
timestamp, and correction notes. Manager-only corrections reject silent
no-op edits and cross-tenant access. Focused Laboratory and Radiology tests
cover authorization, tenant isolation, and audit event creation.

### Business-type medical defaults audit

- Clinic / Doctor defaults now select Medical, Medical Records, Patient
  Records, Prescriptions, Allergies, Pharmacy, Inventory, Stock, and Item
  Sales, while excluding restaurant-only modules.
- Hospital is now a persisted business type using the same medical defaults.
- Pharmacy includes Prescriptions but remains pharmacy-focused and does not
  enable the clinic patient/appointment workflow by default.
- Existing businesses are not recalculated; only future registration defaults
  and business-type module pivots are affected.
- Module-cap conflicts are now rejected explicitly instead of silently
  dropping recommended modules. Current seeded plans are uncapped.
- Focused default-module regressions pass (11 tests, 166 assertions), and
  affected authorization/default tests pass (19 tests, 187 assertions).
- Vite production build passes, and the changed PHP files pass syntax
  validation.
- With MySQL running, the full suite passed at 224/224 tests and 913
  assertions. Browser verification at `/admin/restaurants/create` confirmed
  Hospital appears in the business-type dropdown and Clinic / Doctor and
  Hospital each apply the medical defaults without restaurant-only modules.
- Hospital staff gating is covered by a regression test confirming Nurse,
  Guard, Receptionist, and Pharmacist options are exposed through the existing
  User-based staff flow.
- Track A browser verification completed with the dedicated `Clinic Hospital
  Demo` Hospital tenant. Registration persisted 20 medical modules, Manager
  login succeeded, and Doctors, Patients, Medical Queue, Prescriptions,
  Queue Notifications, Medical Reports, and Staff creation all returned HTTP
  200. Staff creation displayed Nurse, Guard, Receptionist, and Pharmacist.
- The existing `test-restaurant` record was not recalculated; its existing
  enabled-module list remained unchanged.
- Manager Access now also displays the business owner account created during
  registration. Registration stores that first login with role `admin`, while
  the per-manager access list intentionally contains only separate `manager`
  accounts; owner accounts inherit all enabled business modules.

### Approved two-track Hospital plan

- **Clinic Hospital** is the immediate clinic-style track. It uses the
  existing Doctor, Patient, Visit, Queue, Consultation, Prescription,
  Dispensing, Notification, and Medical Reports workflows.
- **Professional Hospital** is a separate future business type for inpatient
  admissions, departments, wards, rooms, beds, nursing, laboratory, radiology,
  operating theatre, hospital billing, discharge, and optional departments.
- Professional Hospital features must use separate persisted module bundles and
  explicit `module:<key>` gates. They must not be added automatically to
  Clinic / Doctor, Clinic Hospital, Pharmacy, or existing businesses.
- Access must be defined at business type, module, User role, staff type,
  department, and tenant-record layers before each model is implemented.
- The shared `User` model remains the login/staff account source. A future
  `StaffProfile` may hold employee ID, license, qualification, department,
  employment, and emergency-contact fields without duplicating credentials.
- Full-hospital implementation order: Track A browser verification, staff
  profile/department foundations, Professional Hospital type, admissions and
  departments, wards/beds/nursing, laboratory/radiology, OT, then billing and
  discharge.

#### Radiology slice (implemented)

Professional Hospital tenants can now use the gated `hospital-radiology`
module. Tenant-scoped radiology orders and results link each record to a
Patient and HospitalAdmission. Managers/owners create orders and manage
results; doctors create and view orders; nurses see records for active
admission assignments. Receptionists, pharmacists, and guards are denied.
Orders cannot predate admission and results cannot predate their order.

#### Operating Theatre / Surgery slice (implemented)

Professional Hospital tenants can now use the separately gated
`hospital-operating-theatre` module at `/manager/operating-theatre`. Tenant-
scoped `OperatingRoom` and `Surgery` records link to `Patient` and
`HospitalAdmission`; rooms and surgery schedules are visible through the
Manager navigation. Owners/managers manage rooms and surgery records, doctors
can schedule/update/view surgeries, and nurses can view surgeries only for
admissions with an active nursing assignment. Receptionists, pharmacists, and
guards are denied.

The implementation supports `scheduled`, `in_progress`, `completed`, and
`cancelled` statuses, validates surgery times against admission/discharge
windows, requires end time after start time, and rejects overlapping
non-cancelled surgeries in the same room. Surgery records capture optional
anaesthetist/assistant surgeon links, surgical team details, consent, a
pre-operative checklist, post-operative notes, and `completed_at`. Consent
and all identity, site, fasting, and laboratory readiness items are required
before `in_progress` or `completed`; completed cases require post-operative
notes.

Calendar/drag-and-drop scheduling remains out of scope. The clinical
completeness migration is applied to tenant 18; rollout to every production
tenant and live authenticated browser verification remain operational
follow-up work.

#### Professional Hospital dashboard operations (implemented)

The Manager dashboard's hospital panel now adds tenant-safe outstanding posted
billing totals, a short recent unpaid-charge list, surgery counts for today and
the next seven days, and pending laboratory/radiology order counts. Links are
shown only when the corresponding Professional Hospital module is enabled;
clinic and non-hospital dashboards retain their existing metrics and layout.

#### Professional Hospital inpatient medication (implemented)

Professional Hospital tenants now have the separately gated
`hospital-medication` module. Admission-linked medication orders reuse the
tenant medicine catalog without duplicating outpatient prescriptions or
dispensing records. Managers/owners and doctors assigned to an admission (or
its department) can create orders; managers and nurses with an active nursing
assignment can record scheduled, administered, omitted, refused, or cancelled
doses. Server-side checks enforce tenant isolation, admission/order time
windows, required reasons, non-future administration times, and one record per
scheduled dose. Records capture dose, route, scheduled/administered times,
actor, notes, and audit timestamps. Existing hospital billing remains a
focused manual charge workflow; medication does not silently create speculative
stock or billing transactions.

#### Professional Hospital inpatient care plans (implemented)

The separately gated `hospital-care-plans` module adds tenant-scoped,
admission- and patient-linked `HospitalCarePlan` records without duplicating
outpatient medical records. Plans capture a problem/diagnosis, goal,
interventions, optional responsible role/user, active/resolved/cancelled
status, opening/review/resolution timestamps, notes, and nursing progress.
Owners/managers and doctors authorized for the admission can create and update
plans. Nurses can view plans for active nursing assignments and update only
nursing progress/status fields; tenant and admission ownership is never
client-editable.

Admission-window and review ordering validation is enforced server-side.
Resolved plans require `resolved_at`, resolved/cancelled plans are terminal
and cannot be reopened, and corrections, status changes, and nursing updates
write append-only `HospitalCarePlanEvent` audit snapshots. Central and tenant
migrations use explicit short MySQL-safe foreign-key and index identifiers.

## Work Order

### Hospital admission billing reconciliation

Hospital admission discharge now reconciles tenant-scoped hospital billing:
posted charges must be paid or void before discharge, while draft, paid, and
void charges do not block the workflow. The admissions screen shows total,
paid, and outstanding posted billing amounts and warns managers before they
submit discharge. This validation is enforced server-side in the discharge
transaction as well as in the form.

#### Formal inpatient invoice and payment receipts

Hospital billing charges now receive a tenant-scoped, unique invoice number
when created. Managers/owners retain the existing draft, posted, paid, and
void lifecycle; posting and payment do not change the outstanding calculation.
Recording a payment captures the payment method, reference, payer, timestamp,
and a tenant-scoped receipt number. Payments are append-only ledger rows:
managers can record positive partial payments and refunds, while a posted
ledger row may only be voided with its actor and timestamp retained. Net
settlement cannot exceed the invoice amount and a refund cannot exceed the
already settled balance. A charge remains `posted` while partially settled and
becomes `paid` only at full settlement; a refund can return a paid charge to
`posted`. Posted outstanding balance (not gross charge total) continues to
block discharge. Paid charges expose a manager-only printable receipt at
`manager.hospital-billing.receipt`. Existing `InsuranceClaim` records remain customer-linked and can still be
optionally attached to a billing charge/invoice. Hospital patient identity is
now represented by the tenant-scoped `HospitalPatientInsurance` profile, with
payer, policy/member numbers, coverage dates, status, notes, and an explicit
Patient relation. Managers can create, edit, and remove profiles at
`manager.hospital-billing.insurance`, link a profile to an admission, and
select it on a billing charge. Every profile, admission link, and profile
selection is checked against the current tenant and admission patient; legacy customer claims are not duplicated or rewritten. If an existing claim
is explicitly linked to a hospital profile, billing also verifies that profile
belongs to the admission patient (and, when selected, the admission profile).
Claim linkage remains manager-only and tenant-scoped, and the draft/posted/paid/void lifecycle,
invoice, receipt, and discharge reconciliation are unchanged. The
manager-only `manager.hospital-billing.report` view and CSV export continue to
provide filters and aggregates by status, charge type, date, admission,
payment method, and insurance-linked/outstanding claim amounts.

### 1. Establish a clean baseline

- Check the current Git working tree before changing files.
- Reproduce any reported error in the running application.
- Record the affected route, page, browser error, and server error.
- Fix only the root cause of the reported issue.
- Run the smallest relevant syntax, test, or browser verification.

### 2. Stabilize shared application foundations

Prioritize shared code before page-specific improvements:

- Authentication and role authorization.
- Restaurant/tenant resolution and tenant data isolation.
- Admin and manager layout navigation.
- Shared JavaScript initialization.
- Shared Blade components, styles, and form behavior.
- Error handling and validation.

The tenancy rules in
`docs/CODEIBEX_TENANCY_CODING_STANDARD.md` are mandatory. Tenant data must
always be accessed through the established tenancy context.

### 3. Verify the core business flows

After shared foundations are stable, verify these flows end to end:

1. Super admin login and platform dashboard.
2. Business creation and business settings.
3. Manager login and manager dashboard.
4. Module access and role restrictions.
5. Menu/product management.
6. Customer and order flow.
7. Subscription and billing status.
8. Notifications and account settings.

### 4. Improve user experience

Only after the flows above are verified:

- Fix responsive layout problems.
- Improve navigation clarity.
- Remove duplicated or confusing labels.
- Add loading, empty, success, and error states.
- Keep branding and theme behavior consistent between admin and manager areas.

### 5. Add new features

New features may begin only when:

- The affected existing flow has a passing baseline check.
- The feature has a clear route, permission rule, data model, and validation
  plan.
- Tenant boundaries are defined.
- Existing behavior will remain compatible or the behavior change is explicitly
  documented.

## Rules For Every Change

- Read the relevant existing code before editing.
- Search for an existing helper or pattern before creating a new one.
- Keep changes surgical and limited to the requested behavior.
- Do not hide exceptions or silently fall back to success.
- Do not modify unrelated files.
- Update documentation when behavior or architecture changes.
- Verify the result in the running application when the change affects UI.
- Do not consider a task complete until the expected behavior is verified.

## Definition Of Done

A task is complete only when:

- The requested behavior works.
- Existing related behavior still works.
- Relevant syntax checks, tests, or browser checks pass.
- No new console error is introduced.
- The change follows the tenancy, authorization, and project conventions.
- The result and any remaining limitation are recorded here or in the relevant
  technical documentation.

## Next Action

The Professional Hospital rollout verification pass is complete for the current
tenant inventory. Tenant 18 (`Professional Hospital Demo`) was migrated with
`php artisan tenants:migrate 18`; the command completed with one tenant
migrated and zero failures. Its 32 enabled modules include all 29 required
Professional Hospital modules, with no required module missing.

The focused hospital regression suite now passes **106 tests** across
admissions, beds and transfers, billing and insurance, staff rosters, care
plans, medication, clinical notes, operational reports, authorization, and
tenant isolation. Authenticated browser checks have confirmed the dashboard,
Vital Signs, Clinical Notes, and Reports pages, including styled fields,
dependent Ward/Location filtering, report sections, and CSV export actions.
The remaining browser matrix should be repeated when additional hospital
tenants or role accounts are provisioned.

The next verification action is to complete the remaining UI consistency pass,
authenticated hospital browser verification, tenant rollout confirmation, and
reporting/export validation. The next approved product slice is **pharmacy
stock and medication fulfillment**. Its first two increments now support
manager-only dispensing, earliest-expiring valid batch selection, expired-stock
exclusion, transactional stock deduction across multiple batches, partial
fulfillment, repeat fulfillment, allocation history, and draft pharmacy
charges linked to each dispensing operation. Follow the existing tenant,
module-gate, authorization, migration, test, browser-verification, and
documentation workflow. Keep Blood Bank, Ambulance Dispatch, Biomedical
Equipment, Mortuary, Complaints, and Statutory Compliance deferred until the
hospital explicitly approves them.

The next major slice after pharmacy hardening is **bed pricing and
room-category billing**. Its first increment now lets managers configure a
ward room category and daily rate, snapshots that rate on admission, and
creates a draft initial bed charge when a priced bed is assigned. Recurring
stay-day charges remain manager-controlled for the next increment so existing
billing and discharge workflows are not changed implicitly.

The stay-day increment is now implemented: discharge reconciliation calculates
at least one billable day, rounds partial days up to the next day, updates the
existing draft bed charge idempotently, and creates a final draft charge only
when an initial charge does not exist. Posted or paid charges are not silently
rewritten.

### Inpatient billing verification and payment retry safety

- Focused inpatient billing, invoice/receipt, insurance, and admission
  reconciliation tests pass **23/23** in the verification run.
- The Hospital Billing page renders for Professional Hospital Demo with tenant
  reconciliation totals, status/charge/payment/insurance filters, draft-charge
  entry, and the billing report link.
- Repeated payment submissions using the same posted payment reference are now
  idempotent: the existing ledger row is retained and no duplicate payment or
  settlement is created. The billing row lock protects this check during
  concurrent retries.
- A transient MySQL cleanup deadlock occurred during the first parallel
  environment run; the serial rerun passed completely. No application failure
  was reproduced.
- Refund retries now use the same reference-based idempotency guard as
  payments, preventing duplicate refund ledger rows and repeated settlement
  changes.

### Next approved slice: clinical access refinement

Billing is now hardened for payment and refund retries. The next slice is to
standardize doctor-to-admission access across clinical workflows, beginning
with an explicit comparison of exact attending-doctor access versus
department-level coverage in care plans, laboratory, radiology, notes, and
medication ordering. Preserve manager access, nurse assignment access, tenant
isolation, and existing audit behavior while adding focused authorization
regression tests and browser verification for a doctor assigned to one
admission and denied access to another.

### Clinical access refinement (implemented)

- Added a shared tenant-safe admission access policy for managers, assigned
  nurses, and attending or department-matched doctors.
- Applied the policy to care plans, clinical notes, medication ordering,
  laboratory ordering, and radiology ordering.
- Added regression coverage proving a doctor cannot create a clinical note for
  another doctor's admission outside the doctor's department.
- Corrected clinical-notes integration to use the shared policy method.
- Focused clinical-notes authorization tests pass; browser verification of the
  manager and clinical workflow pages remains part of the next end-to-end
  verification pass.

### Next recommended slice: inpatient billing reconciliation

Before adding another clinical module, verify the complete browser workflow:
admit, transfer between priced wards, add pharmacy/laboratory/theatre charges,
record partial and full payments, and discharge. Confirm bed-period breakdowns,
invoice/receipt totals, immutable posted or paid charges, and no duplicate
charges on retry. Then add one cross-module reconciliation regression test
covering the same scenario.

### Inpatient billing reconciliation verification (implemented)

- Added a cross-module regression covering bed, medication, laboratory, and
  surgery charges on one admission.
- Verified that a partial payment leaves the admission admitted and returns the
  billing error instead of allowing discharge.
- Verified that settling every charge allows discharge and does not create
  duplicate billing rows.
- The focused HospitalBillingPaymentLedgerTest suite passes with the new
  scenario.
- Browser billing-page smoke verification remains recommended for a seeded
  priced-bed scenario before starting the next product module.

## Progress Log

### Hospital pharmacy fulfillment foundation (implemented)

- Added tenant-scoped medication fulfillment records linked to the inpatient
  medication order, medicine batch, fulfilling manager, and hospital billing
  charge.
- Added transactional fulfillment that selects the earliest-expiring
  non-expired batch, prevents negative stock, rejects out-of-stock requests,
  records fulfillment status, and creates a draft pharmacy charge.
- Added a manager-only dispense action to the inpatient medication page while
  keeping doctors and nurses on their existing ordering and administration
  workflows.
- Added central and tenant migrations, route/controller wiring, and regression
  coverage for valid batch selection, expired stock, stock deduction, billing,
  tenant authorization, and out-of-stock behavior.
- The focused medication suite passes **16 tests**.

### Multi-batch pharmacy fulfillment (implemented)

- Added tenant-scoped fulfillment allocation rows so one medication order can
  consume multiple valid medicine batches in earliest-expiry order.
- Added partial fulfillment when available non-expired stock is below the
  requested total, with repeat fulfillment allowed for the remaining quantity.
- Updated the medication page to show requested, dispensed, remaining status,
  every allocated batch, and the partial-dispense action.
- Updated controller eager loading and success feedback for partial versus full
  dispensing.
- Applied the central and tenant allocation migrations; tenant 18 completed
  successfully.
- Added regression coverage for multi-batch allocation, stock deductions,
  partial-to-complete fulfillment, allocation totals, and per-operation billing.
- The focused medication suite passes **9 tests** and the clinical-notes suite
  passes **5 tests**.

### Medication administration linkage (implemented)

- Added a tenant-scoped administered quantity to each nursing administration,
  defaulting legacy submissions to one dose unit.
- Prevented administered quantities from exceeding the order's dispensed
  quantity, while preserving omitted, refused, cancelled, and unfulfilled
  order workflows.
- Added the quantity to the manager medication page so pharmacy fulfillment and
  nursing administration are visible together.
- Applied the central and tenant migrations for tenant 18 and added regression
  coverage for over-administration rejection.
- The focused medication suite now passes **10 tests**.
- Browser verification at desktop and mobile widths confirmed the manager
  sidebar/content layout, responsive medication table, seconds-enabled time
  fields, custom admission dropdown, and in-viewport datepicker. Fixed the
  remaining Flowbite datepicker close behavior so selecting a day now removes
  the active picker instead of leaving an invisible `hidden` picker rendered
  above the page.

The medication and clinical-notes pages now use the official
`flowbite-datepicker` stylesheet alongside the datepicker JavaScript. Calendar
controls are scoped to the picker, render as a seven-column light calendar,
retain Today/Clear actions, and keep the separate time controls synchronized
with the backend datetime fields. The focused medication and clinical-notes
tests pass **18 tests**; Blade compilation and template diagnostics are clean.

The follow-up UI pass gives date and time controls explicit SVG icons, preserves
seconds with `step="1"` and backend synchronization, keeps the medication
submit action aligned to the form row, and constrains the clinical-note modal
below the manager header with its own scroll region. The modal is moved to the
document body when opened so table/card containing blocks cannot clip its top or
bottom. Browser checks confirm the sidebar is visible, the time field exposes
`HH:MM:SS`, and the modal stays between the header and viewport bottom.

### Manager Medical Store POS layout fix

- Fixed the blank/right-side layout symptom on `/manager/pos` by removing an
  extra closing `</div>` in the POS Blade view. The extra tag closed the shared
  Manager shell before its footer, causing the content flex column to render at
  a narrow width and leaving most of the viewport unused.
- Browser verification confirmed the Manager shell now spans the available
  content width. At a desktop viewport, the catalog and billing panel render
  side by side with the billing panel visible; at narrower responsive widths,
  the existing stacked layout remains intact.
- The footer is again nested inside the Manager content column, and the
  existing Clinic Hospital tenant/module behavior was not changed.

### Professional Hospital foundation

- Added a separate persisted `Professional Hospital` business type with its
  own `hospital-admissions` and `hospital-departments` module gates.
- Professional Hospital uses the medical POS mode and retains the current
  Clinic Hospital outpatient modules, but existing Clinic / Doctor, Hospital,
  Pharmacy, and other tenants are not changed.
- The first inpatient admission/department models and routes remain the next
  gated implementation slice; they should be added only behind these modules
  with tenant, role, staff-type, and department authorization checks.
- Implemented the first gated slice: tenant-scoped Department records with
  unique per-tenant codes, active/inactive state, Manager CRUD screens, and
  `module:hospital-departments` route protection. Focused coverage passes
  **14 tests, 205 assertions**; central and tenant migrations were applied
  successfully to all 5 local businesses.
- Created and provisioned a separate local `Professional Hospital Demo`
  tenant (`codeibex_tenant_18`) with owner login `03009998889` and the local
  verification password `password123`. Do not reuse these credentials outside
  local development.
- Implemented the first inpatient Admissions slice. Admissions are
  tenant-scoped, link existing Patients, active Doctors, and active
  Departments, generate admission numbers, support admitted/transferred/
  discharged status, and are protected by `module:hospital-admissions`.
  Focused coverage passes **4 tests, 7 assertions**. Central and all 6 local
  tenant migrations completed; the first central migration attempt encountered
  a transient MySQL deadlock and succeeded on retry.
- The fresh browser login verification was rate-limited with HTTP 429, so
  Manager Admissions page rendering remains a follow-up browser check after
  the local login throttle window clears.
- Added the next inpatient capacity slice: tenant-scoped Wards and Beds,
  with unique per-tenant codes, available/occupied status groundwork, and
  `module:hospital-wards-beds` navigation and route protection. Existing
  Clinic Hospital tenants remain unchanged.
- Added optional tenant-scoped physical locations beneath wards. Managers can
  create Room, Hall, or Bay locations and place new beds under them while
  legacy ward-level beds remain valid.
- Added the Professional Hospital laboratory slice behind
  `module:hospital-laboratory`: tenant-scoped LabOrder and LabResult records
  link Patient and HospitalAdmission. Managers/owners create orders and
  manage results, doctors create/view records, nurses view active assigned
  admissions, and receptionist/pharmacist/guard access is denied server-side.

### Phase 3 consultation workflow

- Added tenant-scoped consultation endpoints for existing Visits, including
  diagnosis/clinical notes, status updates synchronized with QueueEntry, and
  read-only/printable consultation views.
- Linked prescriptions to Visit, Patient, and Doctor. Prescription creation
  validates tenant ownership and blocks active patient allergy triggers and
  known medicine interactions using the existing allergy/interaction data.
- Added a printable prescription slip. Dispensing, stock deduction, billing,
  SMS, and hospital workflows remain out of scope.

### Completed

- Began Phase 2 queue infrastructure within the approved scope. Added
  tenant-scoped `Visit`, `QueueCounter`, and `QueueEntry` models and central
  plus tenant migrations. Added Manager reception check-in and today's
  per-doctor queue pages with transactional daily token issuance, manual
  `Next`, and guarded status transitions. Verified the focused queue test:
  **1/1 test passing, 12 assertions**. Full Phase 2 workflow and browser
  verification remain pending.
- Added the Phase 2 paper token slip and safe digital token status page.
  Reception now opens a printable slip after check-in, and each queue entry
  receives an unguessable public token. The public page exposes only the
  doctor, token number, date, and queue status; it does not expose patient or
  medical data. Added a regression test covering the public/private boundary:
  **1/1 test passing, 16 assertions**.
- Added appointment-to-Visit reconciliation at medical reception. A
  same-day scheduled/confirmed appointment can now be linked to the selected
  Patient and Doctor when checked in; the appointment receives one Visit and
  one QueueEntry/token only. Repeated check-in of the same appointment is
  rejected, and the public token lookup is constrained to the restaurant
  slug. Browser verification confirmed the Manager queue and check-in pages
  load successfully, including the existing authenticated Test Restaurant
  session.
- Added the read-only kiosk/shared display at
  `/queue-display/{restaurantSlug}`. It auto-refreshes every 10 seconds and
  shows only currently called/in-progress token numbers and doctor names; it
  never exposes patient names, CNICs, phone numbers, or clinical information.
  Added regression coverage for the public display privacy boundary.
- Completed light returning-patient history for authorized Manager users within
  the current tenant. Patient listings now link to a read-only history page
  showing prior visit date, Doctor, specialty, status, and reason with
  pagination; no diagnosis, prescription, vitals, allergies, or editable
  clinical notes are exposed. Tenant isolation and read-only behavior are
  covered by a focused feature test. Browser verification confirmed the
  Patient list and history page render correctly for the authenticated Manager
  session.
- Phase 2 queue work is now complete: appointment reconciliation, printable
  token slips, safe digital token links, kiosk display, and light returning
  history are implemented and verified. Final validation passed:
  **205/205 tests, 786 assertions**, and the Vite production build.

### Medical Module Expansion — Phase 3

- Added the tenant-scoped consultation workflow for existing Visits, including
  diagnosis and clinical notes, explicit plain-string status validation, queue
  status synchronization, tenant-safe visit lookup, and printable consultation
  output.
- Linked prescriptions to `Visit`, `Patient`, and `Doctor` while preserving the
  existing prescription workflow. Prescription creation validates all linked
  records against the current tenant and checks medicine interactions before
  saving.
- Added a separate tenant-scoped `PatientAllergy` model and central/tenant
  migrations. Patient-specific allergy checks are now distinct from the
  retail `CustomerAllergy` workflow.
- Added Manager Patient Allergy CRUD under the medical module, with
  tenant-scoped patient/medicine validation and active-warning management.
- Added focused Phase 3 regression coverage for consultation completion,
  queue synchronization, cross-tenant visit access, PatientAllergy CRUD, and
  prescription allergy/interaction safety. Tenant provisioning and the
  complete suite pass: **209/209 tests, 797 assertions**.
- Phase 3 deliberately does not include pharmacist dispensing, stock
  deduction, billing expansion, SMS, hospital-scope work, or a new permission
  system.

- Fixed the shared admin layout JavaScript initialization error by moving
  `initManagerNavigationGroups()` to module scope in
  `resources/js/super-admin/layout.js`.
- Declared the optional `header` prop in
  `resources/views/components/page/card.blade.php`. This fixed the shared
  component error that caused admin pages to return HTTP 500.
- Migrated POS configuration from deleted `admin.pos.*` view names to the
  current `manager.pos.*` views.
- Added a compatibility alias for the legacy `admin/dashboard` view name.
- Preserved notification compatibility by allowing both GET and POST requests
  for notification read links while current forms continue to use POST.
- Verified the frontend with `npm run build`.
- Verified the focused admin access tests:
  `AdminSuperAdminAccessTest` passes.
- Verified the focused medical POS and manager notification tests.
- Verified the complete feature suite: **183/183 tests passing**.
- Reloaded the admin and manager pages. No current shared-layout JavaScript
  error is reported by the browser.
- Refactored the Sales Returns page to use reusable CSS component classes from
  `resources/css/app.css` instead of page-specific utility-heavy styling.
- Moved the Sales Returns customer filter into
  `resources/js/pages/sales-returns.js`, loaded through the main asset bundle.
- Confirmed the Sales Returns page responds with HTTP 200 and renders the
  extracted classes in the browser.
- Re-ran the complete feature suite after the refactor: **183/183 tests
  passing**.
- Removed model queries, tenancy resolution, theme assembly, notification
  queries, and role navigation setup from all four role master views.
- Added dedicated view composers for the dashboard, customer, and CEO layout
  contracts. The manager and super-admin masters share
  `DashboardLayoutComposer`; customer and CEO keep separate composers because
  their data and security boundaries are different.
- Registered the composers in `AppServiceProvider`, so layout data is prepared
  only when the relevant master view is rendered instead of being duplicated
  in templates.
- Verified PHP syntax, the Vite production build, the complete feature suite
  (**183/183 tests, 681 assertions**), and live admin/manager browser pages.
- Extracted the duplicated dashboard-shell style block from the manager and
  super-admin masters into `resources/css/layouts.css`.
- Verified the extracted CSS matches the original block byte-for-byte.
- Recompiled Blade views, rebuilt frontend assets, reran the full feature
  suite, and reloaded both admin and manager pages successfully.
- Standardized the customer master contract by rendering
  `@yield('page-content')` inside the shared shell.
- Migrated the standalone customer feedback, menu-empty, no-restaurant, and
  storefront-unavailable pages to extend `customer.layout.master` and place
  their UI inside `@section('page-content')`.
- Confirmed that the remaining non-master role files are intentional login,
  PDF/receipt export, partial, or script views rather than full application
  pages.
- Recompiled all Blade views, rebuilt frontend assets, reloaded the manager
  page, and verified the complete suite still passes (**183/183 tests,
  681 assertions**).
- Added named preset runtime variables for Ocean, Forest, and Sunset so preset
  selection overrides the dashboard variables that are intentionally emitted
  inline for tenant-specific defaults.
- Removed the manager master’s duplicate notification and light/dark theme
  handlers; the shared bundled layout module is now the single owner of those
  interactions.
- Rebuilt the frontend, recompiled Blade views, and verified the complete
  suite still passes (**183/183 tests, 681 assertions**).
- Audited the manager master against the shared `resources/js/super-admin/layout.js`
  implementation before removing duplicate inline behavior. Removed the
  mobile navigation, active-link scrolling, header page search, manager
  navigation grouping, and delete-confirmation wiring scripts while preserving
  the confirmation modal markup and manager new-order listener.
- Confirmed the super-admin and CEO masters do not contain duplicate scripts
  for those categories; their remaining script hooks are the theme bootstrap
  and normal `@stack('scripts')` contract.
- The manager master decreased from the supplied 857-line baseline to 670
  lines, a reduction of 187 lines. The current file is 56,027 bytes.
- Browser validation confirmed bundled mobile navigation open/close, header
  search results, navigation grouping markup, active-link initialization, and
  delegated confirmation modal cancel behavior. Vite, Blade cache compilation,
  diagnostics, and the complete suite all pass (**183/183 tests, 681
  assertions**).
- Confirmed the super-admin master does not currently contain dead
  `x-super-admin.*` comment blocks in the active branch; the include-based shell
  (`sidebar`, `header`, `footer`) is the verified source of truth, and no legacy
  dead code remains to remove there.
- Audited the remaining Customer inline assets and confirmed the active inline
  `<style>` and `<script>` blocks are intentional shell/template logic (customer
  app shell theme vars, analytics consent, confirmation modal, password toggle,
  storefront hero/cart behavior) rather than stale dead code. The PDF/receipt
  export views remain intentionally standalone and were excluded from the app
  shell audit.
- Added `AuthorizationAndTenantIsolationTest` covering Super Admin, CEO,
  Manager, and Customer route boundaries; guessed cross-tenant menu-item IDs;
  Super Admin impersonation enter/exit; Manager impersonation denial; and
  disabled/enabled module route behavior.
- Fixed `Tenancy::end()` so exiting a no-database impersonation also clears the
  container-bound restaurant context. This restores a genuinely tenant-less
  Super Admin context instead of leaving stale tenant state in the process.
- Fixed order tracking to bind the resolved order restaurant before rendering
  the customer layout, so tracking pages consistently render the correct
  restaurant branding after a cross-restaurant lookup.
- Focused authorization, tenancy, menu-module, and checkout tests pass.
  The complete suite now passes: **191/191 tests, 702 assertions**.
- Rebuilt the Vite production assets successfully.
- Seeded deterministic browser-verification users and tenant data with
  `TestUserSeeder`, including an active, publicly discoverable storefront.
- Completed live browser verification of the first five core flows:
  Super Admin login/dashboard; business creation and edit persistence;
  Manager login/dashboard with tenant context; module visibility and direct
  route blocking; and menu/product create, edit, and delete.
- Completed the customer/order flow: the storefront loaded, the seeded
  product was added to the cart, checkout was submitted, and the application
  redirected to the order-tracking page with an order-placed confirmation.
- Verified the Manager subscription page shows the seeded Starter plan with
  Active status, monthly billing, and the expected next-invoice details.
- Verified Manager account settings save successfully and persist after the
  page reload.
- Created a browser-verification notification in the Manager workspace,
  confirmed it appeared in the list, and confirmed the mark-as-read action
  returned the expected success message.

### Medical Module Expansion — Phase 1

- Added tenant-scoped `Doctor` and `Patient` models using
  `BelongsToRestaurant`, with central and tenant migrations.
- Added Manager CRUD routes and controllers under `manager.doctors.*` and
  `manager.patients.*`, gated by the existing `module:medical` middleware.
- Added Manager pages under `resources/views/manager/doctors/` and
  `resources/views/manager/patients/`, extending
  `manager.layout.master` and defining `page-content`.
- Doctor statuses are restricted to `pending`, `active`, and `declined`.
- Patient creation/update checks the current tenant's existing patients by
  name or phone and rejects likely duplicate records.
- Phase 1 intentionally excludes queue, tokens, visits, check-in,
  allergy/interaction checks, billing, and all other Phase 2+ work.
- Focused Phase 1 tests pass: **3/3 tests, 12 assertions**.
- The complete suite passes: **197/197 tests, 734 assertions**.
- Blade view compilation and the Vite production build pass.
- Live browser verification on the current Test Restaurant tenant confirmed
  Doctor and Patient index/create pages load successfully after enabling the
  medical module and applying the new migrations. A Doctor and Patient were
  created through the browser, the Patient appeared in the index, and a
  duplicate Patient submission was rejected with the expected validation
  message.
- Confirmed the existing Super Admin-to-business module path: enabling
  `medical` on a business makes the existing medical routes available to its
  Manager accounts, while disabling it returns `403`. Added a regression test
  for this boundary.
- Implemented the approved Patient identity follow-up: CNIC is the strongest
  tenant-scoped identifier, no-CNIC matching uses name + phone + date of birth,
  and shared guardian phones never merge Patient records.
- Added dependent/guardian fields and exact relationship validation to the
  Patient CRUD flow, including the dependent-only form block and adult CNIC
  recommendation.
- Added the central and tenant follow-up migrations for CNIC, dependent and
  guardian fields, nullable contact phone, and the tenant-scoped CNIC
  uniqueness constraint.
- Patient identity tests pass: **6/6 tests, 33 assertions**; the full suite
  passes **202/202 tests, 753 assertions**, Blade compilation passes, and the
  Vite production build passes.

### Hospital staff-role addition and future scope documentation

- Added the plain-string `staff_type` field to the existing `User`-backed
  Manager Staff flow; the existing account `role` field remains unchanged.
- The approved initial staff-type list is exactly `nurse`, `guard`,
  `receptionist`, and `pharmacist`, validated with `Rule::in()`.
- Added the staff-type selector to Manager Staff create/edit pages and the
  staff listing.
- Added focused staff-role tests: create/update and invalid-value rejection.
- Added the documentation-only
  **Hospital-Specific Extensions (Full Hospital Management Scope — Future
  Initiative)** section to the medical expansion plan. It is explicitly
  excluded from approved Phase 1–5 implementation and requires separate
  approval.
- Staff-role tests, related Doctor/Patient tests, Blade compilation, and the
  Vite production build pass.

### Pending Work

All eight core browser flows are now verified:

1. Super Admin login/dashboard — passed.
2. Business creation/settings — passed; creation succeeded and the edited
   address persisted after reload.
3. Manager login/dashboard — passed with the expected Test Restaurant context.
4. Module access/role restrictions — passed; the disabled POS route returned
   403 and unavailable navigation was hidden.
5. Menu/product management — passed; create, edit, and delete all succeeded.
6. Customer/order flow — passed; storefront browse, cart, checkout, and order
   placement redirected to tracking successfully.
7. Subscription/billing status — passed; Starter plan and Active monthly status
   matched the seeded tenant.
8. Notifications/account settings — passed; notification creation and
   mark-as-read succeeded, and account changes persisted after reload.

The stabilization and verification gate is complete. New feature work may now
begin, subject to the route, permission, tenant-boundary, and validation
requirements above.

### Phase 3 UI Convention

Manager medical action and print links use the shared Blade icon components
(`x-icons.*`) with accessible text labels. Consultation screens show a clear
warning when the patient has active clinical allergies.

### Follow-up Bug Fixes

- Fixed the customer tracking-page duplicate card at the shell composition
  boundary. `customer.layout.header` already owns the customer content wrapper
  and `@yield('page-content')`; the master was yielding the same section a
  second time. The lookup page now renders exactly one tracking card.
- Added a regression test for the single customer tracking form and a module
  regression test confirming that a manager with inherited access immediately
  reflects modules enabled or disabled on the business by Super Admin.
- Smoothed the dashboard sidebar gradient by removing the abrupt three-stop
  color reversal and using a controlled midpoint blend.
- Reworked dark-mode shell tokens and overrides to grayscale (near-black
  surfaces, white/light-gray text, gray borders, and contrast-based active
  states) across dashboard and customer shells. Customer pages now initialize
  from the shared dashboard theme preference as well.
- Targeted regression tests pass (**10/10**), the Vite production build passes,
  edited files report no diagnostics, and the complete suite passes:
  **193/193 tests, 708 assertions**.

### Super Admin View Reorganization

The Super Admin page views now use this mapping without changing route names or
URLs:

- `super-admin.restaurants.{index,create,edit,manager-access,ceo-access}` →
  `super-admin.businesses.{index,create,edit,manager-access,ceo-access}`
- `super-admin.restaurants._homepage_fields` →
  `super-admin.businesses._homepage_fields`
- `super-admin.restaurants._module-plan-script` →
  `super-admin.businesses._module-plan-script`
- `super-admin.feedback-centre.{index,show}` references were corrected to the
  existing `super-admin.feedback.{index,show}` views.
- Existing `business-types`, `modules`, `subscription-plans`, `feedback`,
  `notifications`, `account`, and `platform-settings` folders were retained;
  `layout/` was not changed.

The module-access divergence was fixed: `User::hasModuleAccess()` now uses
business-level enablement as the single source of truth for restaurant admins,
Managers, and Super Admin impersonation. The root cause was that direct Manager
logins applied the legacy `module_access` narrowing list, while impersonated
Super Admin sessions bypassed it. Legacy per-manager selections are retained for
compatibility, but enabled business modules now produce the same access result
through either login path; disabled business modules remain blocked.

The direct Manager login regression is covered by a comparison test that checks
both module decisions and rendered sidebar items against Super Admin
impersonation. The seeded Test Restaurant Manager now inherits the full enabled
business module set. Verification passed: **388/388 tests, 0 failures**; the
direct Manager browser session rendered Restaurant POS, Operations, Sales, Menu,
Money, Staff, Account, and Reports for Test Restaurant.

### Final UI verification closure

- Live browser verification completed for the medical Staff Type branch by
  temporarily assigning the existing Test Restaurant tenant the existing
  `Clinic / Doctor` BusinessType, opening Manager → Add Staff, and confirming
  the dropdown rendered exactly Nurse, Guard, Receptionist, and Pharmacist.
  The tenant was then restored to its original `Restaurant` BusinessType; no
  staff or business records were created or deleted.
- The complementary live Restaurant check remained confirmed: after restore,
  the Staff Type field was hidden on the same Add Staff page.
- CEO and Customer views were cross-checked against
  `docs/CEO_VIEW_STRUCTURE.md` and `docs/CUSTOMER_VIEW_STRUCTURE.md`. CEO has
  executive dashboards, business/branch detail, reports, alerts, and profile
  pages; Customer has storefront, checkout/tracking, feedback, and account
  pages. Neither role contains a data-table page with an Actions column, so
  there were no CEO/Customer table actions omitted from the icon conversion.

### Test Restaurant medical regression follow-up

- Repaired the Test Restaurant tenant's missing local `restaurants` mirror
  row using the established non-destructive `syncRestaurantMirror()` path.
  This restored the tenant-local foreign-key target required by `doctors`.
- Added the missing tenant `appointments` table migration
  (`2026_09_12_200000_create_appointments_table.php`) and applied it to tenant
  15 without a reset or `migrate:fresh`.
- Re-fetched QueueEntry and Visit records inside the active tenant context in
  queue-print and consultation actions, fixing route-binding relationship
  resolution for Doctor and Patient.
- Browser regression verified Doctor creation, Patient creation and duplicate
  rejection, queue token issuance, queue advance/start, consultation
  completion, diagnosis/notes persistence, and consultation printing.
- Fixed prescription submission when the optional `medicines` field is absent;
  the controller now treats it as an empty selection instead of raising an
  undefined-array-key error.
- Expanded the Manager prescription form with Visit, Patient, Doctor, and
  tenant medicine fields so the existing allergy and interaction checks can be
  exercised from the UI. The focused Phase 3 suite passes **4/4 tests**, and
  the Vite production build passes.
- The browser session became unauthenticated during the prescription submit
  navigation, so prescription persistence, safety rejection, and prescription
  printing remain pending live verification. Phase 4 dispensing is not yet
  approved.
- The focused Phase 3 tests pass **4/4**. A subsequent fresh full-suite run
  initially exposed an idempotency failure in `TenantProvisioningTest`;
  guarding the existing tenant `restaurant_id` columns fixed the root cause.
  The current complete result is **210/210 tests passing**.
- After removing implicit route-model binding from prescription show/print
  actions, the live prescription flow is complete: an unconflicted
  prescription persisted and appeared in the list, an active patient allergy
  produced the expected rejection warning, and the printable prescription
  slip rendered real patient/doctor/date data. Phase 4 remains pending
  product approval, not blocked by this regression.

### Phase 4 — prescription dispensing foundation

- Added tenant and central dispensing metadata (`dispensed_by`,
  `dispensed_at`) to prescriptions.
- Added a medical dispense action for verified prescriptions. Managers and
  admins are operational overrides; staff users must have `staff_type` set to
  `pharmacist`.
- The action
  consumes non-expired `MedicineBatch` stock in expiry order inside a
  transaction, rejects insufficient stock without marking the prescription
  used, and records the authenticated dispenser and timestamp.
- Added focused coverage for successful stock deduction, insufficient-stock
  rollback, expired-batch exclusion, expiry-order consumption, repeat
  dispensing protection, authorization boundaries, and pharmacist
  authorization.
- Added transactional prescription-row locking so concurrent dispense
  requests cannot fulfill the same prescription twice.
- Live verification initially exposed a tenant schema boundary bug: the
  tenant `prescriptions.dispensed_by` column incorrectly had a foreign key to
  tenant-local `users`, while authenticated users are central records. The
  tenant FK was removed non-destructively; the audit ID column remains and the
  central migration retains its central-user FK.
- Browser verification then succeeded with real Test Restaurant data:
  prescription `RX-BROWSER-001` changed to `Used`, recorded dispenser ID 19
  and timestamp, and reduced the prepared batch from 3 to 2 units.
- Focused medical and notification suite: **22/22 passing**.
  Fresh full suite after the schema repair: **219/219 passing, 845
  assertions**. Vite production build:
  **passed**. Phase 4 dispensing validation is complete.

### Phase 5 — queue display hardening

- Added `no-store` response headers to the public queue-token and kiosk
  display pages so shared screens and browsers do not cache queue status.
- Preserved the existing privacy boundary: public pages expose only the
  doctor, token, queue date/status, and no patient or medical information.
- Queue display regression coverage passes, including privacy assertions and
  cache-header verification.
- Browser verification completed on Test Restaurant for both
  `/queue-display/test-restaurant` and the public token page: both rendered
  successfully with doctor/token/status-only content, exposed no patient
  names or medical data, and returned `Cache-Control:
  max-age=0, must-revalidate, no-cache, no-store, private`.
- Added matching 10-second meta refresh to the individual public token page,
  so patients see queue status changes without manually reloading.
- Added an explicit `Refresh now` control and a local `Last checked` time on
  both public queue screens for users who need an immediate status check.
- Added a provider-independent `QueueNotificationService` message builder for
  future SMS/WhatsApp delivery. Its called-token message contains only the
  token and doctor name; patient identity and medical details are excluded.
- Added the first real delivery pipeline slice: Manager-side tenant opt-in and
  SMS/WhatsApp channel selection, patient phone consent captured at check-in,
  tenant-aware queued delivery jobs with standard retries/backoff, and a
  log-only provider bound behind a `NotificationProvider` contract.
- Notifications remain safely disabled by default. Dispatch requires both
  tenant opt-in/channel enablement and patient consent; the default provider
  records a privacy-safe message in the application log and makes no external
  call.
- Final validation for this Phase 5 slice: **220/220 tests passing, 847
  assertions**, Vite production build **passed**, and changed-file
  diagnostics reported no errors.
- Added regression coverage proving medical report KPIs are date-filtered and
  isolated to the authenticated tenant. The latest focused medical suite
  passes **14/14 tests, 95 assertions**, and the complete suite remains green.
- The new delivery-pipeline focused tests pass after the tenant schema was
  applied to Test Restaurant. A real provider (Twilio, WhatsApp Business API,
  or another approved service) still must be selected and configured before
  enabling delivery for real patients.
- Added an explicit `medical_queue_notifications.driver` configuration key
  (default `log`) and terminal failure logging from the queued job, so
  provider outages remain visible through normal Laravel failed-job handling.
- Added a Twilio adapter behind the existing `NotificationProvider` contract
  for SMS and WhatsApp, using Laravel's HTTP client and environment-based
  credentials. The default driver remains `log`; live delivery is not enabled
  until sender approval, sandbox testing, and production credential review are
  complete.
- Improved the existing Manager medical reports landing page with real
  tenant-scoped date-filtered KPIs for patients, doctors, visits,
  prescriptions, dispensing, and today's active queue; removed placeholder
  dash values without creating a second reporting system.
- Live browser verification confirmed the Manager medical notification page
  renders with opt-in/SMS/WhatsApp controls, and Patient Check-In renders an
  unchecked consent checkbox with the phone-number consent explanation.
- Kept this foundation in the Manager medical workflow and tenant scope.
  Super Admin should only receive future platform/provider configuration
  controls; business opt-in and recipient preferences belong to each
  Manager-side tenant workflow.

### Queue notification provider readiness record

#### Implemented

- Tenant opt-in and enabled-channel selection for SMS and WhatsApp.
- Patient phone-number consent captured during medical check-in.
- Privacy-safe queue-called message builder.
- Tenant-aware queued job with Laravel retry/backoff behavior.
- `NotificationProvider` contract with a log driver and a Twilio adapter.
- Twilio SMS and WhatsApp request formatting covered by HTTP-fake tests.
- Tenant-scoped delivery audit records now track queued, sent, and terminal
  failed status, provider message ID, timestamps, masked recipient, and
  sanitized failure text without storing medical details.
- Repaired the dispensing tenant migration to check for the foreign key
  constraint idempotently across MySQL and SQLite, allowing all tenant
  databases and provisioning tests to migrate safely.
- Added a Manager delivery-history page with status filtering. It shows only
  queue token, doctor, channel, masked recipient, status, and sent time; it
  does not expose patient identity, message content, or failure details.
- Added a no-network readiness test proving incomplete Twilio configuration is
  rejected before any provider request is attempted. The local environment
  currently has no Twilio queue-notification credentials configured, so no
  sandbox send was performed.
- Live delivery remains disabled because the configured default is
  `MEDICAL_QUEUE_NOTIFICATION_DRIVER=log`.

#### Pending before live patient delivery

1. Confirm the approved provider: Twilio SMS, Twilio WhatsApp, Meta WhatsApp
   Business API, or an approved local SMS gateway.
2. Create and verify the provider account, sender identity, templates, and
   destination-country support.
3. Rotate any credential that has ever been placed in a local `.env` file or
   shared during development; keep secrets only in environment variables or a
   production secrets manager.
4. Configure production credentials and sender values without committing them,
   then keep the driver set to `log` until sandbox tests pass.
5. Test sandbox numbers for successful delivery, invalid numbers, provider
   rejection, timeout, retry, and terminal failure logging.
6. Obtain final consent/privacy approval for the message wording, retention,
   channel policy, and patient opt-out process.
7. Add delivery-status persistence and operational monitoring before enabling
   live traffic.
8. Change the production driver to `twilio` only after the preceding checks
   are signed off; never enable it for real patients as part of local testing.

#### Recommended next implementation slice

Run provider sandbox tests before enabling live traffic. Keep provider
credentials outside the database and do not store unnecessary medical
information in the audit record.

### Professional Hospital wards, beds, and bed assignment

- Added the tenant-scoped `Ward` and `Bed` models and the gated
  `hospital-wards-beds` module for Professional Hospital.
- Added the optional `WardLocation` hierarchy (`Ward -> Room/Hall/Bay ->
  Bed`). `Bed::location_id` is nullable, so existing ward/bed rows and all
  `HospitalAdmission::bed_id` references continue to work unchanged.
- Managers can create tenant-authorized Room, Hall, and Bay locations and
  assign beds to a location from the Wards & Beds screen. Location codes are
  unique per tenant, and a bed cannot reference a location from another ward
  or tenant.
- Added the nullable tenant-scoped `bed_id` relationship on
  `HospitalAdmission`.
- Admissions can assign an available bed during creation or afterward.
  Assignment marks the bed occupied; discharging an admission releases it.
  Transferred admissions retain their current bed until a later assignment or
  discharge.
- Bed assignment uses tenant-scoped route binding and a transaction with a
  row lock, so an occupied or cross-tenant bed cannot be assigned.
- Added the `Bed::ward()` relationship used by the admission form and eager
  loaded admission relationships for the listing.
- Applied the bed migration centrally and across all six local tenant
  databases.
- Focused hospital tests pass: 14 tests, 207 assertions. The Vite production
  build also passes.
- The forwarded-port CSS issue is resolved locally by stopping the Vite dev
  server and removing `public/hot`; port 8000 now serves the built manifest
  asset `/build/assets/app-Dc_A43r6.css` instead of an unreachable port 5173.
- Browser login remains subject to the previously observed HTTP 429 throttle;
  the forwarded page itself now loads its built stylesheet successfully.

### Professional Hospital access and tenant migration correction

- Professional Hospital now exposes only its approved medical and inpatient
  modules: Medical, Medical Records, Patient Records, Prescriptions,
  Pharmacy, Allergies, Inventory, Stock, Item Sales, Cashbook, Expenses,
  Reports, Notifications, Follow-up Reminders, Admissions, Departments, and
  Wards & Beds.
- Generic Customers and Appointments access was removed from the Professional
  Hospital default bundle, so salon-style appointment navigation is not shown
  for that business.
- The missing Admissions 500 error was caused by the hospital migrations being
  stored only under the central `database/migrations` path. Tenant migrations
  are executed from `database/tenant_migrations`; the Department, Admission,
  Ward/Bed, and Admission Bed migrations are now present there.
- Tenant migrations were applied successfully to all six local business
  databases. Professional Hospital Demo now loads Departments and Admissions
  with HTTP 200, and a direct Appointments request is correctly rejected with
  HTTP 403 because that module is disabled.

#### Next Professional Hospital slice

Nursing assignments are now implemented as the first separately gated,
tenant-scoped inpatient staffing slice. Vital Signs, Laboratory, Radiology,
and the admission clinical-notes/EMR foundation are now implemented as
separately gated slices.

### Professional Hospital admission clinical notes / EMR foundation

- Added the separately gated `hospital-clinical-notes` module to the
  Professional Hospital bundle and Manager navigation.
- Added tenant-scoped `HospitalClinicalNote` records linked to the specific
  inpatient admission, author, note type (`clinical`, `progress`, or
  `nursing`), content, and recorded timestamp. These records intentionally do
  not duplicate outpatient `MedicalRecord` or `Visit` rows.
- Owners/managers can record all note types and correct or void notes.
  Assigned doctors can create clinical/progress notes only for their assigned
  admissions. Active assigned nurses can create nursing notes and view notes
  for their assigned admissions; unauthorized staff are denied.
- Admission-window timestamp validation applies on create and manager
  correction. Corrections and voids require a reason and write
  tenant-scoped append-only `HospitalClinicalNoteEvent` audit records with
  actor, timestamp, and before/after snapshots.
- Added matching central and tenant migrations with explicit short MySQL
  foreign-key/index names, focused authorization/tenant/type/timestamp/audit
  coverage, and the admission notes Manager screen at
  `/manager/hospital-clinical-notes`.
- Tenant 18 (`Professional Hospital Demo`) was migrated and its
  `hospital-clinical-notes` module enabled; authenticated verification returned
  HTTP 200 and rendered the Admission Clinical Notes screen.

### Professional Hospital nursing assignments

- Added the `hospital-nursing` module to the Professional Hospital bundle.
- Added tenant-scoped `NursingAssignment` records linking active admissions to
  active users whose staff type is `nurse`.
- Added Manager Nursing navigation, assignment form, assignment history, and
  module-gated routes.
- Admission and nurse validation is tenant-scoped; discharged admissions and
  non-nurse/inactive/cross-tenant users cannot be assigned.
- Added the tenant migration and applied it across all six local business
  databases.
- Browser verification succeeded at `/manager/nursing` for Professional
  Hospital Demo. Clinic and non-hospital tenants remain blocked by the module
  gate.
- Focused nursing and module tests pass: 13 tests, 208 assertions. The
  production Vite build also passes.

### Professional Hospital staff duty rosters

- Added the separately gated `hospital-staff-rosters` module to Professional
  Hospital defaults and existing Professional Hospital tenants.
- Added tenant-scoped `HospitalStaffRoster` shifts linking active nurse/doctor
  User accounts to a shift date, start/end window, shift type, optional
  department/ward, status, and notes.
- Hospital owners/managers can schedule, edit, delete, confirm, and cancel
  shifts. Active nurses and doctors can view only their own roster rows.
- Server-side validation rejects cross-tenant staff/location references,
  overlapping scheduled/confirmed windows, invalid date ranges, and shifts
  ending before they start. Central and tenant migrations use explicit short
  MySQL-safe foreign-key and index names.
- Added Staff Rosters navigation and the Manager roster screen at
  `/manager/staff-rosters`; existing Nursing Assignments remain unchanged.

### Professional Hospital vital signs

- Added the separately gated `hospital-vital-signs` module to Professional
  Hospital.
- Added tenant-scoped vital-sign records linked directly to both the patient
  and hospital admission, as well as the recording user. Existing rows are
  backfilled from their admission during the tenant migration.
- Nurses can view and record only observations for their active nursing
  assignments. Doctors can review inpatient observations but cannot record
  them. Managers and owners retain full tenant-scoped recording access.
  Receptionists, pharmacists, guards, and other staff types are denied
  server-side.
- Added range validation for temperature, pulse, respiratory rate, oxygen
  saturation, weight, and pain score, plus blood-pressure and notes fields.
- Added Vital Signs navigation, form, history table, tenant migration, and
  module regression coverage.
- Applied the patient-link migration to all six local tenant databases with
  zero failures. The production Vite build passes.
- Focused Vital Signs tests pass: 2 tests, 2 assertions, including the
  receptionist clinical-access denial. Browser verification for the module
  remains successful at `/manager/vitals` for Professional Hospital Demo.

### Nursing assignment lifecycle

- Nursing assignments now support active, completed, and cancelled states,
  morning/evening/night shifts, ending assignments, and active-only/history
  views.
- Reassignment validates the replacement nurse, tenant, shift, and active
  workload. A nurse may have at most five active assignments.
- Duplicate active assignments for the same admission remain blocked.

### Nursing assignment audit events

- Added tenant-scoped `NursingAssignmentEvent` records for `assigned`,
  `reassigned`, `completed`, and `cancelled` actions.
- Each event records the assignment, previous and new nurse, acting user,
  timestamp, and optional notes. Assignment mutations and their events are
  committed in one transaction.
- Assignment history now loads and displays the event trail, including the
  actor and nurse transition. The event migration was added under the tenant
  migration path and applied successfully to all six local tenants.
- Focused hospital regression tests pass: 14 tests, 209 assertions. Blade
  compilation, the production Vite build, and browser verification of the
  active/history Nursing pages also pass.

### Vital Sign correction history

- Added tenant-scoped `VitalSignEvent` records for initial recording and
  corrections, storing previous values, new values, actor, timestamp, and a
  required correction reason.
- Managers and owners can correct Vital Signs through the server-side
  `manager.vitals.update` route. Nurses and doctors remain unable to edit
  observations.
- The Vital Signs page now exposes the correction form only to managers, and
  every correction is committed atomically with its audit event.
- Applied the tenant migration successfully to all six local databases.

### Professional Hospital dashboard

- Added a hospital-only dashboard section to the existing Manager dashboard,
  gated by `hospital-admissions`, without changing the restaurant dashboard for
  non-hospital tenants.
- The section shows active admissions, available and occupied beds, unassigned
  admissions, active nursing assignments, admissions without a Vital Sign in
  the last 24 hours, discharges today, and active admissions by department.
- All metrics are tenant-scoped and link to the existing Admissions, Nursing,
  and Vital Signs screens when those modules are enabled.
- Blade compilation, the dashboard regression test, and browser verification
  of the Professional Hospital dashboard passed.

### Admission transfer foundation

- Audited the existing hospital models and added missing inverse relationships
  for admission Vital Signs/transfers and bed admissions.
- Added tenant-scoped `HospitalTransfer` records capturing the admission,
  previous/new departments and beds, requesting user, transfer time, reason,
  and notes.
- Added a transactional transfer action that validates tenant ownership,
  releases the previous bed, occupies the destination bed, updates the
  admission, and preserves the transfer history.
- Added the transfer form to the Admissions page.

### Transfer consistency follow-up

- The generic admission status action no longer accepts `transferred`; all
  transfers must use the formal transfer action so department, bed, requester,
  reason, and timestamp history cannot be bypassed.
- Discharging an admission now locks and releases its bed transactionally and
  clears the admission's `bed_id`, preventing a discharged record from
  retaining a stale bed reference.
- The next implementation slice is `DischargeSummary` and a structured
  discharge workflow, followed by readmission history.

### Structured discharge summary

- Added tenant-scoped `DischargeSummary` with diagnosis, discharge
  medications, follow-up instructions, notes, discharging user, and timestamp.
- Added a one-summary-per-admission constraint and an admission relationship so
  discharge history remains attached to the specific inpatient encounter.
- Replaced the generic discharge status form with a validated discharge
  workflow. The transaction releases and clears the assigned bed, creates the
  summary, and marks the admission discharged.
- Applied the tenant migration successfully across all six local businesses.
- The next lifecycle improvement is explicit readmission history and
  discharge-summary viewing/edit policy before Laboratory orders/results.

### Hospital structure audit follow-up

- Admission transfer and discharge timestamps are now validated so they cannot
  precede the original admission time.
- The current structures are tenant-scoped and preserve encounter history:
  Vital Signs and transfers belong to the admission, while DischargeSummary is
  unique to that admission and retains the patient snapshot.
- Recommended before Laboratory: make discharge summaries explicitly
  immutable or add audited corrections, add a readmission/encounter history
  view, and enforce staff-specific authorization for discharge and transfer
  actions rather than relying only on the Manager route boundary.

### Readmission safety

- Added the inverse `Patient::hospitalAdmissions()` relationship so a patient’s
  inpatient encounters remain available as a chronological history.
- Admission creation now blocks a second active admission for the same patient
  while allowing a new admission after the previous encounter is discharged.
- Browser verification passed for the current Admissions, Nursing, Vital Signs,
  Dashboard, and disabled Appointments surfaces before continuing.

### Encounter history and discharge authorization

- Admissions now show an expandable chronological encounter history for each
  patient, including prior admission numbers, statuses, and dates.
- Transfer and discharge actions now require an authenticated restaurant
  manager/owner in the controller, in addition to route/module gates.

### Laboratory structure audit

- Laboratory orders and results are tenant-scoped and linked directly to both
  the patient and the inpatient admission.
- Order timestamps cannot precede admission, and result timestamps cannot
  precede the order.
- Managers/owners manage results, doctors can order and view records, and
  assigned nurses can view records for their active admissions.
- Doctor visibility is currently tenant-wide because Doctor records are not
  yet linked to authenticated users or departments. Add that StaffProfile or
  doctor-assignment foundation before narrowing doctor access.
- Added a central rollout migration so existing Professional Hospital tenants
  receive the Laboratory module flag; tenant schema migrations must still be
  applied separately during deployment.

## Professional Hospital bed transfer history

Manager bed assignments and transfers are now persisted as explicit tenant-scoped `BedTransfer` records that link to the active admission, patient, previous/new bed and ward, department, actor, timestamp, reason, and notes. This history supplements the existing `HospitalTransfer` event log while preserving bed availability checks, duplicate rejection, manager-only approval, and cross-tenant isolation.

### Bed-transfer pricing periods

- Added tenant-scoped `HospitalBedStayPeriod` records with the admission,
  bed/ward, start/end timestamps, daily rate snapshot, and linked billing line.
- Initial priced-bed assignment starts a period. A transfer closes the active
  period at the transfer timestamp and starts a new period using the
  destination ward's current rate.
- Discharge reconciles every period independently, rounding partial days up
  and updating draft charges idempotently without rewriting posted or paid
  charges. Legacy admissions without period rows retain the prior
  single-charge reconciliation behavior.
- Central and tenant migrations were applied successfully to Professional
  Hospital Demo (tenant 18). The focused admission and ward suites pass
  18/18 tests.
- Browser verification remains recommended for the next pass: transfer an
  admission from a standard ward to a priced private ward, confirm both
  pricing periods in the billing summary, then discharge and verify the
  separate quantities.
- Added focused transfer regression coverage for standard-to-private pricing
  periods, period closure, destination-rate billing, authorization, tenant
  isolation, release transfers, and timestamp/duplicate-transfer rules.
- Admissions now include an expandable Bed stay breakdown showing each ward,
  rate, period timestamps, reconciled days, and line total when period data is
  available. The local Admissions page rendered successfully after the
  change; the seeded demo records currently have no priced period rows to
  display.
