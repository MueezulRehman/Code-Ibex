# Hospital operating theatre

Professional Hospital tenants receive the gated `hospital-operating-theatre` module. It provides tenant-scoped operating rooms and inpatient surgery scheduling at `/manager/operating-theatre`.

Owners and managers manage rooms and surgeries. Doctors may schedule and update surgeries. Nurses see surgeries only for admissions with an active nursing assignment; receptionists, pharmacists, and guards are denied.

Schedules support `scheduled`, `in_progress`, `completed`, and `cancelled`. A surgery must fall within its admission window, end after it starts, and cannot overlap another non-cancelled surgery in the same room.

The schedule now exposes status transitions directly in the surgery table, so authorized managers and doctors can move a record through its lifecycle without bypassing the controller validation. The update action preserves the tenant, admission window, room conflict checks, and audit-safe surgery identity.

Surgery records now capture optional tenant-scoped anaesthetist and assistant
surgeon links, free-text surgical team details, consent status, a four-item
pre-operative checklist, post-operative notes, and completion time. A manager
or doctor may schedule a case before readiness is complete, but the controller
rejects `in_progress` and `completed` transitions until consent is obtained and
identity, site, fasting, and laboratory checks are all marked ready. Completed
cases also require post-operative notes and receive `completed_at`.
