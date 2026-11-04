<?php

declare(strict_types=1);

namespace Leuffen\Shiller\Automation;

use Leuffen\Shiller\Ai\ShillerAiContextFactory;
use Phore\Cli\Annotation\CliCommand;
use Phore\Cli\Annotation\CliParameter;
use Phore\Cli\Annotation\CliScope;
use RuntimeException;

/** CLI-Einstieg fuer typisierte Change-Request-Erfassung. */
#[CliScope('shiller')]
final class ShillerEditCli
{
    /**
     * Erstellt mit AiContext::struct() eine T_Changes-YAML.
     *
     * Ohne Pfade liest edit bis EOF von stdin. Projektkontext, optionale
     * --context-Dateien sowie Theme-Skills werden von der Action geladen.
     *
     * @param list<string> $argv Relative Datei- oder Verzeichnispfade.
     * @param string $output YAML-Ziel relativ zur Projektwurzel.
     * @param string $context Weitere Kontextdateien, kommagetrennt.
     * @param string $skillsDir Optionaler Skills-Pfad (sonst skills_dir aus .shiller.yml).
     * @param string $model AI-Modell fuer die strukturierte Extraktion.
     * @throws RuntimeException Bei fehlender Eingabe oder Analysefehlern.
     * @see ShillerEditAction::analyze()
     *
     * Beispiel: shiller edit feedback/ --context briefing.md --output changes/kunde.yml
     */
    #[CliCommand('edit', 'Extrahiert Quotes und Tasks aus Kundenfeedback.', 'Liest stdin, Dateien oder Verzeichnisse und erzeugt eine typisierte T_Changes-YAML. Es werden keine Website-Dateien bearbeitet.')]
    public function edit(
        array $argv,
        #[CliParameter('output', 'Relativer YAML-Zielpfad')]
        string $output = '.shiller-edit/change-request.yml',
        #[CliParameter('context', 'Zusaetzliche Kontextdateien, kommagetrennt')]
        string $context = '',
        #[CliParameter('skills-dir', 'Optionaler Theme-Skills-Pfad')]
        string $skillsDir = '',
        #[CliParameter('model', 'AI-Modell fuer die Task-Extraktion')]
        string $model = '',
    ): void {
        $stdinText = null;
        if ($argv === []) {
            if (function_exists('stream_isatty') && stream_isatty(STDIN)) {
                fwrite(STDERR, "Aenderungswuensche eingeben; mit Strg+D abschliessen:\n");
            }
            $stdinText = stream_get_contents(STDIN);
            if (!is_string($stdinText) || trim($stdinText) === '') {
                throw new RuntimeException('No changes provided via stdin.');
            }
        }

        $startDirectory = getcwd();
        if ($startDirectory === false) {
            throw new RuntimeException('Cannot determine current directory.');
        }

        $contextFiles = array_values(array_filter(
            array_map('trim', explode(',', $context)),
            static fn(string $path): bool => $path !== '',
        ));
        $aiContext = (new ShillerAiContextFactory($startDirectory))->create(
            additionalContextFiles: $contextFiles,
            model: $model,
            skillsDir: $skillsDir,
        );
        $written = (new ShillerEditAction(
            $startDirectory,
            context: $aiContext,
        ))->analyze($argv, $stdinText, $output);

        echo $written . PHP_EOL;
    }
}
