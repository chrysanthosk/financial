# Financial Portal (Laravel)

A single-tenant financial tracking portal built on Laravel 12. Track **income** and **expenses**, run **reports**, manage **users**, configure **SMTP**, view **audit logs**, and import data from spreadsheets — all behind a secure login with optional two-factor authentication.

---

## Tech stack

| Layer | Technology |
|---|---|
| Framework | Laravel 12.x |
| PHP | ^8.2 (developed on 8.5) |
| UI framework | AdminLTE 4.0.0-rc6 (Bootstrap 5) |
| JS | Alpine.js 3.x, Axios 1.x |
| CSS | AdminLTE + custom dark-mode overrides; Tailwind CSS 3.x (pagination only) |
| Icons | FontAwesome 7.x |
| Asset build | Vite 7.x with laravel-vite-plugin |
| Database | SQLite (default) / MySQL (supported) |
| Sessions / Cache / Queue | `database` driver |
| 2FA | `pragmarx/google2fa` (QR/secret) + `spomky-labs/otphp` (TOTP verification) |
| Spreadsheet import | `phpoffice/phpspreadsheet ^5.4` |
| QR codes | `bacon/bacon-qr-code ^3.0` |

---

## Roles

Two roles stored in `users.role`:

| Role | Access |
|---|---|
| `admin` | Full access including `/admin/*` and `/tools/*` |
| `user` | Core app only (income, expenses, reports, profile) |

Role is enforced by the `AdminOnly` middleware, registered as the `admin` alias.

---

## Modules

### Dashboard

**Controller:** `DashboardController`

- Time-based greeting (morning / afternoon / evening)
- Today's income summary
- Month-to-date income, expenses, and net profit widgets
- Two charts: daily income vs. expenses for the current month; income breakdown by source

---

### Income

**Controller:** `IncomeController`

- Month-selector filter (defaults to current month)
- Rendered as a **pivot grid**: rows = calendar days, columns = income sources — pivot built in PHP (DB-agnostic)
- Per-source column totals and per-day row totals
- Full CRUD; unique constraint on `(income_date, income_source_id)` prevents duplicate entries per day/source
- All mutations audit-logged

**DB tables:** `income_sources`, `incomes`

---

### Expenses

**Controller:** `ExpenseController`

- Month-selector, category filter, payment method filter, sortable by date
- Paginated list (20/page) with `withQueryString()` preserving filters across pages
- Payment method breakdown subtotals shown alongside the month total
- Optional `cheque_no` field surfaced when payment method is Cheque
- Full CRUD; all mutations audit-logged

**DB tables:** `expense_categories`, `payment_methods`, `expenses`

---

### Reports

**Controller:** `ReportsController` — 10 report types, all aggregated in PHP (no DB-specific date functions, works on both SQLite and MySQL):

| Report | Description |
|---|---|
| Monthly Profit | Bar/line chart of income, expenses, and profit per month for a selected year |
| YTD Income | By-source breakdown + monthly trend chart |
| YTD Expenses | By-category and by-payment-method breakdown + monthly trend |
| Previous Year Comparison | Side-by-side profit by month: current vs. prior year |
| Largest Transactions | Top N income or expense records for a selected period |
| Recurring Expenses | Vendors appearing in 3+ distinct months over the last N months |
| Category Trend | Stacked chart of top N expense categories by month |
| Top Vendors | Ranked payee table + chart by total spend |
| Expense Category Breakdown | Pie chart with "Other" bucket for tail categories |
| Income Method Trend | Percentage share per income source by month (stacked bar) |

---

### Bonus Calculator

**Controller:** `BonusController`

Standalone tiered-rate bonus calculator:

| Gross range | Bonus rate |
|---|---|
| < 1,600 | 0% |
| 1,600 – 2,800 | 3% |
| 2,800 – 4,000 | 4% |
| 4,000 – 5,200 | 5% |
| 5,200+ | 6% |

---

### Profile & account security

**Controllers:** `ProfileController`, `ThemeController`, `EmailChangeController`, `TwoFactorController`

- **Profile:** update first/last name, email, password (requires current password); delete account (password confirmation required)
- **Theme toggle:** light / dark — persisted to `users.theme` via async POST to `/theme`; preference also stored in `localStorage` for instant application on load
- **Email change flow:** token-based (SHA-256 of 64-char random string stored in `pending_email_token`); confirmation link sent to the new address; 60-minute expiry; `hash_equals()` constant-time comparison
- **Two-Factor Authentication:** TOTP via authenticator app; setup includes QR code scan + code confirmation; recovery codes generated and stored encrypted; all fields (`two_factor_secret`, `two_factor_recovery_codes`) use Laravel's `encrypted` / `encrypted:array` cast

**2FA login flow:**

1. User submits correct password
2. If 2FA is enabled and confirmed, user is immediately logged out
3. `2fa:user:id` and `2fa:remember` are written to the session
4. `EnsureTwoFactorChallenged` middleware (appended to the web stack) intercepts all requests until the TOTP code is verified at `/two-factor-challenge`

