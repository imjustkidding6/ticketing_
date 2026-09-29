#!/usr/bin/env python3
"""Check that the agent/skill definitions and the workspaces they produce agree.

The team's roles only work if three things stay true: every agent a skill
dispatches exists, every path an agent tells you to run exists, and every
message names something that can actually be dispatched. None of that is
enforced by the harness, and all three had drifted by the time this was
written -- a handoff addressed to `qa` matched no agent, and stage 5 had
never once run as a result.

Exit code 0 = clean, 1 = findings. Run it after editing anything under
.claude/agents/ or .claude/skills/.
"""
import json
import pathlib
import re
import sys

ROOT = pathlib.Path(__file__).resolve().parents[4]
AGENTS = ROOT / ".claude/agents"
SKILLS = ROOT / ".claude/skills"
WORKSPACE = ROOT / ".claude/workspace"

# Names that are addressable but are not agent files.
NON_AGENT_NAMES = {"lead", "human", "qa-reality-check"}
MESSAGE_TYPES = {"handoff", "question", "answer", "objection",
                 "approval", "escalation", "correction", "note"}
REQUIRED_KEYS = ["at", "from", "to", "type", "summary"]
STATUSES = {"analysis", "design", "build", "review", "qa",
            "release", "merged", "abandoned"}

findings = []


def report(where, what):
    findings.append(f"{where}: {what}")


def frontmatter(path):
    text = path.read_text()
    if not text.startswith("---\n"):
        return {}, text
    head, _, body = text[4:].partition("\n---\n")
    meta = {}
    for line in head.splitlines():
        if ":" in line and not line.startswith(" "):
            k, _, v = line.partition(":")
            meta[k.strip()] = v.strip()
    return meta, body


def main():
    agents = {}
    for path in sorted(AGENTS.glob("*.md")):
        meta, body = frontmatter(path)
        name = meta.get("name")
        if not name:
            report(path.name, "no `name:` in frontmatter")
            continue
        if name != path.stem:
            report(path.name, f"`name: {name}` does not match the filename")
        if not meta.get("description"):
            report(path.name, "no `description:` -- it will never be selected")
        tools = [t.strip() for t in meta.get("tools", "").split(",") if t.strip()]
        agents[name] = {"path": path, "body": body, "tools": tools, "meta": meta}

    known = set(agents) | NON_AGENT_NAMES

    # Every agent a skill names must exist, and every agent must be dispatched.
    dispatched = set()
    for skill in sorted(SKILLS.glob("*/SKILL.md")):
        meta, body = frontmatter(skill)
        if not meta.get("name"):
            report(skill.parent.name, "SKILL.md has no `name:`")
        elif meta["name"] != skill.parent.name:
            report(skill.parent.name, f"`name: {meta['name']}` does not match its directory")
        for name in re.findall(r"`((?:delivery|qa)-[a-z-]+)`", body):
            if name in NON_AGENT_NAMES:
                continue
            if name not in agents:
                report(skill.parent.name, f"dispatches `{name}`, which has no agent file")
            else:
                dispatched.add(name)

    for name in sorted(set(agents) - dispatched):
        report(name, "defined but no skill dispatches it")

    # Paths an agent or skill tells you to run must exist.
    for owner, body in ([(n, a["body"]) for n, a in agents.items()]
                        + [(s.parent.name, frontmatter(s)[1]) for s in SKILLS.glob("*/SKILL.md")]):
        for rel in set(re.findall(r"\.claude/skills/[\w./-]+\.(?:py|sh)", body)):
            if not (ROOT / rel).exists():
                report(owner, f"references `{rel}`, which does not exist")

    # An agent that hands off must name something dispatchable.
    for name, agent in agents.items():
        for target in re.findall(r"message to `([a-z][\w-]*)`", agent["body"]):
            if target not in known:
                report(name, f"hands off to `{target}`, which is not an agent or a known name")
        for target in re.findall(r"addressed to `([a-z][\w-]*)`", agent["body"]):
            if target not in known:
                report(name, f"addresses `{target}`, which is not an agent or a known name")

    # The message log must stay parseable and addressable.
    for log in sorted(WORKSPACE.glob("**/messages.jsonl")):
        rel = log.relative_to(ROOT)
        for lineno, line in enumerate(log.read_text().splitlines(), 1):
            if not line.strip():
                continue
            try:
                msg = json.loads(line)
            except json.JSONDecodeError as exc:
                report(f"{rel}:{lineno}", f"not valid JSON ({exc.msg})")
                continue
            for key in REQUIRED_KEYS:
                if key not in msg:
                    report(f"{rel}:{lineno}", f"missing required key `{key}`")
            if "ts" in msg:
                report(f"{rel}:{lineno}", "uses `ts`; the schema key is `at`")
            for key in ("from", "to"):
                if key in msg and msg[key] not in known:
                    report(f"{rel}:{lineno}", f"`{key}: {msg[key]}` is not dispatchable")
            if msg.get("type") not in MESSAGE_TYPES:
                report(f"{rel}:{lineno}", f"unknown type `{msg.get('type')}`")

    # A message store outside a workspace is a store nobody reads. This has now
    # happened twice: a role whose handoff could not find messages.jsonl created
    # one beside it instead, splitting the history in two.
    for stray in sorted(ROOT.glob("messages.jsonl")) + sorted(ROOT.glob("*/messages.jsonl")):
        if WORKSPACE in stray.parents:
            continue
        report(str(stray.relative_to(ROOT)),
               "message store outside .claude/workspace/ — fold it into the feature's "
               "workspace and delete it; a handoff written here is invisible to every role")

    # A feature workspace states where it got to.
    for ws in sorted(WORKSPACE.glob("features/*/workspace.md")):
        rel = ws.relative_to(ROOT)
        match = re.search(r"^\*\*Status:\*\* *([a-z]+)", ws.read_text(), re.M)
        if not match:
            report(str(rel), "no `**Status:**` line")
        elif match.group(1) not in STATUSES:
            report(str(rel), f"status `{match.group(1)}` is not one of {sorted(STATUSES)}")

    if findings:
        print(f"{len(findings)} finding(s):\n")
        for f in findings:
            print(f"  - {f}")
        return 1
    print("team config clean: agents, skills, scripts, messages and statuses all agree")
    return 0


if __name__ == "__main__":
    sys.exit(main())
