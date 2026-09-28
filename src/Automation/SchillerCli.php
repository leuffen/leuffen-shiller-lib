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
     * @param string $templateDir _tpl-Pfad; leer liest template_dir aus der .shiller.yml im Document Root.
     * @param string $root Document Root, standardmäßig docs im aktuellen Projekt.
     * @throws RuntimeException Bei ungültiger Konfiguration oder Installationsfehlern.
     * @see SchillerAutomation::init()
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
        $written = $this->automation($root, $templateDir)->init($this->tags($tags));
        echo implode("\n", $written) . "\n";
    }

    /**
     * Installiert ausgewählte Vorlagen erneut über bestehende Projektdateien.
     *
     * @param string $tags Kommagetrennte Tags; für install erforderlich.
     * @param string $templateDir _tpl-Pfad; leer liest template_dir aus .shiller.yml.
     * @param string $root Document Root, standardmäßig docs im aktuellen Projekt.
     * @throws RuntimeException Bei ungültiger Konfiguration oder Installationsfehlern.
     * @see SchillerAutomation::install()
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
        $written = $this->automation($root, $templateDir)->install($this->tags($tags));
        echo implode("\n", $written) . "\n";
    }

    private function automation(string $root, string $templateDir): SchillerAutomation
    {
        // Ein vorhandener Document Root wird kanonisch aufgelöst; bei init genügt sein vorhandenes Elternverzeichnis.
        if (is_link($root) || is_file($root)) {
            throw new RuntimeException("Invalid document root: $root");
        }
        $resolved = realpath($root);
        if ($resolved !== false && is_dir($resolved)) {
            $project = dirname($resolved);
            $documentName = basename($resolved);
            $documentPath = $resolved;
        } else {
            $project = realpath(dirname($root));
            $documentName = basename($root);
            if ($project === false || !is_dir($project) || $documentName === '.' || $documentName === '..' || $documentName === '') {
                throw new RuntimeException("Document root parent missing: $root");
            }
            $documentPath = $project . '/' . $documentName;
        }

        if ($templateDir === '') {
            $configFile = $documentPath . '/.shiller.yml';
            if (!is_file($configFile) || !is_readable($configFile)) {
                throw new RuntimeException("Cannot read template configuration: $configFile");
            }
            $config = @yaml_parse_file($configFile);
            $templateDir = is_array($config) ? ($config['template_dir'] ?? '') : '';
            if (!is_string($templateDir) || $templateDir === '') {
                throw new RuntimeException("Missing template_dir in $configFile");
            }
            // Konfigurationspfade sind immer relativ zur jeweiligen Website.
            if (!str_starts_with($templateDir, '/')) {
                $templateDir = $documentPath . '/' . $templateDir;
            }
        } elseif (!str_starts_with($templateDir, '/')) {
            // Ein expliziter Pfad bezieht sich auf die Projektwurzel.
            $templateDir = $project . '/' . $templateDir;
        }

        return new SchillerAutomation($project, $templateDir, $documentName);
    }

    private function tags(string $tags): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $tags)), fn(string $tag): bool => $tag !== ''));
    }
}
