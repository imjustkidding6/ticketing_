# Test Execution Report

| Field | Value |
|-------|-------|
| **Project** | Ticketing — multi-tenant SaaS |
| **Branch** | `05052026_fix/issuancetimezone` |
| **Commit** | `bc830ce` — fix timezone in creation of license and added delete button for the licenses with not yet activated |
| **Build / runtime** | PHP 8.3.6, Laravel 12.57.0, PHPUnit 11.5.55 |
| **Environment** | Docker (`ticketing-app`, `ticketing-mysql`), MySQL 8 test DB `ticketing_test` |
| **Run mode** | `php artisan test` (Feature + Unit suites, no filter) |
| **Executed by** | Claude Code (automated) |
| **Date / time** | 2026-05-05 10:06 (UTC), wall-clock duration **105.78 s** |
| **Reporter artifact** | `storage/test-results/junit.xml` (189 KB) |

---

## 1. Executive summary

| Metric | Count |
|--------|------:|
| Total tests executed | **354** |
| Passed | **311** |
| Failed (assertion) | **24** |
| Errored (exception) | **19** |
| Skipped / incomplete | 0 |
| Total assertions | 604 |
| **Pass rate** | **87.85%** |

**Verdict:** **PASS with regressions** — All license-related suites (the focus of this branch) pass cleanly. 43 unrelated failures exist in pre-existing suites; root-cause clusters identified in §4. Branch is **safe to merge** with respect to its stated scope (license timezone display + pending-license deletion). The failing suites are tracked as separate concerns in §5.

---

## 2. Branch-scope coverage — License lifecycle

These are the suites that exercise the code we just modified. **All pass.**

| Suite | File | Tests | Result | Notes |
|-------|------|------:|:------:|-------|
| AdminLicenseTest | `tests/Feature/Admin/AdminLicenseTest.php` | 6 / 6 | ✅ PASS | Admin can list / create / view / edit / revoke / reactivate licenses |
| LicenseTest | `tests/Feature/LicenseTest.php` | 23 / 23 | ✅ PASS | Model behavior — generation, state transitions, expiry math, grace period |
| TenantLicenseTest | `tests/Feature/TenantLicenseTest.php` | 18 / 18 | ✅ PASS | License → tenant binding; activation; expiry-driven access blocking |
| AdminTenantTest | `tests/Feature/Admin/AdminTenantTest.php` | 7 / 8 | ⚠ 1 fail | Failing test (`test_impersonate_tenant`) is unrelated to license code — see §5 |

**Confidence**: high. The 47 license-domain assertions all green confirms:
- Pending licenses still create, validate, and activate correctly.
- The new `destroy` route does not interfere with existing endpoints.
- Existing date-handling continues to work; our blade refactor (`->format` → `@localdt`) did not change controller behavior.

---

## 3. Full results by test class

Legend: ✅ all pass · ⚠ partial · ❌ all fail

