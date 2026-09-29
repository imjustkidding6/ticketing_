---
name: qa-reality-check
description: Runs a QA team that finds where this Laravel helpdesk actually behaves differently from how it is documented and intended — driving the real app in a browser and corroborating with database and log evidence, so every finding carries both a reproduction and the underlying rows. Use whenever someone asks what really happens versus what should happen, wants a feature verified end to end rather than unit tested, says something "seems broken" or "doesn't work like it should", asks for a regression sweep or pre-release check, wants to know if a behaviour is a bug or intended, or asks to test a workflow the way a real user would. Also use to verify a delivery-team branch before it ships. Prefer this over writing a one-off test when the question is about observed behaviour rather than a known expectation.
---

# QA reality-check team

Most testing asks "does the code do what the test says". This team asks a harder question: **what does the system actually do, and is that what it is supposed to do?**

Those come apart constantly. A tour step points at an element that only renders in one state. A report prints a row it excluded from its own query. An agent is refused the show page but can open `/edit`. None of these are logic errors — every unit test passes — and none are visible without running the real thing and then checking the data underneath.

The repo already contains the pattern this team extends. `tests/Feature/QaScenarioProbeTest.php` opens with:

> *QA EVIDENCE PROBES — these tests DOCUMENT CURRENT BEHAVIOUR, they are not assertions of desired behaviour. Several of them assert that a bug exists. Do not "fix" a probe; fix the code and then invert the probe.*

That is the mental model. A probe records reality. Whether reality is acceptable is a separate judgement, made explicitly.

## Where "should happen" comes from

A finding is only meaningful against a stated expectation, so never invent one. Cite it:

| Source | What it tells you |
|---|---|
| `CLAUDE.md` | The invariants — status lifecycle, tenant isolation, SLA and hold time, timezone rules. Sections marked CRITICAL are the highest-value targets. |
| `config/help.php` | 24 topics and 22 tours describing what each screen does for each role. The product's own promise to users. |
| `tests/Feature/*GuardrailsTest.php`, `RolePermissionMatrixTest`, `HelpPermissionSweepTest` | Expectations already encoded and enforced. |
| The acceptance criteria | When verifying a delivery-team branch. |

If you cannot cite a source, you have found a *question*, not a defect. Record it as one and say so — "the system does X, and nothing states whether X is intended" is a genuinely useful output.

## The four roles

Dispatch as subagents. Stages 2 and 3 can run together once scenarios exist.

| Stage | Subagent | Produces |
|---|---|---|
| 1. Author | `qa-scenario-author` | Scenarios: expectation + cited source + concrete steps |
| 2. Drive | `qa-browser-runner` | What the UI actually did — screenshots, console, network |
| 3. Corroborate | `qa-evidence-correlator` | The database and log state underneath it |
| 4. Report | `qa-reporter` | Findings with reproduction *and* data, severity-ranked |

The split between stages 2 and 3 is the point of the team. A screenshot alone produces "the page looked wrong", which nobody can act on. The database row alone produces "this column is null", which nobody can reproduce. Together they produce a finding an engineer can fix.

## Workspace

```
.claude/workspace/qa/<run-slug>/
├── scenarios.md    # expectation, source, steps — written by the author
├── observed.md     # what actually happened — browser runner
├── evidence.md     # DB/log state — correlator
├── findings.md     # the report
└── artifacts/      # screenshots, traces, console logs
```

## Running the app

The browser roles need a live instance at `http://localhost:8005`:

```bash
docker ps --format '{{.Names}}' | grep ticketing   # already up?
make up                                             # if not
npx playwright test tests/e2e/<spec> --reporter=list
```

Seeded credentials are `admin@example.com` / `password` (platform admin) and `test@example.com` / `password` (tenant user). Playwright runs headless, chromium, 1920x1080, with screenshots on failure.

For PHPUnit-level probes use the bundled script — the raw command fails every test on database auth because `phpunit.xml` pins credentials that do not match the container:

```bash
.claude/skills/delivery-team/scripts/run_tests.sh --filter=QaScenarioProbeTest
```

## Scenarios are data, not code

The author role writes scenarios as structured steps, and the runner interprets them. There is no browser tool in this project — only `laravel-boost` is configured in `.mcp.json` — so the runner drives Playwright through Bash, and that means it writes driver scripts. The rule is not "never generate a script", which would leave stage 2 with no way to run at all. The rule is that a driver script is **evidence, and is kept as evidence**:

- every script the runner writes is saved under the run's `artifacts/` directory, named for the scenario it drives (`artifacts/s3-driver.mjs`), and is never deleted or rewritten between runs — a finding is only reproducible if the exact thing that produced it still exists;
- nothing is written into `tests/e2e/` unless it is meant to be a committed, reviewable spec that survives the run, in which case it is code and is reviewed like code.

A script that lives for one invocation and then vanishes is what makes findings unreproducible, not the fact that an agent wrote it.

## Severity

Rank by what it costs, not by how it looks:

- **Critical** — cross-tenant data exposure, data loss, auth bypass. Stop and report immediately rather than finishing the sweep.
- **High** — a documented capability does not work, or produces wrong numbers a user would act on.
- **Medium** — wrong behaviour with a workaround, or a dead-end in the UI.
- **Low** — cosmetic, or a documentation mismatch where the code is right.

A documentation mismatch is a real finding. When `CLAUDE.md` says one thing and the code does another, one of them is wrong and both are worth knowing about — several such drifts have already been found this way.

## Filing

Findings land in `findings.md`. When one is confirmed and worth fixing, it can be promoted into the app's own `BugReport` queue at `/admin/bugs`, which feeds the existing `ai-fix` loop. Only promote confirmed findings with a reproduction — that queue is where humans triage, and filling it with unverified reports makes it useless.

## Reporting back

Lead with what is actually broken. Give the count of scenarios run and findings by severity, then the findings themselves worst-first, each with its cited expectation, what happened instead, and the evidence. If nothing was found, say so plainly and state what was covered — "18 scenarios across tickets and SLA, no findings" is a real result, and padding it with near-misses devalues the ones that matter.
