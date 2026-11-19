<?php

declare(strict_types=1);

namespace Leuffen\Shiller\Automation;

use Phore\Cli\Annotation\CliCommand;
use Phore\Cli\Annotation\CliParameter;
use Phore\Cli\Annotation\CliScope;
use Phore\Log\Driver\PhoreConsoleLoggerDriver;
use Phore\Log\PhoreLogger;

/** CLI subcommands for AI-assisted project file operations. */
#[CliScope(['shiller', 'ai'])]
final class ShillerAiCli
{
    /**
     * Passt ausgewaehlte Projektdateien anhand von Kontext und Skills an.
     *
     * @param array<int, string> $argv Dateinamen, Globs oder tag:<name>.
     * @throws RuntimeException Bei fehlender Dateiauswahl.
     * @see ShillerContentAction::adapt()
     * @example shiller ai adjust index.md "_data/*.yml"
     */
    #[CliCommand('adjust', 'Passt ausgewaehlte Projektdateien per AI an.')]
    public function adjust(
        array $argv,
        #[CliParameter('context', 'Zusaetzliche Kontextdateien, kommagetrennt')]
        string $context = '',
        #[CliParameter('skill', 'Optionaler Basis-Skill')]
        string $skill = '',
        #[CliParameter('model', 'AI-Modell')]
        string $model = ShillerAiDefaults::MODEL,
        #[CliParameter('template-dir', 'Pfad zum _tpl-Verzeichnis')]
        string $templateDir = '',
        #[CliParameter('root', 'Document Root')]
        string $root = 'docs',
        #[CliParameter('debug', 'Skill- und Dateilogging aktivieren')]
        bool $debug = false,
    ): void {
        $selectors = $this->selectors($argv);
        if ($selectors === []) {
            throw new RuntimeException('ai adjust requires at least one filename, glob or tag selector.');
        }

        $startDirectory = getcwd();
        if ($startDirectory === false) {
            throw new RuntimeException('Cannot determine current directory.');
        }

        $written = (new ShillerAutomationFactory($startDirectory))
            ->createContentAction(
                $root,
                $templateDir,
                $this->csv($context),
                $skill,
                $this->logger(),
                $model,
            )
            ->adapt($selectors, $debug);

        // Ergebnisse werden bereits unmittelbar nach jeder Datei ausgegeben.
    }

    /** @param array<int, string> $argv @return list<string> */
    private function selectors(array $argv): array
    {
        $selectors = [];
        foreach ($argv as $value) {
            if (is_string($value)) {
                $selectors = [...$selectors, ...$this->csv($value)];
            }
        }
        return $selectors;
    }

    /** @return list<string> */
    private function csv(string $values): array
    {
        return array_values(array_filter(
            array_map('trim', explode(',', $values)),
            static fn(string $value): bool => $value !== '',
        ));
    }

    private function logger(): PhoreLogger
    {
        return new PhoreLogger(new PhoreConsoleLoggerDriver());
    }
}
