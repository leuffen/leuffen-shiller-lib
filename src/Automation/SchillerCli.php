<?php

declare(strict_types=1);

namespace Leuffen\Schiller\Automation;

use Phore\Cli\Annotation\CliParameter;
use Phore\Cli\Annotation\CliScope;
use RuntimeException;

/** CLI adapter; the installation rules live in SchillerAutomation. */
#[CliScope('schiller')]
final class SchillerCli
{
    /**
     * Kopiert _root und installiert optional ausgewählte Tags im Projekt.
     *
     * @param string $tags Kommagetrennte Tags, etwa base,theme:osman.
     * @param string $templateDir _tpl-Pfad; leer liest template_dir aus .shiller.yml.
     * @param string $root Projektverzeichnis, standardmäßig das aktuelle Verzeichnis.
     * @throws RuntimeException Bei ungültiger Konfiguration oder Installationsfehlern.
     * @see SchillerAutomation::init()
     * @example schiller init --tags base,theme:osman
     */
    public function init(
        #[CliParameter('tags', 'Kommagetrennte Vorlagentags')]
        string $tags = '',
        #[CliParameter('template-dir', 'Pfad zum _tpl-Verzeichnis')]
        string $templateDir = '',
        #[CliParameter('root', 'Projektverzeichnis')]
        string $root = '.',
    ): void {
        $written = $this->automation($root, $templateDir)->init($this->tags($tags));
        echo implode("\n", $written) . "\n";
    }

    /**
     * Installiert ausgewählte Vorlagen erneut über bestehende Projektdateien.
     *
     * @param string $tags Kommagetrennte Tags; für install erforderlich.
     * @param string $templateDir _tpl-Pfad; leer liest template_dir aus .shiller.yml.
     * @param string $root Projektverzeichnis, standardmäßig das aktuelle Verzeichnis.
     * @throws RuntimeException Bei ungültiger Konfiguration oder Installationsfehlern.
     * @see SchillerAutomation::install()
     * @example schiller install --tags theme:osman
     */
    public function install(
        #[CliParameter('tags', 'Kommagetrennte Vorlagentags')]
        string $tags,
        #[CliParameter('template-dir', 'Pfad zum _tpl-Verzeichnis')]
        string $templateDir = '',
        #[CliParameter('root', 'Projektverzeichnis')]
        string $root = '.',
    ): void {
        $written = $this->automation($root, $templateDir)->install($this->tags($tags));
        echo implode("\n", $written) . "\n";
    }

    private function automation(string $root, string $templateDir): SchillerAutomation
    {
        $project = realpath($root);
        if ($project === false || !is_dir($project)) {
            throw new RuntimeException("Project directory missing: $root");
        }

        if ($templateDir === '') {
            $configFile = $project . '/.shiller.yml';
            if (!is_file($configFile) || !is_readable($configFile)) {
                throw new RuntimeException("Cannot read template configuration: $configFile");
            }
            $config = @yaml_parse_file($configFile);
            $templateDir = is_array($config) ? ($config['template_dir'] ?? '') : '';
            if (!is_string($templateDir) || $templateDir === '') {
                throw new RuntimeException("Missing template_dir in $configFile");
            }
        }

        if (!str_starts_with($templateDir, '/')) {
            $templateDir = $project . '/' . $templateDir;
        }

        return new SchillerAutomation($project, $templateDir);
    }

    private function tags(string $tags): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $tags)), fn(string $tag): bool => $tag !== ''));
    }
}
