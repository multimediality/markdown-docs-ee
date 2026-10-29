# TODO (build order)

## Next

- [x] **Icon.** `markdown_docs/icon.svg` in the house style of the siblings' icons (the Add-on Manager and the site's add-on entry both show it).
- [ ] **Screenshots** for the store listing (a rendered docs page with its table of contents, a changelog page, the Manual page in the Add-on Manager) — outside the repo in `docs/screenshots/`.
- [ ] **A path source.** `path="docs/README.md"` under a configured root (`$config['markdown_docs']['path_root']`), with a traversal guard, for sites whose docs live in the repository rather than the File Manager. Only if someone asks: the file-field source covers the add-on-store use.
- [ ] **A `safe="yes"` parameter** that strips inline HTML from the Markdown, for documents from untrusted authors. MarkdownExtra passes HTML through today, which is right for a vendor's own manual.
- [ ] **`version="1.2.0"`** on the changelog tag to render one named release (today: `limit` from the newest only).

## Completed

- ~~Generalise the multimediality.com `addon_docs` site plugin~~ (1.0.0: the link-rewrite origin and the table wrapper class became parameters with `config.php` defaults, `file_id` as an alternative source, a `cache` parameter, the renderer split out and covered by a harness)
