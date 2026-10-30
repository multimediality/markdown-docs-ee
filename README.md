<img src="markdown_docs/icon.svg" alt="" width="96">

# Markdown Docs for ExpressionEngine

Render a product's shipped `README.md` and `CHANGELOG.md`, uploaded to file fields, as documentation and release-notes pages: sections, GitHub-style anchors, a table of contents, and a version, date and anchor per release. Free, MIT-licensed. It is the plugin behind the add-on docs on [multimediality.com](https://multimediality.com/add-ons/).

This repository holds the add-on (`markdown_docs/`), the packaging scripts and the product documents. The add-on's own [README](markdown_docs/README.md) is the customer manual ExpressionEngine renders in the Add-on Manager and the site renders at [multimediality.com/add-ons/markdown-docs/docs](https://multimediality.com/add-ons/markdown-docs/docs/).

- Developer guide for agents and contributors: [CLAUDE.md](CLAUDE.md), with how the agent team works in [AGENTS.md](AGENTS.md)
- Changes: [CHANGELOG.md](CHANGELOG.md)
- Release checklist: [docs/launch-punchlist.md](docs/launch-punchlist.md)

## Build

```bash
npm install
npm run build:release      # dist/markdown_docs_<version>.zip
```

A pushed `v<version>` tag builds the same zip on GitHub Actions and attaches it to a GitHub release ([release.yml](.github/workflows/release.yml)).

## Develop

Copy `markdown_docs/` to `system/user/addons/markdown_docs` on an EE 7 site and install it from the Add-on Manager, or with the CLI:

```bash
php system/ee/eecli.php addons:install --addon=markdown_docs
```

There is no PHPUnit suite. Verification is PHP lint on 8.1 and 8.3 plus `scripts/test_addon_cli.php`, which boots a local ExpressionEngine install and runs the renderer over the fixtures in `scripts/fixtures/` (the invocation is in CLAUDE.md).

## License

MIT; see [LICENSE](LICENSE).
