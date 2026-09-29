<?php

declare(strict_types=1);

namespace Leuffen\Schiller\Automation;

use Phore\Cli\Annotation\CliParameter;
use Phore\Cli\Annotation\CliScope;
use Phore\Log\Driver\PhoreConsoleLoggerDriver;
use Phore\Log\PhoreLogger;
use RuntimeException;

/** CLI adapter; discovery and automation rules live outside the CLI. */
#[CliScope('schiller')]
final class SchillerCli
{
    /**
     * Kopiert _root und installiert optional ausgewaehlte Tags im Projekt.
     *
     * @param string $tags Kommagetrennte Tags, etwa base,theme:osman.
     * @param string $templateDir _tpl-Pfad; leer liest template_dir aus der .shiller.yml im Document Root.
     * @param string $root Document Root, standardmaessig docs im aktuellen Projekt.
     * @throws RuntimeException Bei ungueltiger Konfiguration oder Installationsfehlern.
     * @see SchillerAutomationFactory::create()
     * @example schiller init --template-dir ./node_modules/@leuffen/themejs2/_tpl --tags raven
     */
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

        $written = (new SchillerAutomationFactory($startDirectory))
            ->create($root, $templateDir)
            ->init($this->csv($tags));
        echo implode("\n", $written) . "\n";
    }

    /**
     * Installiert ausgewaehlte Vorlagen erneut ueber bestehende Projektdateien.
     *
     * @param string $tags Kommagetrennte Tags; fuer install erforderlich.
     * @param string $templateDir _tpl-Pfad; leer liest template_dir aus .shiller.yml.
     * @param string $root Document Root, standardmaessig docs im aktuellen Projekt.
     * @throws RuntimeException Bei ungueltiger Konfiguration oder Installationsfehlern.
     * @see SchillerAutomationFactory::create()
     * @example schiller install --tags raven
     */
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

        $written = (new SchillerAutomationFactory($startDirectory))
            ->create($root, $templateDir)
            ->install($this->csv($tags));
        echo implode("\n", $written) . "\n";
    }

    /**
     * Passt installierte Website-Inhalte mit phore/ai-harness an den Projektkontext an.
     *
     * @param string $select Kommagetrennte Dateipfade, Globs oder tag:<name>.
     * @param string $mode concurrent oder sequential.
     * @param string $context Zusaetzliche Kontextdateien relativ zur Projektwurzel.
     * @param string $skill Optionaler Basis-Skill; leer nutzt den mitgelieferten Skill.
     * @param string $model AI-Modell fuer phore/ai-harness.
     * @param string $templateDir _tpl-Pfad; leer liest template_dir aus .shiller.yml.
     * @param string $root Document Root.
     * @throws RuntimeException Bei ungueltiger Konfiguration oder Anpassungsfehlern.
     * @see SchillerAutomationFactory::createContentAction()
     * @example schiller adapt --select "index.md,_data/general.yml" --mode concurrent
     */
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
    ): void {
        if (!in_array($mode, ['concurrent', 'sequential'], true)) {
            throw new RuntimeException('mode must be concurrent or sequential.');
        }

        $startDirectory = getcwd();
        if ($startDirectory === false) {
            throw new RuntimeException('Cannot determine current directory.');
        }

        $written = (new SchillerAutomationFactory($startDirectory))
            ->createContentAction(
                $root,
                $templateDir,
                $this->csv($context),
                $skill,
                new PhoreLogger(new PhoreConsoleLoggerDriver()),
                $model,
            )
            ->adapt($this->csv($select), $mode === 'concurrent');

        if ($written !== []) {
            echo implode("\n", $written) . "\n";
        }
    }

    /**
     * Stellt eine oder mehrere Content-Dateien aus den installierten Originalvorlagen wieder her.
     *
     * @param list<string> $argv Dateinamen, Globs oder tag:<name>.
     * @param string $templateDir _tpl-Pfad; leer liest template_dir aus .shiller.yml.
     * @param string $root Document Root.
     * @throws RuntimeException Bei fehlender Dateiauswahl oder Restore-Fehlern.
     * @see SchillerAutomation::revert()
     * @example schiller revert index.md "_data/*.yml"
     */
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

        $written = (new SchillerAutomationFactory($startDirectory))
            ->create($root, $templateDir)
            ->revert($selectors);

        if ($written !== []) {
            echo implode("\n", $written) . "\n";
        }
    }

    /**
     * Fuehrt AI-Unteraktionen aus; aktuell ist adjust implementiert.
     *
     * Ohne weitere Optionen nutzt adjust den Standard-Document-Root, template_dir,
     * context_file, .shiller.d, den mitgelieferten Basis-Skill und gpt-5-mini.
     *
     * @param list<string> $argv Erstes Argument adjust, danach Dateinamen, Globs oder tag:<name>.
     * @param string $mode concurrent oder sequential.
     * @param string $context Zusaetzliche Kontextdateien relativ zur Projektwurzel.
     * @param string $skill Optionaler Basis-Skill.
     * @param string $model AI-Modell fuer phore/ai-harness.
     * @param string $templateDir Optionaler _tpl-Pfad.
     * @param string $root Document Root.
     * @throws RuntimeException Bei ungueltiger Unteraktion oder fehlender Dateiauswahl.
     * @see SchillerContentAction::adapt()
     * @example schiller ai adjust index.md "_data/*.yml"
     */
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

        $written = (new SchillerAutomationFactory($startDirectory))
            ->createContentAction(
                $root,
                $templateDir,
                $this->csv($context),
                $skill,
                new PhoreLogger(new PhoreConsoleLoggerDriver()),
                $model,
            )
            ->adapt($selectors, $mode === 'concurrent');

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

    /**
     * @return list<string>
     */
    private function csv(string $values): array
    {
        return array_values(array_filter(
            array_map('trim', explode(',', $values)),
            static fn (string $value): bool => $value !== '',
        ));
    }
}
