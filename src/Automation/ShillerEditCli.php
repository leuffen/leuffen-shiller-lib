<?php

declare(strict_types=1);

namespace Leuffen\Shiller\Automation;

use Closure;
use Leuffen\Shiller\Ai\ShillerAiContextFactory;
use Phore\Cli\Annotation\CliCommand;
use Phore\Cli\Annotation\CliParameter;
use Phore\Cli\Annotation\CliScope;
use Phore\Log\Driver\PhoreConsoleLoggerDriver;
use Phore\Log\PhoreLogger;
use RuntimeException;

/** CLI-Einstieg fuer typisierte Change-Request-Erfassung. */
#[CliScope('edit')]
final class ShillerEditCli
{
    /**
     * Erstellt mit AiContext::struct() eine T_Changes-YAML.
     *
     * Ohne Pfade wird .shiller-edit.d/ als Quelle vorbereitet. Ein neu
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
     * Beispiel: shiller edit create --context briefing.md
     */
    #[CliCommand('create', 'Extrahiert Quotes und Tasks aus Kundenfeedback.', 'Liest standardmaessig .shiller-edit.d/ oder explizite Dateien und Verzeichnisse und erzeugt eine T_Changes-YAML.')]
    public function create(
        array $argv,
        #[CliParameter('output', 'Relativer YAML-Zielpfad')]
        string $output = '.shiller-edit.d/shiller-edit.yml',
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
        $logger = new PhoreLogger(new PhoreConsoleLoggerDriver());
        $logger->step('Starte Edit-Analyse mit konfiguriertem AI-Modell und Reasoning.');
        $aiContext = (new ShillerAiContextFactory($startDirectory))->create(
            additionalContextFiles: $contextFiles,
            model: $model,
            skillsDir: $skillsDir,
            debug: true,
        );
        $written = (new ShillerEditAction(
            $startDirectory,
            context: $aiContext,
            logger: $logger,
        ))->analyze($argv, output: $output);

        echo $written . PHP_EOL;
    }

    /**
     * Plant die Umsetzung bestehender Tasks, ohne Website-Dateien zu veraendern.
     *
     * Verwendet den Projektkontext und die Theme-Skills aus .shiller.yml.
     * Gibt die aktualisierte YAML-Datei mit Resolutions und offenen Fragen aus.
     *
     * @param string $input Relative Change-Request-YAML aus der Create-Phase.
     * @param string $projectDir Projektwurzel; leer nutzt das aktuelle Verzeichnis.
     * @param string $documentRoot Document Root relativ zur Projektwurzel.
     * @param string $context Weitere Kontextdateien, kommagetrennt.
     * @param string $skillsDir Optionaler Theme-Skills-Pfad.
     * @param string $model Optionaler Modell-Override aus .shiller.yml.
     * @throws RuntimeException Bei ungueltigen Tasks oder fehlenden Quellen.
     * @see ShillerResolveAction::resolve()
     *
     * Beispiel: shiller edit resolve --input .shiller-edit.d/shiller-edit.yml
     */
    #[CliCommand('resolve', 'Plant Loesungen fuer vorhandene Tasks.', 'Erzeugt Resolutions oder offene Fragen pro Task ohne Website-Dateien zu bearbeiten.')]
    public function resolve(
        #[CliParameter('input', 'Relative Shiller-Edit-YAML')]
        string $input = '.shiller-edit.d/shiller-edit.yml',
        #[CliParameter('project-dir', 'Projektwurzel; standardmaessig aktuelles Verzeichnis')]
        string $projectDir = '',
        #[CliParameter('document-root', 'Document Root relativ zum Projekt')]
        string $documentRoot = 'docs',
        #[CliParameter('context', 'Zusaetzliche Kontextdateien, kommagetrennt')]
        string $context = '',
        #[CliParameter('skills-dir', 'Optionaler Theme-Skills-Pfad')]
        string $skillsDir = '',
        #[CliParameter('model', 'AI-Modell fuer die Resolution-Planung')]
        string $model = '',
    ): void {
        $root = $projectDir !== '' ? $projectDir : getcwd();
        if ($root === false) {
            throw new RuntimeException('Cannot determine current directory.');
        }

        $contextFiles = array_values(array_filter(
            array_map('trim', explode(',', $context)),
            static fn(string $path): bool => $path !== '',
        ));
        $aiContext = (new ShillerAiContextFactory($root))->create(
            additionalContextFiles: $contextFiles,
            model: $model,
            skillsDir: $skillsDir,
            debug: true,
        );
        $written = (new ShillerResolveAction($root, context: $aiContext))->resolve($input, $documentRoot);

        echo $written . PHP_EOL;
    }

