# AGENTS.md

Guidance for AI agents working in this repository. For the codebase itself (architecture, commands, conventions, gotchas), read [CLAUDE.md](CLAUDE.md) first — this file covers *how the agent team operates*.

## Team model: a Fable-class lead, Sonnet for scoped work

The top-level session runs on the current Fable-class model and acts as **lead**. The lead:

1. **Plans** — reads the relevant code, decides the approach, and names every file that must change, including the cross-file sync points in CLAUDE.md (the rendering rules in the `Renderer` docblock ↔ the manual's "How the Markdown is rendered" ↔ the harness fixtures; the version in `addon.setup.php` ↔ `CHANGELOG.md` ↔ the manual's Changelog bullet).
2. **Implements the critical path itself** — this add-on is small and its work is dominated by Markdown-rendering edge cases and EE questions (what the file model returns, how the template parser treats the variables) that are settled by reading the EE core on the dev install and running the harness. Context is the scarce resource; the lead does that work directly.
3. **Delegates** what is well-scoped and cold-startable to **Sonnet subagents** (Agent tool, `model: "sonnet"`): a doc section mirroring an existing one, a new fixture with its checks, CHANGELOG/TODO bookkeeping, broad read-only exploration (the `Explore` agent type).
4. **Reviews & integrates** — reads every delegated diff, lints on 8.1/8.3, runs the harness, and reports plainly what was and wasn't verified (the agent cannot log in to the dev site; the rendered pages and a fresh install are verified by Steven in a browser).

## Working agreement

- Every request gets a checkable "done when"; if none is given, propose one and work to it. Rerun the check (lint + harness) after the last edit.
- Never commit or push unless asked in the current turn. Before proposing a commit, list changed files and flag anything not explicitly requested.
- Understand before changing: read the failing path (and the EE core it depends on) and state the suspected cause; a fix Steven proposes is a hypothesis to confirm.
- Ask first for commits, pushes, version bumps, anything on a live site, and any publish to production.
- Facts that get re-asked go in CLAUDE.md "Repo facts", not chat. A review's outcome goes in `docs/launch-punchlist.md`, not chat.
