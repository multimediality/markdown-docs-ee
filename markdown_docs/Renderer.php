<?php

declare(strict_types=1);

namespace Multimediality\MarkdownDocs;

use Michelf\MarkdownExtra;

/**
 * Turns a Markdown document into the structures the template tags print.
 *
 * Pure: it takes the Markdown source and the rendering options and returns
 * arrays, so the harness can run it over fixtures without a template or a
 * database. The plugin (pi.markdown_docs.php) resolves the file, caches the
 * result and parses the template.
 *
 * Rendering rules (the manual's "How the Markdown is rendered" section
 * states the same rules for customers; keep the two in step):
 *   - a first line "# Title" is dropped and offered as the title;
 *   - everything between "<!-- site:skip -->" and "<!-- /site:skip -->"
 *     lines is dropped (content that only makes sense in the zip or in the
 *     control panel's Manual page);
 *   - <style> blocks are dropped (they style the control panel's Manual
 *     page; the site has its own CSS);
 *   - Markdown is rendered with Michelf MarkdownExtra, the parser
 *     ExpressionEngine uses for an add-on's Manual page;
 *   - every h2 becomes a section; every h2, h3 and h4 gets a GitHub-style
 *     id, de-duplicated within the document;
 *   - links under the rewrite origin are rewritten to the site URL;
 *   - tables are wrapped in a div with the table class, when one is given;
 *   - "{" and "}" are encoded, so template-tag examples never run as tags.
 */
final class Renderer
{
    /** Bump when the output changes shape, to invalidate cached renders. */
    public const VERSION = 1;

    private string $siteUrl;
    private string $rewritePattern;
    private string $tableClass;

    /**
     * @param string $siteUrl     The site's URL, with a trailing slash.
     * @param string $rewriteFrom An origin such as "https://example.com/"; links
     *                            under it (with or without "www.") are rewritten
     *                            to $siteUrl. Empty: no rewriting.
     * @param string $tableClass  Class of the div wrapped around every table.
     *                            Empty: tables are left as rendered.
     */
    public function __construct(string $siteUrl, string $rewriteFrom = '', string $tableClass = '')
    {
        $this->siteUrl = rtrim($siteUrl, '/') . '/';
        $this->rewritePattern = self::rewritePattern($rewriteFrom);
        $this->tableClass = trim($tableClass);
    }

    /** Part of the cache key: everything that changes the output. */
    public function signature(): string
    {
        return self::VERSION . '|' . $this->siteUrl . '|' . $this->rewritePattern . '|' . $this->tableClass;
    }

