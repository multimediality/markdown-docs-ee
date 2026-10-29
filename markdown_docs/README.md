# Markdown Docs

Markdown Docs is a free ExpressionEngine 7 plugin that renders a product's shipped documentation on your site. Upload the `README.md` and `CHANGELOG.md` that ship with an add-on, a library or any product to a file field, and two template tags render them as a documentation page with sections, anchors and a table of contents, and as release notes with a version, a date and an anchor per release. The files are the single source: replace a file and the page follows, with nothing retyped.

It is the plugin that renders the docs and changelog pages on [multimediality.com](https://multimediality.com/add-ons/), including this one.

<!-- site:skip -->

This manual is also available online at [multimediality.com/add-ons/markdown-docs/docs](https://multimediality.com/add-ons/markdown-docs/docs/).

<!-- /site:skip -->

## Requirements

- ExpressionEngine 7.2 or later.
- PHP 8.1 or later with the `mbstring` extension (standard on typical EE hosts).
- Markdown is rendered with Michelf MarkdownExtra, which ExpressionEngine ships and uses for an add-on's Manual page in the control panel, so nothing else is installed.

## Installation

1. Unzip the release and copy the `markdown_docs/` folder to `system/user/addons/markdown_docs/`.
2. In the Control Panel go to **Developer → Add-ons** and install **Markdown Docs**.

There are no settings. Rendering is controlled by tag parameters, with site-wide defaults available from `config.php` (see **Site-wide defaults** under Usage).

### Upgrading

Replace the `markdown_docs/` folder and run the add-on's **Update** from the Add-ons page. Rendered pages are cached and keyed on the file and the add-on's rendering version, so a release that changes the output takes effect on the next request without clearing the cache.

### Uninstalling

Uninstalling removes the plugin's registration. Your file fields, the uploaded files and the templates stay as they are; the tags simply render nothing until the add-on is installed again.

## Usage

### Setting up

1. Create (or pick) an upload directory for the documentation files, and allow Markdown files in it: in the directory's settings, **Allowed file types** must be *All file types*, or the `md` extension must be added to the site's **Mime type whitelist** (`text/markdown` and `text/plain`). ExpressionEngine refuses the upload otherwise.
2. Add a **File** field to the channel whose entries have documentation, one per document: for example `addon_manual` for the manual and `addon_changelog` for the changelog.
3. Upload each product's `README.md` to the manual field and its `CHANGELOG.md` to the changelog field. To publish a new version, replace the file in place (the File Manager's **Replace**, or an upload with the same name that overwrites): the file keeps its id, the entry is not touched, and the next request renders the new content.
4. Put the tags below in the templates that show the entry.

A tag names its file through the entry and the field (`entry_id` and `field`), or directly by file id (`file_id`), which needs no channel field at all.

### The manual tag

```
{exp:markdown_docs:manual entry_id="{entry_id}" field="addon_manual"}
	{manual_intro}

	{if manual_section_count > 1}
	<nav aria-label="Table of contents">{manual_toc}</nav>
	{/if}

	{manual_sections}
	<section id="{section_anchor}">
		<h2>{section_heading}</h2>
		{section_body}
	</section>
	{/manual_sections}

	{if no_results}<p>No documentation has been published yet.</p>{/if}
{/exp:markdown_docs:manual}
```

Parameters:

| Parameter | Description |
|---|---|
| `entry_id` | The entry whose field holds the file. Required unless `file_id` is given. |
| `field` | The short name of the file field. Required with `entry_id`. |
| `file_id` | The file's id, instead of `entry_id` and `field`. |
| `sections` | Render only these sections, as a pipe-separated list of their anchors (`sections="requirements|installation"`). The table of contents still lists every section. |
| `rewrite_links`, `table_class`, `cache` | See **Parameters both tags take**. |

Variables:

| Variable | Description |
|---|---|
| `{manual_title}` | The document's first line when it is a `# Title` heading. That line is dropped from the output, so the template prints its own heading. |
| `{manual_intro}` | Everything before the first `## ` heading, rendered. |
| `{manual_toc}` | A `<ul>` linking every section, with each section's `### ` headings in a nested list. Empty when there are no sections. |
| `{manual_section_count}` | The number of sections rendered (after `sections=`). |
| `{manual_sections}…{/manual_sections}` | A variable pair, one row per `## ` heading, with `{section_heading}`, `{section_anchor}` and `{section_body}` (everything under the heading up to the next `## `, rendered). |

The tag shows its `{if no_results}` content when the field is empty, the file is missing, or the tag's parameters do not name a file.

### The changelog tag

```
{exp:markdown_docs:changelog entry_id="{entry_id}" field="addon_changelog"}
	<section id="{release_anchor}">
		<h2>{release_version}{if release_date} &middot; {release_date}{/if}</h2>
		{release_notes}
	</section>
	{if no_results}<p>No release notes have been published yet.</p>{/if}
{/exp:markdown_docs:changelog}
```

The newest release alone, for a "Version 1.2.0, released October 28, 2026" line on an overview page or a listing card:

```
{exp:markdown_docs:changelog entry_id="{entry_id}" field="addon_changelog" limit="1"}
	Version {release_version}{if release_date}, released {release_date}{/if}
{/exp:markdown_docs:changelog}
```

Parameters:

| Parameter | Description |
|---|---|
| `entry_id`, `field`, `file_id` | As for the manual tag. |
| `limit` | Render only the newest N releases. |
| `date_format` | The format of `{release_date}`, in ExpressionEngine's date format codes. Default `%F %j, %Y` (October 28, 2026). |
| `rewrite_links`, `table_class`, `cache` | See **Parameters both tags take**. |

Variables, one row per release:

| Variable | Description |
|---|---|
| `{release_version}` | The version from the heading, as written (`1.2.0`, `1.1.0-beta.1`). |
| `{release_date}` | The date from the heading, formatted with `date_format`. Empty when the heading has no date. |
| `{release_date_iso}` | The date as written in the heading (`2026-10-28`). |
| `{release_anchor}` | `v` plus the version with its punctuation removed (`v120`), unique within the document. |
| `{release_notes}` | Everything under the heading up to the next release, rendered. Its `### ` headings (Added, Changed, Fixed…) get ids prefixed with the release anchor (`v120-added`). |
| `{release_count}`, `{release_total}` | The row's position and the number of rows rendered. |

A release is a `## ` heading that starts with a version number, optionally prefixed `v`, followed by anything: `## 1.2.0 — 2026-10-28`, `## v1.2.0 (2026-10-28)`, `## 1.2.0`. The first `YYYY-MM-DD` in the heading is the release date. A `## ` heading that does not start with a version, such as `## Unreleased` or `## Pre-release review notes (0.24.0 to 1.0.0)`, is skipped together with its content, so an in-progress section at the top of the file never reaches the site. Releases are rendered in the file's order, newest first if the file is written that way.

### Parameters both tags take

| Parameter | Description |
|---|---|
| `rewrite_links` | An origin, such as `https://example.com/`. Every link in the document under that origin, with or without `www.`, is rewritten to this site's URL. Write links to your own site as absolute URLs (the control panel's Manual page cannot resolve relative ones) and a development or staging copy of the site links to itself. Default: no rewriting. |
| `table_class` | Wraps every table in `<div class="…">`, for horizontal scrolling on narrow screens. Default: tables are left as rendered. |
| `cache` | `cache="no"` renders on every request. Default: renders are cached (see **Caching**). |

