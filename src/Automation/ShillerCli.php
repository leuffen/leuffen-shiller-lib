<?php

declare(strict_types=1);

namespace Leuffen\Shiller\Automation;

use Phore\Cli\Annotation\CliParameter;
use Phore\Cli\Annotation\CliScope;
use RuntimeException;

/** CLI adapter; discovery and installation rules live outside the CLI. */
#[CliScope('schiller')]
final class ShillerCli
{
    /**
     * Kopiert _root und installiert optional ausgewählte Tags im Projekt.
     *
     * @param string $tags Kommagetrennte Tags, etwa base,theme:osman.
     * @param string $templateDir _tpl-Pfad; leer liest template_dir aus der .shiller.yml im Document Root.
     * @param string $root Document Root, standardmäßig docs im aktuellen Projekt.
     * @throws RuntimeException Bei ungültiger Konfiguration oder Installationsfehlern.
     * @see ShillerAutomationFactory::create()
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

        $written = (new ShillerAutomationFactory($startDirectory))
            ->create($root, $templateDir)
            ->init($this->tags($tags));
        echo implode("\n", $written) . "\n";
    }

    /**
     * Installiert ausgewählte Vorlagen erneut über bestehende Projektdateien.
     *
     * @param string $tags Kommagetrennte Tags; für install erforderlich.
     * @param string $templateDir _tpl-Pfad; leer liest template_dir aus .shiller.yml.
     * @param string $root Document Root, standardmäßig docs im aktuellen Projekt.
     * @throws RuntimeException Bei ungültiger Konfiguration oder Installationsfehlern.
     * @see ShillerAutomationFactory::create()
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

        $written = (new ShillerAutomationFactory($startDirectory))
            ->create($root, $templateDir)
            ->install($this->tags($tags));
        echo implode("\n", $written) . "\n";
    }

    private function tags(string $tags): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $tags)), fn(string $tag): bool => $tag !== ''));
    }
}
