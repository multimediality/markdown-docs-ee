<?php

return [
    'author'      => 'Multimediality',
    'author_url'  => 'https://multimediality.com',
    'name'        => 'Markdown Docs',
    'description' => 'Renders a product\'s shipped README.md and CHANGELOG.md, uploaded to file fields, as documentation and release-notes pages with sections, anchors and a table of contents.',
    'version'     => '1.0.0',
    'namespace'   => 'Multimediality\\MarkdownDocs',
    'settings_exist' => false,
    'docs_url'    => 'https://multimediality.com/add-ons/markdown-docs/docs',
    // Enforced by the Add-on Manager at install.
    'requires'    => [
        'php' => '8.1',
        'ee'  => '7.2.0',
    ],
];