---

### Admin area

All routes under `/admin/*` and `/tools/*` require `auth` + `admin` middleware.

#### Users (`/admin/users`)

**Controller:** `Admin\UserController`

- Full CRUD (paginated 20/page)
- Self-deletion and deletion of the last admin are blocked
- All operations audit-logged

#### SMTP settings (`/admin/settings/smtp`)

**Controller:** `Admin\SmtpSettingsController`

- Configure host, port, encryption, credentials, from address/name, and enabled toggle
- **Send test email** to a specified address; records `last_tested_at`
- SMTP password stored with Laravel `encrypted` cast; only updated when a new value is submitted
- Settings are applied at boot via `AppServiceProvider` — override `.env` values without redeployment
- All changes audit-logged

**DB table:** `smtp_settings`

#### System configuration (`/admin/settings/configuration`)

**Controller:** `Admin\ConfigurationController`

- Manage **Income Sources**, **Expense Categories**, **Payment Methods** (add / edit / toggle active / delete — delete blocked if the item is in use)
- **System Settings:** portal header name and footer name (stored in `system_settings`)

#### Audit log viewer (`/admin/audit`)

**Controller:** `Admin\AuditLogController`

- Filter by: category, action (LIKE search), user, IP address, date range
- Full-text search across action, category, target, and IP fields
- Paginated (25/page) with filter persistence

**DB table:** `audit_logs` — columns: `user_id` (nullable FK, NULLed on user delete), `action`, `category`, `target_type`, `target_id`, `ip`, `user_agent`, `meta` (JSON), `created_at` (indexed)

#### Import wizard (`/tools/import`)

**Controller:** `Admin\ImportController`

Four-step wizard: **Upload → Column Mapping → Preview → Commit**

Supports **Income** (wide format: date column + one column per income source) and **Expenses** (field mapping to date, payee, category, payment method, amount, cheque_no, reason).

File formats: `.xlsx`, `.xls`, `.csv`

Notable behaviours:
- Smart date parsing: Excel serial numbers, `Y-m-d`, `d/m/Y`, `d-m-Y`, `m/d/Y`, optional fallback year for day-only values
- Amount normalization: thousands separators, comma/dot decimal variants, non-breaking spaces
- Preview shows up to 80 rows with validation errors highlighted per row
- Commit runs inside a DB transaction; per-row errors skip that row without aborting the whole import
- Unknown categories / payment methods fall back to "Other" (auto-created if missing)
- Temp files stored in `storage/app/imports/{uuid}/` and deleted after commit

---

## Database schema (key tables)

| Table | Purpose |
|---|---|
| `users` | Auth + profile + 2FA + pending email change |
| `income_sources` | Configurable income source names |
| `incomes` | Income records — unique on `(income_date, income_source_id)` |
| `expense_categories` | Configurable expense category names |
| `payment_methods` | Configurable payment method names |
| `expenses` | Expense records with category, payment method, optional cheque_no |
| `smtp_settings` | Single-row SMTP configuration (password encrypted) |
| `system_settings` | Single-row portal branding (header/footer name) |
| `audit_logs` | Immutable audit trail for all mutations + auth events |
| `sessions` | DB-backed sessions |
| `cache` / `cache_locks` | DB-backed cache |
| `jobs` / `failed_jobs` | DB-backed queue |

### Foreign key delete strategy

| FK | On delete |
|---|---|
| `incomes.created_by → users` | RESTRICT — prevents deleting a user with income records |
| `expenses.created_by → users` | SET NULL — user can be deleted; expense record is preserved |
| `audit_logs.user_id → users` | SET NULL — audit trail preserved after user deletion |

---

## Security

### Authentication & session

- Passwords hashed via `Hash::make()` (bcrypt/argon2id, 12 rounds in production)
- Login rate-limited: 5 attempts per `email|IP` combination (`LoginRequest`)
- Session lifetime: 120 minutes (configurable via `SESSION_LIFETIME`)
- Session driver: `database`
- CSRF: all Blade forms include `@csrf`; enforced by Laravel's web middleware stack

### Audit logging

`App\Support\Audit::log()` is called on every mutating operation (create, update, delete) across all controllers, and also wired to Laravel framework events:

| Event | Action logged |
|---|---|
| `Illuminate\Auth\Events\Login` | `auth.login` |
| `Illuminate\Auth\Events\Logout` | `auth.logout` |
| `Illuminate\Auth\Events\Failed` | `auth.failed` (includes attempted email) |

Audit logging is **best-effort** — wrapped in `try/catch` so a logging failure never blocks the business operation.

### Encrypted fields

| Field | Model | Method |
|---|---|---|
| `two_factor_secret` | `User` | `encrypted` cast |
| `two_factor_recovery_codes` | `User` | `encrypted:array` cast |
| `smtp_settings.password` | `SmtpSetting` | `encrypted` cast |

