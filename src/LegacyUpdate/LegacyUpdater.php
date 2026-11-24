<?php

declare(strict_types=1);

namespace Leuffen\Shiller\LegacyUpdate;

use Phore\FileSystem\Exception\FilesystemException;

/** Initialisiert ausgecheckte ThemeJS1-Projekte für den Shiller-Zugriff. */
final class LegacyUpdater
{
    /**
     * Kopiert die Standardvorlagen aus _tpl, wenn .shiller.yml fehlt.
     * Vorhandene Dateien werden nie überschrieben; .shiller.yml entsteht zuletzt.
     * Es werden keine Website-Dateien oder alten Kontextdateien verändert.
     *
     * @param string $projectRoot Bereits ausgecheckte Website-Projektwurzel.
     * @return bool true bei neuer Konfiguration, false bei bereits vorhandener Datei.
     * @throws FilesystemException Bei ungültigen Pfaden oder Dateisystemfehlern.
     * @see \Leuffen\Shiller\Access\ShillerRepository
     * @example $initialized = (new LegacyUpdater())->update('/srv/site');
     */
    public function update(string $projectRoot): bool
    {
        $path = (string) phore_uri($projectRoot)->abs();
        $root = phore_dir($path, ['rootDir' => $path])->assertDirectory()->assertReadable();
        $config = $root->withSubPath('.shiller.yml')->asFile();
        if ($config->exists()) {
            return false;
        }

        $templatePath = __DIR__ . '/_tpl';
        $template = phore_dir($templatePath, ['rootDir' => $templatePath])->assertDirectory()->assertReadable();
        $content = $template->withSubPath('.shiller.yml')->asFile()->get_contents();

        // Alle Vorlagendateien übernehmen, ohne vorhandene Regeln zu überschreiben.
        foreach ($template->listFiles(recursive: true, sort: 'path') as $source) {
            $relative = (string) $source->getRelPath($template);
            if ($relative === '.shiller.yml') {
                continue;
            }

            $target = $root->withSubPath($relative)->asFile();
            if ($target->exists()) {
                $target->assertFile()->assertReadable();
                continue;
            }

            $target->mkdir()->set_contents($source->get_contents());
        }

        // Erst nach erfolgreichem Kopieren die Initialisierung markieren.
        $config->set_contents($content);

        return true;
    }
}
