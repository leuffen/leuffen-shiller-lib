<?php

declare(strict_types=1);

namespace Leuffen\Shiller\LegacyUpdate;

use Phore\FileSystem\Exception\FilesystemException;

/** Initialisiert ausgecheckte ThemeJS1-Projekte für den Shiller-Zugriff. */
final class LegacyUpdater
{
    /**
     * Legt die Standardkonfiguration und User Rules an, wenn .shiller.yml fehlt.
     * Vorhandene Konfigurationen und Regeln werden nie überschrieben.
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

        $template = phore_file(__DIR__ . '/Tpl/.shiller.yml')->assertFile()->assertReadable();
        $content = $template->get_contents();

        // User Rules müssen existieren, bevor die neue Konfiguration darauf verweist.
        $rules = $root->withSubPath('.shiller-user-rules.md')->asFile();
        if (!$rules->exists()) {
            $rules->set_contents("## Global\n");
        } else {
            $rules->assertFile()->assertReadable();
        }

        $config->set_contents($content);

        return true;
    }
}
