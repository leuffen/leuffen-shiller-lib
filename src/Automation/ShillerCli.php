<?php

declare(strict_types=1);

namespace Leuffen\Shiller\Automation;

use Phore\Cli\Annotation\CliCommand;
use Phore\Cli\Annotation\CliParameter;
use Phore\Cli\Annotation\CliScope;
use Phore\Log\Driver\PhoreConsoleLoggerDriver;
use Phore\Log\PhoreLogger;
use RuntimeException;

/** CLI command for the first project initialization step. */
#[CliScope('shiller')]
final class ShillerInitCli
{
    /**
     * Initialisiert ein Projekt aus _root und installiert optional ausgewaehlte Vorlagentags.
     *
     * @param string $tags Kommagetrennte Tags, etwa base,theme:osman.
     * @param string $templateDir _tpl-Pfad; leer liest template_dir aus der .shiller.yml im Projekt-Root.
     * @param string $root Document Root, standardmaessig docs im aktuellen Projekt.
     * @throws RuntimeException Bei ungueltiger Konfiguration oder Installationsfehlern.
     * @see ShillerAutomationFactory::create()
     * @example shiller init --template-dir ./node_modules/@leuffen/themejs2/_tpl --tags raven
     */
    #[CliCommand('init', 'Initialisiert ein Projekt aus dem Template.', 'Kopiert die _root-Basis in das Projekt und installiert optional ausgewaehlte Vorlagentags. Verwende init fuer die erstmalige Grundinitialisierung eines Projekts.')]
    public function init(
        #[CliParameter('tags', 'Kommagetrennte Vorlagentags')]
        string $tags = '',
        #[CliParameter('template-dir', 'Pfad zum _tpl-Verzeichnis')]
        string $templateDir = '',
        #[CliParameter('root', 'Document Root')]
        string $root = 'docs',
    ): void {
        $startDirectory = getcwd();
        if ($startDirectory === false) {
            throw new RuntimeException('Cannot determine current directory.');
        }

        $hooks = $this->loadInitHooks($startDirectory);
        $this->runInitHooks($hooks['before'], $startDirectory);

        $written = (new ShillerAutomationFactory($startDirectory))
            ->create($root, $templateDir, $this->logger())
            ->init($this->csv($tags));

        $this->runInitHooks($hooks['after'], $startDirectory);

        echo implode("\n", $written) . "\n";
    }

    /**
     * Laedt die konfigurierten Lifecycle-Kommandos fuer shiller init.
     *
     * @return array{before: list<string>, after: list<string>}
     */
    private function loadInitHooks(string $startDirectory): array
    {
        $projectDirectory = phore_dir($startDirectory, ['rootDir' => $startDirectory])
            ->assertDirectory()
            ->assertReadable();
        $configFile = $projectDirectory->withSubPath('.shiller.yml')->asFile();

        if (!$configFile->exists()) {
            return ['before' => [], 'after' => []];
        }

        $config = $configFile->get_yaml();
        if (!is_array($config)) {
            throw new RuntimeException("Invalid Shiller config: $configFile");
        }

        $hooks = [];
        foreach (['before', 'after'] as $phase) {
            $commands = $config['hooks']['init'][$phase] ?? [];
            if (!is_array($commands)) {
                throw new RuntimeException("hooks.init.$phase must be a list in $configFile");
            }

            $hooks[$phase] = [];
            foreach ($commands as $command) {
                if (!is_string($command) || trim($command) === '') {
                    throw new RuntimeException(
                        "hooks.init.$phase entries must be non-empty strings in $configFile",
                    );
                }
                $hooks[$phase][] = $command;
            }
        }

        return $hooks;
    }

    /**
     * @param list<string> $commands
     */
    private function runInitHooks(array $commands, string $startDirectory): void
    {
        foreach ($commands as $command) {
            $process = proc_open(
                $command,
                [0 => STDIN, 1 => STDOUT, 2 => STDERR],
                $pipes,
                $startDirectory,
            );
            if (!is_resource($process)) {
                throw new RuntimeException("Cannot start init hook: $command");
            }

            $exitCode = proc_close($process);
            if ($exitCode !== 0) {
                throw new RuntimeException("Init hook failed with exit code $exitCode: $command");
            }
        }
    }

    private function logger(): PhoreLogger
    {
        return new PhoreLogger(new PhoreConsoleLoggerDriver());
    }

    /**
     * @return list<string>
     */
    private function csv(string $values): array
    {
        return array_values(array_filter(
            array_map('trim', explode(',', $values)),
            static fn(string $value): bool => $value !== '',
        ));
    }
}

/** CLI adapter; discovery and automation rules live outside the CLI. */
#[CliScope('shiller')]
final class ShillerCli
{
    /**
     * Installiert gezielt ausgewaehlte Vorlagentags erneut ueber bestehende Projektdateien.
     *
     * @param string $tags Kommagetrennte Tags; fuer install erforderlich.
     * @param string $templateDir _tpl-Pfad; leer liest template_dir aus .shiller.yml.
     * @param string $root Document Root, standardmaessig docs im aktuellen Projekt.
     * @throws RuntimeException Bei ungueltiger Konfiguration oder Installationsfehlern.
     * @see ShillerAutomationFactory::create()
     * @example shiller install --tags raven
     */
    #[CliCommand('install', 'Installiert ausgewaehlte Vorlagentags.', 'Installiert die mit --tags ausgewaehlten Vorlagen gezielt in ein bereits initialisiertes Projekt und schreibt die zugehoerigen Projektdateien erneut.')]
    public function install(
        #[CliParameter('tags', 'Kommagetrennte Vorlagentags')]
        string $tags,
        #[CliParameter('template-dir', 'Pfad zum _tpl-Verzeichnis')]
        string $templateDir = '',
        #[CliParameter('root', 'Document Root')]
        string $root = 'docs',
    ): void {
        $startDirectory = getcwd();
        if ($startDirectory === false) {
            throw new RuntimeException('Cannot determine current directory.');
        }

        $written = (new ShillerAutomationFactory($startDirectory))
            ->create($root, $templateDir, $this->logger())
            ->install($this->csv($tags));
        echo implode("\n", $written) . "\n";
    }

