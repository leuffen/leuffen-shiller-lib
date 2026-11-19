<?php

declare(strict_types=1);

namespace Leuffen\Shiller\Automation;

use Phore\Cli\Annotation\CliCommand;
use Phore\Cli\Annotation\CliParameter;
use Phore\Cli\Annotation\CliScope;
use Phore\Log\Driver\PhoreConsoleLoggerDriver;
use Phore\Log\PhoreLogger;

/** CLI subcommands for project context operations. */
#[CliScope(['shiller', 'context'])]
final class ShillerContextCli
{
    /**
     * Erstellt oder aktualisiert den zentralen Projektkontext.
     *
     * @param array<int, string> $argv Optionale Dateien oder Verzeichnisse.
     * @param string $focus Optionaler Schwerpunkt fuer die Informationsuebernahme.
     * @param string $skill Optionaler Context-Build-Skill.
     * @param string $model AI-Modell fuer phore/ai-harness.
     * @throws RuntimeException Bei ungueltiger Quelle.
     * @see ShillerContextAction::build()
     * @example shiller context build kundeninfo.pdf --focus "Nur Leistungen und Kontaktdaten"
     */
    #[CliCommand('build', 'Erstellt oder aktualisiert den Projektkontext.')]
    public function build(
        array $argv,
        #[CliParameter('focus', 'Optionaler Schwerpunkt fuer die Context-Uebernahme')]
        string $focus = '',
        #[CliParameter('skill', 'Optionaler Context-Build-Skill')]
        string $skill = '',
        #[CliParameter('model', 'AI-Modell')]
        string $model = ShillerAiDefaults::MODEL,
    ): void {
        foreach ($argv as $source) {
            if (!is_string($source) || trim($source) === '') {
                throw new RuntimeException('context build sources must be non-empty paths.');
            }
        }

        $sources = $argv === [] ? null : $argv;

        $startDirectory = getcwd();
        if ($startDirectory === false) {
            throw new RuntimeException('Cannot determine current directory.');
        }

        $processed = (new ShillerAutomationFactory($startDirectory))
            ->createContextAction($skill, $this->logger(), $model)
            ->build($sources, $focus);

        if ($processed !== []) {
            echo implode("\\n", $processed) . "\\n";
        }
    }

    private function logger(): PhoreLogger
    {
        return new PhoreLogger(new PhoreConsoleLoggerDriver());
    }
}
