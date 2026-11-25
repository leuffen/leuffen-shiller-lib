<?php

declare(strict_types=1);

namespace Leuffen\Shiller\LegacyUpdate;

use Phore\FileSystem\Exception\FilesystemException;
use Leuffen\Shiller\Access\Helper\Codec;

/** Initialisiert ausgecheckte ThemeJS1-Projekte für den Shiller-Zugriff. */
final class LegacyUpdater
{
    /**
     * Kopiert fehlende Standardvorlagen, wenn .shiller.yml oder deren Version fehlt.
     * Unversionierte Konfigurationen behalten ihre Werte und erhalten fehlende Defaults.
     * Andere vorhandene Dateien werden nie überschrieben; .shiller.yml wird zuletzt geschrieben.
     * Es werden keine Website-Dateien oder alten Kontextdateien verändert.
     *
     * @param string $projectRoot Bereits ausgecheckte Website-Projektwurzel.
     * @return bool true bei Initialisierung oder Migration, false bei vorhandener Version.
     * @throws FilesystemException Bei ungültigen Pfaden oder Dateisystemfehlern.
     * @see \Leuffen\Shiller\Access\ShillerRepository
     * @example $initialized = (new LegacyUpdater())->update('/srv/site');
     */
    public function update(string $projectRoot): bool
    {
        $path = (string) phore_uri($projectRoot)->abs();
        $root = phore_dir($path, ['rootDir' => $path])->assertDirectory()->assertReadable();
        $config = $root->withSubPath('.shiller.yml')->asFile();
        // Nur versionierte Konfigurationen gelten als bereits initialisiert.
        $existing = null;
        if ($config->exists()) {
            $current = $config->assertFile()->assertReadable()->get_contents();
            $existing = trim($current) === '' ? [] : Codec::yaml($current, (string) $config);
            if (array_key_exists('version', $existing)) {
                return false;
            }
        }

        $templatePath = __DIR__ . '/_tpl';
        $template = phore_dir($templatePath, ['rootDir' => $templatePath])->assertDirectory()->assertReadable();
        $content = $template->withSubPath('.shiller.yml')->asFile()->get_contents();

        // Bestehende Werte einschließlich verschachtelter AI-Einstellungen haben Vorrang.
        if ($existing !== null) {
            $defaults = Codec::yaml($content, (string) $template->withSubPath('.shiller.yml'));
            $content = Codec::emitYaml(array_replace_recursive($defaults, $existing));
        }

        // Nur neue Verzeichnisse erhalten 0777; die ursprüngliche umask wird auch bei Fehlern wiederhergestellt.
        $previousUmask = umask(0);
        try {
            // Vorhandene Vorlagendateien und ihre Rechte bleiben unverändert.
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

                $target->mkdir(0777)->set_contents($source->get_contents())->chmod(0666);
            }

            // Erst nach erfolgreichem Kopieren die Initialisierung mit den gewünschten Dateirechten markieren.
            $config->set_contents($content)->chmod(0666);
        } finally {
            umask($previousUmask);
        }

        return true;
    }
}
