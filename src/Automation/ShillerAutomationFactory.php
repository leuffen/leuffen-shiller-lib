<?php

declare(strict_types=1);

namespace Leuffen\Shiller\Automation;

use Phore\FileSystem\Exception\FilesystemException;
use Phore\FileSystem\PhoreDirectory;
use RuntimeException;

/**
 * Erstellt die Template-Automation aus einem Startverzeichnis.
 */
final class ShillerAutomationFactory
{
    private readonly PhoreDirectory $startDirectory;

    /**
     * Bindet das Startverzeichnis als Aufloesungsbasis.
     *
     * @param string $startDirectory Vorhandenes und lesbares Startverzeichnis.
     * @throws FilesystemException Bei ungueltigen oder nicht lesbaren Verzeichnissen.
     * @see self::create()
     * @example $factory = new ShillerAutomationFactory('/srv/site'); assert($factory instanceof ShillerAutomationFactory);
     */
    public function __construct(string $startDirectory)
    {
        $path = (string) phore_uri($startDirectory)->abs();
        $this->startDirectory = phore_dir($path, ['rootDir' => $path])
            ->assertDirectory()
            ->assertReadable();
    }

    /**
     * Loest Document Root, Projektwurzel, Konfiguration und Template-Pfad auf.
     *
     * Relative Pfade werden ueber die gebundenen Phore-Objekte abgeleitet.
     * Ohne expliziten Template-Pfad wird template_dir aus .shiller.yml gelesen.
     *
     * @param string $documentRoot Relativer oder absoluter Document Root.
     * @param string $templateDir Optionaler Template-Pfad.
     * @return ShillerAutomation Konfigurierte Automation.
     * @throws FilesystemException Bei Dateisystemfehlern.
     * @throws RuntimeException Wenn template_dir fehlt oder ungueltig ist.
     * @see ShillerAutomation
     * @example $automation = (new ShillerAutomationFactory('/srv/site'))->create('docs', './node_modules/theme/_tpl'); assert($automation instanceof ShillerAutomation);
     */
    public function create(string $documentRoot = 'docs', string $templateDir = ''): ShillerAutomation
    {
        $documentUri = str_starts_with($documentRoot, '/')
            ? phore_uri($documentRoot)->abs()
            : $this->startDirectory->withRelativePath($documentRoot);

        if ($documentUri->exists()) {
            $documentUri->assertDirectory()->assertReadable();
        } else {
            $documentUri->withParentDir()->assertDirectory()->assertReadable();
        }

        $projectRoot = $documentUri->withParentDir()->assertDirectory()->assertReadable();
        $documentName = $documentUri->getBasename();

        if ($templateDir === '') {
            $configFile = $documentUri->withSubPath('.shiller.yml')->asFile();
            $config = $configFile->get_yaml();
            $templateDir = is_array($config) ? ($config['template_dir'] ?? '') : '';

            if (!is_string($templateDir) || $templateDir === '') {
                throw new RuntimeException("Missing template_dir in $configFile");
            }

            $templateUri = str_starts_with($templateDir, '/')
                ? phore_uri($templateDir)->abs()
                : $documentUri->withRelativePath($templateDir);
        } else {
            $templateUri = str_starts_with($templateDir, '/')
                ? phore_uri($templateDir)->abs()
                : $projectRoot->withRelativePath($templateDir);
        }

        return new ShillerAutomation(
            (string) $projectRoot,
            (string) $templateUri,
            $documentName,
        );
    }
}