All encrypted via Laravel's AES-256-CBC encrypter using `APP_KEY`.

---

## Local setup (SQLite)

```bash
composer install
npm install

cp .env.example .env
php artisan key:generate

# Create the SQLite file
touch database/database.sqlite

php artisan migrate --seed
npm run build
php artisan serve
```

Or use the bundled composer shortcut:

```bash
composer setup
```

This runs: `composer install` → `cp .env.example .env` → `key:generate` → `migrate` → `npm install` → `npm run build`.

### Default admin user

Created by `database/seeders/AdminUserSeeder.php` (only if no admin exists):

| Field | Value |
|---|---|
| Email | `admin@financial.i-portal.me` |
| Password | `ChangeMe123!` — **change immediately after first login** |

---

## Development mode

Run all dev processes concurrently in one terminal:

```bash
composer dev
```

This starts (with colored labels):
- `php artisan serve`
- `php artisan queue:listen`
- `php artisan pail --timeout=0` (log tail)
- `npm run dev` (Vite HMR)

---

## Deployment

### Production deploy

```bash
bash scripts/deploy.sh
```

The script is interactive — prompts to select the project from `/opt/*` — then:

1. `git pull`
2. `composer install --no-dev --optimize-autoloader`
3. `npm ci && npm run build`
4. Artisan cache clear and optimize
5. Fix permissions (www-data group)
6. `php artisan migrate --force`
7. Selective seeders based on `git diff` (only runs seeders for changed seeder files)
8. Restarts PHP-FPM and Nginx (service names configurable)

### Initial server setup

```bash
bash scripts/install.sh
```

Covers first-run provisioning on Debian/Ubuntu with `php8.4-fpm` and `nginx`.

### MySQL (production)

Uncomment the MySQL block in `.env` and comment out the SQLite line:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=financial
DB_USERNAME=your_user
DB_PASSWORD=your_password
```

All queries and aggregations are DB-agnostic (no SQLite- or MySQL-specific date functions) — the switch requires only the `.env` change.

---

## Tests

```bash
php artisan test
```

Test environment uses in-memory SQLite (`DB_DATABASE=:memory:`), `BCRYPT_ROUNDS=4`, array session/cache/mail drivers, and synchronous queue.

Current coverage: Breeze-scaffolded auth tests (`tests/Feature/Auth/`) and a profile test. Business logic (income/expense CRUD, reports, 2FA flow, import wizard, audit logging) does not yet have test coverage — the infrastructure is in place to add it.

---

## Routing overview

- `routes/web.php` — all application routes
- `routes/auth.php` — authentication routes (Breeze style)

Route groups:

| Prefix | Middleware | Purpose |
|---|---|---|
| `/` | `auth`, `verified`, `2fa` | Core app (dashboard, income, expenses, reports, bonus, profile) |
| `/admin` | `auth`, `admin` | Admin area (users, SMTP, configuration, audit logs) |
| `/tools` | `auth`, `admin` | Import wizard |

---

## Architectural notes

**DB-agnostic aggregations** — All reports and dashboard queries fetch raw rows and aggregate in PHP using Carbon. No `DATE_FORMAT`, `strftime`, or other DB-specific functions are used. This keeps SQLite (dev) and MySQL (prod) behaviour identical.

**PHP-side pivot** — The income index renders as a grid without any PIVOT SQL. Flat DB rows are transformed in PHP: rows = calendar days, columns = income sources.

**SMTP from DB at boot** — `AppServiceProvider::boot()` applies `smtp_settings` to `config()` before every request when enabled. SMTP can be reconfigured at runtime without touching `.env` or redeploying.

**`SystemSetting` singleton cache** — `SystemSetting::current()` uses a static property for in-process caching, avoiding repeated DB reads for portal header/footer branding on every request.

**Best-effort audit logging** — `Audit::log()` is wrapped in `try/catch`. A logging failure is silent and never propagates to the user.

---

## Notes / troubleshooting

### rtrim(null) deprecation in `config/filesystems.php`

Ensure `.env` contains:

```env
APP_URL=http://127.0.0.1:8000
```

### OneDrive / CloudStorage paths

Running a Laravel project (especially with SQLite) inside OneDrive/CloudStorage can cause sync edge cases and SQLite locking issues. If you hit intermittent DB errors, move the project to a local folder such as `~/Projects/financial`.

---

## Where to start reading the code

1. **Routes:** `routes/web.php`, `routes/auth.php`
2. **Core CRUD:** `IncomeController`, `ExpenseController`
3. **Dashboard:** `DashboardController`
4. **Reports:** `ReportsController`
5. **Admin settings:** `Admin/ConfigurationController`, `Admin/SmtpSettingsController`
6. **Security / account:** `ProfileController`, `TwoFactorController`, `EmailChangeController`
7. **Audit:** `App\Support\Audit` + `Admin/AuditLogController`
8. **Import:** `Admin/ImportController`
9. **2FA middleware:** `App/Http/Middleware/EnsureTwoFactorChallenged`
