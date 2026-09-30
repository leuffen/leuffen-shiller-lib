<?php

declare(strict_types=1);

namespace Leuffen\Shiller\Automation;

use Phore\FileSystem\Exception\FilesystemException;
use Phore\FileSystem\PhoreDirectory;
use Phore\Log\PhoreLogger;
use RuntimeException;

/**
 * Erstellt Template- und Content-Automation aus einem Startverzeichnis.
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
     * @see self::createContentAction()
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
     * Erstellt die AI-Harness-Action fuer bereits installierte Website-Inhalte.
     *
     * Die Konfiguration wird aus .shiller.yml im Document Root gelesen; als
     * Kompatibilitaetsfallback wird die Datei in der Projektwurzel akzeptiert.
     * context_file und context_dir werden relativ zum Ort dieser Konfiguration
     * aufgeloest. Zusaetzliche Kontextdateien sind relativ zur Projektwurzel.
     *
     * @param string $documentRoot Relativer oder absoluter Document Root.
     * @param string $templateDir Optionaler _tpl-Pfad; leer liest template_dir aus der Konfiguration.
     * @param list<string> $additionalContextFiles Zusaetzliche Kontextdateien relativ zur Projektwurzel.
     * @param string $skillFile Optionaler Basis-Skill; leer nutzt den mitgelieferten Skill.
     * @param PhoreLogger|null $logger Optionales phore/log-Logging.
     * @param string $model AI-Modell fuer phore/ai-harness.
     * @return ShillerContentAction Konfigurierte Content-Action.
     * @throws FilesystemException Bei Dateisystemfehlern.
     * @throws RuntimeException Bei fehlender oder ungueltiger Konfiguration.
     * @see ShillerContentAction
     * @example $action = (new ShillerAutomationFactory('/srv/site'))->createContentAction(); assert($action instanceof ShillerContentAction);
     */
    public function createContentAction(
        string $documentRoot = 'docs',
        string $templateDir = '',
        array $additionalContextFiles = [],
        string $skillFile = '',
        ?PhoreLogger $logger = null,
        string $model = 'gpt-5-mini',
    ): ShillerContentAction {
        $documentUri = str_starts_with($documentRoot, '/')
            ? phore_uri($documentRoot)->abs()
            : $this->startDirectory->withRelativePath($documentRoot);
        $documentUri->assertDirectory()->assertReadable();

        $projectRoot = $documentUri->withParentDir()->assertDirectory()->assertReadable();
        $configFile = $documentUri->withSubPath('.shiller.yml')->asFile();
        if (!$configFile->exists()) {
            $configFile = $projectRoot->withSubPath('.shiller.yml')->asFile();
        }
        $config = $configFile->get_yaml();
        if (!is_array($config)) {
            throw new RuntimeException("Invalid Shiller config: $configFile");
        }
        $configDir = $configFile->withParentDir()->assertDirectory()->assertReadable();

        if ($templateDir === '') {
            $templateDir = $config['template_dir'] ?? '';
            if (!is_string($templateDir) || $templateDir === '') {
                throw new RuntimeException("Missing template_dir in $configFile");
            }
            $templateUri = str_starts_with($templateDir, '/')
                ? phore_uri($templateDir)->abs()
                : $configDir->withRelativePath($templateDir);
        } else {
            $templateUri = str_starts_with($templateDir, '/')
                ? phore_uri($templateDir)->abs()
                : $projectRoot->withRelativePath($templateDir);
        }
        $templateUri->assertDirectory()->assertReadable();

        $contextFiles = [];
        $configuredContext = $config['context_file'] ?? '.shiller-context.txt';
        $configuredContext = is_string($configuredContext) ? [$configuredContext] : $configuredContext;
        if (!is_array($configuredContext)) {
            throw new RuntimeException("Invalid context_file in $configFile");
        }

        foreach ($configuredContext as $contextFile) {
            if (!is_string($contextFile) || $contextFile === '') {
                throw new RuntimeException("Invalid context_file in $configFile");
            }

            $candidate = str_starts_with($contextFile, '/')
                ? phore_uri($contextFile)->abs()
                : $configDir->withRelativePath($contextFile);
            if (!$candidate->exists() && !str_starts_with($contextFile, '/')) {
                $candidate = $projectRoot->withRelativePath($contextFile);
            }

            if ($candidate->exists()) {
                $contextFiles[] = (string) $candidate->assertFile()->assertReadable();
            } elseif (array_key_exists('context_file', $config)) {
                $candidate->assertFile()->assertReadable();
            }
        }

        $contextDirName = $config['context_dir'] ?? '.shiller.d';
        if (!is_string($contextDirName) || $contextDirName === '') {
            throw new RuntimeException("Invalid context_dir in $configFile");
        }

        $contextDir = str_starts_with($contextDirName, '/')
            ? phore_uri($contextDirName)->abs()
            : $configDir->withRelativePath($contextDirName);
        if (!$contextDir->exists() && !str_starts_with($contextDirName, '/')) {
            $contextDir = $projectRoot->withRelativePath($contextDirName);
        }
        if ($contextDir->exists()) {
            foreach ($contextDir->assertDirectory()->assertReadable()->listFiles(recursive: true, sort: 'path') as $contextFile) {
                $contextFiles[] = (string) $contextFile->assertReadable();
            }
        }

        foreach ($additionalContextFiles as $additionalContextFile) {
            if (!is_string($additionalContextFile) || $additionalContextFile === '') {
                throw new RuntimeException('Additional context filenames must be non-empty strings.');
            }

            $contextFiles[] = (string) $projectRoot
                ->withSubPath($projectRoot->assertRelativePath($additionalContextFile))
                ->assertFile()
                ->assertReadable();
        }

        $contextFiles = array_values(array_unique($contextFiles));
        if ($contextFiles === []) {
            throw new RuntimeException('No Shiller context files found.');
        }

        if ($skillFile === '') {
            $skillFile = dirname(__DIR__, 2) . '/resources/skills/adapt-content/SKILL.md';
        } elseif (!str_starts_with($skillFile, '/')) {
            $skillFile = (string) $projectRoot
                ->withSubPath($projectRoot->assertRelativePath($skillFile))
                ->assertFile()
                ->assertReadable();
        }

        return new ShillerContentAction(
            (string) $documentUri,
            (string) $templateUri,
            $contextFiles,
            $skillFile,
            $logger,
            $model,
        );
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
