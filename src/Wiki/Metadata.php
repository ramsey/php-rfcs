<?php

declare(strict_types=1);

namespace PhpRfcs\Wiki;

use DOMDocument;
use DOMElement;
use DOMXPath;
use PhpRfcs\ProcessFactory;

class Metadata
{
    private const EMAIL_REGEX = '#([\w.\-]+(@| at |\.at\.|\#at\#)[\w.\-]+(( dot | \. )[\w.\-]+)*)#sm';

    private const STATUS_ACCEPTED = 'Accepted';
    private const STATUS_DECLINED = 'Declined';
    private const STATUS_DRAFT = 'Draft';
    private const STATUS_IMPLEMENTED = 'Implemented';
    private const STATUS_UNKNOWN = 'Unknown';
    private const STATUS_VOTING = 'Voting';
    private const STATUS_WITHDRAWN = 'Withdrawn';

    private const STATUS_NORMALIZING_EXPRESSIONS = [
        // These match full strings, so we'll keep them at the top of the list.
        '#^Draft - no content except a very old decision on the topic$#i' => self::STATUS_WITHDRAWN,
        '#^Draft\s?\(Inactive\)$#i' => self::STATUS_WITHDRAWN,
        '#^Meta-RFC$#i' => self::STATUS_DRAFT,
        '#^Partially Accepted \(in PHP 7\.0\)#i' => self::STATUS_ACCEPTED,
        '#^Passed Proposal 1\. 2 and 3 declined\.$#i' => self::STATUS_ACCEPTED,
        '#^Ready for Review &amp; Discussion$#i' => self::STATUS_DRAFT,
        '#^in the works$#i' => self::STATUS_DRAFT,
        '#^revising after v1\.0$#i' => self::STATUS_DRAFT,
        '#^updated stream_resolve_include_path\(\) was added in PHP 5\.3\.3$#i' => self::STATUS_IMPLEMENTED,

        // Accepted statuses.
        '#^(<a.*>)?Accepted#i' => self::STATUS_ACCEPTED,

        // Declined statuses.
        '#^(Declined|Rejected)#i' => self::STATUS_DECLINED,

        // Draft statuses.
        '#^(In |Under )?(Discussion|Draft|Brainstorming|Reopened|Started)#i' => self::STATUS_DRAFT,

        // Implemented statuses.
        '#^(<a.*>)?(Applied|Deprecation Implemented|`?Implemented|Merged)#i' => self::STATUS_IMPLEMENTED,

        // Voting statuses.
        '#^(In )?Voting#i' => self::STATUS_VOTING,

        // Withdrawn statuses.
        '#^(Abandoned|Closed|Dead|Inactive|Obsolete|Superseded|Suspended|Wid?thdrawn?)#i' => self::STATUS_WITHDRAWN,
    ];

    public function __construct(
        private ProcessFactory $processFactory,
        private string $rawPath,
    ) {
    }

    public function gatherMetadata(?string $rfcSlug): array
    {
        if ($rfcSlug !== null) {
            return [
                $this->parseMetadataFromFile(
                    $rfcSlug,
                    $this->rawPath . '/' . $rfcSlug . '.txt',
                ),
            ];
        }

        $metadata = [];

        foreach (glob($this->rawPath . '/*.txt') as $rawFile) {
            $slug = basename($rawFile, '.txt');
            $metadata[] = $this->parseMetadataFromFile($slug, $rawFile);
        }

        // Sort the RFCs by date.
        array_multisort(array_column($metadata, 'Date'), SORT_ASC, $metadata);

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
            'Wiki URL' => 'https://wiki.php.net/rfc/' . $rfcSlug,
            'Slug' => $rfcSlug,
            'Title' => $this->cleanTitle($title),
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

            if ($rawKey === 'first published at' && str_contains($rawValue, '://wiki.php.net/rfc/' . $rfcSlug)) {
                // If the wiki URL is the same as "first published at," skip it.
                continue;
            }

            // If $itemKeyValue has only 1 element, then we assume that it does
            // not have the characters ": " to split the string. In this case,
            // set the single item as the $rawValue and the key as "extra."
            if (count($itemKeyValue) === 1) {
                $rawKey = 'extra';
                $rawValue = array_merge(
                    $metadata['Extra'] ?? [],
                    [$this->convertTo('rst', trim($itemKeyValue[0] ?? '', '*'))],
                );
            }

            $key = match ($rawKey) {
                'author', 'author of rfc and creator of pr' => 'authors',
                'contributor' => 'contributors',
                'maintainer' => 'maintainers',
                'original author', 'based on previous rfc by', 'author of original patch' => 'original authors',
                'rfc version' => 'version',
                'target version', 'target php version', 'proposed version', 'proposed php version', 'target' => 'PHP version',
                default => $rawKey,
            };

            $value = match ($key) {
                'authors', 'contributors', 'original authors', 'maintainers' => $this->parseAuthors($rawValue),
                'date' => $this->parseDates($rawValue),
                'extra' => $rawValue,
                'status' => $this->normalizeStatus($rawValue),
                'version', 'PHP version' => $this->parseVersion($rawValue),
                default => $this->convertTo('rst', $rawValue),
            };

            $metadata[ucwords($key)] = $value;

            // Retain these original values, for historical purposes.
            if (in_array($key, ['status', 'date', 'version']) && $value !== $rawValue) {
                $metadata[ucwords('original ' . $key)] = $this->convertTo('rst', $rawValue);
            }
        }

