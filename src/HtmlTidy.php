<?php

declare(strict_types=1);

namespace PhpRfcs;

use Tidy as PhpTidy;

final class HtmlTidy extends PhpTidy
{
    private const CONFIG = [
        'bare' => true,
        'clean' => true,
        'coerce-endtags' => true,
        'drop-empty-elements' => true,
        'drop-empty-paras' => true,
        'enclose-block-text' => true,
        'enclose-text' => true,
        'escape-scripts' => true,
        'fix-backslash' => true,
        'fix-bad-comments' => true,
        'fix-style-tags' => true,
        'fix-uri' => true,
        'hide-comments' => true,
        'indent' => true,
        'literal-attributes' => false,
        'output-xhtml' => false,
        'preserve-entities' => true,
        'punctuation-wrap' => false,
        'quote-ampersand' => true,
        'quote-marks' => true,
        'quote-nbsp' => true,
        'skip-nested' => false,
        'word-2000' => true,
        'wrap' => 1000,
        'wrap-attributes' => false,
        'wrap-sections' => false,
        'vertical-space' => 'auto',
    ];

    public function __construct()
    {
        parent::__construct(
            config: self::CONFIG,
            encoding: 'utf8',
        );
    }
}
