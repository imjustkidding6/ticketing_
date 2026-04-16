# CliqueHA TechDesk — PRD (Startup SaaS Ticketing on AWS)

## 1. Executive Summary
CliqueHA TechDesk is a multi-tenant SaaS ticketing system built with Laravel 12, designed for SMEs that need fast, reliable support operations. It provides isolated tenant workspaces, an admin console for global management, and a public client portal. This PRD consolidates project documentation into a startup-oriented direction that emphasizes MVP scope, cost efficiency, and phased growth on AWS.

## 2. Vision & Goals
**Vision:** Provide an affordable, enterprise-ready helpdesk platform for SMEs with strong tenant isolation, modern workflows, and a clear upgrade path.

**Startup goals (MVP):**
- Launch a reliable multi-tenant ticketing platform with admin console + public portal.
- Enforce tiered feature access to drive upgrades.
- Keep infrastructure cost-efficient while maintaining availability and security.
- Deliver measurable traction via activation, retention, and ticket throughput.

## 3. Target Users & Personas
- **Global Admin:** Manages distributors, licenses, plans, and tenants.
- **Tenant Owner/Admin:** Manages organization settings, users, and advanced features.
- **Manager/Agent:** Handles tickets, SLAs, reporting, and escalations.
- **Client (End User):** Submits and tracks tickets via public portal.
- **Distributor (Reseller):** Issues licenses to customers.

## 4. Success Metrics (Startup-Oriented)
- **Activation:** % of tenants creating their first ticket within 24 hours.
- **Retention:** 30/60/90-day active tenant rates.
- **Engagement:** Tickets per tenant per month; avg first response time.
- **Upgrade Rate:** Starter → Business → Enterprise conversion.
- **Operational:** SLA compliance rate; platform uptime.

## 5. Scope & Non-Goals
**In scope (MVP):**
- Multi-tenant ticketing with admin console and public portal.
- Tiered feature gating with plan limits.
- Core workflows: tickets, tasks, SLAs, reporting, attachments.

**Out of scope (for MVP):**
- Mobile apps.
- Public API (planned).
- Advanced AI automation (planned, see AI strategy).

## 6. Plans, Limits, and Feature Tiers (Confirmed)
**Agent/Admin user limits:**
- **Starter:** 5
- **Business:** 10
- **Enterprise:** 20

**Customization:**
- Starter/Business: Not available
- Enterprise: Limited (see section 12)

**Integrations:**
- Starter/Business: Not available
- Enterprise: Limited (see section 12)

### Starter Features
- Admin Dashboard
- Personal Dashboard
- Ticket Creation
- Ticket Task Creation
- Product/Services Management
- Category Management
- User Roles (Admin, Manager, Agents)
- Fixed Departments
- Basic Reporting + Export
- Public Client Portal (landing, submit, track)

### Business Features
All Starter features, plus:
- Ticket Activity History (Audit Logs)
- Billing
- Mark as Spam
- Auto-Generated Service Reports
- Attachments
- Agent Availability Schedule + Dashboard
- SLA Management
- SLA Compliance Report
- Email Notifications
- Detailed Reporting + Export
- Knowledge Base
- Canned Responses

### Enterprise Features
All Business features, plus:
- Ticket Merging
- Ticket Re-Opening
- Custom Roles & Permissions
- Department Management
- Agent Tiering & Escalation
- Client ↔ Agent Comments & Updates

## 7. Core User Journeys
**Tenant onboarding**
1. Distributor issues license.
2. Tenant registers with license key.
3. Tenant created; owner assigned; plan limits enforced.

**Ticket lifecycle**
Create → Assign → In Progress → On Hold → Closed/Cancelled, with history and SLA tracking.

**Client portal**
Guest submits ticket → receives tracking link → tracks status by token → replies (Enterprise).

**Admin console**
Manage distributors/licenses/plans/tenants, suspend/unsuspend tenants, impersonate tenant.

## 8. System Architecture (Current State)
- **Backend:** Laravel 12
- **Frontend:** Tailwind CSS v4, Alpine.js, Blade
- **Multi-tenancy:** Custom; `BelongsToTenant` + `TenantScope` with session-based tenant resolution
- **Feature Gating:** `PlanFeature` enum + middleware + Blade checks
- **Permissions:** Spatie Permission (teams mode, `tenant_id`)
- **Email:** Per-tenant SMTP; queued delivery for production

## 9. AWS Deployment Target (Startup-Ready)
**Core services:**
- **ALB + CloudFront**
- **ECS (web + worker)**
- **RDS MySQL (Multi-AZ)**
- **ElastiCache Redis**
- **S3 (attachments, reports, exports)**
- **SQS (queues)**
- **SES (emails)**
- **SSM Parameter Store**
- **CloudWatch + SNS**

**Scaling & reliability:**
- ECS auto-scaling on CPU/memory
- Health checks (`/up` or `/api/health`)
- Redis for cache/session
- RDS backups + encryption

## 10. Security & Compliance
- Tenant isolation via `tenant_id` scoping.
- RBAC enforced via Spatie teams mode.
- Public portal uses tokenized tracking links.
- Encryption at rest/in transit in AWS.

## 11. Non-Functional Requirements
- **Availability:** 99.5% target for MVP
- **Performance:** < 2s page load; ticket ops < 500ms server response
- **Scalability:** ECS auto-scaling; MySQL read replicas as needed
- **Recovery:** 30-day backups; infra-as-code recovery path

## 12. Enterprise “Limited” Customizations & Integrations
**Allowed customizations (review-required):**
- Enhanced branding beyond logo/colors
- Limited custom ticket fields
- Custom email templates and SLA thresholds
- Additional dashboard widgets (limited scope)

**Allowed integrations (review-required):**
- Email (SMTP/SES)
- SSO (SAML or OIDC)
- Webhooks for ticket events
- CRM sync (read-only)
- Chat tools (Slack/Teams notifications)
- Analytics export (CSV or warehouse sync)

## 13. Lean Roadmap
1. **Phase 1 (Core MVP):** Multi-tenancy, tickets, admin console, portal.
2. **Phase 2:** Email notifications + SLA enforcement.
3. **Phase 3:** Knowledge base + canned responses.
4. **Phase 4:** Real-time dashboards + reporting upgrades.
5. **Phase 5:** API + integrations.
6. **Phase 6:** Advanced automation/AI (per AI strategy).

## 14. Open Items (For Future Decisions)
- Pricing model (PHP vs USD, tier pricing)
- Usage-based overages vs fixed limits
- API & integration monetization tiers
