<?php

return [
    'tidy' => require __DIR__ . '/tidy.php',
    'json' => JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
    'paths' => [
        'repository' => realpath(__DIR__ . '/..'),
        'import' => realpath(__DIR__ . '/../resources/imported-rfcs'),
        'overrides' => realpath(__DIR__ . '/../resources/metadata-overrides'),
        'cleanRfcs' => realpath(__DIR__ . '/../rfcs'),
    ],
];
