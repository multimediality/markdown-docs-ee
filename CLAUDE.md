# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this repo is

A free ExpressionEngine 7 plugin ("Markdown Docs", namespace `Multimediality\MarkdownDocs`, MIT) that renders a product's shipped `README.md` and `CHANGELOG.md`, uploaded to file fields, as documentation and release-notes pages. The repo root holds packaging scripts and docs; the add-on itself is `markdown_docs/`, installed by copying it to `system/user/addons/markdown_docs`. It follows the house structure of the sibling add-ons (`F:\GitHub\multimediality\addsearch-crawl-ee`, `yumpu-embed-ee`, `mcp-server-for-ee`, `outbound-ee`): same build script, manual-in-the-add-on, CHANGELOG grouped by kind of change, harness instead of PHPUnit.

It started life as the `addon_docs` site plugin of multimediality.com and still renders that site's add-on pages, so the site is both the dev install and the first customer.

## Commands

```bash
npm install
npm run build:release      # dist/markdown_docs_<version>.zip (+ CHANGELOG/LICENSE and the manual as README.md)
```

The version is read from `markdown_docs/addon.setup.php` (the single source). A version bump = `addon.setup.php` + a dated `## <version> — <date>` section in `CHANGELOG.md` + one `- **<version>** …` bullet under `## Changelog` in `markdown_docs/README.md`; the harness checks the three agree. Bump `Renderer::VERSION` when the rendered output changes shape, so cached renders are invalidated.

Lint with the MAMP PHP builds (neither is on PATH; the MAMP CLI loads no php.ini):

```bash
"C:\MAMP\bin\php\php8.1.0\php.exe" -l markdown_docs/Renderer.php
"C:\MAMP\bin\php\php8.3.7\php.exe" -d extension_dir="C:\MAMP\bin\php\php8.3.7\ext" -d extension=mbstring -d extension=curl -d extension=openssl -d extension=pdo_mysql -d extension=fileinfo scripts/test_addon_cli.php "P:/Steven Demanett/Web/multimediality" dev.multimediality.com
```

The harness takes an optional entry id and field name as the 3rd and 4th arguments to run the file lookup against that site (on the multimediality site: an `add_ons` entry id and `addon_manual`). Without them the lookup checks are skipped, not failed.

## Dev site workflow

