---
name: delivery-team
description: Runs a cross-functional agent team (Business Analyst, Architect, Developer, Reviewer, QA, Release Manager) to take a feature requirement from a one-line ask to a reviewed, tested branch in this Laravel helpdesk repo. Use this whenever someone describes a feature, change or enhancement they want built rather than asking a specific coding question — phrases like "we need X", "clients should be able to Y", "add a way to Z", "build me", "I want the system to", or when they hand over requirements, a spec, a customer request or a feature idea and expect it implemented. Also use when asked to research how other products solve a problem before building it, or when someone asks for "the team" to pick something up. Prefer this over ad-hoc implementation for anything that touches more than one file or needs a design decision, because the roles catch the tenant-isolation, SLA and plan-gating invariants that a single pass reliably misses.
---

# Delivery team

A requirement arrives as prose. Six roles turn it into a branch: research it, design it for *this* codebase, build it, review it, prove it, ship it.

The value is not the division of labour for its own sake. It is that each role asks a question the others systematically skip. A developer handed "let clients rate a resolved ticket" writes the migration and the controller and never asks whether a Starter tenant should see the widget, what happens when the ticket reopens, or whether the rating query is tenant-scoped. The analyst and architect exist to make those questions unskippable.

## The workspace is how roles talk to each other

Everything lives in `.claude/workspace/features/<slug>/`. Create it at intake:

```
.claude/workspace/features/<slug>/
├── workspace.md      # the shared document every role reads first and appends to
└── messages.jsonl    # one line per handoff, question, answer, objection, approval
```

Roles never talk to each other directly. They read `workspace.md`, do their work, append their section, and write a message. This matters for two reasons: a subagent starts with no memory of what the others did, so the file *is* the shared mind; and when the result is wrong, the file shows exactly which role's reasoning was wrong.

Append to `messages.jsonl` on every transition:

```json
{"from":"analyst","to":"architect","type":"handoff","summary":"Acceptance criteria written, 3 open questions flagged","at":"2026-09-18T10:12:00Z"}
```

`type` is one of `handoff`, `question`, `answer`, `objection`, `approval`, `escalation`.

## Running the team

Dispatch each role as a subagent using the Agent tool, passing the workspace path. Run them in sequence — each depends on the last one's output. Read `workspace.md` yourself between stages so you can judge whether a stage actually finished or just claimed to.

| Stage | Subagent | Leaves behind |
|---|---|---|
| 1. Analysis | `delivery-analyst` | Problem statement, acceptance criteria, non-goals, external research |
| 2. Design | `delivery-architect` | Where it lives in this codebase, which invariants apply, migration sketch |
| 3. Build | `delivery-developer` | Working-tree changes on a branch, tests written |
| 4. Review | `delivery-reviewer` | Approval, or an objection that sends it back |
| 5. Proof | the `qa-reality-check` skill | Evidence each acceptance criterion is actually met in the running app |
| 6. Ship | `delivery-release` | Pint, full suite, commit, branch pushed / PR opened |

Stage 5 is not a subagent of this team — invoke the `qa-reality-check` skill and give it the acceptance criteria as its scope. That team drives the real app and corroborates with the database, which is a stronger claim than "the tests pass". Point it at this workspace so its findings land beside the design they are judging.

Skip stages only with a stated reason written into `workspace.md`. A one-line copy change does not need an architect; a new database table does.

## Intake

Before dispatching anyone, write `workspace.md` with the requirement verbatim, and pick a slug. Do not paraphrase the requirement — the analyst needs the user's actual words, including the ambiguous parts, because the ambiguity is what it is being asked to resolve.

```markdown
# <Title>

**Requested:** <date>
**Slug:** <slug>
**Status:** analysis

## Requirement (verbatim)
> <exactly what the user asked for>

## Acceptance criteria
_pending — analyst_

## Design
_pending — architect_

## Build notes
_pending — developer_

## Review
_pending — reviewer_

## QA evidence
_pending — qa_

## Decision log
| When | Who | Decision | Why |
|---|---|---|---|
```

The decision log is for the irreversible calls only — scope cut, design chosen, objection overruled. Keeping it separate from the chatter means someone reading this in six months finds the three things that mattered instead of forty messages.

## Questions and objections

A role that cannot proceed without an answer writes a `question` message naming the role it needs, and stops. You route it: dispatch that role with the question, get an `answer`, then re-dispatch the asker with the answer in its prompt. This is what makes it a team rather than a pipeline — the developer should ask "what happens if the ticket reopens after it was rated?" instead of guessing, and guessing is what happens if there is no channel for the question.

The reviewer and QA can send an `objection`, which moves the work backwards a stage. **Cap this at three round-trips per stage.** After the third, write an `escalation` message and stop, presenting the disagreement to the human. Two agents will otherwise refine each other's work until the context window runs out, and the third exchange is almost always the point where a human would have settled it in one sentence.

## What must not happen without a human

**Destructive migrations.** Any migration that drops or renames a column or table, or backfills data, stops the team. Run the bundled check before any commit and before any deploy:

```bash
python3 .claude/skills/delivery-team/scripts/check_migrations.py
```

It exits non-zero on a blocking finding. When it does, write an `escalation` message and present the findings — do not reason about whether this particular drop is safe. The gate is valuable precisely because it is not a judgement call; a model asked to be careful about data loss is not a control, an exit code is.

**Deploying.** The team produces a branch. Merging to `main` triggers the ECS deploy, so a human merges.

## Repo facts every role needs

Read `CLAUDE.md` first — it is long but it is the invariants, and most defects in this codebase are invariant violations rather than logic errors. The ones that bite hardest:

- **Tenant isolation.** `User` is *not* auto-scoped; every user query needs an explicit `whereHas('tenants', ...)`. Validation rules are not tenant-scoped either — use the `ScopesRulesToTenant` trait for any foreign key from a request.
- **Ticket status.** There is no `resolved` status. Never write `status` directly; go through `TicketService::changeStatus()`.
- **Plan gating.** 19 features across three tiers; `PlanFeature::forPlan()` matches the slug `start`, not `starter`.
- **Timezones.** Never `whereDate(..., today())` — use `App\Support\TenantTime`.
- **Permissions.** Every permission must be enforced somewhere or `RolePermissionMatrixTest` fails the build.

Running tests: use the bundled script, because the repo's `phpunit.xml` pins credentials that do not match the local container and the raw command fails every test on auth.

```bash
.claude/skills/delivery-team/scripts/run_tests.sh                    # full suite
.claude/skills/delivery-team/scripts/run_tests.sh --filter=TicketTest # one class
```

Formatting is `vendor/bin/pint --dirty --format agent` and it runs before any commit, because CI fails on unformatted code.

## Reporting back

When the team finishes or escalates, summarise in chat: what was built, which acceptance criteria are met, what the test run said, and anything the team decided that the human might disagree with. Link the workspace file rather than pasting it. If the team escalated, lead with the disagreement and what you need from them — that is the only thing they have to act on.
