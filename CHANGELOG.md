# Changelog

All notable changes to Markdown Docs are documented here, newest first. Each release groups its changes under Added, Changed, Removed, Fixed and Security, with Upgrading notes when a release needs a step.

## Unreleased

## 1.0.0 — 2026-10-29

First public release. Markdown Docs is the plugin that has rendered the documentation and changelog pages on multimediality.com since the site moved to ExpressionEngine, made general and packaged for the ExpressionEngine add-on store.

### Added

- **The manual tag** `{exp:markdown_docs:manual}`: renders a `README.md` from an entry's file field (or a file id) as `{manual_title}`, `{manual_intro}`, a `{manual_toc}` and a `{manual_sections}` pair with a heading, an anchor and a body per `## ` heading; `sections=` renders a subset.
- **The changelog tag** `{exp:markdown_docs:changelog}`: renders a `CHANGELOG.md` as one row per release with `{release_version}`, `{release_date}` (formatted with `date_format=`), `{release_date_iso}`, `{release_anchor}`, `{release_notes}`, `{release_count}` and `{release_total}`; `limit=` for the newest releases only. Headings that do not start with a version (`## Unreleased`, prose headings) are skipped with their content.
- **Rendering rules**: Michelf MarkdownExtra (the parser ExpressionEngine uses for an add-on's Manual page); the `# Title` line captured and dropped; `<!-- site:skip -->` blocks and `<style>` blocks left out; GitHub-style ids on every h2, h3 and h4, de-duplicated within the document; `{` and `}` encoded so examples never run as tags.
- **`rewrite_links`** rewrites every link under an origin to the site's URL, so a staging copy links to itself; **`table_class`** wraps tables for horizontal scrolling. Both take a tag parameter or a `$config['markdown_docs']` default.
- **Caching**: renders are kept in ExpressionEngine's cache for thirty days, keyed on the file's id, modification time and size and on the rendering version and parameters, so replacing a file shows at once; `cache="no"` bypasses it.
- A CLI harness (`scripts/test_addon_cli.php`) that boots a local ExpressionEngine install and runs the renderer over fixtures, plus the file lookup against a real entry when one is given.
