<?php

declare(strict_types=1);

namespace Leuffen\Schiller\Automation;

use RuntimeException;

/**
 * Erstellt eine SchillerAutomation aus einem Startverzeichnis und löst Projekt-, Document-Root- und Template-Pfade auf.
 */
final class SchillerAutomationFactory
{
    private readonly string $startDirectory;

    /**
     * Bindet das Startverzeichnis, relativ zu dem Document Roots und explizite Projektpfade ausgewertet werden.
     *
     * Das Startverzeichnis selbst wird nur als Auflösungsbasis verwendet. Die eigentliche Website-Konfiguration
     * liegt weiterhin im gewählten Document Root als .shiller.yml.
     *
     * @param string $startDirectory Vorhandenes und lesbares Startverzeichnis, typischerweise die Projektwurzel.
     * @throws RuntimeException Wenn das Startverzeichnis fehlt, nicht lesbar oder ein Symlink ist.
     * @see self::create()
     * @example $factory = new SchillerAutomationFactory('/srv/site');
     */
    public function __construct(string $startDirectory)
    {
        $absolute = (string) phore_uri('/')->withRelativePath((string) phore_uri($startDirectory)->abs());
        if (is_link($absolute)) {
            throw new RuntimeException("Start directory must not be a symlink: $startDirectory");
        }

        try {
            phore_dir($absolute)->assertDirectory()->assertReadable();
        } catch (\Throwable $exception) {
            throw new RuntimeException("Cannot read start directory: $startDirectory", 0, $exception);
        }

        $this->startDirectory = $absolute;
    }

    /**
     * Erzeugt die Automation und übernimmt die komplette Pfad- und Konfigurationsauflösung.
     *
     * Ein relativer Document Root wird gegen das Startverzeichnis aufgelöst; ein absoluter Document Root wird direkt
     * verwendet. Existiert der Document Root beim init noch nicht, muss sein Elternverzeichnis existieren. Ohne
     * expliziten Template-Pfad wird template_dir aus <document-root>/.shiller.yml gelesen und relativ zum Document
     * Root aufgelöst. Ein expliziter relativer Template-Pfad wird dagegen relativ zur Projektwurzel interpretiert.
     *
     * @param string $documentRoot Document Root relativ zum Startverzeichnis oder als absoluter Pfad.
     * @param string $templateDir Optionaler _tpl-Pfad; leer lädt template_dir aus .shiller.yml.
     * @return SchillerAutomation Fertig konfigurierte Automation für init() oder install().
     * @throws RuntimeException Bei ungültigen Pfaden, fehlender Konfiguration oder nicht lesbaren Verzeichnissen.
     * @see SchillerAutomation
     * @example $automation = (new SchillerAutomationFactory('/srv/site'))->create('docs', './node_modules/@leuffen/themejs2/_tpl');
     */
    public function create(string $documentRoot = 'docs', string $templateDir = ''): SchillerAutomation
    {
        $documentPath = $this->resolvePath($documentRoot, $this->startDirectory);
        if (is_link($documentPath)) {
            throw new RuntimeException("Document root must not be a symlink: $documentPath");
        }

        $documentUri = phore_uri($documentPath);
        if ($documentUri->exists()) {
            if (!$documentUri->isDirectory()) {
                throw new RuntimeException("Invalid document root: $documentPath");
            }

            try {
                $documentUri->assertDirectory()->assertReadable();
            } catch (\Throwable $exception) {
                throw new RuntimeException("Cannot read document root: $documentPath", 0, $exception);
            }
        } else {
            try {
                $documentUri->withParentDir()->assertDirectory()->assertReadable();
            } catch (\Throwable $exception) {
                throw new RuntimeException("Document root parent missing: $documentPath", 0, $exception);
            }
        }

        $projectRoot = (string) $documentUri->withParentDir();
        $documentName = $documentUri->getBasename();
        if ($documentName === '' || $documentName === '.' || $documentName === '..') {
            throw new RuntimeException("Invalid document root: $documentPath");
        }

        if ($templateDir === '') {
            $configFile = (string) $documentUri->join('.shiller.yml');

            try {
                $config = phore_file($configFile)->assertFile()->assertReadable()->get_yaml();
            } catch (\Throwable $exception) {
                throw new RuntimeException("Cannot read template configuration: $configFile", 0, $exception);
            }

            $templateDir = is_array($config) ? ($config['template_dir'] ?? '') : '';
            if (!is_string($templateDir) || $templateDir === '') {
                throw new RuntimeException("Missing template_dir in $configFile");
            }

            $templateDir = $this->resolvePath($templateDir, $documentPath);
        } else {
            $templateDir = $this->resolvePath($templateDir, $projectRoot);
        }

        return new SchillerAutomation($projectRoot, $templateDir, $documentName);
    }

    private function resolvePath(string $path, string $base): string
    {
        if ($path === '' || str_contains($path, "\0") || str_contains($path, '\\')) {
            throw new RuntimeException("Invalid path: $path");
        }

        if (str_starts_with($path, '/')) {
            return (string) phore_uri('/')->withRelativePath($path);
        }

        return (string) phore_uri($base)->withRelativePath($path);
    }
}
