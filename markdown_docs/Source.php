<?php

declare(strict_types=1);

namespace Multimediality\MarkdownDocs;

use ExpressionEngine\Model\File\File;

/**
 * Finds the Markdown file a tag points at and reads it.
 *
 * A tag names the file either through an entry's file field (entry_id and
 * field) or directly (file_id). The result carries a change stamp (file id,
 * modification time, size) that keys the render cache, so replacing the file
 * in place shows up on the next request.
 */
final class Source
{
    /**
     * @return array{0: string, 1: string}|null  [file contents, change stamp]
     */
    public function fromEntryField(int $entryId, string $fieldName): ?array
    {
        if ($entryId <= 0 || $fieldName === '') {
            return null;
        }

        $field = ee('Model')->get('ChannelField')->filter('field_name', $fieldName)->first();
        $entry = ee('Model')->get('ChannelEntry', $entryId)->first();

        if (! $field || ! $entry) {
            $this->log("field {$fieldName} or entry {$entryId} not found.");

            return null;
        }

        $raw = (string) $entry->{'field_id_' . $field->field_id};
        $file = null;

        if (preg_match('/\{file:(\d+):url\}/', $raw, $m)) {
            $file = ee('Model')->get('File', (int) $m[1])->first();
        } elseif (preg_match('/\{filedir_(\d+)\}(.+)$/', $raw, $m)) {
            $file = ee('Model')->get('File')
                ->filter('upload_location_id', (int) $m[1])
                ->filter('file_name', $m[2])
                ->first();
        }

        if (! $file) {
            return null; // an empty field is "no results", not an error
        }

        return $this->read($file);
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    public function fromFileId(int $fileId): ?array
    {
        if ($fileId <= 0) {
            return null;
        }

        $file = ee('Model')->get('File', $fileId)->first();

        if (! $file) {
            $this->log("file {$fileId} not found.");

            return null;
        }

        return $this->read($file);
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    private function read(File $file): ?array
    {
        $path = $file->getAbsolutePath();

        if (is_string($path) && is_file($path)) {
            clearstatcache(true, $path);

            return [(string) file_get_contents($path), $file->file_id . '|' . filemtime($path) . '|' . filesize($path)];
        }

        // A file on a remote adapter (S3 and the like) has no local path.
        try {
            $contents = (string) $file->getFilesystem()->read($file->getSubfoldersPath() . $file->file_name);
        } catch (\Throwable $e) {
            $this->log('could not read ' . $file->file_name . ': ' . $e->getMessage());

            return null;
        }

        return [$contents, $file->file_id . '|' . $file->modified_date . '|' . md5($contents)];
    }

    private function log(string $message): void
    {
        if (isset(ee()->TMPL)) {
            ee()->TMPL->log_item('Markdown Docs: ' . $message);
        }
    }
}
