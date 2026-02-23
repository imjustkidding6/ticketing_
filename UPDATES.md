# Ticketing System Updates

## Version 1.0.0 - License-Based Multi-Tenant System

**Date:** February 20, 2026

---

## Overview

Transformed the ticketing system into a distributor/license-based multi-tenant system with three subscription tiers: Start, Business, and Enterprise.

---

## Features Implemented

### 1. Subscription Plans

Three subscription tiers with configurable limits:

| Plan | Max Users | Max Tickets/Month |
|------|-----------|-------------------|
| Start | 5 | 100 |
| Business | 25 | 500 |
| Enterprise | Unlimited | Unlimited |

### 2. Distributor Management

- Distributors can issue licenses to customers
- Auto-generated slugs from company name
- Auto-generated API keys for future API access
- Track all licenses issued by each distributor

### 3. License System

**License Key Format:** `XXXX-XXXX-XXXX-XXXX-XXXX`

**License Status Flow:**
```
pending → active (on activation)
active → expired (after expires_at + grace_days)
active/expired → revoked (manual revocation)
```

**Grace Period (Default: 7 days):**
- During grace period: Full access with warning
- After grace period: Tenant should be suspended

**License Features:**
- Configurable seats (max users)
- Configurable expiration date
- Configurable grace period
- Plan assignment
- Activation tracking

### 4. Tenant Registration with License

New registration flow requires:
1. Valid license key (pending status)
2. Company name
3. User details (name, email, password)

On registration:
- Tenant is created
- License is activated and linked to tenant
- User is added as tenant owner

### 5. Admin Panel

**URL:** `/admin`

**Sections:**
- **Dashboard** - Overview statistics and quick actions
- **Distributors** - CRUD operations for distributors
- **Licenses** - Create, view, edit, and revoke licenses
- **Plans** - Manage subscription plans
- **Tenants** - View tenants, suspend/unsuspend

---

## Database Changes

### New Tables

#### `plans`
| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| name | string | Plan name |
| slug | string | URL-friendly identifier |
| description | text | Plan description |
| max_users | int/null | User limit (null = unlimited) |
| max_tickets_per_month | int/null | Ticket limit (null = unlimited) |
| is_active | boolean | Plan availability |
| timestamps | | Created/updated at |

#### `distributors`
| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| name | string | Company name |
| slug | string | URL-friendly identifier |
| email | string | Contact email |
| contact_person | string/null | Contact name |
| phone | string/null | Phone number |
| address | text/null | Address |
| is_active | boolean | Active status |
| api_key | string/null | API key for future use |
| timestamps | | Created/updated at |

#### `licenses`
| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| license_key | string | Unique license key |
| distributor_id | foreign | Issuing distributor |
| plan_id | foreign | Associated plan |
| tenant_id | foreign/null | Activated tenant |
| seats | int | Max users allowed |
| status | string | pending/active/expired/revoked |
| issued_at | timestamp | When license was created |
| activated_at | timestamp/null | When license was activated |
| expires_at | timestamp | Expiration date |
| grace_days | int | Days after expiry with full access |
| timestamps | | Created/updated at |

### Modified Tables

#### `tenants` (added columns)
| Column | Type | Description |
|--------|------|-------------|
| license_id | foreign/null | Associated license |
| settings | json/null | Tenant settings |
| suspended_at | timestamp/null | Suspension date |

#### `users` (added columns)
| Column | Type | Description |
|--------|------|-------------|
| is_admin | boolean | Admin access flag |

---

## New Files

### Models
- `app/Models/Plan.php`
- `app/Models/Distributor.php`
- `app/Models/License.php`

### Controllers
- `app/Http/Controllers/Admin/DashboardController.php`
- `app/Http/Controllers/Admin/DistributorController.php`
- `app/Http/Controllers/Admin/LicenseController.php`
- `app/Http/Controllers/Admin/PlanController.php`
- `app/Http/Controllers/Admin/TenantController.php`

### Middleware
- `app/Http/Middleware/AdminMiddleware.php`

### Factories
- `database/factories/PlanFactory.php`
- `database/factories/DistributorFactory.php`
- `database/factories/LicenseFactory.php`

### Seeders
- `database/seeders/PlanSeeder.php`

### Views
- `resources/views/layouts/admin.blade.php`
- `resources/views/admin/dashboard.blade.php`
- `resources/views/admin/distributors/*.blade.php`
- `resources/views/admin/licenses/*.blade.php`
- `resources/views/admin/plans/*.blade.php`
- `resources/views/admin/tenants/*.blade.php`

### Tests
- `tests/Feature/PlanTest.php`
- `tests/Feature/DistributorTest.php`
- `tests/Feature/LicenseTest.php`
- `tests/Feature/TenantLicenseTest.php`

---

## Routes

### Admin Routes (requires authentication + admin role)

| Method | URI | Action |
|--------|-----|--------|
| GET | /admin | Dashboard |
| GET | /admin/distributors | List distributors |
| POST | /admin/distributors | Create distributor |
| GET | /admin/distributors/{id} | View distributor |
| GET | /admin/distributors/{id}/edit | Edit form |
| PUT | /admin/distributors/{id} | Update distributor |
| DELETE | /admin/distributors/{id} | Delete distributor |
| GET | /admin/licenses | List licenses |
| POST | /admin/licenses | Create license |
| GET | /admin/licenses/{id} | View license |
| GET | /admin/licenses/{id}/edit | Edit form |
| PUT | /admin/licenses/{id} | Update license |
| POST | /admin/licenses/{id}/revoke | Revoke license |
| GET | /admin/plans | List plans |
| POST | /admin/plans | Create plan |
| GET | /admin/plans/{id}/edit | Edit form |
| PUT | /admin/plans/{id} | Update plan |
| GET | /admin/tenants | List tenants |
| GET | /admin/tenants/{id} | View tenant |
| POST | /admin/tenants/{id}/suspend | Suspend tenant |
| POST | /admin/tenants/{id}/unsuspend | Unsuspend tenant |

---

## Usage

### Admin Access

```
URL: http://localhost:8080/admin
Email: admin@example.com
Password: password
```

### Creating a License

1. Log in to admin panel
2. Go to Distributors → Add Distributor (if needed)
3. Go to Licenses → Create License
4. Select distributor, plan, seats, expiration
5. Copy the generated license key

### Customer Registration

1. Go to http://localhost:8080/register
2. Enter the license key
3. Enter company name
4. Enter personal details
5. Submit to activate license and create tenant

### License Validation in Code

```php
// Check if tenant has valid license
$tenant->isLicenseValid();

// Check if tenant can add more users
$tenant->canAddUsers();

// Get available user slots
$tenant->availableUserSlots();

// Get tenant's plan
$tenant->plan();

// Check license status
$license->isValid();
$license->isExpired();
$license->isInGracePeriod();
$license->isFullyExpired();
```

---

## Testing

Run all tests:
```bash
docker exec ticketing-app php artisan test --compact
```

Run specific test files:
```bash
docker exec ticketing-app php artisan test --filter=PlanTest
docker exec ticketing-app php artisan test --filter=LicenseTest
docker exec ticketing-app php artisan test --filter=DistributorTest
docker exec ticketing-app php artisan test --filter=TenantLicenseTest
```

---

## Future Enhancements (Out of Scope)

- [ ] Ticketing functionality
- [ ] Usage tracking/enforcement
- [ ] License validation middleware (auto-suspension job)
- [ ] Distributor portal/UI
- [ ] API endpoints for distributors
- [ ] Grace period expiration notifications
- [ ] License renewal flow
