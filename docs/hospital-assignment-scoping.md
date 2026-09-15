# Professional Hospital assignment scoping

Doctors are scoped to clinical records through their tenant-local Doctor profile.
Managers can link a Doctor profile to one `users` account and optionally assign a
department. Existing profiles remain unlinked and are intentionally denied
clinical admission access until a manager assigns them.

For laboratory and radiology, a linked doctor can view and order only active
admissions where either:

* `hospital_admissions.doctor_id` is that doctor's profile; or
* `hospital_admissions.department_id` is the doctor's assigned department.

Managers retain tenant-wide access, and nurses retain access through their active
nursing assignments. All checks include the effective restaurant/tenant.

## Rollout

1. Run tenant migrations (`php artisan migrate --path=database/tenant_migrations`).
2. In each Professional Hospital tenant, edit each doctor and select the matching
   doctor login user and department.
3. Validate one directly assigned and one department-assigned admission in both
   Laboratory and Radiology before enabling the workflow for staff.

The new columns are nullable and preserve existing doctor and admission data.
