# Hospital operating theatre

Professional Hospital tenants receive the gated `hospital-operating-theatre` module. It provides tenant-scoped operating rooms and inpatient surgery scheduling at `/manager/operating-theatre`.

Owners and managers manage rooms and surgeries. Doctors may schedule and update surgeries. Nurses see surgeries only for admissions with an active nursing assignment; receptionists, pharmacists, and guards are denied.

Schedules support `scheduled`, `in_progress`, `completed`, and `cancelled`. A surgery must fall within its admission window, end after it starts, and cannot overlap another non-cancelled surgery in the same room.