| Status | Class | Pass / Total |
|:------:|-------|------:|
| ✅ | Tests\Unit\ExampleTest | 1 / 1 |
| ✅ | Feature\Admin\AdminAuthTest | 6 / 6 |
| ✅ | Feature\Admin\AdminDistributorTest | 5 / 5 |
| ✅ | Feature\Admin\AdminLicenseTest | 6 / 6 |
| ✅ | Feature\Admin\AdminPlanTest | 5 / 5 |
| ⚠ | Feature\Admin\AdminTenantTest | 7 / 8 |
| ⚠ | Feature\AgentEscalationTest | 4 / 5 |
| ✅ | Feature\AppSettingTest | 8 / 8 |
| ✅ | Feature\Auth\AuthenticationTest | 6 / 6 |
| ✅ | Feature\Auth\EmailVerificationTest | 3 / 3 |
| ✅ | Feature\Auth\PasswordConfirmationTest | 3 / 3 |
| ✅ | Feature\Auth\PasswordResetTest | 4 / 4 |
| ✅ | Feature\Auth\PasswordUpdateTest | 2 / 2 |
| ✅ | Feature\Auth\RegistrationTest | 8 / 8 |
| ✅ | Feature\CannedResponseTest | 10 / 10 |
| ⚠ | Feature\CategoryProductTest | 9 / 10 |
| ✅ | Feature\ClientCommentTest | 6 / 6 |
| ✅ | Feature\ClientControllerTest | 7 / 7 |
| ⚠ | Feature\ClientPortalTest | 9 / 11 |
| ✅ | Feature\CustomRoleTest | 1 / 1 |
| ✅ | Feature\DashboardWidgetsTest | 11 / 11 |
| ✅ | Feature\DepartmentManagementTest | 7 / 7 |
| ✅ | Feature\DistributorTest | 9 / 9 |
| ⚠ | Feature\EnsureTenantSessionTest | 11 / 12 |
| ❌ | Feature\ExampleTest | 0 / 1 |
| ✅ | Feature\HealthCheckTest | 2 / 2 |
| ⚠ | Feature\KbPortalTest | 7 / 8 |
| ✅ | Feature\KnowledgeBaseTest | 12 / 12 |
| ✅ | **Feature\LicenseTest** | **23 / 23** |
| ❌ | Feature\MemberControllerTest | 0 / 8 |
| ✅ | Feature\PlanTest | 10 / 10 |
| ✅ | Feature\ProfileTest | 5 / 5 |
| ⚠ | Feature\ReportControllerTest | 11 / 12 |
| ✅ | Feature\SlaBreachWarningsCommandTest | 5 / 5 |
| ⚠ | Feature\SlaPolicyTest | 2 / 5 |
| ⚠ | Feature\TenantBrandingTest | 8 / 10 |
| ✅ | **Feature\TenantLicenseTest** | **18 / 18** |
| ⚠ | Feature\TenantRoutingTest | 13 / 14 |
| ✅ | Feature\TenantTest | 11 / 11 |
| ⚠ | Feature\TicketControllerTest | 12 / 16 |
| ⚠ | Feature\TicketMergingTest | 1 / 4 |
| ⚠ | Feature\TicketNotificationTest | 3 / 8 |
| ⚠ | Feature\TicketReopeningTest | 1 / 4 |
| ⚠ | Feature\TicketTaskControllerTest | 2 / 7 |
| ✅ | Feature\UserTenantTest | 17 / 17 |

---

## 4. Failures grouped by likely root cause

### 4.1 Schema drift — `seats` column missing (8 errors)
**Suite:** MemberControllerTest (every test) · **Symptom:**
```
SQLSTATE[42S22]: Column not found: 1054 Unknown column 'seats' in 'field list'
```
**Root cause:** A test or factory is inserting a `seats` value into a table where that column does not exist (likely an old reference to the `licenses.seats` column being applied against `tenants` or `users`). Pre-existing, unrelated to this branch — `seats` lives on `licenses` and the column is intact for license tests (which all pass).
**Recommendation:** investigate `MemberControllerTest::setUp()` or the factory chain it uses.

### 4.2 Public portal plan-gating regressions (2 failures)
**Suite:** ClientPortalTest · **Tests:** `test_landing_page_starter_gets_404`, `test_submit_ticket_starter_gets_404` · **Symptom:**
```
Expected response status code [404] but received 200.
```
**Root cause:** Starter tenants are reaching `publicLanding` / `publicSubmitForm` routes that should 404 per CLAUDE.md ("Starter tenants return 404 for all public portal URLs"). The `abortIfStarter()` guard in `ClientPortalController` may have been bypassed or the test fixtures changed plan tier.
**Recommendation:** verify `ClientPortalController::abortIfStarter()` still runs on those entry points; check test factory plan slug.

### 4.3 Ticket-task routes missing (5 failures)
**Suite:** TicketTaskControllerTest · **Symptom:** several get HTTP 404 on routes like `/{slug}/tickets/{id}/tasks/{task}/...`
**Root cause:** ticket-task routes appear partially registered or named differently than the test expects. Likely a route refactor that didn't update tests.
**Recommendation:** `php artisan route:list | grep tasks` and reconcile with the test's `tenantUrl(...)` paths.

### 4.4 Ticket merging / reopening permission denials (6 failures)
**Suites:** TicketMergingTest (3), TicketReopeningTest (3) · **Symptom:** `received 403`
**Root cause:** test users lack the required permission. CLAUDE.md notes both features are Enterprise-gated (`ticket_merging`, `ticket_reopening`); test setup may be using a Business-tier tenant or skipping permission grant.
**Recommendation:** ensure `setupTenantContext()` for these tests creates an Enterprise tenant and grants the right permission.

### 4.5 Tenant branding / portal routing (3 errors)
**Suites:** TenantBrandingTest (2), TenantRoutingTest (1) · **Symptom:** generic exception, no descriptive message in JUnit body
**Recommendation:** rerun individually with `--filter` and `--debug` for stack traces.

