# Live deployment and HTTP 500 recovery

This application is Laravel 13 and requires PHP 8.3 or later. Its data is
split between a central MySQL database and separate tenant databases. The web
server document root must point to the application's `public` directory.

The repository does not contain production credentials. Never copy a local
`.env` to the server, commit one, or paste its contents into a support request.

## Current release

Verify the intended release on `main` before deploying:

```sh
git ls-remote https://github.com/MueezulRehman/Restaurants-System.git refs/heads/main
```

## First deployment on a Linux server with SSH

### 1. Prepare the host

- Install PHP 8.3+ and the PHP extensions required by Composer, including
  `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `tokenizer`, `xml`, `ctype`,
  `curl`, and `dom`.
- Install Composer, Node.js/npm, and MySQL or MariaDB.
- Create a central database and a database user. This application also
  creates one database per tenant; the central DB user must therefore have
  permission to create the configured tenant databases. Grant only the
  permissions required by this application.
- Configure HTTPS and point the domain's document root at
  `/path/to/codeibex/public`, **not** the repository root.
- Keep the application code, `.env`, `vendor`, and `storage` outside the
  public document root.

For cPanel, use its Terminal/Git Version Control and MultiPHP Manager where
available. Set the domain document root to the app's `public` directory. Do
not put the entire Laravel repository inside `public_html`. If the hosting
plan cannot set a document root or run the required PHP/Composer commands, it
is not suitable for this deployment without a hosting-specific secure layout.

### 2. Clone the repository and install dependencies

Replace the example path with a private application directory owned by the
deploy user:

```sh
cd /home/CPANEL_USER/apps
git clone --branch main https://github.com/MueezulRehman/Restaurants-System.git codeibex
cd codeibex
composer install --no-dev --prefer-dist --optimize-autoloader
npm ci
npm run build
```

Use the PHP version used by the web server when running Composer and Artisan:

```sh
php -v
composer check-platform-reqs --no-dev
```

### 3. Configure production environment

Create a server-only environment file and edit it using the hosting provider's
secure editor:

```sh
cp .env.example .env
nano .env
```

At minimum, set the following appropriately. Use the provider's real
credentials and database names; the values below are examples, not secrets:

```dotenv
APP_NAME=CodeIbex
APP_ENV=production
APP_DEBUG=false
APP_URL=https://codeibex.store

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=YOUR_CENTRAL_DATABASE
DB_USERNAME=YOUR_DATABASE_USER
DB_PASSWORD=YOUR_DATABASE_PASSWORD

SESSION_SECURE_COOKIE=true
```

Also verify the app's mail, cache, queue, file storage, and tenant database
settings against the actual host. `TENANCY_DB_PREFIX` defaults to
`codeibex_tenant_`; each tenant database name is that prefix plus its
restaurant ID. Do not enable debug mode on a public site.

Generate `APP_KEY` only for a truly new installation, after setting `.env`:

```sh
php artisan key:generate
```

**Never regenerate `APP_KEY` for an existing installation.** It can make
encrypted tenant connection data and other encrypted application values
unreadable.

### 4. Run initial schema setup

Back up databases first if any data already exists. Apply central migrations,
then tenant migrations:

```sh
php artisan migrate --force
php artisan tenants:migrate
php artisan storage:link
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

`tenants:migrate` applies the tenant schema to restaurants already present in
the central database and can create missing tenant databases. It requires the
database privileges described above. Do not run `db:seed` or provision demo
accounts in production unless the exact seeder has been reviewed and approved.

Ensure the deploy/web-server user owns or can write to `storage` and
`bootstrap/cache`. Fix ownership with the host's correct application user; do
not use world-writable permissions such as `chmod -R 777`.

### 5. Start background services

The app's queue connection defaults to the database. If production uses
database queues, run a supervised worker that restarts on deployment, for
example:

```sh
php artisan queue:work --sleep=3 --tries=3 --timeout=90
```

Configure the hosting provider's process supervisor to keep it running and
configure the Laravel scheduler using the provider's cron facility:

```cron
* * * * * cd /path/to/codeibex && php artisan schedule:run >> /dev/null 2>&1
```

Do not launch a queue worker in an interactive SSH session and assume it will
remain alive after logout.

