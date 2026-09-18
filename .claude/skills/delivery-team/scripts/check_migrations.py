#!/usr/bin/env python3
"""Flag destructive database migrations before they can be committed or deployed.

This is the one hard gate on the delivery team. A migration that drops a column,
renames a table or backfills rows cannot be undone by redeploying the previous
image, so it is the single change class where an autonomous team must stop and
ask a human.

The gate is deliberately mechanical. Asking a model to "be careful about data
loss" is not a control -- it will find a reason why this particular drop is
fine. An exit code is a control.

Usage:
    check_migrations.py                      # migrations changed on this branch
    check_migrations.py --base dev_intern    # against a specific branch
    check_migrations.py --files a.php b.php  # specific files
    check_migrations.py --all                # every migration in the repo

Exit codes:
    0  nothing blocking
    1  blocking findings (destructive)
    2  bad invocation
"""

from __future__ import annotations

import argparse
import re
import subprocess
import sys
from pathlib import Path

# Each rule is (regex, label). Kept as source patterns rather than an AST walk
# because migrations are a narrow, highly conventional dialect of PHP -- the
# Blueprint API surface is small and stable, so patterns hold up well here.
BLOCKING_RULES: list[tuple[re.Pattern[str], str]] = [
    (re.compile(r"Schema::\s*dropIfExists\s*\(", re.I), "drops a table (Schema::dropIfExists)"),
    (re.compile(r"Schema::\s*drop\s*\(", re.I), "drops a table (Schema::drop)"),
    (re.compile(r"Schema::\s*rename\s*\(", re.I), "renames a table"),
    (re.compile(r"->\s*dropColumn\s*\(", re.I), "drops a column"),
    (re.compile(r"->\s*renameColumn\s*\(", re.I), "renames a column"),
    (re.compile(r"->\s*dropConstrainedForeignId\s*\(", re.I), "drops a constrained foreign id column"),
    (re.compile(r"DB::\s*statement\s*\(", re.I), "raw DB::statement (may alter or move data)"),
    (re.compile(r"DB::\s*unprepared\s*\(", re.I), "raw DB::unprepared"),
    (re.compile(r"DB::\s*table\s*\([^)]*\)\s*->\s*(update|delete|truncate)", re.I), "data backfill or deletion via DB::table"),
    (re.compile(r"\b(TRUNCATE|DROP\s+TABLE|DROP\s+COLUMN|RENAME\s+TABLE)\b", re.I), "destructive SQL keyword in a string literal"),
    (re.compile(r"->\s*change\s*\(\s*\)", re.I), "column type change (may truncate existing values)"),
]

# Worth a human's eye, but not a stop. Reported and ignored by the exit code.
WARNING_RULES: list[tuple[re.Pattern[str], str]] = [
    (re.compile(r"->\s*dropForeign\s*\(", re.I), "drops a foreign key constraint"),
    (re.compile(r"->\s*dropUnique\s*\(", re.I), "drops a unique constraint"),
    (re.compile(r"->\s*dropIndex\s*\(", re.I), "drops an index"),
    (re.compile(r"use\s+App\\\\Models\\\\", re.I), "imports an Eloquent model (model use in a migration usually means a backfill)"),
]

MIGRATION_DIR = "database/migrations"


def repo_root() -> Path:
    try:
        out = subprocess.run(
            ["git", "rev-parse", "--show-toplevel"],
            capture_output=True, text=True, check=True,
        )
        return Path(out.stdout.strip())
    except (subprocess.CalledProcessError, FileNotFoundError):
        return Path.cwd()