    /**
     * Passt ausgewaehlte installierte Website-Inhalte direkt mit phore/ai-harness an den Projektkontext an.
     *
     * @param array<int, string> $argv Dateipfade, Globs oder tag:<name>.
     * @param string $select Zusaetzliche kommagetrennte Dateipfade, Globs oder tag:<name>.
     * @param string $context Zusaetzliche Kontextdateien relativ zur Projektwurzel.
     * @param string $skill Optionaler Basis-Skill; leer nutzt den mitgelieferten Skill.
     * @param string $model AI-Modell fuer phore/ai-harness.
     * @param string $templateDir _tpl-Pfad; leer liest template_dir aus .shiller.yml.
     * @param string $root Document Root.
     * @param bool $debug Aktiviert Skill- und Dateilogging.
     * @throws RuntimeException Bei ungueltiger Konfiguration oder Anpassungsfehlern.
     * @see ShillerAutomationFactory::createContentAction()
     * @example shiller adapt index.md "_data/*.yml" --debug
     */
    #[CliCommand('adapt', 'Passt installierte Inhalte per AI an.', 'Passt installierte Website-Inhalte mit AiContext und ThemeJS2-Skills an. Die Auswahl kann ueber Pfade, Globs oder Tags erfolgen.')]
    public function adapt(
        array $argv,
        #[CliParameter('select', 'Zusaetzliche kommagetrennte Dateipfade, Globs oder tag:<name>')]
        string $select = '',
        #[CliParameter('context', 'Zusaetzliche Kontextdateien, kommagetrennt')]
        string $context = '',
        #[CliParameter('skill', 'Optionaler Basis-Skill')]
        string $skill = '',
        #[CliParameter('model', 'AI-Modell')]
        string $model = 'gpt-5-mini',
        #[CliParameter('template-dir', 'Pfad zum _tpl-Verzeichnis')]
        string $templateDir = '',
        #[CliParameter('root', 'Document Root')]
        string $root = 'docs',
        #[CliParameter('debug', 'Skill- und Dateilogging aktivieren')]
        bool $debug = false,
    ): void {

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
            ->adapt([...$this->selectors($argv), ...$this->csv($select)], $debug);

        if ($written !== []) {
            echo implode("\n", $written) . "\n";
        }
    }

    /**
     * Stellt ausgewaehlte Content-Dateien aus den installierten Originalvorlagen wieder her und verwirft deren Anpassungen.
     *
     * @param list<string> $argv Dateinamen, Globs oder tag:<name>.
     * @param string $templateDir _tpl-Pfad; leer liest template_dir aus .shiller.yml.
     * @param string $root Document Root.
     * @throws RuntimeException Bei fehlender Dateiauswahl oder Restore-Fehlern.
     * @see ShillerAutomation::revert()
     * @example shiller revert index.md "_data/*.yml"
     */
    #[CliCommand('revert', 'Stellt installierte Originalvorlagen wieder her.', 'Verwirft Anpassungen an ausgewaehlten Content-Dateien und stellt deren Inhalt aus den installierten Originalvorlagen wieder her. Akzeptiert Dateinamen, Globs und Tag-Selektoren.')]
    public function revert(
        array $argv,
        #[CliParameter('template-dir', 'Pfad zum _tpl-Verzeichnis')]
        string $templateDir = '',
        #[CliParameter('root', 'Document Root')]
        string $root = 'docs',
    ): void {
        $selectors = $this->selectors($argv);
        if ($selectors === []) {
            throw new RuntimeException('revert requires at least one filename, glob or tag selector.');
        }

        $startDirectory = getcwd();
        if ($startDirectory === false) {
            throw new RuntimeException('Cannot determine current directory.');
        }

        $written = (new ShillerAutomationFactory($startDirectory))
            ->create($root, $templateDir, $this->logger())
            ->revert($selectors);

        if ($written !== []) {
            echo implode("\n", $written) . "\n";
        }
    }

    /**
     * @param list<string> $argv
     * @return list<string>
     */
    private function selectors(array $argv): array
    {
        $selectors = [];
        foreach ($argv as $value) {
            if (!is_string($value)) {
                continue;
            }
            $selectors = [...$selectors, ...$this->csv($value)];
        }
        return $selectors;
    }

    private function logger(): PhoreLogger
    {
        return new PhoreLogger(new PhoreConsoleLoggerDriver());
    }

    /**
     * @return list<string>
     */
    private function csv(string $values): array
    {
        return array_values(array_filter(
            array_map('trim', explode(',', $values)),
            static fn(string $value): bool => $value !== '',
        ));
    }
}

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
        string $model = 'gpt-5-mini',
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
        string $model = 'gpt-5-mini',
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
