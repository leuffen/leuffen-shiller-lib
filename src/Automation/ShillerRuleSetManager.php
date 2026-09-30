<?php

declare(strict_types=1);

namespace Leuffen\Shiller\Automation;

use Phore\AiHarness\PromptType\FilePrompt;
use Phore\AiHarness\PromptType\TextPrompt;
use Phore\FileSystem\Exception\FilesystemException;
use Phore\FileSystem\PhoreDirectory;
use RuntimeException;

/**
 * Laedt _rules.d und loest die fuer eine Zieldatei geltenden Rule-Sets auf.
 */
final class ShillerRuleSetManager
{
    private readonly PhoreDirectory $documentRoot;
    private readonly ShillerContentSelector $contentSelector;

    /** @var list<array{source:string, selectors:list<string>, events:list<string>, important:bool, content:string}> */
    private array $definitions = [];

    /** @var array<string,list<string>> */
    private array $selectorMatches = [];

    /**
     * Bindet den Manager an einen Document Root und laedt dessen Rule-Dateien.
     *
     * Rule-Dateien liegen standardmaessig unter <document-root>/_rules.d/*.md.
     * Jede Datei benoetigt im Front Matter selector als String oder Liste.
     * on ist optional und kann ebenfalls String oder Liste sein; fehlt on, gilt
     * die Rule fuer jedes Event. important ist optional und standardmaessig false.
     *
     * @param string $documentRoot Vorhandener und lesbarer Document Root.
     * @param string $rulesDirectory Relatives Rule-Verzeichnis im Document Root.
     * @throws FilesystemException Bei Dateisystemfehlern.
     * @throws RuntimeException Bei ungueltigem Rule-Front-Matter.
     * @see self::getRulesFor()
     * @example $manager = new ShillerRuleSetManager('/srv/site/docs'); assert($manager instanceof ShillerRuleSetManager);
     */
    public function __construct(string $documentRoot, string $rulesDirectory = '_rules.d')
    {
        $path = (string) phore_uri($documentRoot)->abs();
        $this->documentRoot = phore_dir($path, ['rootDir' => $path])
            ->assertDirectory()
            ->assertReadable();
        $this->contentSelector = new ShillerContentSelector($path);

        $relativeRulesDirectory = $this->documentRoot->assertRelativePath($rulesDirectory);
        $rulesPath = $this->documentRoot->withSubPath($relativeRulesDirectory);
        if (!$rulesPath->exists()) {
            return;
        }

        $rulesRoot = $rulesPath->assertDirectory()->assertReadable();
        foreach ($rulesRoot->listFiles(recursive: true, sort: 'path') as $ruleFile) {
            $relative = str_replace('\\', '/', (string) $ruleFile->getRelPath($rulesRoot));
            if (!str_ends_with(strtolower($relative), '.md')) {
                continue;
            }

            $frontMatter = $ruleFile->get_front_matter(required: false);
            if ($frontMatter === null || !is_array($frontMatter->header)) {
                throw new RuntimeException("Rule front matter missing or invalid: {$ruleFile}");
            }

            $header = $frontMatter->header;
            $selectors = $this->stringList($header['selector'] ?? null, 'selector', (string) $ruleFile, false);

            // ext-yaml follows YAML 1.1 and may parse the unquoted key "on" as boolean true.
            $eventValue = array_key_exists('on', $header) ? $header['on'] : ($header[1] ?? null);
            $events = $this->stringList($eventValue, 'on', (string) $ruleFile, true);
            $important = $header['important'] ?? false;
            if (!is_bool($important)) {
                throw new RuntimeException("Rule important must be boolean: {$ruleFile}");
            }

            $this->definitions[] = [
                'source' => $rulesDirectory . '/' . $relative,
                'selectors' => $selectors,
                'events' => $events,
                'important' => $important,
                'content' => $frontMatter->content,
            ];
        }
    }

