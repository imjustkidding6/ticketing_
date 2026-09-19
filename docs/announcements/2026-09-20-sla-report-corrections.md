---
title: SLA compliance figures corrected — your numbers may change
severity: update
status: draft
published_at: null
announcement_id: null
supersedes: null
release: null
---

We fixed several reporting bugs, and as a result your SLA compliance percentages may read differently from last week. The figures are now correct; the previous ones were not.

What changed and why:

- Response compliance could show a ticket as Met while printing response hours that exceeded the target beside it. The verdict was measured against a deadline that had been extended for on-hold time, while the hours shown were not. Both now use the same figure, so some tickets that read Met will now read Missed. If you use on-hold, expect your response compliance to fall.

- Closed tickets with no SLA policy were silently left out of the percentage, which made compliance look better than it was. They are still excluded, because no target was ever set for them, but the report now tells you how many were excluded so you can see what the number covers.

- Service reports judged SLA compliance slightly differently again. Some will flip from Met to Missed for the same reason as above.

- CSV exports printed times in UTC while the screen printed them in your workspace timezone, so an export and the page it came from disagreed. Exports now use your workspace timezone.

- The Export CSV buttons on report pages appeared for roles that were not permitted to use them and failed when clicked. They are now shown only to roles that can use them. If a button has disappeared for someone, they need the matching export permission.

No action is required. If you have shared SLA figures externally and need the previous numbers explained, contact support and we will help reconcile them.
