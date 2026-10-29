<?php

declare(strict_types=1);

/**
 * CLI harness for Markdown Docs: boots a real local ExpressionEngine
 * installation (for Michelf MarkdownExtra, ee() and the models) and
 * exercises the add-on's classes from THIS repo's working tree, read-only.
 *
 * What it covers: class loading of every file, the version source against
 * the changelog and the manual, the renderer over the fixtures in
 * scripts/fixtures/ (title capture, site:skip and <style> removal, sections,
 * table of contents, GitHub-style anchors with de-duplication, link
 * rewriting, table wrapping, template-tag encoding, release parsing with
 * the headings that are not releases), and, when an entry id and field
 * name are given, the file lookup against that site's database.
 *
 * The MAMP CLI PHP loads no php.ini, so pass the extensions by flag:
 *   "C:\MAMP\bin\php\php8.3.7\php.exe" -d extension_dir="C:\MAMP\bin\php\php8.3.7\ext" \
 *     -d extension=mbstring -d extension=curl -d extension=openssl -d extension=pdo_mysql -d extension=fileinfo \
 *     scripts/test_addon_cli.php "P:/Steven Demanett/Web/ee-skeleton-tester" dev.ee-skeleton-tester.com
 *
 * Usage: php scripts/test_addon_cli.php [ee-root] [host] [entry_id] [field]
 *   e.g. … "P:/Steven Demanett/Web/multimediality" dev.multimediality.com 12 addon_manual
 */

$addonDir = dirname(__DIR__) . '/markdown_docs';
$eeRoot = $argv[1] ?? 'P:/Steven Demanett/Web/ee-skeleton-tester';
$host = $argv[2] ?? 'dev.ee-skeleton-tester.com';
$entryId = (int) ($argv[3] ?? 0);
$fieldName = (string) ($argv[4] ?? '');

if (! is_file($eeRoot . '/index.php')) {
    fwrite(STDERR, "No ExpressionEngine index.php under {$eeRoot}\n");
    exit(1);
}

chdir($eeRoot);
$_SERVER['HTTP_HOST'] = $host;
$_SERVER['SERVER_NAME'] = $host;
$_SERVER['REQUEST_URI'] = '/?ACT=1';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTPS'] = 'on';

define('REQ', 'ACTION');
define('BOOT_ONLY', false);

ob_start();
require $eeRoot . '/index.php';
ob_end_clean();

// Load Multimediality\MarkdownDocs\* from the working tree, ahead of any
// autoloader EE registered for a deployed copy.
spl_autoload_register(static function (string $class) use ($addonDir): void {
    $prefix = 'Multimediality\\MarkdownDocs\\';
    if (! str_starts_with($class, $prefix)) {
        return;
    }
    $file = $addonDir . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
}, true, true);

use Multimediality\MarkdownDocs\Renderer;
use Multimediality\MarkdownDocs\Source;

$failures = 0;
$checks = 0;
$skips = 0;

/**
 * @param mixed $actual
 */
function check(string $label, bool $passed, $actual = null): void
{
    global $failures, $checks;
    $checks++;
    if ($passed) {
        echo "PASS  {$label}\n";

        return;
    }
    $failures++;
    $detail = $actual === null ? '' : ' (got: ' . (is_scalar($actual) ? (string) $actual : json_encode($actual)) . ')';
    echo "FAIL  {$label}{$detail}\n";
}

function skip(string $label, string $why): void
{
    global $skips;
    $skips++;
    echo "SKIP  {$label} ({$why})\n";
}

// ---------------------------------------------------------------------
// 1. Every file parses and declares what it should.
// ---------------------------------------------------------------------
echo "\n# Files and classes\n";
check('class Multimediality\\MarkdownDocs\\Renderer loads', class_exists(Renderer::class));
check('class Multimediality\\MarkdownDocs\\Source loads', class_exists(Source::class));
check('Michelf\\MarkdownExtra is autoloaded by ExpressionEngine', class_exists('Michelf\\MarkdownExtra'));
require_once $addonDir . '/pi.markdown_docs.php';
check('Markdown_docs declared (plugin)', class_exists('Markdown_docs', false));
foreach (['manual', 'changelog'] as $tag) {
    check("plugin has {$tag}()", method_exists('Markdown_docs', $tag));
}