    /**
     * Ueberarbeitet einen Change Request im interaktiven Terminal.
     *
     * Die Eingabe verwendet phore/cli2 AskMultiLine. Mehrere Runden
     * koennen Tasks zusammenlegen und Quote-/Resolution-Zuordnungen
     * anpassen. Gespeichert wird erst mit :save.
     *
     * @param string $input Bestehendes Edit-YAML.
     * @param string $projectDir Projektwurzel, leer nutzt CWD.
     * @param string $documentRoot Lesbarer Website-Document-Root.
     * @param string $skillsDir Optionales Theme-Skills-Verzeichnis.
     * @param string $model Optionales AI-Modell.
     * @throws RuntimeException Bei ungueltigen Aenderungen.
     * @see ShillerRefineAction::run()
     *
     * Beispiel: shiller edit refine --input .shiller-edit.d/shiller-edit.yml
     */
    #[CliCommand('refine', 'Ueberarbeitet Tasks interaktiv.', 'Mehrzeilige Anweisungen, Merge von Tasks, :save, :undo, :quit.')]
    public function refine(
        #[CliParameter('input', 'Bestehende Edit-YAML')]
        string $input = '.shiller-edit.d/shiller-edit.yml',
        #[CliParameter('project-dir', 'Projektwurzel; standardmaessig CWD')]
        string $projectDir = '',
        #[CliParameter('document-root', 'Lesbarer Website-Document-Root')]
        string $documentRoot = 'docs',
        #[CliParameter('skills-dir', 'Theme-Skills-Verzeichnis')]
        string $skillsDir = '',
        #[CliParameter('model', 'AI-Modell')]
        string $model = '',
    ): void {
        $root = $projectDir !== '' ? $projectDir : getcwd();
        if ($root === false) {
            throw new RuntimeException('Cannot determine current directory.');
        }

        $written = (new ShillerRefineAction($root, model: $model, skillsDir: $skillsDir))
            ->run($input, $documentRoot);
        echo $written === '' ? "Refine abgebrochen.\n" : $written . PHP_EOL;
    }

    /**
     * Fuehrt vorhandene Resolutions taskweise aus und schreibt Diff-Historie.
     *
     * Ohne --yes wird vor jedem Task bestaetigt; --dry-run schreibt nicht.
     * Alle geaenderten Textdateien werden mit Task-ID, Dateipfad,
     * Hashes und Zeilen-Diffs in der Changes-YAML erfasst.
     *
     * @param string $input Bestehende Edit-YAML.
     * @param string $projectDir Projektwurzel, leer nutzt CWD.
     * @param string $changesFile YAML fuer Diff-Historie.
     * @param string $taskId Optional nur diesen Task ausfuehren.
     * @param bool $yes Vor Schreibvorgaengen nicht bestaetigen.
     * @param bool $dryRun Nur Vorschau ohne Dateioperationen.
     * @param string $skillsDir Optionales Theme-Skills-Verzeichnis.
     * @param string $model Optionales AI-Modell.
     * @throws RuntimeException Bei Konflikten oder ungueltigen Datei-Antworten.
     * @see ShillerExecuteAction::execute()
     *
     * Beispiel: shiller edit execute --task-id T-0001 --dry-run
     */
    #[CliCommand('execute', 'Setzt Resolutions taskweise um.', 'Schreibt Website-Dateien und zeilengenaue Changes-YAML nach Bestaetigung.')]
    public function execute(
        #[CliParameter('input', 'Bestehende Edit-YAML')]
        string $input = '.shiller-edit.d/shiller-edit.yml',
        #[CliParameter('project-dir', 'Projektwurzel; standardmaessig CWD')]
        string $projectDir = '',
        #[CliParameter('changes-file', 'YAML fuer ausgefuehrte Datei-Diffs')]
        string $changesFile = '.shiller-edit.d/shiller-edit-changes.yml',
        #[CliParameter('task-id', 'Optional genau einen Task ausfuehren')]
        string $taskId = '',
        #[CliParameter('yes', 'Jede Task-Aenderung ohne Rueckfrage anwenden')]
        bool $yes = false,
        #[CliParameter('dry-run', 'Nur Diff-Vorschau; nichts schreiben')]
        bool $dryRun = false,
        #[CliParameter('skills-dir', 'Theme-Skills-Verzeichnis')]
        string $skillsDir = '',
        #[CliParameter('model', 'AI-Modell')]
        string $model = '',
    ): void {
        $root = $projectDir !== '' ? $projectDir : getcwd();
        if ($root === false) {
            throw new RuntimeException('Cannot determine current directory.');
        }

        $written = (new ShillerExecuteAction($root, model: $model, skillsDir: $skillsDir))
            ->execute($input, $changesFile, $taskId, $yes, $dryRun);
        if ($written !== '') {
            echo $written . PHP_EOL;
        }
    }

    /**
     * Bereitet den Standard-Eingabeordner fuer einen parameterlosen Create-Aufruf vor.
     *
     * Existiert .shiller-edit.d/ bereits, wird sein Inhalt direkt verarbeitet.
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
        $directory = $root->withSubPath('.shiller-edit.d');

        $emit = $notify ?? static fn(string $text): int => fwrite(STDOUT, $text);
        if ($directory->exists()) {
            $directory->assertDirectory()->assertReadable();
            $emit("Lade Aenderungsinformationen aus .shiller-edit.d/.\n");
            return ['.shiller-edit.d'];
        }

        // Nach dem ersten Anlegen erhaelt der Nutzer Gelegenheit, die Quellen
        // hineinzukopieren. Ohne ein ausdrueckliches Y wird nicht analysiert.
        $directory->asDirectory()->mkdir();
        $emit("Das Verzeichnis .shiller-edit.d/ wurde angelegt.\n");
        $emit("Kopiere die Aenderungsinformationen (.md, .txt, .pdf, .jpg, .jpeg, .png) dorthin.\n");
        $emit("Wenn alle Dateien bereitliegen, Y eingeben und mit Enter bestaetigen: ");
        $answer = $readConfirmation !== null ? $readConfirmation() : fgets(STDIN);
        if (!is_string($answer) || strtoupper(trim($answer)) !== 'Y') {
            throw new RuntimeException('Edit abgebrochen: keine Bestaetigung mit Y.');
        }

        return ['.shiller-edit.d'];
    }

}