def resolve_base(base: str | None, root: Path) -> str | None:
    """Work out what to diff against.

    Defaulting to `main` is wrong whenever a repo integrates on another branch:
    this one works off `dev_intern`, so `main...HEAD` drags in every migration
    added since the branches diverged and the gate reports a wall of historical
    findings. A gate that cries wolf on 58 files is one nobody reads, which is
    worse than no gate.

    Preference order: what the caller asked for, then the branch's configured
    upstream, then the first conventional integration branch that exists.
    """
    def rev_exists(ref: str) -> bool:
        return subprocess.run(
            ["git", "rev-parse", "--verify", "--quiet", ref],
            capture_output=True, cwd=root,
        ).returncode == 0

    if base:
        if rev_exists(base):
            return base
        print(f"warning: base ref '{base}' not found; falling back to auto-detect", file=sys.stderr)

    out = subprocess.run(
        ["git", "rev-parse", "--abbrev-ref", "--symbolic-full-name", "@{upstream}"],
        capture_output=True, text=True, cwd=root,
    )
    if out.returncode == 0 and out.stdout.strip():
        return out.stdout.strip()

    for candidate in ("dev_intern", "develop", "main", "master"):
        if rev_exists(candidate):
            return candidate

    return None


def changed_migrations(base: str, root: Path) -> list[Path]:
    """Migration files that differ from `base`, including uncommitted work.

    Uncommitted files matter: the team's developer stage leaves changes in the
    working tree, and the gate has to see them before the reviewer signs off,
    not only after a commit exists.
    """
    paths: set[str] = set()

    for cmd in (
        ["git", "diff", "--name-only", f"{base}...HEAD"],
        ["git", "diff", "--name-only"],
        ["git", "diff", "--name-only", "--cached"],
        ["git", "ls-files", "--others", "--exclude-standard"],
    ):
        try:
            out = subprocess.run(cmd, capture_output=True, text=True, cwd=root, check=True)
            paths.update(line for line in out.stdout.splitlines() if line.strip())
        except subprocess.CalledProcessError:
            # A missing base ref shouldn't silently produce "nothing to check".
            if "..." in " ".join(cmd):
                print(f"warning: could not diff against '{base}'", file=sys.stderr)

    return sorted(
        root / p for p in paths
        if p.startswith(MIGRATION_DIR) and p.endswith(".php") and (root / p).exists()
    )


def strip_comments(source: str) -> str:
    """Blank out comments, leaving string literals untouched.

    This has to be a single pass that tracks strings and comments together.
    Doing it with separate regexes gets it backwards inside string literals:
    a hex colour like '#6366f1' or a URL like 'https://x' contains what looks
    like a comment marker, and blanking to end-of-line eats the closing quote.
    The brace matcher downstream then desyncs and the whole file is reported
    unparseable -- which reads as a blocking finding on a perfectly ordinary
    migration.

    Comment bodies become spaces rather than being deleted, so line numbers
    still match the file on disk.
    """
    out: list[str] = []
    i = 0
    n = len(source)
    quote: str | None = None

    while i < n:
        ch = source[i]

        if quote:
            out.append(ch)
            if ch == "\\" and i + 1 < n:
                out.append(source[i + 1])
                i += 2
                continue
            if ch == quote:
                quote = None
            i += 1
            continue

        if ch in ("'", '"'):
            quote = ch
            out.append(ch)
            i += 1
            continue

        if source.startswith("/*", i):
            end = source.find("*/", i + 2)
            end = n if end == -1 else end + 2
            out.append("".join("\n" if c == "\n" else " " for c in source[i:end]))
            i = end
            continue

        # `#[` is an attribute, not a comment.
        if source.startswith("//", i) or (ch == "#" and not source.startswith("#[", i)):
            end = source.find("\n", i)
            end = n if end == -1 else end
            out.append(" " * (end - i))
            i = end
            continue

        out.append(ch)
        i += 1

    return "".join(out)


def extract_up_method(source: str) -> str | None:
    """Return the body of `up()`, with everything else blanked out.

    Scanning the whole file is what makes this kind of check useless in
    practice: every `create_*_table` migration drops its table in `down()`, so
    a whole-file scan flags essentially the entire repo. Only the forward path
    can destroy data that exists -- `down()` is the rollback, and a rollback
    that drops the table it created is correct.

    Non-`up` regions are blanked rather than removed so reported line numbers
    still match the real file.
    """
    match = re.search(r"\bfunction\s+up\s*\([^)]*\)\s*(?::\s*\w+\s*)?\{", source)
    if not match:
        return None

    start = match.end() - 1  # at the opening brace
    depth = 0
    in_string: str | None = None
    escaped = False

    for i in range(start, len(source)):
        ch = source[i]

        if in_string:
            if escaped:
                escaped = False
            elif ch == "\\":
                escaped = True
            elif ch == in_string:
                in_string = None
            continue

        if ch in ("'", '"'):
            in_string = ch
        elif ch == "{":
            depth += 1
        elif ch == "}":
            depth -= 1
            if depth == 0:
                before = re.sub(r"[^\n]", " ", source[:start])
                after = re.sub(r"[^\n]", " ", source[i + 1:])
                return before + source[start:i + 1] + after

    return None  # unbalanced braces; treat as unparseable


