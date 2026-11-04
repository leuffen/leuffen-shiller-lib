<?php

declare(strict_types=1);

namespace Leuffen\Shiller\Automation;

use Closure;
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
     * Ohne Pfade wird .schiller-edit.d/ als Quelle vorbereitet. Ein neu
     * angelegtes Verzeichnis wird erst nach Y-Bestaetigung analysiert.
     * Projektkontext, --context-Dateien und Skills kommen aus der Factory.
     *
     * @param list<string> $argv Relative Datei- oder Verzeichnispfade.
     * @param string $output YAML-Ziel relativ zur Projektwurzel.
     * @param string $context Weitere Kontextdateien, kommagetrennt.
     * @param string $skillsDir Optionaler Skills-Pfad (sonst skills_dir aus .shiller.yml).
     * @param string $model AI-Modell fuer die strukturierte Extraktion.
     * @throws RuntimeException Bei fehlender Eingabe oder Analysefehlern.
     * @see ShillerEditAction::analyze()
     *
     * Beispiel: shiller edit --context briefing.md
     */
    #[CliCommand('edit', 'Extrahiert Quotes und Tasks aus Kundenfeedback.', 'Liest standardmaessig .schiller-edit.d/ oder explizite Dateien und Verzeichnisse und erzeugt eine T_Changes-YAML.')]
    public function edit(
        array $argv,
        #[CliParameter('output', 'Relativer YAML-Zielpfad')]
        string $output = '.schiller-edit.d/shiller-edit.yml',
        #[CliParameter('context', 'Zusaetzliche Kontextdateien, kommagetrennt')]
        string $context = '',
        #[CliParameter('skills-dir', 'Optionaler Theme-Skills-Pfad')]
        string $skillsDir = '',
        #[CliParameter('model', 'AI-Modell fuer die Task-Extraktion')]
        string $model = '',
    ): void {
        $startDirectory = getcwd();
        if ($startDirectory === false) {
            throw new RuntimeException('Cannot determine current directory.');
        }

        if ($argv === []) {
            $argv = $this->prepareDefaultSources($startDirectory);
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
        ))->analyze($argv, output: $output);

        echo $written . PHP_EOL;
    }

    /**
     * Bereitet den Standard-Eingabeordner fuer einen parameterlosen Edit-Aufruf vor.
     *
     * Existiert .schiller-edit.d/ bereits, wird sein Inhalt direkt verarbeitet.
     * Andernfalls wird der Ordner angelegt und der Aufruf bis zur expliziten
     * Eingabe von Y unterbrochen. Bei Abbruch erfolgt keine AI-Anfrage.
     *
     * @param string $startDirectory Projektwurzel des CLI-Aufrufs.
     * @param Closure|null $readConfirmation Optionaler Eingabe-Callback fuer Tests.
     * @param Closure|null $notify Optionaler Ausgabe-Callback fuer Tests.
     * @return list<string> Quellverzeichnisse relativ zur Projektwurzel.
     * @throws RuntimeException Wenn die erforderliche Y-Bestaetigung ausbleibt.
     * @see ShillerEditAction::analyze()
     *
     * Beispiel: $paths = (new ShillerEditCli())->prepareDefaultSources('/srv/site');
     */
    public function prepareDefaultSources(
        string $startDirectory,
        ?Closure $readConfirmation = null,
        ?Closure $notify = null,
    ): array {
        $path = (string) phore_uri($startDirectory)->abs();
        $root = phore_dir($path, ['rootDir' => $path])->assertDirectory()->assertReadable();
        $directory = $root->withSubPath('.schiller-edit.d');

        $emit = $notify ?? static fn(string $text): int => fwrite(STDOUT, $text);
        if ($directory->exists()) {
            $directory->assertDirectory()->assertReadable();
            $emit("Lade Aenderungsinformationen aus .schiller-edit.d/.\n");
            return ['.schiller-edit.d'];
        }

        // Nach dem ersten Anlegen erhaelt der Nutzer Gelegenheit, die Quellen
        // hineinzukopieren. Ohne ein ausdrueckliches Y wird nicht analysiert.
        $directory->asDirectory()->mkdir();
        $emit("Das Verzeichnis .schiller-edit.d/ wurde angelegt.\n");
        $emit("Kopiere die Aenderungsinformationen (.md, .txt, .pdf, .jpg, .jpeg, .png) dorthin.\n");
        $emit("Wenn alle Dateien bereitliegen, Y eingeben und mit Enter bestaetigen: ");
        $answer = $readConfirmation !== null ? $readConfirmation() : fgets(STDIN);
        if (!is_string($answer) || strtoupper(trim($answer)) !== 'Y') {
            throw new RuntimeException('Edit abgebrochen: keine Bestaetigung mit Y.');
        }

        return ['.schiller-edit.d'];
    }

}
