<?php

declare(strict_types=1);

namespace Leuffen\Shiller\Automation;

use Phore\Cli\Annotation\CliCommand;
use Phore\Cli\Annotation\CliParameter;
use Phore\Cli\Annotation\CliScope;
use Phore\Log\Driver\PhoreConsoleLoggerDriver;
use Phore\Log\PhoreLogger;
use RuntimeException;

/** CLI adapter; discovery and automation rules live outside the CLI. */
#[CliScope('shiller')]
final class ShillerCli
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
     * @param string $select Kommagetrennte Dateipfade, Globs oder tag:<name>.
     * @param string $mode concurrent oder sequential.
     * @param string $context Zusaetzliche Kontextdateien relativ zur Projektwurzel.
     * @param string $skill Optionaler Basis-Skill; leer nutzt den mitgelieferten Skill.
     * @param string $model AI-Modell fuer phore/ai-harness.
     * @param string $templateDir _tpl-Pfad; leer liest template_dir aus .shiller.yml.
     * @param string $root Document Root.
     * @param string $event Event fuer _rules.d-on-Filter.
     * @param bool $debug Gibt die angewandten Rules mit Spezifitaet und Reihenfolge aus.
     * @throws RuntimeException Bei ungueltiger Konfiguration oder Anpassungsfehlern.
     * @see ShillerAutomationFactory::createContentAction()
     * @example shiller adapt --select "index.md,_data/general.yml" --mode concurrent
     */
    #[CliCommand('adapt', 'Passt installierte Inhalte per AI an.', 'Passt ausgewaehlte installierte Website-Inhalte mit phore/ai-harness an Projektkontext und aktive Rules an. Die Auswahl kann ueber Pfade, Globs oder Tags erfolgen.')]
    public function adapt(
        #[CliParameter('select', 'Kommagetrennte Dateipfade, Globs oder tag:<name>')]
        string $select = '',
        #[CliParameter('mode', 'concurrent oder sequential')]
        string $mode = 'concurrent',
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
        #[CliParameter('event', 'Rule-Event, z. B. edit, user-request oder upgrade')]
        string $event = 'edit',
        #[CliParameter('debug', 'Angewandte Rules vor dem AI-Request ausgeben')]
        bool $debug = false,
    ): void {
        if (!in_array($mode, ['concurrent', 'sequential'], true)) {
            throw new RuntimeException('mode must be concurrent or sequential.');
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
            ->adapt($this->csv($select), $mode === 'concurrent', $event, $debug);

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
     * Erstellt oder aktualisiert den zentralen Projektkontext aus einer Datei, einem Verzeichnis oder dem Raw-Kontext.
     *
     * Ohne Quelle verarbeitet build .shiller-context.d/raw und ueberspringt
     * dort bereits unveraenderte Dateien. Eine explizite Datei oder ein
     * explizites Verzeichnis wird bewusst erneut verarbeitet.
     *
     * @param list<string> $argv Erstes Argument build, optional gefolgt von genau einer Quelle.
     * @param string $focus Optionaler Schwerpunkt fuer die Informationsuebernahme.
     * @param string $skill Optionaler Context-Build-Skill.
     * @param string $model AI-Modell fuer phore/ai-harness.
     * @throws RuntimeException Bei ungueltiger Unteraktion oder Quelle.
     * @see ShillerContextAction::build()
     * @example shiller context build kundeninfo.pdf --focus "Nur Leistungen und Kontaktdaten"
     */
    #[CliCommand('context', 'Erstellt oder aktualisiert den Projektkontext.', 'Mit der Unteraktion build wird der zentrale Projektkontext aus einer Datei, einem Verzeichnis oder dem Raw-Kontext aufgebaut beziehungsweise aktualisiert.')]
    public function context(
        array $argv,
        #[CliParameter('focus', 'Optionaler Schwerpunkt fuer die Context-Uebernahme')]
        string $focus = '',
        #[CliParameter('skill', 'Optionaler Context-Build-Skill')]
        string $skill = '',
        #[CliParameter('model', 'AI-Modell')]
        string $model = 'gpt-5-mini',
    ): void {
        $subAction = array_shift($argv);
        if ($subAction !== 'build') {
            throw new RuntimeException('context requires the sub-action build.');
        }
        if (count($argv) > 1) {
            throw new RuntimeException('context build accepts at most one file or directory source.');
        }

        $source = array_shift($argv);
        if ($source !== null && (!is_string($source) || trim($source) === '')) {
            throw new RuntimeException('context build source must be a non-empty path.');
        }

        $startDirectory = getcwd();
        if ($startDirectory === false) {
            throw new RuntimeException('Cannot determine current directory.');
        }

        $processed = (new ShillerAutomationFactory($startDirectory))
            ->createContextAction(
                $skill,
                $this->logger(),
                $model,
            )
            ->build($source, $focus);

        if ($processed !== []) {
            echo implode("\n", $processed) . "\n";
        }
    }

    /**
     * Fuehrt AI-Unteraktionen aus; `adjust` passt ausgewaehlte Projektdateien anhand von Kontext und Rules an.
     *
     * Ohne weitere Optionen nutzt adjust den Standard-Document-Root, template_dir,
     * alle aktiven Markdown-Dateien aus .shiller-context.d, den mitgelieferten
     * Basis-Skill und gpt-5-mini. Das Unterverzeichnis raw/ wird nicht geladen.
     *
     * @param list<string> $argv Erstes Argument adjust, danach Dateinamen, Globs oder tag:<name>.
     * @param string $mode concurrent oder sequential.
     * @param string $context Zusaetzliche Kontextdateien relativ zur Projektwurzel.
     * @param string $skill Optionaler Basis-Skill.
     * @param string $model AI-Modell fuer phore/ai-harness.
     * @param string $templateDir Optionaler _tpl-Pfad.
     * @param string $root Document Root.
     * @param string $event Event fuer _rules.d-on-Filter.
     * @param bool $debug Gibt angewandte Rules, Spezifitaet und Reihenfolge vor dem Request aus.
     * @throws RuntimeException Bei ungueltiger Unteraktion oder fehlender Dateiauswahl.
     * @see ShillerContentAction::adapt()
     * @example shiller ai adjust index.md "_data/*.yml"
     */
    #[CliCommand('ai', 'Fuehrt AI-Unteraktionen fuer Projektdateien aus.', 'Mit der Unteraktion adjust werden ausgewaehlte Projektdateien anhand des Projektkontexts und der aktiven Rules angepasst. Dateinamen, Globs und Tag-Selektoren werden unterstuetzt.')]
    public function ai(
        array $argv,
        #[CliParameter('mode', 'concurrent oder sequential')]
        string $mode = 'concurrent',
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
        #[CliParameter('event', 'Rule-Event, z. B. edit, user-request oder upgrade')]
        string $event = 'edit',
        #[CliParameter('debug', 'Angewandte Rules vor dem AI-Request ausgeben')]
        bool $debug = false,
    ): void {
        $subAction = array_shift($argv);
        if ($subAction !== 'adjust') {
            throw new RuntimeException('ai requires the sub-action adjust.');
        }

        $selectors = $this->selectors($argv);
        if ($selectors === []) {
            throw new RuntimeException('ai adjust requires at least one filename, glob or tag selector.');
        }
        if (!in_array($mode, ['concurrent', 'sequential'], true)) {
            throw new RuntimeException('mode must be concurrent or sequential.');
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
            ->adapt($selectors, $mode === 'concurrent', $event, $debug);

        if ($written !== []) {
            echo implode("\n", $written) . "\n";
        }
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
