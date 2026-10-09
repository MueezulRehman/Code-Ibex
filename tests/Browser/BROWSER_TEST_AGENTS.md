# Codeibex Browser Testing Agents

This document defines the browser-testing agent personas and their test scripts.
Each agent simulates a real user role interacting with the live app.

## Agent Roster

| Agent | Role | Business Type | Tests |
|---|---|---|---| 
| `super-admin-agent` | Platform Super Admin | All | Tenant provisioning, module config, subscriptions |
| `restaurant-manager-agent` | Manager | Restaurant | Menu CRUD, POS checkout, deals, stock |
| `restaurant-customer-agent` | Customer | Restaurant | Storefront browse, cart, checkout, order tracking |
| `medical-manager-agent` | Manager | Medical/Clinic | Patients, appointments, prescriptions, POS |
| `hospital-admin-agent` | Admin | Hospital | Admissions, beds, wards, lab, radiology, billing |
| `retail-manager-agent` | Manager | General Store | Inventory, POS, stock adjustments, reports |
| `ceo-agent` | CEO | Multi-business | Portfolio view, branch access, reports |

---

## How to Run

Each agent is a spawnable task. Run from the project root:

```bash
# Start the local dev server first:
php artisan serve --port=8000

# Then trigger an agent (pick one):
php artisan test:browser --agent=restaurant-manager
php artisan test:browser --agent=restaurant-customer
# etc.
```

Or use the Playwright scripts directly:
```bash
cd tests/Browser
npx playwright test agents/restaurant-manager.spec.js --headed
```

---

## Agent Specs

### 1. super-admin-agent

**Login:** super admin credentials from `.env` / seeder
**Base URL:** `http://localhost:8000/super-admin`

**Test script:** `tests/Browser/agents/super-admin.spec.js`

Steps:
1. Navigate to `/super-admin/login`, login
2. Assert dashboard loads with tenant list
3. Create a new restaurant tenant (Restaurant type)
4. Verify tenant DB provisioned (check success flash)
5. Assign subscription plan
6. Enable modules (POS, Inventory, Deals)
7. Logout

---

### 2. restaurant-manager-agent

**Login:** seeded manager for TasteHut restaurant
**Base URL:** `http://tastehut.localhost:8000` or `http://localhost:8000/{slug}`

**Test script:** `tests/Browser/agents/restaurant-manager.spec.js`

Steps:
1. Login as manager
2. Create a menu category ("Burgers")
3. Add a menu item with image upload
4. Create a deal
5. Open POS → add item to cart → cash checkout
6. Check stock adjustment UI
7. View sales report
8. Logout

---

### 3. restaurant-customer-agent

**Login:** anonymous / registered customer
**Base URL:** `http://tastehut.localhost:8000`

**Test script:** `tests/Browser/agents/restaurant-customer.spec.js`

Steps:
1. Navigate to storefront homepage
2. Browse menu, add 2 items to cart
3. Go to checkout
4. Fill delivery details, submit order
5. Track order via `/track/{token}`
6. Register as customer (new account)
7. Login as customer, view order history

---

### 4. medical-manager-agent

**Login:** seeded manager for a medical/clinic tenant
**Base URL:** `http://localhost:8000/{medical-slug}`

**Test script:** `tests/Browser/agents/medical-manager.spec.js`

Steps:
1. Login as medical manager
2. Register new patient
3. Book appointment with doctor
4. Create visit / consultation
5. Write prescription
6. Dispense medicine via POS
7. View medical records

---

### 5. hospital-admin-agent

**Login:** seeded admin for Professional Hospital demo
**Base URL:** `http://localhost:8000/{hospital-slug}`

**Test script:** `tests/Browser/agents/hospital-admin.spec.js`

Steps:
1. Login as hospital admin
2. Admit a patient to a ward/bed
3. Assign nurse
4. Order lab test → mark result
5. Order radiology → mark result
6. Create hospital billing
7. Record payment
8. Discharge patient

---

### 6. retail-manager-agent

**Login:** seeded manager for general store tenant
**Base URL:** `http://localhost:8000/{store-slug}`

**Test script:** `tests/Browser/agents/retail-manager.spec.js`

Steps:
1. Login as retail manager
2. Add product with variants (size/color)
3. Record inventory purchase
4. Open POS → add product → checkout with discount
5. Create stock adjustment (shrinkage)
6. View inventory report

---

### 7. ceo-agent

**Login:** seeded CEO user with multi-business access
**Base URL:** `http://localhost:8000/ceo`

**Test script:** `tests/Browser/agents/ceo.spec.js`

Steps:
1. Login as CEO
2. View portfolio dashboard (all assigned businesses)
3. Switch to restaurant branch view
4. View sales summary for the branch
5. Switch to medical branch view
6. View reports

---