## Recover the currently failing site (HTTP 500)

The browser confirms `https://codeibex.store/` is returning HTTP 500. That
response does not identify whether the cause is PHP, the database, missing
dependencies, permissions, or a wrong document root. Do not turn on
`APP_DEBUG` publicly to investigate.

### 1. Connect and collect safe diagnostics

From the server's application directory:

```sh
cd /path/to/codeibex
php -v
php artisan --version
composer check-platform-reqs --no-dev
php artisan optimize:clear
php artisan migrate:status
```

Then inspect the most recent Laravel exception and the hosting provider's PHP
and web-server error logs. Common locations are `storage/logs/laravel.log`,
cPanel **Metrics → Errors**, PHP-FPM's journal, and Apache/Nginx error logs.
Do not paste full logs publicly: remove passwords, tokens, connection strings,
SQL parameters, patient identifiers, phone numbers, diagnoses, and clinical
text. Share only the sanitized exception class/message and relevant
application stack frames with a trusted administrator.

### 2. Check the usual causes

1. The domain document root is not `/path/to/codeibex/public`.
2. The web server uses an older PHP version than 8.3 or required PHP
   extensions are missing.
3. `.env` is absent, invalid, or points to the wrong central MySQL database.
   Confirm `APP_KEY` is present and unchanged; do not share it.
4. Composer dependencies or built assets are missing/out of date.
5. A migration has not been applied to the central or relevant tenant database.
6. The web-server user cannot write to `storage` or `bootstrap/cache`.
7. Laravel has stale configuration/route/view caches after `.env` or code
   changes.
8. The host's PHP error log reports a PHP-FPM, Apache/Nginx, or document-root
   failure before Laravel can write its own log.

If Laravel logging itself fails due to permissions, first use the hosting
provider's PHP/web-server error log. Correct ownership for `storage` and
`bootstrap/cache` using the provider's application user; do not make them
world-writable.

### 3. Deploy the current Git version safely

Back up the central database **and every tenant database** before migrations.
Keep the server's existing `.env` unchanged. From the release directory:

The current Hostinger installation was manually uploaded and is not a Git
checkout, so `git pull` inside `public_html/Codeibex` will not update it. For
that installation, back up the current release first, then upload the changed
PHP files from the verified Git commit to their matching paths:

- `app/Console/Commands/ProvisionProfessionalHospitalDemoCommand.php`
- `app/Console/Commands/SeedProfessionalHospitalDemoCommand.php`
- `app/Console/Commands/SeedDemoBusinessesCommand.php`
- `app/Http/Controllers/Admin/RestaurantController.php`
- `database/seeders/DemoBusinessesSeeder.php`
- `database/seeders/ProfessionalHospitalDemoSeeder.php`
- `database/seeders/TasteHutSeeder.php`
- `database/seeders/AlMajidAryansStoreSeeder.php`
- `database/seeders/AmjdKaryanaStoreSeeder.php`
- `database/seeders/FastFoodSeeder.php`
- `database/seeders/FoodClinicSeeder.php`
- `database/seeders/MedicineSeeder.php`
- `database/seeders/PakistaniGeneralStoreSeeder.php`
- `database/seeders/PakistaniMedicineSeeder.php`
- `resources/css/layouts.css`

Do not replace `.env`, regenerate `APP_KEY`, overwrite `storage`, or remove the
existing backup. There is no schema migration, but the mobile sidebar fix
requires the rebuilt Vite assets. Build them locally with `npm run build`, copy
the new files under `public/build/assets/` to the server, then replace
`public/build/manifest.json` last. Keep old hashed assets until the new
manifest is in place. After uploading, run `php artisan optimize:clear`.

## Seed the curated demo businesses

`businesses:seed-demos` provisions the demo businesses present in the
repository's focused seeders: Taste Hut, City Pharmacy, Al-Barkat General
Store, Karachi Broast, Food Clinic, Al Majid Aryans Store, Amjd Karyana Store,
and Professional Hospital Demo. It adds missing central rows, applies tenant
migrations, and seeds the matching synthetic catalogs/records. It does not run
the general `DatabaseSeeder`, reset the Super Admin, or print manager
passwords. Use Super Admin **Enter business** to inspect the businesses.

Always inspect the planned scope first:

```sh
php artisan businesses:seed-demos --dry-run
```

On shared hosting where the app DB user cannot create databases, create the
central business rows and get their exact tenant database names first. Replace
the prefix below with the name prefix accepted by the hosting provider:

```sh
php artisan businesses:seed-demos \
  --create-only \
  --tenant-database-prefix="HOSTINGER_ACCOUNT_codeibex_demo_" \
  --confirm
```

Create each reported tenant database as a separate empty database in hPanel
and grant the application DB user access. Then run the command again without
`--create-only`:

```sh
php artisan businesses:seed-demos \
  --tenant-database-prefix="HOSTINGER_ACCOUNT_codeibex_demo_" \
  --confirm
```

If the app DB user is permitted to create tenant databases, omit
`--tenant-database-prefix` and run once with `--confirm`. The command is
additive and safe to rerun; it does not delete seeded catalogs or overwrite
existing manager passwords. All patient and other business records are
synthetic demo data—never run this against tenants containing real customer,
clinical, or other production records. Do not use `php artisan db:seed
--force` as a substitute; the general seeder has unrelated development
accounts and behavior.

```sh
git status --short --branch
git pull --ff-only origin main
composer install --no-dev --prefer-dist --optimize-autoloader
npm ci
npm run build
php artisan optimize:clear
php artisan migrate --force
php artisan tenants:migrate
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
```

Run the maintenance mode commands only if the site is already bootable and
you can ensure it will be restored:

```sh
php artisan down
# perform the backed-up deployment steps
php artisan up
```

If any deployment step fails, stop rather than continuing to cache or restart
services. Record the command's sanitized error, resolve the underlying issue,
and bring the application back up once it is healthy.

**Never run `php artisan migrate:fresh` on a live system.** It deletes tables.
Do not use `git reset --hard`, replace the production `.env`, rerun seeders, or
regenerate `APP_KEY` as a generic fix.

## Production readiness warning

The medical security audit in
[`SECURITY_COMPLIANCE_AUDIT.md`](./SECURITY_COMPLIANCE_AUDIT.md) has unresolved
high-severity authorization and tenant-isolation findings. Restoring the site
does not mean the clinical system is approved for real patient data or
clinical operations. Keep real PHI/clinical workflows disabled until those
findings are remediated and retested.

## Create a Professional Hospital demo business

Do not use `php artisan db:seed --force` to create a live demo: the general
seeder adds unrelated demo businesses and accounts. The dedicated
`hospital:provision-demo` command creates exactly one active, hidden
Professional Hospital business, provisions its own tenant schema, and adds
the deterministic demo hospital records. A Super Admin can then open the
business from the Businesses page using **Enter business**. The command does
not create or reveal Super Admin login credentials.

First validate the inputs without writing:

```sh
php artisan hospital:provision-demo \
  --name="Professional Hospital Demo" \
  --slug="professional-hospital-demo" \
  --dry-run
```

On shared hosting, first create a **separate empty tenant database** in hPanel
and grant the existing application DB user access to it. Pass its exact
Hostinger-prefixed database name to the command; do not use the central
database name:

```sh
php artisan hospital:provision-demo \
  --name="Professional Hospital Demo" \
  --slug="professional-hospital-demo" \
  --database="HOSTINGER_ACCOUNT_hospital_demo" \
  --confirm
```

When the hosting DB account is allowed to create databases directly, omit
`--database` and the application will use its configured tenant database
prefix. The command refuses duplicate slugs and requires explicit `--confirm`
for writes. If tenant provisioning fails after the central business row is
created, do not rerun with another slug; inspect the reported error and resume
the existing business deliberately.

The demo dataset contains synthetic patient/clinical records and demo staff
accounts with random, undisclosed passwords. It is for demonstration only,
not real patient care. Never seed it into a real hospital tenant or a tenant
containing real PHI. Existing-business data-only seeding remains available
through `hospital:seed-demo --restaurant=ID`; always use the actual dedicated
demo tenant ID, never an assumed ID.

The Super Admin restaurant-create form defaults a missing plan to the first
active subscription plan, or the schema-compatible `basic` value if no active
plan exists. This prevents an omitted optional plan from inserting `NULL` into
the non-null `restaurants.plan` column.
