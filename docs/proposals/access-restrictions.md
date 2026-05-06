# Access restriction proposals

Captured from a discussion on 2026-05-05 about restricting *where* a tenant's users can sign in from. **None of these are implemented.** The team chose to ship single-active-session enforcement (see `app/Models/User.php::purgeOtherSessions`) as the v1 abuse-prevention control. This document preserves the alternatives so we don't have to redo the analysis if requirements change.

## Option A — GPS geofence (browser Geolocation API)

The tenant configures a center point (lat/lng) and a radius (default 500 m). User devices report their location; requests outside the fence are blocked.

**Components**
- Tenant config: `lat`, `lng`, `geofence_radius_meters` on `AppSetting`. Picker UI uses Leaflet+OpenStreetMap (no API key) or Google Maps (key required).
- Client capture: `navigator.geolocation.getCurrentPosition()` on login + every N minutes; POSTs `lat,lng,accuracy` to a heartbeat endpoint. Requires HTTPS in production.
- Server enforcement: middleware after `EnsureTenantSession` reads the latest cached heartbeat and computes haversine distance to the fence center; redirects to a "must be on-site" page if outside or stale.
- Audit: log every block to `activity_log` for the tenant owner to review.

**Tradeoff (the reason we didn't ship it)**
Browser GPS is **trivially spoofable** — devtools → Sensors → Custom Location fakes coords in seconds, and any "fake GPS" mobile app does the same. It's a soft policy guardrail, not a security control. Adds noticeable friction (permission prompts, possible drops in dense urban canyons / indoors / on desktops without GPS hardware that fall back to coarse IP-derived coords).

**When to revisit**
If the requirement is *compliance signaling* ("the system records that the user attested to being on-site") rather than enforcement, this is the right shape. Pair with the audit log so the *attempt* is the value, not the prevention.

## Option B — IP allowlist

The tenant configures a list of CIDR ranges. Requests from outside those ranges are rejected at the middleware layer.

**Components**
- Tenant config: `ip_allowlist` JSON array of CIDR strings on `AppSetting`. Empty = disabled. Validate entries with `Symfony\Component\HttpFoundation\IpUtils::checkIp()`.
- Middleware: `EnforceTenantIpAllowlist` registered after `EnsureTenantSession` in `routes/tenant.php`. Compares `$request->ip()` against the list with `IpUtils::checkIp`. Owner role bypasses (matching the convention used elsewhere for permission checks). On miss, redirect to `/{slug}/access-blocked` showing the rejected IP so the user can ask their admin to add it.
- Trust proxies: **must** configure `App\Http\Middleware\TrustProxies` (`$proxies = '*'` or specific reverse-proxy IPs) before turning enforcement on, otherwise `$request->ip()` returns the load balancer's IP and the rule degenerates. Verify with `request()->ip()` in tinker behind the production proxy.
- Lockout recovery: artisan command `tenant:clear-ip-allowlist {slug}` for when an admin saves a wrong CIDR. Optional "log-only" mode that writes to `activity_log` for a week before going enforcing — gives admins data on who *would have been* blocked.

**Tradeoff**
Excellent for tenants with a static commercial office IP or a corporate VPN gateway (VPN gives WFH coverage for free). Painful for tenants on consumer ISPs with dynamic IPs and effectively useless against motivated bypass once an attacker is on the office network or VPN. Also doesn't help on mobile carriers without allowlisting carrier-wide ranges (huge, shared with the rest of the country).

**When to revisit**
When at least one customer has a static office IP and asks for it. Cheap to ship per-tenant — about a day of work end-to-end including the lockout-recovery command.

## Option C — Combined geofence + IP allowlist

Require *both* checks to pass. Meaningful policy ("this account can only be used from the office, on a device that confirms it's at the office") that's harder to defeat than either alone.

**Tradeoff**
Doubles the setup friction (two configs, two failure modes, two recovery paths). Worth it only if a customer specifically asks for layered enforcement; otherwise pick whichever single option fits the customer's network shape.

## What we shipped instead — single active session

`User::purgeOtherSessions()` deletes all rows in the `sessions` table for the user except the current session ID, called on every successful login (password and Google OAuth). When a previously-logged-in device makes its next request, the session is gone, the auth middleware drops it back to the login page.

**Why this first**
- Solves the most common abuse pattern (one credential shared across multiple devices/people) with a fraction of the complexity of geofencing.
- Works regardless of network shape, no client-side cooperation needed, and unspoofable from the user side — they don't get to choose which session wins, the most recent login always does.
- Reversible: removing the call to `purgeOtherSessions()` reverts to multi-session behavior. No data migration.

**Limits**
- Does not restrict *where* a user can log in from, only that they can only have one active session at a time.
- A determined user can keep re-logging-in from different devices in turn (each login boots the prior). This is fine for "one person, many devices" but not a deterrent for credential sharing where people hand off in shifts.
- "Remember me" tokens / `remember_token` are out of scope for this v1 — a remembered cookie can re-establish a session even after a purge. If we want strict enforcement we should also rotate `remember_token` on every login.
