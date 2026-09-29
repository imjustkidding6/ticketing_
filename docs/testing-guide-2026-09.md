# Manual testing guide — the September changes

For verifying by hand what the four unmerged branches actually do. Written for
someone clicking through the app, not for CI.

**As of:** 2026-09-29

---

## 0. The awkward bit first: four branches

Each change is on its own branch and **none is merged**, so there is no single
checkout where you can see all four:

| Branch | What it changes |
|---|---|
| `feat/msp-client-contracts` | New feature: client contracts + token balance |
| `fix/member-permission-gap` | Security: member directory was readable by anyone |
| `fix/portal-soft-delete-leak` | Security: deleted tickets stayed on the public portal |
| `fix/e2e-suite-repair` | The browser test suite runs again |

**Two ways to go about it.**

*Branch by branch* — switch, test that one, move on. Slower, but each result is
unambiguous.

```bash
git checkout feat/msp-client-contracts    # then follow §2
```

*One combined branch* — ask Claude to merge all four into a throwaway
`test/september-integration`, test everything in one pass, then delete it. Faster,
and it also tells you whether the four merge cleanly, which you need to know
anyway. Nothing is pushed and `dev_intern` is untouched.

**Whichever you pick, the containers serve the working tree**, so the branch you
have checked out *is* what `localhost:8005` shows.

### Before you start

```bash
docker ps --format '{{.Names}}' | grep ticketing     # up?
make up                                               # if not
curl -s -o /dev/null -w '%{http_code}\n' http://localhost:8005/health   # expect 200
```

### Accounts you will need

One workspace already has one user per role, created during QA. Password is
`password` for all of them, on workspace **`qa-contracts-a`** (Business plan):

| Login | Role |
|---|---|
| `qa.admin@a.test` | admin |
| `qa.manager@a.test` | manager |
| `qa.supervisor@a.test` | supervisor |
| `qa.senioragent@a.test` | senior agent |
| `qa.agent@a.test` | agent |
| `qa.viewer@a.test` | viewer |
| `qa.billing@a.test` | billing |

Also `admin@example.com` / `password` (platform admin) and `test@example.com` /
`password` (belongs to two workspaces — see §5).

**None of these workspaces has AI enabled.** `qa-contracts-a` and `-b` are on the
Business plan and `ai_chatbot` is Enterprise-only, so they cannot reach an AI route
at all; `qa-contracts-ent` has the feature but all three AI switches default to off.
Nothing in §2–§4 needs AI. See §4b if you want it.

---

## 1. A warning that will otherwise cost you an afternoon

**The contracts feature is invisible until you switch it on.** Not broken —
invisible. Every contract screen 404s, the sidebar shows nothing, and the five new
permissions are granted to nobody. QA hit exactly this and nearly filed a pile of
false bugs.

It needs four steps in order, and step 2 is the one people miss.

---

## 2. Testing W1.1 — client contracts

**Branch:** `feat/msp-client-contracts`

### Unlock it first

```bash
# 1. create the table
docker exec ticketing-app php artisan migrate --force

# 2. add the new feature to the stored plan JSON (business AND enterprise)
docker exec ticketing-app php artisan tinker --execute="
foreach (['business','enterprise'] as \$s) {
    \$p = App\Models\Plan::where('slug',\$s)->first();
    \$p->features = App\Enums\PlanFeature::forPlan(\$s);
    \$p->save();
    echo \$s.' -> '.count(\$p->features).' features'.PHP_EOL;
}"

# 3. clear the 300-second feature cache for every workspace
docker exec ticketing-app php artisan tinker --execute="
foreach (App\Models\Tenant::withoutGlobalScopes()->get() as \$t) {
    app(App\Services\PlanService::class)->clearCache(\$t);
}
echo 'cleared';"

# 4. grant the new permissions to the seeded roles
docker exec ticketing-app php artisan roles:migrate-permissions
```

Step 3 uses `app(PlanService::class)->clearCache($tenant)` — an **instance**
method. The static form `PlanService::clearCache()` looks right and fatals.

### Then check

Sign in as `qa.admin@a.test` on `qa-contracts-a`.