- The multimediality site (`P:\Steven Demanett\Web\multimediality`, EE 7.5.28, MAMP, https://dev.multimediality.com/) runs this add-on from its own committed copy at `system/user/addons/markdown_docs/`, the same way it carries MCP Server For EE. After linting and the harness, copy `markdown_docs/` over that folder, check `/add-ons/<slug>/docs/` and `/add-ons/<slug>/changelog/` in a browser, and commit the site repo separately. The site's templates (`add-ons.group/docs.html`, `changelog.html`, `entry.html`, `_partials/addon_card.html`) pass `field="addon_manual"` / `field="addon_changelog"`; `rewrite_links` and `table_class` come from `$config['markdown_docs']` in the site's `config.php`.
- A fresh-install check uses the EE skeleton tester (`P:\Steven Demanett\Web\ee-skeleton-tester`, https://dev.ee-skeleton-tester.com/admin.php): install the built zip there from the Add-on Manager.
- Write PHP with the Write/Edit tools, never shell heredocs (shell-generated PHP has lost namespace backslashes in the sibling repos). Lint before syncing.

## Publishing the docs to multimediality.com

`markdown_docs/README.md` (the manual) and `CHANGELOG.md` are the only source of Markdown Docs' public docs. The multimediality.com ExpressionEngine site renders the two files with this very add-on: the manual at `/add-ons/markdown-docs/docs/`, every release at `/add-ons/markdown-docs/changelog/`, and the version and release date on the overview and the add-on cards from the newest `## <version> — <date>` heading (a `## Unreleased` section is skipped). Nothing is copied by hand.

Release step, whenever a release changes the manual or the changelog. The site has two copies, and every publish goes to both, dev first:

| Site | MCP connector | `get_site_settings` reports |
|---|---|---|
| Dev | "MCP Server For EE — Multimediality (dev.multimediality.com)" | `site_url` `https://dev.multimediality.com/` |
| Production | the multimediality.com connector | `site_url` `https://multimediality.com/` |

Call `get_site_settings` first on each connector to be sure which site it is. Publish to production only from a released commit (version bumped, CHANGELOG section dated, committed); dev can take a working copy as a preview.

On dev, then on production:

1. `list_file_directories`, and take the id of **Add-on manuals**.
2. `upload_file` with `fileName: "markdown-docs-manual.md"` and `onConflict: "replace"`, then run the returned curl command with `--data-binary @"<absolute path to markdown_docs/README.md>"`. Add `-k` on dev (self-signed certificate); on production, add `--ssl-no-revoke` if Windows curl reports a revocation error. Repeat with `fileName: "markdown-docs-changelog.md"` and `CHANGELOG.md`. The bytes go straight to the server: never paste either file into a tool call.
3. If that site's entry (channel `add_ons`, URL title `markdown-docs`) has no `addon_manual` or `addon_changelog` yet, `update_entry` them to `"{file:<id>:url}"` with the ids this upload returned. Each site has its own entry and file ids: never reuse an id from the other site. Later publishes replace the files in place and keep their ids, so the entry is not touched. Before the first publish on a site, `create_entry` in `add_ons` with the title "Markdown Docs" and an `addon_tagline` (one sentence, from `docs/elevator-pitch.txt`), then set the two file fields.
4. Entry fields that are not in this repo (`addon_tagline`, `addon_buy_url`, `addon_icon`): when one changes, make the same change on both sites. A new icon is uploaded to **Images** on each site with `upload_file`, then set as `{file:<id>:url}` with that site's id. Markdown Docs is free, so `addon_buy_url` points at the store listing (or stays empty until it exists).
5. Open `/add-ons/markdown-docs/docs/` and `/add-ons/markdown-docs/changelog/`, and check that `/add-ons/markdown-docs/` shows the version from the CHANGELOG's newest dated heading.

In the wrap-up, say which sites were published, and if production was skipped (an unreleased commit), say so.

How the site renders the manual is this add-on's own "How the Markdown is rendered" section: the first `# ` line is dropped; every `## ` heading is a section and table-of-contents entry with its `### ` headings listed under it, all with GitHub-style ids; content between `<!-- site:skip -->` and `<!-- /site:skip -->` lines, and any `<style>` block, is left out; `{` and `}` never run as template tags. Keep links to the add-on's own pages absolute (`https://multimediality.com/add-ons/markdown-docs/...`): the control panel's Manual page cannot resolve relative ones, and the site rewrites them to whichever copy of the site is rendering. The tagline, purchase URL and icon are fields on the site entry, not in this repo. Code samples in the manual are raw `<pre class="markdown-docs-manual-code">…</pre>` HTML blocks (content HTML-escaped, a blank line before and after; the `<style>` block at the end of the README styles the class on the control panel's Manual page from the CP theme variables, and the site drops it), never fenced code blocks: the control panel's Manual renderer turns a fence into a two-line scrolling box. The siblings follow the same rule.

## Architecture

- `pi.markdown_docs.php` — the plugin class `Markdown_docs` with the two tags, `manual()` and `changelog()`. It reads the parameters (`entry_id` + `field`, or `file_id`; `sections`; `limit`, `date_format`; `rewrite_links`, `table_class`, `cache`), resolves the file through `Source`, caches the render under `/markdown_docs/<md5>` in `ee()->cache` keyed on the file stamp and `Renderer::signature()`, formats release dates with `ee()->localize`, and fills the template with `parse_variables_row` (manual) / `parse_variables` (changelog). `option()` reads a parameter with `$config['markdown_docs']` as the fallback.
- `Renderer.php` — pure rendering, no EE calls beyond the Michelf class EE autoloads: `manual()` and `changelog()` take the Markdown source and return arrays. `prepare()` (title line, `site:skip`, `<style>`), `markdown()` (MarkdownExtra with `url_filter_func` for the link rewrite), `finish()` (h3/h4 ids, table wrap, brace encoding), `slug()` (GitHub-style ids, de-duplicated per document). `VERSION` is part of the cache key.
- `Source.php` — `fromEntryField()` (reads the file field's `{file:ID:url}` or legacy `{filedir_N}name` value) and `fromFileId()`; `read()` prefers the local path and falls back to the file's filesystem adapter for remote uploads. Returns `[contents, stamp]`; logs to the template log when something named by the tag is missing.
- `scripts/test_addon_cli.php` — boots a local EE install and checks the classes, the version sources, the renderer over `scripts/fixtures/manual.md` and `changelog.md`, and (optionally) the lookup against a real entry. `scripts/build_release.js` — the zip.

## Conventions & gotchas

- PHP 8.1 is the floor: no readonly classes, no 8.2+ syntax. `declare(strict_types=1)` in the namespaced classes; the plugin file follows EE's untyped plugin convention.
- The rendering rules live in three places that must agree: the `Renderer` docblock, the manual's "How the Markdown is rendered", and the harness fixtures. A change to a rule touches all three and bumps `Renderer::VERSION`.
- The `site:skip` marker name is shared with the sibling add-ons' manuals and their CLAUDE.md files; do not rename it.
- The output carries no classes of its own apart from `table_class`; styling is the site's job.
- Michelf is autoloaded by EE's boot (`Michelf` prefix → `system/ee/legacy/libraries/typography/Markdown/Michelf/`); do not bundle a copy.
- License is MIT; keep third-party code MIT-compatible.

## Repo facts (things that get re-asked)

- Status: 1.0.0 built from the site plugin on 2026-10-29; not yet submitted to the ExpressionEngine add-on store. A store version cannot be overwritten once submitted, so any later change is a new patch.
- Free (MIT). No license page terms to maintain: the site's license page for this add-on shows the MIT text.
- The website's docs and changelog pages are the multimediality EE site (`P:/Steven Demanett/Web/multimediality`), rendered by this add-on from the published files (see Publishing the docs). The site entry (URL title `markdown-docs`) is created at the first publish on each site.
- The icon (`markdown_docs/icon.svg`) was drawn 2026-10-29 (blue page outline with text lines, filled aqua Markdown M↓, 128-unit viewBox like the siblings) but is not yet uploaded to the sites' `addon_icon`; the release checklist (store copy, icon, screenshots, site entry) is `docs/launch-punchlist.md`.
