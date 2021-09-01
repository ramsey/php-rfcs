<?php

declare(strict_types=1);

namespace PhpRfcs\Rfc;

use PhpRfcs\ProcessFactory;
use PhpRfcs\Wiki\Metadata as WikiMetadata;

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
        '#^Ready for Review & Discussion$#i' => self::STATUS_DRAFT,
        '#^in the works$#i' => self::STATUS_DRAFT,
        '#^revising after v1\.0$#i' => self::STATUS_DRAFT,
        '#^updated stream_resolve_include_path\(\) was added in PHP 5\.3\.3$#i' => self::STATUS_IMPLEMENTED,

        // Accepted statuses.
        '#^`?Accepted#i' => self::STATUS_ACCEPTED,

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

    private array $rawMetadata;

    public function __construct(
        private ProcessFactory $processFactory,
        private WikiMetadata $wikiMetadata,
        private string $rawPath,
        private string $overridesPath,
    ) {
    }

    public function getMetadata(?string $rfcSlug): array
    {
        $rawData = $this->wikiMetadata->gatherMetadata($rfcSlug);
        $cleanData = [];

        foreach ($rawData as $slug => $rfcData) {
            $cleanData[$slug] = $this->applyDataOverrides(
                $this->cleanAndSanitizeMetadata($rfcData),
            );
        }

        // Sort the RFCs by date.
        array_multisort(array_column($cleanData, 'Date'), SORT_ASC, $cleanData);

        return $cleanData;
    }

    private function applyDataOverrides(array $cleanData): array
    {
        $overridesFile = $this->overridesPath . '/' . $cleanData['Slug'] . '.json';

        if (!file_exists($overridesFile)) {
            return $cleanData;
        }

        $overrides = json_decode(file_get_contents($overridesFile), true);
        $cleanWithOverrides = array_replace_recursive($cleanData, $overrides);

        $this->arrayWalkRecursiveArray($cleanWithOverrides, function (&$value, $key, &$array): void {
            if ($value === null) {
                unset($array[$key]);
            }
        });

        return $cleanWithOverrides;
    }

    private function cleanAndSanitizeMetadata(array $raw): array
    {
        $clean = [];

        foreach ($raw as $rawKey => $rawValue) {
            if ($rawKey === 'first published at' && str_contains($rawValue, '://wiki.php.net/rfc/' . $raw['slug'])) {
                // If the wiki URL is the same as "first published at," skip it.
                continue;
            }

            $cleanKey = match ($rawKey) {
                'author', 'author of rfc and creator of pr' => 'authors',
                'contributor' => 'contributors',
                'maintainer' => 'maintainers',
                'original author', 'based on previous rfc by', 'author of original patch' => 'original authors',
                'rfc version' => 'version',
                'target version', 'target php version', 'proposed version', 'proposed php version', 'target' => 'PHP version',
                default => $rawKey,
            };

            $cleanValue = match ($cleanKey) {
                'authors', 'contributors', 'original authors', 'maintainers' => $this->parseAuthors($rawValue),
                'date' => $this->parseDates($rawValue),
                'status' => $this->normalizeStatus($rawValue),
                'title' => $this->cleanTitle($rawValue),
                'version', 'PHP version' => $this->parseVersion($rawValue),
                default => $rawValue,
            };

            $clean[ucwords($cleanKey)] = $cleanValue;

            // Retain these original values, for historical purposes.
            if (in_array($cleanKey, ['status', 'date', 'version']) && $cleanValue !== $rawValue) {
                $clean[ucwords('original ' . $cleanKey)] = $rawValue;
            }
        }

        // Some RFC pages do not have a Date property. Use the earliest commit
        // date for the RFC in order to sort the RFCs properly.
        if (!array_key_exists('Date', $clean) || $clean['Date'] === '0000-00-00') {
            $clean['Date'] = $this->getEarliestCommitDateForRfc($clean['Slug']);
        }

        if (!array_key_exists('Status', $clean)) {
            $clean['Status'] = 'Unknown';
        }

        ksort($clean, SORT_NATURAL);

        return $clean;
    }

    private function cleanTitle(string $title): string
    {
        if (str_starts_with(strtolower($title), 'request for comments:')) {
            $title = trim(substr($title, 21));
        }

        if (str_starts_with(strtolower($title), 'php rfc:')) {
            $title = trim(substr($title, 8));
        }

        return ucwords($title);
    }

    private function parseAuthors(string $authorValue): array
    {
        // If there are any reStructured Text links in the value, remove them.
        $authorValue = preg_replace('#`(.*) <(.*)>`__#msU', '$1', $authorValue);

        // Do some initial clean up to help with parsing email addresses later.
        $authorValue = str_replace(['&lt;', '&gt;', '(', ')', '<', '>', ';'], '', $authorValue);

        $authorsResult = [];
        $orphanedEmails = [];
        $position = 0;

        // Put angle brackets around anything that looks like an email address.
        $authorValue = preg_replace(self::EMAIL_REGEX, '<$1>', $authorValue);

        // Add commas wherever there's a closing angle bracket (>) followed by
        // a space, since this likely means the entry is continuing with another
        // author. If there's an ampersand (&) surrounded by spaces, also add a
        // comma for the same reason.
        $authorValue = str_replace(
            ['> ', ' & ', '/ ', ' - '],
            ['>, ', ', ', ', ', ', '],
            $authorValue,
        );

        $previousEmail = null;
        foreach (explode(',', $authorValue) as $author) {
            // Find the email address and separate it from the name.
            preg_match('#<(.*)>#sm', $author, $matches);
            $name = trim($author);
            $email = trim($matches[1] ?? '');

            if ($position > 0) {
                $previousEmail = $authorsResult[$position - 1]['email'] ?? '';
            }

            if ($email !== '') {
                $name = trim(str_replace("<$email>", '', $name));
            }

            if ($email === '' && preg_match(self::EMAIL_REGEX, $name) === 1 && $previousEmail === '') {
                $orphanedEmails[$position ? $position - 1 : 0] = $name;
                continue;
            }

            if ($name === '' && preg_match(self::EMAIL_REGEX, $email) === 1 && $previousEmail === '') {
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

        return '1.0';
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
    private function getEarliestCommitDateForRfc(string $rfcSlug): string
    {
        $rfcFilePath = $this->rawPath . '/' . $rfcSlug . '.txt';

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

    private function arrayWalkRecursiveArray(array &$array, callable $callback): void
    {
        foreach ($array as $k => &$v) {
            if (is_array($v)) {
                $this->arrayWalkRecursiveArray($v, $callback);
            } else {
                $callback($v, $k, $array);
            }
        }
    }
}
