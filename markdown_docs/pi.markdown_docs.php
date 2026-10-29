<?php

use Multimediality\MarkdownDocs\Renderer;
use Multimediality\MarkdownDocs\Source;

/**
 * Markdown Docs: renders a product's shipped README.md and CHANGELOG.md,
 * uploaded to file fields, as documentation and release-notes pages.
 *
 * {exp:markdown_docs:manual entry_id="{entry_id}" field="addon_manual" sections="requirements|installation"}
 *   {manual_title} {manual_intro} {manual_toc} {manual_section_count}
 *   {manual_sections}{section_heading} {section_anchor} {section_body}{/manual_sections}
 *   {if no_results}…{/if}
 * {/exp:markdown_docs:manual}
 *
 * {exp:markdown_docs:changelog entry_id="{entry_id}" field="addon_changelog" limit="1" date_format="%F %j, %Y"}
 *   {release_version} {release_date} {release_date_iso} {release_anchor} {release_notes} {release_count} {release_total}
 *   {if no_results}…{/if}
 * {/exp:markdown_docs:changelog}
 *
 * Both tags also take file_id="123" in place of entry_id/field, and
 * rewrite_links="https://example.com/", table_class="docs__table" and
 * cache="no". rewrite_links and table_class fall back to
 * $config['markdown_docs'] (keys rewrite_links, table_class).
 *
 * Rendering is Multimediality\MarkdownDocs\Renderer; the file lookup is
 * Multimediality\MarkdownDocs\Source. This class parses the parameters,
 * caches renders and fills the template.
 */
class Markdown_docs
{
    public $return_data = '';

    private const CACHE_PREFIX = '/markdown_docs/';
    private const CACHE_TTL = 2592000; // 30 days; the key changes with the file

    public function manual()
    {
        $doc = $this->load('manual');

        if ($doc === null) {
            return $this->return_data = ee()->TMPL->no_results();
        }

        $sections = $doc['sections'];
        $only = trim((string) ee()->TMPL->fetch_param('sections', ''));

        if ($only !== '') {
            $wanted = array_map('trim', explode('|', strtolower($only)));
            $sections = array_values(array_filter($sections, static function (array $s) use ($wanted): bool {
                return in_array($s['section_anchor'], $wanted, true);
            }));
        }

        $vars = [
            'manual_title' => $doc['title'],
            'manual_intro' => $doc['intro'],
            'manual_toc' => $doc['toc'],
            'manual_section_count' => count($sections),
            'manual_sections' => $sections,
        ];

        return $this->return_data = ee()->TMPL->parse_variables_row(ee()->TMPL->tagdata, $vars);
    }

    public function changelog()
    {
        $releases = $this->load('changelog');

        if (empty($releases)) {
            return $this->return_data = ee()->TMPL->no_results();
        }

        $limit = (int) ee()->TMPL->fetch_param('limit', 0);
        if ($limit > 0) {
            $releases = array_slice($releases, 0, $limit);
        }

        $format = (string) ee()->TMPL->fetch_param('date_format', '%F %j, %Y');
        $total = count($releases);
        $rows = [];

        foreach ($releases as $i => $r) {
            $rows[] = [
                'release_version' => $r['version'],
                'release_date_iso' => $r['date'],
                'release_date' => $r['date'] !== ''
                    ? ee()->localize->format_date($format, strtotime($r['date'] . ' 12:00:00 UTC'))
                    : '',
                'release_anchor' => $r['anchor'],
                'release_notes' => $r['notes'],
                'release_count' => $i + 1,
                'release_total' => $total,
            ];
        }

        return $this->return_data = ee()->TMPL->parse_variables(ee()->TMPL->tagdata, $rows);
    }

    // ------------------------------------------------------------------

    /**
     * Resolve the file the tag points at, then return its cached or fresh render.
     *
     * @param 'manual'|'changelog' $kind
     * @return array|null  null when there is no file (the tag shows no_results)
     */
    private function load(string $kind): ?array
    {
        $file = $this->file();
        if ($file === null) {
            return null;
        }

        [$source, $stamp] = $file;
        $renderer = new Renderer(
            (string) ee()->config->item('site_url'),
            $this->option('rewrite_links'),
            $this->option('table_class')
        );

        $useCache = strtolower((string) ee()->TMPL->fetch_param('cache', 'yes')) !== 'no';
        $key = self::CACHE_PREFIX . md5($kind . '|' . $stamp . '|' . $renderer->signature());

        if ($useCache) {
            $cached = ee()->cache->get($key);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $result = $kind === 'manual' ? $renderer->manual($source) : $renderer->changelog($source);

        if ($useCache) {
            ee()->cache->save($key, $result, self::CACHE_TTL);
        }

        return $result;
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    private function file(): ?array
    {
        $source = new Source();
        $fileId = (int) ee()->TMPL->fetch_param('file_id', 0);

        if ($fileId > 0) {
            return $source->fromFileId($fileId);
        }

        $entryId = (int) ee()->TMPL->fetch_param('entry_id', 0);
        $field = trim((string) ee()->TMPL->fetch_param('field', ''));

        if ($entryId <= 0 || $field === '') {
            ee()->TMPL->log_item('Markdown Docs: the tag needs entry_id and field, or file_id.');

            return null;
        }

        return $source->fromEntryField($entryId, $field);
    }

    /** A tag parameter, else the same key under $config['markdown_docs'], else empty. */
    private function option(string $name): string
    {
        $value = ee()->TMPL->fetch_param($name, null);

        if ($value === null || $value === false) {
            $config = ee()->config->item('markdown_docs');
            $value = is_array($config) && isset($config[$name]) ? $config[$name] : '';
        }

        return trim((string) $value);
    }
}

// EOF