| # | Do this | Expect |
|---|---|---|
| 1 | Look at the sidebar | A **Contracts** entry |
| 2 | Open a client, scroll the detail page | A **Contract** card |
| 3 | Go to Contracts → add one: plan `BASIC`, value `25000.50`, allowance `10`, period monthly, term `2026-01-01`–`2026-12-31` | Saves; reopening shows all six values unchanged |
| 4 | Look at how the value renders | `₱25,000.50` — not `25000.5`, not `25,000` |
| 5 | Set an allowance of `2.5` | Accepted. Half-tokens are deliberate — 42 minutes is not a whole anything |
| 6 | Set value `0` | Accepted (bundled clients). `-1` is rejected on the field |
| 7 | Set term end *before* term start | Rejected **on the end-date field** |
| 8 | Add a second contract overlapping the first, same client | Rejected — but see the caveat below |
| 9 | Look at the token balance | `10 / 10`. Always 10 remaining — the ledger does not exist yet, so consumption is deliberately zero |
| 10 | Open an **expired** contract | Shows its *final* in-term period, with a caption saying so. Deliberate, not a bug |

### Permission behaviour

| Sign in as | Expect |
|---|---|
| `qa.supervisor@a.test` | Sees contracts, **View** only — no Add, no Edit, no Delete links |
| `qa.viewer@a.test` | **No** Contract card on the client page. Sees a token count where one is shown, never the pesos |
| `qa.billing@a.test` | Full access — it is the finance-facing role |
| `qa.agent@a.test` | Cannot open the client directory at all (by design, unrelated to contracts) |

### Plan gating

Switch a workspace to the Starter plan and every contract URL should return a real
**403** page. `e2e-starter` is already on Starter if the E2E fixtures are seeded (§5).

### Two things that are known and deliberate

- **The overlap rule has a race.** MySQL has no exclusion constraint, so two
  genuinely simultaneous submits could still create overlapping contracts. QA
  never managed to exercise it and three roles have said so rather than let it read
  as tested. Step 8 tests the ordinary case, which does work.
- **The delete dialog is lying.** It says *"Its history stays in the activity
  log."* It does not — the log records `ClientContract #4 — Deleted` and nothing
  else: no client, no value, no dates. Contracts are hard-deleted, so that is all
  there will ever be. Known, filed, and the fix is queued.

---

## 3. Testing the member-directory fix

**Branch:** `fix/member-permission-gap`

This one is best tested by confirming something is now *refused*.

1. Sign in as `qa.agent@a.test`.
2. Confirm there is **no** Members entry in the sidebar. (That part was always right.)
3. Now type the URLs directly:

| URL | Before the fix | After |
|---|---|---|
| `/qa-contracts-a/members` | 200 — full staff directory | **403** |
| `/qa-contracts-a/members/17` | 200 | **403** |
| `/qa-contracts-a/members/17/edit` | 200, form pre-filled | **403** |
| `/qa-contracts-a/members/create` | 200 | **403** |

4. Repeat as `qa.viewer@a.test` — same.
5. Now as `qa.manager@a.test` — all four should still work normally.

**What was exposed:** every colleague's name, email, role, departments and ticket
counts, plus the edit form loaded with their role assignment. Writes were always
refused, so nothing could be changed — what failed was confidentiality.

---

## 4. Testing the portal soft-delete fix

**Branch:** `fix/portal-soft-delete-leak`

The clearest test in the set, because you can see the old behaviour.

1. As `qa.manager@a.test`, open any ticket and note its tracking token — or grab one:

```bash
docker exec ticketing-app php artisan tinker --execute="
\$t = App\Models\Ticket::withoutGlobalScopes()
        ->whereHas('tenant', fn(\$q) => \$q->where('slug','qa-contracts-a'))
        ->whereNotNull('tracking_token')->first();
echo 'http://localhost:8005/qa-contracts-a/track-ticket/'.\$t->tracking_token.PHP_EOL;
echo 'ticket: '.\$t->ticket_number.PHP_EOL;"
```

2. Open that URL in a **private window** (it is public, no login).  Expect the ticket.
3. Now delete the ticket from the app as a manager.
4. Reload the tracking URL.

**Expect a 404**, with a page byte-identical to a made-up token's. That sameness is
deliberate: a distinct "this ticket was removed" message would tell anyone guessing
tokens that a ticket had existed.

Also check, still in the private window: the reply form is gone, and the ticket no
longer appears on a signed-in client's portal dashboard or ticket list.

**If the ticket is still readable, the fix is not on the branch you have checked out.**

### 4b. The AI half of this fix — optional, and not testable as things stand

