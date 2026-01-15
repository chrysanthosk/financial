# Financial Portal (Laravel)

A lightweight AdminLTE-style portal to track **Income** and **Expenses**, manage **settings** (income sources, expense categories, payment methods), run **reports**, and administer **users**, **SMTP**, and **audit logs**.

> Project archive reviewed on 2026-01-15.

---

## Tech stack

- **Laravel**: framework (composer.json indicates laravel/framework ^12.0)
- **PHP**: ^8.2 (you are running 8.5 locally; compatible, but you may see new deprecation warnings if env values are missing)
- **Vite**: asset bundling (`npm run build`)
- **Database**: SQLite by default (can be adapted to MySQL if desired)

---

## Roles (current)

The app currently supports **two roles** stored in `users.role`:

- `admin` — full access (guarded by the `admin` middleware)
- `user` — limited access (no admin area)

See migration: `database/migrations/2026_01_08_213639_add_role_to_users_table.php`.

---

## Modules & what they do

### Dashboard
**Controller:** `app/Http/Controllers/DashboardController.php` (`index`)
- Landing page after login.
- Shows summary widgets (income/expenses) and recent activity (implementation details in controller).

### Income
**Controller:** `app/Http/Controllers/IncomeController.php`
- `index` — list & filter income records (typically by month/source)
- `create/store` — add income record
- `edit/update` — update income record
- `destroy` — delete income record (with confirmation in UI)

**Models/Migrations**
- `income_sources` (`database/migrations/2026_01_09_000001_create_income_sources_table.php`)
- `incomes` (`database/migrations/2026_01_09_000002_create_incomes_table.php`)
- Uniqueness constraint: `2026_01_09_114758_add_unique_income_date_source_to_incomes_table.php`

### Expenses
**Controller:** `app/Http/Controllers/ExpenseController.php`
- `index` — list & filter expenses (by month/category/method)
- `create/store` — add expense
- `edit/update` — update expense
- `destroy` — delete expense (with confirmation in UI)

**Models/Migrations**
- `payment_methods` (`2026_01_09_150001_create_payment_methods_table.php`)
- `expense_categories` (`2026_01_09_150002_create_expense_categories_table.php`)
- `expenses` (`2026_01_09_150003_create_expenses_table.php`)

### Reports
**Controller:** `app/Http/Controllers/ReportsController.php`
Available report endpoints:
- `index` — reports home
- `monthlyProfit`
- `ytdIncome`
- `ytdExpenses`
- `prevYearComparison`
- `largestTransactions`
- `recurringExpenses`
- `categoryTrend`
- `topVendors`
- `expenseCategoryBreakdown`
- `incomeMethodTrend`

### Bonus (utility)
**Controller:** `app/Http/Controllers/BonusController.php`
- `index`, `calculate` — bonus-related calculator/summary (depends on your business logic).

### Profile & account security
**Controller:** `app/Http/Controllers/ProfileController.php`
- `edit/update` — profile details
- `password` — change password
- `destroy` — delete account (if enabled)

**Theme**
- `app/Http/Controllers/ThemeController.php` (`update`) — toggles user theme (`users.theme` added by `2026_01_08_211649_add_theme_to_users_table.php`)

**Email change flow**
- `app/Http/Controllers/EmailChangeController.php` (`requestChange`, `confirm`)
- Uses `users.pending_email`, `pending_email_token`, `pending_email_requested_at` (see migration `2026_01_09_160000_add_profile_2fa_email_change_to_users_table.php`)

**Two-Factor Authentication (2FA)**
- `app/Http/Controllers/TwoFactorController.php`
    - `show`, `enable`, `confirm`, `disable`, `regenerateRecoveryCodes`
- Libraries present: `pragmarx/google2fa`, `spomky-labs/otphp`
- User fields stored encrypted: `two_factor_secret`, `two_factor_recovery_codes` (see `App\Models\User` casts and migration `2026_01_09_160000_...`)

### Admin area (admin-only)
Admin routes are grouped under `/admin` and protected by middleware alias `admin`:

#### Users
**Controller:** `app/Http/Controllers/Admin/UserController.php`
- CRUD users: `index/create/store/edit/update/destroy`

#### SMTP settings (for email sending)
**Controller:** `app/Http/Controllers/Admin/SmtpSettingsController.php`
- `edit/update` — configure SMTP
- `test` — send a test email / validate connection (implementation in controller)