$setup = require $addonDir . '/addon.setup.php';
check('addon.setup.php declares requires.php and requires.ee', isset($setup['requires']['php'], $setup['requires']['ee']));
check('addon.setup.php declares docs_url', isset($setup['docs_url']) && str_starts_with($setup['docs_url'], 'https://'));
check('addon.setup.php has no settings page', ($setup['settings_exist'] ?? null) === false);

$repo = dirname(__DIR__);
$changelogSource = (string) file_get_contents($repo . '/CHANGELOG.md');
$manualSource = (string) file_get_contents($addonDir . '/README.md');
preg_match('/^## v?(\d+\.\d+\.\d+)\b/m', $changelogSource, $newest);
check('CHANGELOG.md newest dated release is the setup version', ($newest[1] ?? '') === $setup['version'], $newest[1] ?? '');
check('the manual\'s Changelog section lists the setup version', str_contains($manualSource, '- **' . $setup['version'] . '**'));
check('the manual has the "## Usage" heading EE\'s in-app docs expect', (bool) preg_match('/^## Usage$/m', $manualSource));

// ---------------------------------------------------------------------
// 2. The renderer over the fixtures.
// ---------------------------------------------------------------------
echo "\n# Renderer: manual\n";
$manualFixture = (string) file_get_contents(__DIR__ . '/fixtures/manual.md');
$renderer = new Renderer('https://dev.example.test', 'https://example.com/', 'docs__table');
$doc = $renderer->manual($manualFixture);

check('title is the "# " line', $doc['title'] === 'Example Add-on', $doc['title']);
check('intro is the text before the first "## "', str_contains($doc['intro'], 'Example Add-on does a thing'));
check('site:skip block is dropped', ! str_contains($doc['intro'], 'only makes sense in the zip'));
check('<style> block is dropped', ! str_contains($doc['intro'], '<style') && ! str_contains($doc['intro'], 'example-manual-code'));
check('a link under the rewrite origin (with www.) is rewritten to the site URL', str_contains($doc['intro'], 'href="https://dev.example.test/add-ons/example/docs/"'), $doc['intro']);
check('a link elsewhere is left alone', str_contains($doc['intro'], 'href="https://expressionengine.com"'));

$anchors = array_column($doc['sections'], 'section_anchor');
check('one section per "## " heading', $anchors === ['requirements', 'installation', 'usage', 'support'], $anchors);
check('section headings are kept', $doc['sections'][0]['section_heading'] === 'Requirements');
check('TOC links every section', substr_count($doc['toc'], '<a href="#') >= 4 && str_contains($doc['toc'], '<a href="#installation">Installation</a>'));
check('TOC lists "### " headings under their section', str_contains($doc['toc'], '<a href="#installation">Installation</a><ul><li><a href="#upgrading">Upgrading</a></li><li><a href="#uninstalling">Uninstalling</a></li></ul>'), $doc['toc']);

$installation = $doc['sections'][1]['section_body'];
$usage = $doc['sections'][2]['section_body'];
check('h3 gets a GitHub-style id', str_contains($installation, '<h3 id="upgrading">Upgrading</h3>'));
check('a repeated heading gets a de-duplicated id', str_contains($usage, '<h3 id="upgrading-1">Upgrading</h3>'), $usage);
check('h4 id drops punctuation like GitHub', str_contains($usage, '<h4 id="notes--caveats-php-84">'), $usage);
check('"{" and "}" are encoded so tag examples never run', str_contains($usage, '<code>&#123;exp:example:tag entry_id="&#123;entry_id&#125;"&#125;</code>') && ! str_contains($usage, '{') && ! str_contains($usage, '}'), $usage);
check('tables are wrapped in the table class', str_contains($usage, '<div class="docs__table"><table>') && str_contains($usage, '</table></div>'));
check('a link under the rewrite origin (without www.) is rewritten', str_contains($doc['sections'][3]['section_body'], 'href="https://dev.example.test/support/"'));
check('a rendered section never contains an h2', ! str_contains($installation, '<h2'));

