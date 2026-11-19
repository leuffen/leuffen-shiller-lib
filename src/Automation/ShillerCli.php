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
        string $model = ShillerAiDefaults::MODEL,
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
