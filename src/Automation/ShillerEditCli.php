<?php

declare(strict_types=1);

namespace Leuffen\Shiller\Automation;

use Phore\Cli\Annotation\CliCommand;
use Phore\Cli\Annotation\CliParameter;
use Phore\Cli\Annotation\CliScope;
use RuntimeException;

/** CLI-Einstieg fuer die analysebasierte Change-Request-Erfassung. */
#[CliScope('shiller')]
final class ShillerEditCli
{
    /**
     * Erstellt aus Eingabetext, Dateien oder Verzeichnissen eine Change-Request-YAML.
     *
     * Ohne Pfad liest edit bis EOF von stdin; mit Pfaden werden alle enthaltenen
     * Dateien eingelesen. Es werden keine Website-Dateien geaendert.
     *
     * @param list<string> $argv Relative Datei- oder Verzeichnispfade.
     * @param string $output YAML-Ausgabedatei relativ zur Projektwurzel.
     * @param string $model AI-Modell fuer die Task-Erkennung.
     * @throws RuntimeException Bei fehlender Eingabe oder Analysefehlern.
     * @see ShillerEditAction::analyze()
     *
     * Beispiel: shiller edit kundenfeedback --output .shiller-edit/kunde.yml
     */
    #[CliCommand('edit', 'Erfasst Aenderungswuensche als Tasks.', 'Analysiert stdin oder Dateien/Verzeichnisse und schreibt einen Change Request mit referenzierten Textabschnitten als YAML. Fuehrt keine Aenderungen aus.')]
    public function edit(
        array $argv,
        #[CliParameter('output', 'Relativer YAML-Zielpfad')]
        string $output = '.shiller-edit/change-request.yml',
        #[CliParameter('model', 'AI-Modell fuer die Task-Extraktion')]
        string $model = ShillerAiDefaults::MODEL,
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

        $written = (new ShillerEditAction($startDirectory, model: $model))
            ->analyze($argv, $stdinText, $output);
        echo $written . PHP_EOL;
    }
}