$plain = (new Renderer('https://dev.example.test/'))->manual($manualFixture);
check('without rewrite_links, links are untouched', str_contains($plain['intro'], 'href="https://www.example.com/add-ons/example/docs/"'));
check('without table_class, tables are not wrapped', ! str_contains($plain['sections'][2]['section_body'], '<div class=') && str_contains($plain['sections'][2]['section_body'], '<table>'));
check('signature differs when an option differs', $renderer->signature() !== (new Renderer('https://dev.example.test/'))->signature());
check('signature is stable for the same options', $renderer->signature() === (new Renderer('https://dev.example.test/', 'https://www.example.com/anything', 'docs__table'))->signature());

$untitled = $renderer->manual("Just a paragraph.\n\n## Only\n\nBody.");
check('a document without a "# " line has an empty title', $untitled['title'] === '' && $untitled['sections'][0]['section_anchor'] === 'only');
$crlf = $renderer->manual("\xEF\xBB\xBF# T\r\n\r\nIntro.\r\n\r\n## A\r\n\r\nB.\r\n");
check('a BOM and CRLF line endings are handled', $crlf['title'] === 'T' && count($crlf['sections']) === 1 && str_contains($crlf['intro'], 'Intro.'));
$empty = $renderer->manual('');
check('an empty document renders to empty parts', $empty['title'] === '' && $empty['intro'] === '' && $empty['toc'] === '' && $empty['sections'] === []);

echo "\n# Renderer: changelog\n";
$changelogFixture = (string) file_get_contents(__DIR__ . '/fixtures/changelog.md');
$releases = $renderer->changelog($changelogFixture);
$versions = array_column($releases, 'version');
check('a release per heading that starts with a version; Unreleased and prose headings skipped', $versions === ['1.2.0', '1.1.0-beta.1', '1.0'], $versions);
check('the date is read from the heading', $releases[0]['date'] === '2026-10-28' && $releases[1]['date'] === '2026-09-01' && $releases[2]['date'] === '');
check('anchors are "v" plus the version without punctuation', array_column($releases, 'anchor') === ['v120', 'v110-beta1', 'v10'], array_column($releases, 'anchor'));
check('h3 ids inside a release are prefixed with its anchor', str_contains($releases[0]['notes'], '<h3 id="v120-added">Added</h3>') && str_contains($releases[0]['notes'], '<h3 id="v120-fixed">'), $releases[0]['notes']);
check('braces in release notes are encoded', str_contains($releases[0]['notes'], '&#123;exp:example:tag&#125;'));
check('no releases from a document without version headings', $renderer->changelog("# Changelog\n\n## Unreleased\n\n- x\n") === []);

// ---------------------------------------------------------------------
// 3. The file lookup against the booted site (optional).
// ---------------------------------------------------------------------
echo "\n# Source\n";
$source = new Source();
check('fromFileId(0) is null', $source->fromFileId(0) === null);
check('fromEntryField(0, …) is null', $source->fromEntryField(0, 'x') === null);
check('fromEntryField(…, "") is null', $source->fromEntryField(1, '') === null);
check('a missing file id is null', $source->fromFileId(2147483647) === null);

if ($entryId > 0 && $fieldName !== '') {
    $found = $source->fromEntryField($entryId, $fieldName);
    check("entry {$entryId} field {$fieldName} resolves to a file", is_array($found));
    if (is_array($found)) {
        [$contents, $stamp] = $found;
        check('the file has contents', $contents !== '');
        check('the stamp carries the file id, time and size', count(explode('|', $stamp)) === 3, $stamp);
        $live = (new Renderer((string) ee()->config->item('site_url')))->manual($contents);
        check('the live file renders with a title and at least one section', $live['title'] !== '' && count($live['sections']) > 0, ['title' => $live['title'], 'sections' => count($live['sections'])]);
    }
    check('a missing entry is null', $source->fromEntryField(2147483647, $fieldName) === null);
    check('a missing field is null', $source->fromEntryField($entryId, 'no_such_field_' . mt_rand()) === null);
} else {
    skip('file lookup against a real entry', 'pass an entry id and field name as the 3rd and 4th arguments');
}

// ---------------------------------------------------------------------
echo "\n{$checks} checks, " . ($checks - $failures) . " passed, {$failures} failed, {$skips} skipped\n";
exit($failures === 0 ? 0 : 1);