    /**
     * Liefert alle Rules, die auf Datei und Event passen, in Prompt-Reihenfolge.
     *
     * on wirkt als harter Filter: Eine Rule mit on wird bei anderen Events
     * vollstaendig ignoriert. Fuer mehrere passende Selector derselben Rule gilt
     * der mit der hoechsten Spezifitaet. Spezifitaet ist 1 geteilt durch die
     * Anzahl aktuell editierbarer Dateien, die der Selector trifft.
     *
     * Normale Rules werden von niedriger zu hoher Spezifitaet sortiert, danach
     * important-Rules ebenfalls von niedriger zu hoher Spezifitaet. Bei gleicher
     * Stufe entscheidet der Rule-Dateiname deterministisch ueber die Reihenfolge.
     *
     * @param string $relativeFile Existierende Zieldatei relativ zum Document Root.
     * @param string|null $event Aktuelles Event, etwa edit, user-request oder upgrade.
     * @return list<ShillerRuleMatch> Geordnete aufgeloeste Rules.
     * @throws FilesystemException Bei ungueltigem Dateizugriff.
     * @see self::getPromptsFor()
     * @example $rules = $manager->getRulesFor('index.md', 'edit'); assert(is_array($rules));
     */
    public function getRulesFor(string $relativeFile, ?string $event = null): array
    {
        $relativeFile = str_replace('\\', '/', $this->documentRoot->assertRelativePath($relativeFile));
        $this->documentRoot->withSubPath($relativeFile)->assertFile()->assertReadable();

        $resolved = [];
        foreach ($this->definitions as $definition) {
            if ($definition['events'] !== [] && ($event === null || !in_array($event, $definition['events'], true))) {
                continue;
            }

            $bestSelector = null;
            $bestCount = null;
            $bestSpecificity = null;

            foreach ($definition['selectors'] as $selector) {
                $matches = $this->matchesForSelector($selector);
                $matchCount = count($matches);
                if ($matchCount === 0 || !in_array($relativeFile, $matches, true)) {
                    continue;
                }

                $specificity = 1 / $matchCount;
                if ($bestSpecificity === null || $specificity > $bestSpecificity) {
                    $bestSelector = $selector;
                    $bestCount = $matchCount;
                    $bestSpecificity = $specificity;
                }
            }

            if ($bestSelector === null || $bestCount === null || $bestSpecificity === null) {
                continue;
            }

            $resolved[] = new ShillerRuleMatch(
                $definition['source'],
                $bestSelector,
                $bestCount,
                $bestSpecificity,
                $definition['important'],
                $definition['events'],
                $definition['content'],
            );
        }

        usort(
            $resolved,
            static function (ShillerRuleMatch $left, ShillerRuleMatch $right): int {
                if ($left->important !== $right->important) {
                    return $left->important <=> $right->important;
                }

                $specificity = $left->specificity <=> $right->specificity;
                if ($specificity !== 0) {
                    return $specificity;
                }

                return strcmp($left->source, $right->source);
            },
        );

        return $resolved;
    }

    /**
     * Baut die phore/ai-harness-Prompts fuer alle auf Datei und Event passenden Rules.
     *
     * Der erste Prompt beschreibt den Prioritaetsvertrag. Danach folgen die
     * Rule-Dateien in exakt derselben Reihenfolge wie getRulesFor(). Jede Rule
     * traegt Selector, Match-Anzahl, Spezifitaet und important als Metadaten.
     *
     * @param string $relativeFile Existierende Zieldatei relativ zum Document Root.
     * @param string|null $event Aktuelles Event; null aktiviert nur Rules ohne on.
     * @return list<TextPrompt|FilePrompt> Prompts fuer OpenAiPromptTypeConverter.
     * @throws FilesystemException Bei ungueltigem Dateizugriff.
     * @see self::getRulesFor()
     * @example $prompts = $manager->getPromptsFor('index.md', 'user-request'); assert(is_array($prompts));
     */
    public function getPromptsFor(string $relativeFile, ?string $event = null): array
    {
        $rules = $this->getRulesFor($relativeFile, $event);
        if ($rules === []) {
            return [];
        }

        $prompts = [
            new TextPrompt(
                'Die folgenden Shiller Rules gelten fuer targetFile. Wende alle Rules an. Bei Konflikten ueberschreibt eine Rule mit hoeherer specificity eine Rule mit niedrigerer specificity. important Rules ueberschreiben alle normalen Rules; unter important Rules gewinnt ebenfalls die hoehere specificity. Die Reihenfolge der folgenden Rule-Prompts ist bereits von niedriger zu hoher Prioritaet sortiert. important betrifft Bearbeitungsanweisungen und darf keine verbindlichen Fakten des Projektkontexts erfinden oder ersetzen.',
                alias: 'rulePriority',
                allowInstructions: true,
            ),
        ];

        foreach ($rules as $index => $rule) {
            $prompts[] = new FilePrompt(
                $rule->source,
                $rule->content,
                'text/markdown',
                alias: 'rule' . ($index + 1),
                instructions: sprintf(
                    'Shiller Rule fuer targetFile: selector=%s; matches=%d; specificity=%.12f; important=%s. Diese Metadaten bestimmen die Prioritaet der enthaltenen Bearbeitungsanweisungen.',
                    $rule->selector,
                    $rule->matchCount,
                    $rule->specificity,
                    $rule->important ? 'true' : 'false',
                ),
                allowInstructions: true,
            );
        }

        return $prompts;
    }

    /**
     * @return list<string>
     */
    private function matchesForSelector(string $selector): array
    {
        if (array_key_exists($selector, $this->selectorMatches)) {
            return $this->selectorMatches[$selector];
        }

        $matches = [];
        foreach ($this->contentSelector->select($selector) as $file) {
            $matches[] = str_replace('\\', '/', (string) $file->getRelPath($this->documentRoot));
        }

        return $this->selectorMatches[$selector] = $matches;
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value, string $field, string $source, bool $optional): array
    {
        if ($value === null && $optional) {
            return [];
        }

        $values = is_string($value) ? [$value] : $value;
        if (!is_array($values) || $values === []) {
            throw new RuntimeException("Rule {$field} must be a non-empty string or list: {$source}");
        }

        $normalized = [];
        foreach ($values as $item) {
            if (!is_string($item) || trim($item) === '') {
                throw new RuntimeException("Rule {$field} contains an invalid value: {$source}");
            }
            $normalized[] = trim($item);
        }

        return array_values(array_unique($normalized));
    }
}