# Table name is captured deliberately. Matching on the column name alone is a
# safety bug: `status` exists on several tables here, and comparing a widening
# of tickets.status against service_reports.status could demote a real
# narrowing. Anything we cannot attribute to a table is left blocking.
ENUM_MODIFY_RE = re.compile(
    r"ALTER\s+TABLE\s+[`\"']?(\w+)[`\"']?\s+MODIFY\s+(?:COLUMN\s+)?"
    r"[`\"']?(\w+)[`\"']?\s+ENUM\s*\(([^)]*)\)",
    re.I | re.S,
)
ENUM_DECL_RE = re.compile(r"enum\s*\(\s*['\"](\w+)['\"]\s*,\s*\[([^\]]*)\]", re.I)
SCHEMA_BLOCK_RE = re.compile(r"Schema::\s*(?:create|table)\s*\(\s*['\"](\w+)['\"]", re.I)
APPROVAL_RE = re.compile(r"@gate-approved:\s*(.+?)\s*(?:\*/|$)", re.I | re.M)


def enum_values(blob: str) -> set[str]:
    return {m.group(1) for m in re.finditer(r"['\"]([^'\"]+)['\"]", blob)}


def known_enum_values(table: str, column: str, root: Path) -> set[str] | None:
    """The values `table`.`column` currently allows, per the migration history.

    Each enum declaration is attributed to the nearest preceding
    Schema::create/table block, so we compare like with like. Returns None when
    no prior declaration is found, which the caller treats as "cannot prove
    this is safe" -- the conservative direction.
    """
    found: set[str] | None = None

    for mig in sorted((root / MIGRATION_DIR).glob("*.php")):
        try:
            text = strip_comments(mig.read_text(encoding="utf-8", errors="replace"))
        except OSError:
            continue

        blocks = [(m.start(), m.group(1)) for m in SCHEMA_BLOCK_RE.finditer(text)]

        def owning_table(pos: int) -> str | None:
            candidate = None
            for start, name in blocks:
                if start < pos:
                    candidate = name
                else:
                    break
            return candidate

        for m in ENUM_DECL_RE.finditer(text):
            if m.group(1) == column and owning_table(m.start()) == table:
                found = enum_values(m.group(2))

        # A later raw ALTER supersedes the original declaration.
        for m in ENUM_MODIFY_RE.finditer(text):
            if m.group(1) == table and m.group(2) == column:
                found = enum_values(m.group(3))

    return found


def additive_enum_lines(up_body: str, root: Path) -> set[int]:
    """Line numbers belonging to ENUM MODIFY statements that only ADD values.

    Widening an enum cannot destroy data -- no existing row's value stops being
    representable. Narrowing one silently truncates every row holding a removed
    value. A gate that cannot tell these apart stops the safe case as often as
    the dangerous one, and a gate that fires on safe changes is one people learn
    to wave through -- which is how the dangerous case gets waved through too.

    Returning lines rather than a yes/no keeps this precise: a file that widens
    an enum *and* drops a column still blocks on the drop, because only the
    widening's own lines are eligible for demotion.
    """
    safe: set[int] = set()

    for m in ENUM_MODIFY_RE.finditer(up_body):
        table, column, new_values = m.group(1), m.group(2), enum_values(m.group(3))
        current = known_enum_values(table, column, root)
        if current is None or not current.issubset(new_values):
            continue

        # Cover the whole enclosing PHP statement, not just the SQL: the
        # `DB::statement(` wrapper trips a blocking rule of its own and sits on
        # the same line.
        start = up_body.rfind("\n", 0, m.start()) + 1
        end = up_body.find(";", m.end())
        end = len(up_body) if end == -1 else end
        safe.update(
            range(up_body.count("\n", 0, start) + 1, up_body.count("\n", 0, end) + 2)
        )

    return safe


