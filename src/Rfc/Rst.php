<?php

declare(strict_types=1);

namespace PhpRfcs\Rfc;

use PhpRfcs\ProcessFactory;
use RuntimeException;

class Rst
{
    private const FILE_HEADER_REGEX = "#^(?:(?:(?:=|-|`|:|\.|'|\"|~|\^|_|\*|\+|\#){2,}\n)?(?:[^\n]+\n)(?:(=|-|`|:|\.|'|\"|~|\^|_|\*|\+|\#){2,}\n)(?:\n+- .+(?=\n\n))??)#sU";

    private ?array $cleanMetadata = null;

    public function __construct(
        private ProcessFactory $processFactory,
        private Metadata $rfcMetadata,
        private string $rawRfcsPath,
    ) {
    }

    public function generateRst(string $rfcSlug, ?string $cleanMetadataFile): string
    {
        $rawRfcFile = $this->rawRfcsPath . '/' . $rfcSlug . '.txt';

        if (!file_exists($rawRfcFile)) {
            throw new RuntimeException(
                "Could not find raw file for $rfcSlug; "
                . "perhaps you need to run: php bin/rfc.php wiki:save $rfcSlug"
            );
        }

        $fileContents = $this->preProcess(file_get_contents($rawRfcFile));

        $pandocProcess = ($this->processFactory)(['pandoc', '--from', 'dokuwiki', '--to', 'rst']);
        $pandocProcess->setInput($fileContents);
        $pandocProcess->mustRun();

        $rstContents = $pandocProcess->getOutput();

        return $this->postProcess($rstContents, $this->getCleanMetadata($rfcSlug, $cleanMetadataFile));
    }

    private function getCleanMetadata(string $rfcSlug, ?string $cleanMetadataFile): array
    {
        if ($this->cleanMetadata === null && $cleanMetadataFile !== null) {
            $this->cleanMetadata = json_decode(file_get_contents($cleanMetadataFile), true);
        }

        if ($this->cleanMetadata !== null) {
            return $this->cleanMetadata[$rfcSlug];
        }

        return $this->rfcMetadata->getMetadata($rfcSlug, null)[$rfcSlug];
    }

    private function preProcess(string $content): string
    {
        return $this->convertDoodle(
            $this->replaceWikiLineBreaks(
                trim($content),
            ),
        );
    }

    private function postProcess(string $content, array $metadata): string
    {
        return $this->addRfcMetadata(
            $this->convertBlockquotes($content),
            $metadata,
        );
    }

    private function replaceWikiLineBreaks(string $content): string
    {
        // There are instances where folks used the DokuWiki line break to
        // insert an extra line break into the text, presumably for formatting.
        return str_replace("\n\\\\\n", "\n\n", $content);
    }

    /**
     * @link https://www.dokuwiki.org/plugin:doodle4 Doodle Plugin
     */
    private function convertDoodle(string $content): string
    {
        $doodlePattern = "#<doodle(?'attributes'[^>]*)>(?'choices'.*)</doodle>#msU";
        $attributesPattern = "#((?'attr'[a-zA-Z]+)=\"(?'value'[^\"]*)\")#msU";

        if (!preg_match_all($doodlePattern, $content, $doodleMatches)) {
            return $content;
        }

        foreach ($doodleMatches[0] as $index => $value) {
            $doodleAttributes = $doodleMatches[1][$index] ?? '';
            $doodleChoices = $doodleMatches[2][$index] ?? '';
            $title = 'Voting Details';

            if (preg_match_all($attributesPattern, $doodleAttributes, $attributesMatches)) {
                $titleKey = array_search('title', $attributesMatches['attr']);

                if ($titleKey !== false) {
                    $title = $attributesMatches['value'][$titleKey];
                }
            }

            $choices = explode("\n", $doodleChoices);
            $choices = array_map('trim', $choices);
            $choices = array_map(fn (string $v): string => "  $v", $choices);
            $choices = implode("\n", $choices);

            $doodleReplacement = <<<DOODLE
                ==== Question: $title ====

                === Voting Choices ===

                $choices

                DOODLE;

            $content = str_replace($value, $doodleReplacement, $content);
        }

        return $content;
    }