The same leak existed in the portal AI chat tool: asking the bot about a deleted
ticket by number and email returned its subject, status **and a working tracking
link**, to an anonymous visitor. That is fixed, and it is covered by automated tests.

**You cannot verify it by hand on any existing workspace**, because none has AI
switched on:

| Workspace | Plan | `ai_chatbot` in plan | AI settings |
|---|---|---|---|
| `qa-contracts-a`, `qa-contracts-b` | business | **no** — Enterprise-only feature | none |
| `qa-contracts-starter` | start | **no** | none |
| `qa-contracts-ent` | enterprise | yes | **none — all default to off** |

So on the Business workspaces the `feature:ai_chatbot` middleware 403s before
anything else is consulted, and on the Enterprise one all three switches are off.

**Skip this section unless you specifically want to see it.** The fix is real and
tested either way.

#### If you do want to test it

⚠️ **`OPENAI_API_KEY` is configured in this environment.** Enabling AI means real
calls and real cost. Confirm that key has been rotated before you switch anything
on — it was exposed in a chat transcript at one point.

```bash
docker exec ticketing-app php artisan tinker --execute="
\$t = App\Models\Tenant::withoutGlobalScopes()->where('slug','qa-contracts-ent')->first();

// AppSetting::set() reads session('current_tenant_id') and returns SILENTLY if it
// is missing — in tinker there is no session, so without this line the next three
// calls do nothing and report no error.
session(['current_tenant_id' => \$t->id]);

App\Models\AppSetting::set('ai_enabled', true, 'boolean', 'ai');
App\Models\AppSetting::set('ai_portal_widget_enabled', true, 'boolean', 'ai');
echo 'ai_enabled=' . App\Models\AppSetting::get('ai_enabled') . PHP_EOL;"
```

Then, on `qa-contracts-ent`: create a ticket through the public submit form, note the
ticket number and the email you used, delete the ticket as an agent, and ask the
portal chat widget about it. **Expect "I could not find a ticket matching that number
and email."**

Switch it back off afterwards with the same commands and `false`, so the workspace
does not keep making paid calls.

---

## 5. Testing the browser suite

**Branch:** `fix/e2e-suite-repair`

```bash
# one-off: create the three plan-tier test workspaces
docker exec ticketing-app php artisan db:seed --class=E2eFixtureSeeder

npx playwright test --reporter=list
```

**Expect `94 passed, 1 failed` in about 7 minutes.** Before this branch it was
`71 failed, 24 passed` in 40.

The one failure is `ai-chart-types`, and it is expected: it asserts against a chart
renderer ApexCharts replaced. Finishing it needs a hook added to the assistant
widget, which is product code that branch deliberately left alone.

**Do not pipe the command through `tail`** — the exit code then comes from `tail`
and a broken suite reports as green. That mistake was made during this work.

The seeder creates `e2e-starter`, `e2e-business` and `e2e-enterprise`, one per plan
tier, and is safe to re-run.

---

## 6. What is *not* fixed, so you do not report it twice

| Symptom | Status |
|---|---|
| Client detail page: **Quick-submit link** panel dead, 13 console errors | Real, live since 3 September. A `data-tour` attribute pasted inside an `x-data` value. Ticketed |
| `/settings/general` etc: **Save Settings** visible to a manager, then 403 | Real. Five screens across four modules render controls that always refuse. Queued |
| Deleting a client, product, department or SLA policy records only an id | Real. 11 models, log has no detail. Queued |
| Auto-close emails a customer about a deleted ticket | Real. Queued |
| A licence can expire the instant it is activated | Real, one live row. Affects seeder paths, **not** customer signup. Queued |

---

## 7. If something looks wrong

Worth checking before filing:

- **Are you on the right branch?** `git branch --show-current`. The containers serve
  the working tree.
- **Did you run the four unlock steps?** Contracts 404 everywhere without them.
- **Did you clear the plan cache?** It holds for 300 seconds, so a plan change looks
  ignored for five minutes.
- **Settings not saving?** `AppSetting` caches 300s too and is only invalidated by
  `AppSetting::set()` — writing a row directly in tinker leaves the cache stale. That
  produced a false pass during QA.
- **`AppSetting::set()` in tinker doing nothing at all?** It reads
  `session('current_tenant_id')` and **returns silently** when that is absent, which it
  always is in tinker. Set the session first (§4b shows it) or the call is a no-op with
  no error.

When you do file something, say which branch you were on and which account you used.
Half the confusion in this round came from results that were correct for a different
branch.