**DB**
- `smtp_settings` table: `database/migrations/2026_01_09_000000_create_smtp_settings_table.php`
- `last_tested_at`: `2026_01_09_194300_add_last_tested_at_to_smtp_settings_table.php`

#### System configuration (Settings)
**Controller:** `app/Http/Controllers/Admin/ConfigurationController.php`
Manages:
- Income sources (add/edit/delete)
- Expense categories (add/edit/delete)
- Payment methods (add/edit/delete)
- System settings (update) — stored in `system_settings` table (`2026_01_10_000000_create_system_settings_table.php`)

#### Audit logs
**Controllers:**
- `app/Http/Controllers/Admin/AuditLogController.php` (`index`)
- `app/Http/Controllers/Admin/AuditController.php` (`index`)

**DB**
- `audit_logs` table: `2026_01_09_200000_create_audit_logs_table.php`

#### Import
**Controller:** `app/Http/Controllers/Admin/ImportController.php`
Wizard flow for importing data (Income/Expenses):
- `index` — import home
- `showUpload` — routes to upload page by `<class 'type'>`
- `handleUpload` — upload → mapping
- `preview` — mapping → preview
- `commit` — final write into DB

---

## Routing overview

- Main routes: `routes/web.php`
- Authentication routes: `routes/auth.php` (Laravel Breeze style)

Auth controllers live under: `app/Http/Controllers/Auth/*`.

---

## Security checklist (verified in this codebase)

### Password hashing (bcrypt/argon2)
✅ **Covered**
- Passwords are hashed via `Hash::make(...)`, e.g.:
    - `app/Http/Controllers/Auth/RegisteredUserController.php`
    - `app/Http/Controllers/Auth/NewPasswordController.php`
    - `app/Http/Controllers/Auth/PasswordController.php`
    - `app/Http/Controllers/Admin/UserController.php`

Laravel’s hasher backend (bcrypt/argon2id) is handled by the framework; `Hash::make()` will use the configured driver.

### Session timeout
✅ **Covered (configurable)**
- `config/session.php`
    - `'lifetime' => env('SESSION_LIFETIME', 120)`
    - `'expire_on_close' => env('SESSION_EXPIRE_ON_CLOSE', false)`
- Default in `.env.example`: `SESSION_LIFETIME=120`

### CSRF protection
✅ **Covered**
- This app uses Laravel’s default **web** middleware stack (Laravel 12 style via `bootstrap/app.php` routing configuration).
- Blade forms include `@csrf` (example: delete forms in index pages).

### Rate limiting on login (basic)
✅ **Covered**
- `app/Http/Requests/Auth/LoginRequest.php`
    - `ensureIsNotRateLimited()` enforces **5 attempts** per throttle key (`email|ip`)
    - uses `RateLimiter::tooManyAttempts(..., 5)` and triggers lockout/validation errors

---

## Local setup (SQLite)

```bash
composer install
npm install

cp .env.example .env
php artisan key:generate

# SQLite file (if not already present)
mkdir -p database
touch database/database.sqlite

php artisan migrate --seed
npm run build
php artisan serve
```

### Default admin user
Created by `database/seeders/AdminUserSeeder.php` (only if no admin exists).
- Email: `admin@financial.i-portal.me`
- Password: `ChangeMe123!`  **(change immediately)**

---

## Notes / troubleshooting

### Deprecation: rtrim(null) in `config/filesystems.php`
If you see `rtrim(): Passing null...`, ensure `.env` has:
```env
APP_URL=http://127.0.0.1:8000
```
(or set a default in `config/filesystems.php` where `APP_URL` is used).

### OneDrive / CloudStorage paths
Running a Laravel project (especially SQLite) inside OneDrive/CloudStorage can cause:
- missing file sync edge cases
- SQLite locking issues

If you hit intermittent DB file errors, move the project to a local folder like `~/Projects/financial`.

---

## Where to start when reading the code

1. **Routes:** `routes/web.php`, `routes/auth.php`
2. **Core CRUD:** `IncomeController`, `ExpenseController`
3. **Admin settings:** `Admin/ConfigurationController`, `Admin/SmtpSettingsController`
4. **Security/account:** `ProfileController`, `TwoFactorController`, `EmailChangeController`
5. **Audit:** `App\Support\Audit` + `Admin/AuditLogController`