    /**
     * @return array{title: string, intro: string, toc: string, sections: list<array{section_heading: string, section_anchor: string, section_body: string}>}
     */
    public function manual(string $source): array
    {
        $source = $this->prepare($source, $title);
        $html = $this->markdown($source);

        $parts = preg_split('#<h2>(.*?)</h2>#s', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        $used = [];
        $intro = $this->finish((string) array_shift($parts), $used);
        $sections = [];
        $toc = '';

        for ($i = 0; $i + 1 < count($parts); $i += 2) {
            $heading = trim($parts[$i]);
            $anchor = $this->slug(strip_tags($heading), $used);
            $body = $this->finish($parts[$i + 1], $used);
            $sections[] = [
                'section_heading' => $this->encode($heading),
                'section_anchor' => $anchor,
                'section_body' => $body,
            ];

            // Subsections (### ) are listed under their section.
            $sub = '';
            if (preg_match_all('#<h3 id="([^"]+)">(.*?)</h3>#s', $body, $h3s, PREG_SET_ORDER)) {
                foreach ($h3s as $h3) {
                    $sub .= '<li><a href="#' . $h3[1] . '">' . $h3[2] . '</a></li>';
                }
                $sub = '<ul>' . $sub . '</ul>';
            }

            $toc .= '<li><a href="#' . $anchor . '">' . $this->encode($heading) . '</a>' . $sub . '</li>';
        }

        return [
            'title' => $this->encode(htmlspecialchars($title, ENT_QUOTES, 'UTF-8')),
            'intro' => $intro,
            'toc' => $toc === '' ? '' : '<ul>' . $toc . '</ul>',
            'sections' => $sections,
        ];
    }

    /**
     * @return list<array{version: string, date: string, anchor: string, notes: string}>
     */
    public function changelog(string $source): array
    {
        $source = $this->prepare($source, $title);
        $html = $this->markdown($source);

        $parts = preg_split('#<h2>(.*?)</h2>#s', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        array_shift($parts);
        $used = [];
        $releases = [];

        for ($i = 0; $i + 1 < count($parts); $i += 2) {
            $heading = html_entity_decode(strip_tags($parts[$i]), ENT_QUOTES, 'UTF-8');

            // A release heading starts with its version ("1.4.10 — 2026-10-25",
            // optionally "v1.4.10"). A version further in ("Pre-release review
            // notes (0.24.0 to 1.0.0)") is not a release.
            if (! preg_match('/^\s*v?(\d+\.\d+(?:\.\d+)?(?:-[0-9A-Za-z.]+)?)\b/', $heading, $v)) {
                continue; // "Unreleased" and other non-release headings
            }

            $date = preg_match('/\b(\d{4}-\d{2}-\d{2})\b/', $heading, $d) ? $d[1] : '';
            $anchor = $this->slug('v' . $v[1], $used);

            $releases[] = [
                'version' => $v[1],
                'date' => $date,
                'anchor' => $anchor,
                'notes' => $this->finish($parts[$i + 1], $used, $anchor . '-'),
            ];
        }

        return $releases;
    }

    // ------------------------------------------------------------------

    /** Drop the "# Title" line (captured), site:skip blocks and <style> blocks. */
    private function prepare(string $source, ?string &$title): string
    {
        $source = str_replace(["\r\n", "\r"], "\n", $source);
        $source = (string) preg_replace('/^\xEF\xBB\xBF/', '', $source);
        $title = '';

        if (preg_match('/^\s*#[ \t]+([^\n]+)\n/', $source, $m)) {
            $title = trim($m[1]);
            $source = substr($source, strlen($m[0]));
        }

        $source = (string) preg_replace('#<!--\s*site:skip\s*-->.*?<!--\s*/site:skip\s*-->\n?#s', '', $source);

        return (string) preg_replace('#<style\b[^>]*>.*?</style>\n?#is', '', $source);
    }

    private function markdown(string $source): string
    {
        $parser = new MarkdownExtra();

        if ($this->rewritePattern !== '') {
            $pattern = $this->rewritePattern;
            $site = $this->siteUrl;
            $parser->url_filter_func = static function (string $url) use ($pattern, $site): string {
                return (string) preg_replace($pattern, $site, $url);
            };
        }

        return $parser->transform($source);
    }

    /** Ids on h3/h4, wrapped tables, template tags neutralised. */
    private function finish(string $html, array &$used, string $prefix = ''): string
    {
        $html = (string) preg_replace_callback('#<h([34])>(.*?)</h\1>#s', function (array $m) use (&$used, $prefix): string {
            $id = $this->slug($prefix . strip_tags($m[2]), $used);

            return '<h' . $m[1] . ' id="' . $id . '">' . $m[2] . '</h' . $m[1] . '>';
        }, $html);

        if ($this->tableClass !== '') {
            $open = '<div class="' . htmlspecialchars($this->tableClass, ENT_QUOTES, 'UTF-8') . '"><table>';
            $html = str_replace(['<table>', '</table>'], [$open, '</table></div>'], $html);
        }

        return $this->encode(trim($html));
    }

    /** GitHub-style heading anchor, de-duplicated within one document. */
    private function slug(string $text, array &$used): string
    {
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        $slug = mb_strtolower(trim($text), 'UTF-8');
        $slug = (string) preg_replace('/[^\p{L}\p{N}\p{M} _-]/u', '', $slug);
        $slug = str_replace(' ', '-', $slug);

        if ($slug === '') {
            $slug = 'section';
        }

        $base = $slug;
        $n = 0;
        while (isset($used[$slug])) {
            $slug = $base . '-' . (++$n);
        }
        $used[$slug] = true;

        return $slug;
    }

    private function encode(string $html): string
    {
        return str_replace(['{', '}'], ['&#123;', '&#125;'], $html);
    }

    /**
     * "https://www.example.com/docs" -> a pattern matching "http(s)://(www.)example.com/".
     * Only the host is kept: links under any path of that origin are rewritten.
     */
    private static function rewritePattern(string $origin): string
    {
        $origin = trim($origin);
        if ($origin === '') {
            return '';
        }

        $host = parse_url(str_contains($origin, '://') ? $origin : 'https://' . $origin, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return '';
        }

        $host = (string) preg_replace('/^www\./i', '', $host);

        return '#^https?://(www\.)?' . preg_quote($host, '#') . '/#i';
    }
}
