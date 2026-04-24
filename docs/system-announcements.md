# System Announcement Formatting

How to write system announcements so they render correctly in the notification bell and admin list.

## Fields

| Field | Type | Limit | Notes |
|-------|------|-------|-------|
| `title` | plain text | 255 chars | Shown as the bold headline in the bell and in the admin table. Keep it scannable. |
| `body` | plain text | 5000 chars | Main message. Newlines are preserved; see rendering rules below. |
| `severity` | enum | — | One of `info`, `update`, `maintenance`, `warning`. Drives color and icon. |

Published announcements are broadcast to **every user in the system** at creation time and stored in each user's notification bell. Deleting an announcement revokes it from every bell.

## Rendering rules

The body is treated as plain text everywhere it is displayed:

- **HTML is escaped.** `<b>bold</b>` renders as literal `<b>bold</b>`. Do not use HTML tags.
- **Markdown is not parsed.** `**bold**`, `_italic_`, `# heading`, `[link](url)` all render as literal characters. Do not use Markdown syntax.
- **Newlines are preserved** in the notification bell (via `whitespace-pre-line`). Use blank lines between paragraphs and leading dots/dashes for lists.
- **URLs are not clickable.** Write the bare URL; users will copy it. Prefer short paths.
- **Admin list preview is 2 lines** (`line-clamp-2`). Put the most important sentence first so the preview is useful.

## Severity guide

Pick the lowest severity that still fits the message. Overusing `warning` dilutes it.

| Severity | Use for | Bell color |
|----------|---------|------------|
| `info` | General news, tips, non-blocking updates | Blue |
| `update` | Feature releases, improvements, bug fixes shipped | Emerald |
| `maintenance` | Planned downtime, deployments, degraded windows | Amber |
| `warning` | Incidents, data issues, required user action | Red |

## Structure

A good announcement has three parts in this order:

1. **What** — one sentence that stands alone as the preview.
2. **Details** — the bullets or short paragraphs with specifics.
3. **What to do** — any action the reader needs to take, or an explicit "No action required."

## Length

- **Title:** aim for under 70 characters. It must communicate the gist on its own.
- **Body:** aim for under 600 characters. The hard cap is 5000, but long text gets skimmed and missed. Link out or open a ticket for deep detail.

## Tone

- Write in the first person plural ("we're rolling out…", "we'll be deploying…").
- Be specific about dates and times. Include the timezone — announcements are seen by users in every tenant.
- Never use placeholders like `{{DATE}}` in the final published copy. Replace them before saving.

## Examples

### `update` — feature release

**Title:** Platform update — activity logs, ticket deletion, spam folder

**Body:**
```
We're rolling out a platform update with the following improvements:

- Activity logs now capture all create, update, and delete actions.
- Tickets can be permanently deleted by authorized users.
- New Spam folder: review and delete spam; spam is excluded from report counts.
- Admins can broadcast system announcements and manage global settings.

No action is required. If anything looks off, please open a ticket.
```

### `maintenance` — planned downtime

**Title:** Scheduled maintenance — Friday Apr 25, 10:00 PM PHT (up to 10 min)

**Body:**
```
We will deploy a platform update on Friday, April 25 at 10:00 PM PHT.
Expected downtime: up to 10 minutes.

During the window you may be briefly signed out or see a maintenance page.
Please save in-progress work beforehand. Thank you for your patience.
```

### `warning` — incident / required action

**Title:** Action needed — re-authenticate email integration by Apr 30

**Body:**
```
A recent provider change requires every tenant to re-authenticate the
email integration. Existing sessions will stop sending mail after Apr 30.

To fix: Settings > Email > Reconnect. Takes about 30 seconds.

If you do not use email notifications, no action is needed.
```

### `info` — one-liner

**Title:** Reports now exclude spam tickets from counts

**Body:**
```
Spam tickets are no longer counted in any report totals or agent metrics.
Existing reports will reflect the new counts starting today.
```

## Anti-patterns

Do not write announcements like these:

- `**Important**: see <a href="...">here</a>` — HTML and Markdown both render as literal text.
- `Update deploying at {{TIME}}` — placeholders left in the published copy.
- A 2000-character wall of text with the important bit on line 12 — the preview is line-clamped; no one will scroll.
- `URGENT URGENT URGENT` as `info` severity — either it is urgent (use `warning`) or it is not (do not shout).
- Tenant-specific messages — recipients span every tenant. If the message only applies to one tenant, contact them directly instead.
