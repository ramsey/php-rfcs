<?php

declare(strict_types=1);

namespace PhpRfcs\Wiki;

use DOMDocument;
use DOMElement;
use DOMXPath;
use PhpRfcs\ProcessFactory;

class Metadata
{
    public function __construct(
        private ProcessFactory $processFactory,
        private string $rawPath,
    ) {
    }

    public function gatherMetadata(?string $rfcSlug): array
    {
        if ($rfcSlug !== null) {
            return [
                $rfcSlug => $this->parseMetadataFromFile(
                    $rfcSlug,
                    $this->rawPath . '/' . $rfcSlug . '.txt',
                ),
            ];
        }

        $metadata = [];

        foreach (glob($this->rawPath . '/*.txt') as $rawFile) {
            $slug = basename($rawFile, '.txt');
            $metadata[$slug] = $this->parseMetadataFromFile($slug, $rawFile);
        }

        return $metadata;
    }

    private function parseMetadataFromFile(string $rfcSlug, string $rawFile): array
    {
        $rawContents = trim(file_get_contents($rawFile));

        // Find Dokuwiki links in the form of "[[http://foobar|http://foobar]]"
        // and replace them with just "http://foobar". Pandoc is having trouble
        // converting these to HTML links.
        $rawContents = preg_replace(
            "#\[\[(?'url'https?://(?:[a-zA-Z0-9$-_@.&+!*(),]|%[0-9a-fA-F][0-9a-fA-F])+)\|(?P=url)]]#ms",
            '$1',
            $rawContents,
        );

        // Convert the Dokuwiki format to XHTML, so we can use DOM to parse it.
        $command = ['pandoc', '--from', 'dokuwiki', '--to', 'html', '--'];

        $process = ($this->processFactory)($command);
        $process->setInput($rawContents);
        $process->mustRun();

        // This character causes problems when encoding as JSON.
        $html = str_replace(['→'], ['-'], $process->getOutput());

        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="utf-8"?>' . $html);

        $xpath = new DOMXPath($dom);
        $title = $xpath->query('//h1[1] | //h2[1]')[0]->textContent ?? '';

        // This is the metadata list that appears at the top of the RFCs.
        $listItems = $xpath->query('/html/body/ul[1]/li');

        $metadata = [
            'wiki URL' => 'https://wiki.php.net/rfc/' . $rfcSlug,
            'slug' => $rfcSlug,
            'title' => $this->removeExcessWhitespace($title),
        ];

        /** @var DOMElement $item */
        foreach ($listItems as $item) {
            if (!preg_match('#<li>(.*)</li>#sm', $item->C14N(), $matches)) {
                continue;
            }

            $itemText = $this->removeExcessWhitespace($matches[1]);
            $itemText = preg_replace('#^<strong>(.*):</strong>#smU', '$1:', $itemText);

            $itemKeyValue = explode(': ', $itemText, 2);
            $itemKeyValue = array_map('trim', $itemKeyValue);
            $itemKeyValue = array_filter($itemKeyValue);

            if (count($itemKeyValue) === 0) {
                continue;
            }

            $rawKey = trim(strtolower(strip_tags($itemKeyValue[0])), '*');
            $rawValue = trim($itemKeyValue[1] ?? '', '*');

            // If $itemKeyValue has only 1 element, then we assume that it does
            // not have the characters ": " to split the string. In this case,
            // set the single item as the $rawValue and the key as "extra."
            if (count($itemKeyValue) === 1) {
                $rawKey = 'extra';
                $rawValue = array_merge(
                    $metadata['extra'] ?? [],
                    [$this->convertToRst(trim($itemKeyValue[0] ?? '', '*'))],
                );
            }

            $value = match ($rawKey) {
                'extra' => $rawValue,
                default => $this->convertToRst($rawValue),
            };

            $metadata[$rawKey] = $value;
        }

        ksort($metadata, SORT_NATURAL);

        return $metadata;
    }

    private function removeExcessWhitespace(string $value): string
    {
        $value = str_replace("\n", ' ', trim($value));

        return preg_replace('#\s{2,}#m', ' ', $value);
    }

    private function convertToRst(string $value): string
    {
        if ($value === '') {
            return $value;
        }

        // Rewrite internal Wiki URLs.
        $value = preg_replace(
            '#<a(.+)href="/?(rfc/[\w\-/]+)"(.*)>#imsU',
            '<a$1href="https://wiki.php.net/$2"$3>',
            $value
        );

        // If a mailto link has a word character directly in front of it, add
        // a space. The lack of a space causes some issues with pandoc.
        $value = preg_replace(
            '#((?<=\w)<a href="mailto:)#im',
            ' $1',
            $value,
        );

        $process = ($this->processFactory)(['pandoc', '--from', 'html', '--to', 'rst']);
        $process->setInput($value);
        $process->mustRun();

        return $this->removeExcessWhitespace($process->getOutput());
    }
}