        // Some RFC pages do not have a Date property. Use the earliest commit
        // date for the RFC in order to sort the RFCs properly.
        if (!array_key_exists('Date', $metadata) || $metadata['Date'] === '0000-00-00') {
            $metadata['Date'] = $this->getEarliestCommitDateForRfc($rawFile);
        }

        if (!array_key_exists('Status', $metadata)) {
            $metadata['Status'] = 'Unknown';
        }

        ksort($metadata, SORT_NATURAL);

        return $metadata;
    }

    private function removeExcessWhitespace(string $value): string
    {
        $value = str_replace("\n", ' ', trim($value));

        return preg_replace('#\s{2,}#m', ' ', $value);
    }

    private function cleanTitle(string $title): string
    {
        $title = $this->removeExcessWhitespace($title);

        if (str_starts_with(strtolower($title), 'request for comments:')) {
            $title = trim(substr($title, 21));
        }

        if (str_starts_with(strtolower($title), 'php rfc:')) {
            $title = trim(substr($title, 8));
        }

        return ucwords($title);
    }

    private function convertTo(string $format, string $value): string
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

        $process = ($this->processFactory)(['pandoc', '--from', 'html', '--to', $format]);
        $process->setInput($value);
        $process->mustRun();

        return $this->removeExcessWhitespace($process->getOutput());
    }

    private function parseAuthors(string $authorValue): array
    {
        // Do some initial clean up to help with parsing email addresses later.
        $authorValue = strip_tags($authorValue);
        $authorValue = str_replace(['&lt;', '&gt;', '(', ')', '<', '>', ';'], '', $authorValue);

        $authorMarkdown = $this->convertTo('markdown', $authorValue);
        $authorMarkdown = str_replace('\\@', '@', $authorMarkdown);

        $authorsResult = [];
        $orphanedEmails = [];
        $position = 0;

        // Put angle brackets around anything that looks like an email address.
        $authorMarkdown = preg_replace(self::EMAIL_REGEX, '<$1>', $authorMarkdown);

        // Add commas wherever there's a closing angle bracket (>) followed by
        // a space, since this likely means the entry is continuing with another
        // author. If there's an ampersand (&) surrounded by spaces, also add a
        // comma for the same reason.
        $authorMarkdown = str_replace(['> ', ' & '], ['>, ', ', '], $authorMarkdown);

        foreach (explode(',', $authorMarkdown) as $author) {
            // Find the email address and separate it from the name.
            preg_match('#<(.*)>#sm', $author, $matches);
            $name = trim($author);
            $email = trim($matches[1] ?? '');

            if ($email !== '') {
                $name = trim(str_replace("<$email>", '', $name));
            }

            if ($email === '' && preg_match(self::EMAIL_REGEX, $name) === 1) {
                $orphanedEmails[$position ? $position - 1 : 0] = $name;
                continue;
            }

            if ($name === '' && preg_match(self::EMAIL_REGEX, $email) === 1) {
                $orphanedEmails[$position ? $position - 1 : 0] = $email;
                continue;
            }

            if ($name === '' && $email === '') {
                continue;
            }

            if ($name) {
                $authorsResult[$position]['name'] = $name;
            }

            if ($email) {
                $authorsResult[$position]['email'] = $email;
            }

            $position++;
        }

        foreach ($orphanedEmails as $position => $email) {
            $authorsResult[$position]['email'] = trim(str_replace('\\@', '@', $email));
        }

        return $authorsResult;
    }

    private function parseDates(string $dateValue): string
    {
        if (preg_match_all('#(\d{4}-\d{2}-\d{2})#', $dateValue, $matches)) {
            sort($matches[1]);

            return $matches[1][0];
        }

        return '0000-00-00';
    }

    private function parseVersion(string $version): string
    {
        if (preg_match('#(\d*\.?\d*\.?\d+)#', $version, $matches)) {
            return $matches[1];
        }

        return '1';
    }

    private function normalizeStatus(string $status): string
    {
        foreach (self::STATUS_NORMALIZING_EXPRESSIONS as $expression => $normalizedStatus) {
            if (preg_match($expression, $status)) {
                return $normalizedStatus;
            }
        }

        return self::STATUS_UNKNOWN;
    }

    /**
     * @return string
     */
    private function getEarliestCommitDateForRfc(string $rfcFilePath): string
    {
        $logCommand = [
            'git',
            'log',
            '--pretty=format:%as',
            '--reverse',
            '--',
            $rfcFilePath,
        ];

        $logProcess = ($this->processFactory)($logCommand);
        $logProcess->mustRun();

        $logs = explode("\n", trim($logProcess->getOutput()));
        $logs = array_map('trim', $logs);
        $logs = array_filter($logs);

        if (count($logs) > 0) {
            // Use the first date found in the git log output.
            return $logs[0];
        }

        return '0000-00-00';
    }
}