def scan(path: Path, root: Path) -> tuple[list[str], list[str]]:
    rel = path.relative_to(root) if path.is_relative_to(root) else path

    try:
        raw = path.read_text(encoding="utf-8", errors="replace")
    except OSError as exc:
        return ([f"{rel}  could not read file: {exc}"], [])

    source = strip_comments(raw)
    up_body = extract_up_method(source)

    if up_body is None:
        # Better to make a human look than to wave through a file we could not
        # parse -- an unreadable migration is exactly where something hides.
        return ([f"{rel}  could not locate an up() method; review by hand"], [])

    lines = up_body.splitlines()
    blocking: list[str] = []
    warnings: list[str] = []
    safe_lines = additive_enum_lines(up_body, root)

    for rules, bucket in ((BLOCKING_RULES, blocking), (WARNING_RULES, warnings)):
        for pattern, label in rules:
            for n, line in enumerate(lines, start=1):
                if not pattern.search(line):
                    continue
                entry = f"{rel}:{n}  {label}\n      {line.strip()[:120]}"
                if bucket is blocking and n in safe_lines:
                    # Provably additive ENUM widening -- report it, don't stop on it.
                    warnings.append(
                        f"{entry}\n      (demoted: ENUM only gains values, "
                        f"no existing row becomes unrepresentable)"
                    )
                else:
                    bucket.append(entry)
                break  # one finding per rule per file is enough to stop

    # An explicit, human-granted exception. It has to live in the migration
    # itself so it shows up in the diff the reviewer reads -- an approval that
    # is invisible at review time is not an approval, it is a bypass.
    approval = APPROVAL_RE.search(raw)
    if blocking and approval:
        reason = approval.group(1).strip()
        warnings.append(
            f"{rel}  GATE OVERRIDDEN by @gate-approved: {reason}\n"
            f"      {len(blocking)} blocking finding(s) downgraded. A human owns this."
        )
        warnings.extend(f"(overridden) {f}" for f in blocking)
        blocking = []

    return blocking, warnings


def main() -> int:
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    group = ap.add_mutually_exclusive_group()
    group.add_argument("--base", default=None, help="branch to diff against (default: upstream, else dev_intern/develop/main)")
    group.add_argument("--files", nargs="+", help="specific migration files to check")
    group.add_argument("--all", action="store_true", help="check every migration in the repo")
    args = ap.parse_args()

    root = repo_root()

    if args.files:
        targets = [Path(f) if Path(f).is_absolute() else root / f for f in args.files]
    elif args.all:
        targets = sorted((root / MIGRATION_DIR).glob("*.php"))
    else:
        base = resolve_base(args.base, root)
        if base is None:
            print("error: could not determine a base ref to diff against; pass --base or --all", file=sys.stderr)
            return 2
        print(f"Comparing against: {base}")
        targets = changed_migrations(base, root)

    if not targets:
        print("No migrations to check.")
        return 0

    print(f"Checking {len(targets)} migration(s)...\n")

    all_blocking: list[str] = []
    all_warnings: list[str] = []
    for path in targets:
        blocking, warnings = scan(path, root)
        all_blocking.extend(blocking)
        all_warnings.extend(warnings)

    if all_warnings:
        print("WARNINGS (review, not blocking):")
        for w in all_warnings:
            print(f"  - {w}")
        print()

    if all_blocking:
        print("BLOCKING - destructive migration detected:")
        for b in all_blocking:
            print(f"  - {b}")
        print(
            "\nThis change cannot be undone by redeploying the previous image.\n"
            "Stop here. Write an escalation message to the workspace and put the\n"
            "findings above in front of a human before going any further."
        )
        return 1

    print("OK - no destructive operations found.")
    return 0


if __name__ == "__main__":
    try:
        sys.exit(main())
    except KeyboardInterrupt:
        sys.exit(2)