    /**
     * @link https://www.dokuwiki.org/plugin:blockquote BlockQuote Plugin
     */
    private function convertBlockquotes(string $content): string
    {
        $pattern = '#<blockquote.*>(.*)</blockquote>#msU';

        if (!preg_match_all($pattern, $content, $matches)) {
            return $content;
        }

        $replacements = [];
        foreach ($matches[1] as $match) {
            $temp = trim($match);
            $temp = str_replace("\n\n", '||para||', $temp);
            $temp = str_replace("\n", ' ', $temp);
            $temp = explode('||para||', $temp);

            foreach ($temp as &$para) {
                $para = trim($para);
                $para = wordwrap($para, 68);
                $para = explode("\n", $para);
                foreach ($para as &$p) {
                    $p = '    ' . $p;
                }
                $para = rtrim(implode("\n", $para));
            }

            $replacements[] = implode("\n\n", $temp);
        }

        return str_replace($matches[0], $replacements, $content);
    }

    private function addRfcMetadata(string $content, array $metadata): string
    {
        // Add additional newlines to ensure the header expression matches.
        $content .= "\n\n";

        $adornment = '=';
        if (!preg_match(self::FILE_HEADER_REGEX, $content, $matches)) {
            throw new RuntimeException('Unable to parse file header');
        }

        if (count($matches) > 0) {
            $adornment = $matches[1];
        }

        $title = $metadata['Title'] ?? 'Title Error';
        $titleAdornment = str_repeat($adornment, strlen($title));

        $authors = [];
        foreach ($metadata['Authors'] ?? [] as $author) {
            $authors[] = implode(' ', array_filter([
                $author['name'] ?? null,
                isset($author['email']) ? "<{$author['email']}>" : null,
            ]));
        }

        $header = [
            $title,
            $titleAdornment,
            '',
            ':PHP-RFC: ' . ($metadata['PHP-RFC'] ?? '0000'),
            ':Title: ' . $title,
            ':Author: ' . ($authors ? implode(', ', $authors) : 'Unknown'),
            ':Status: ' . $metadata['Status'] ?? 'Unknown',
            ':Type: ' . $metadata['Type'] ?? 'Unknown',
            ':Created: ' . $metadata['Date'] ?? '0000-00-00',
        ];

        if (isset($metadata['PHP Version'])) {
            $header[] = ':PHP-Version: ' . $metadata['PHP Version'];
        }

        if (isset($metadata['Version'])) {
            $header[] = ':Version: ' . $metadata['Version'];
        }

        // Unset the following values, since we've already used them, and add
        // the remaining values as a document footer.
        unset(
            $metadata['Title'],
            $metadata['Authors'],
            $metadata['Status'],
            $metadata['Type'],
            $metadata['Date'],
            $metadata['PHP Version'],
            $metadata['Version'],
        );

        $footer = [];
        foreach ($metadata as $property => $value) {
            if (is_array($value)) {
                $value = implode(', ', $value);
            }

            $footer[] = ":$property: $value";
        }

        // Remove the existing header and replace it with the new one.
        // We do several trimming steps here to keep the output clean.
        $content = preg_replace(self::FILE_HEADER_REGEX, '', $content);
        $content = trim($content);
        $content = trim(implode("\n", $header)) . "\n\n" . $content;
        $content = trim($content);

        // Add the footer.
        if (count($footer) > 0) {
            $content .= "\n\n";
            $content .= "Additional Metadata\n";
            $content .= "-------------------\n";
            $content .= "\n";
            $content .= implode("\n", $footer);
        }

        return trim($content) . "\n";
    }
}