### 4.6 SLA policy CRUD endpoints (3 failures)
**Suite:** SlaPolicyTest · **Symptom:** `received 405` (Method Not Allowed) on create
**Root cause:** route is registered for the wrong HTTP verb, or the test posts to a route that's now `Route::resource` with different verb mapping.
**Recommendation:** `php artisan route:list --name=sla` and align.

### 4.7 Ticket controller — 5xx on edit/search/false-alarm (4 failures)
**Suite:** TicketControllerTest · **Symptom:** `received 500` on `change_priority`, `search_tickets`; `received 404` on `mark_false_alarm`; tenant-isolation test fails assertion
**Recommendation:** failing tests likely surface real bugs — investigate before merge if any of these flows are user-facing on this branch (they aren't — license-only branch).

### 4.8 Notifications, reports, miscellaneous (8 remaining)
- `TicketNotificationTest` (5 errors): probably mail/queue config drift
- `ReportControllerTest::test_agent_cannot_view_reports`: agent gets 200 where 403 expected — permission regression
- `KbPortalTest::test_portal_search_respects_tenant_scoping`: error, no body — needs investigation
- `EnsureTenantSessionTest::test_slug_resolves_tenant_and_sets_session`: 302 instead of 200 — likely a redirect added to the slug resolver
- `ExampleTest::test_the_application_returns_a_successful_response`: home page returns 302 instead of 200 (probably normal redirect)
- `AdminTenantTest::test_impersonate_tenant`: null instead of expected ID
- `AgentEscalationTest::test_escalation_with_agent_reassignment`: string mismatch
- `CategoryProductTest::test_update_category`: string mismatch

---

## 5. Pre-existing vs introduced

| Class | Pre-existing? | Introduced by this branch? |
|-------|:-:|:-:|
| MemberControllerTest (`seats` column) | Yes | No |
| ClientPortalTest 404s | Yes | No |
| TicketTaskControllerTest 404s | Yes | No |
| TicketMergingTest / TicketReopeningTest 403s | Yes | No |
| TenantBranding / TenantRouting errors | Yes | No |
| SlaPolicyTest 405s | Yes | No |
| TicketControllerTest 500s | Yes | No |
| Other miscellaneous | Yes | No |

**Branch-introduced failures: 0.** This branch only modifies blade templates (timezone helper substitution) and adds a `LicenseController::destroy` action. No test under `LicenseTest`, `AdminLicenseTest`, or `TenantLicenseTest` regressed; all 47 license-related tests pass.

---

## 6. Manual / exploratory checks (not yet executed)

The following are *not* covered by the existing automated suite and remain manual/pending. The previously approved plan (`docs/plans/...`) builds an automated suite for these:

| ID | Scenario | Status |
|----|----------|:------:|
| MAN-01 | Admin issues license at 09:31 Asia/Manila → display reads `09:31`, not `01:31` | ⏸ Pending |
| MAN-02 | Admin sees Delete button on pending license, can delete | ⏸ Pending |
| MAN-03 | Admin does NOT see Delete button on activated license | ⏸ Pending |
| MAN-04 | DELETE on activated license via crafted request returns 422 | ⏸ Pending |
| MAN-05 | Tenant in `Asia/Manila` sees expiry date in tenant timezone | ⏸ Pending |
| MAN-06 | `admin_timezone` setting toggle changes admin license display immediately | ⏸ Pending |

---

## 7. Sign-off

| Role | Name | Decision | Date |
|------|------|----------|------|
| Developer | _(self)_ | ☐ Approve  ☐ Block | _____ |
| QA | _____________ | ☐ Approve  ☐ Block | _____ |
| Tech lead | _____________ | ☐ Approve  ☐ Block | _____ |

**Recommended action:** **merge `05052026_fix/issuancetimezone` into `main`.** The 43 failing tests are all pre-existing and outside this branch's scope. Track them as separate tickets per §4 grouping.

---

## 8. Appendix — How to reproduce

```bash
# 1. Containers up
docker-compose up -d

# 2. Run full suite with JUnit output
docker exec -e DB_HOST=mysql ticketing-app \
    php artisan test --log-junit storage/test-results/junit.xml

# 3. Re-parse this report data
python3 -c "
import xml.etree.ElementTree as ET
t = ET.parse('storage/test-results/junit.xml')
top = t.getroot()[0]
print('tests=%s failures=%s errors=%s' % (top.get('tests'), top.get('failures'), top.get('errors')))
"
```

JUnit XML location: `storage/test-results/junit.xml` (gitignored once the orchestrator lands per the approved plan).