### Site-wide defaults

`rewrite_links` and `table_class` can be set once in `system/user/config/config.php`; a tag parameter still overrides them:

```
$config['markdown_docs'] = [
    'rewrite_links' => 'https://example.com/',
    'table_class' => 'docs__table',
];
```

### How the Markdown is rendered

- The parser is Michelf MarkdownExtra, the one ExpressionEngine uses for an add-on's Manual page in the control panel, so a manual looks the same in both places: fenced code blocks, tables, footnotes, definition lists and inline HTML all work.
- A first line `# Title` is dropped from the output and offered as `{manual_title}`.
- Every `## ` heading starts a section. Every `## `, `### ` and `#### ` heading gets a GitHub-style id (lowercase, punctuation removed, spaces to hyphens, a repeated heading suffixed `-1`, `-2`…), so links like `[Installation](#installation)` inside the document work on the page.
- Content between `<!-- site:skip -->` and `<!-- /site:skip -->`, each on its own line, is left out. Use it for text that only makes sense in the zip or the control panel's Manual page, such as "this manual is also available online at…".
- `<style>` blocks are left out: they style the control panel's Manual page, and your site has its own CSS.
- `{` and `}` are encoded, so template-tag examples in the documentation never run as tags, and conditionals in the template around the tag are unaffected.
- The output is HTML with no classes of its own (apart from `table_class`). Style it from the template, for example by wrapping the tag in `<div class="doc-body">`.

### Caching

Each render is cached in ExpressionEngine's cache (the one set under **Settings → Caching**), keyed on the file's id, modification time and size, the add-on's rendering version and the rendering parameters, for thirty days. Replacing the file changes the key, so the new content shows on the next request, and nothing needs clearing. Clearing the site's cache (**Settings → Caching → Clear**) also clears the renders.

## Troubleshooting

- **The tag renders its `no_results` content** — the field is empty, the file it points at no longer exists, or the tag has neither `entry_id` with `field` nor `file_id`. Turn on **Debugging → Template debugging** and look for lines starting *Markdown Docs:* in the template log; a missing entry, field or file is named there.
- **The upload is refused** — the upload directory does not accept Markdown files. Set its **Allowed file types** to *All file types*, or add `md` to the site's mime type whitelist.
- **A new version of the file does not show** — the cache key changes with the file's modification time and size; make sure the file was replaced in place rather than uploaded under a new name (then the entry still points at the old file). `cache="no"` on the tag confirms whether the cache is the cause.
- **Links in the document point at the live site on a staging copy** — pass `rewrite_links="https://your-live-site.com/"` or set it in `config.php`.
- **A code example in the document ran as a template tag** — it should not: braces are encoded on output. Check that the template is not re-parsing the output through another tag.
- **A heading link does not work** — the id is the heading text lowercased with punctuation removed and spaces as hyphens, the same rule as GitHub; a heading that repeats an earlier one gets `-1`, `-2`.

## Support

Markdown Docs is free and supported on a best-effort basis. Send the Markdown file, the tag as written in the template and what the page shows to support@multimediality.com.

## Changelog

- **1.0.0** First release: the manual and changelog tags, file lookup by entry field or file id, link rewriting and table wrapping as parameters with `config.php` defaults, cached renders keyed on the file.

The full changelog is at [multimediality.com/add-ons/markdown-docs/changelog](https://multimediality.com/add-ons/markdown-docs/changelog/).

## License

Markdown Docs is free software under the MIT License. The full text is in the `LICENSE` file in the release and on the [license page](https://multimediality.com/add-ons/markdown-docs/license/).
