<?php

return [
    'tidy' => require __DIR__ . '/tidy.php',
    'paths' => [
        'repository' => realpath(__DIR__ . '/..'),
        'import' => realpath(__DIR__ . '/../resources/imported-rfcs'),
    ],
];
