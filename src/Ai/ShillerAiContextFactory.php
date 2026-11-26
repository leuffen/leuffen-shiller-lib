<?php

declare(strict_types=1);

namespace Leuffen\Shiller\Ai;

use Leuffen\Shiller\Automation\ShillerAiDefaults;
use Leuffen\Shiller\Access\Helper\ProjectConfig;
use Phore\AiHarness\AiContext;
use Phore\AiHarness\PromptType\FilePrompt;
use Phore\AiHarness\PromptType\SystemPrompt;
use Phore\AiHarness\ToolType\WebAccessTool;
use Phore\FileSystem\Exception\FilesystemException;
use Phore\FileSystem\PhoreDirectory;
use Phore\FileSystem\PhoreFile;
use Phore\FileSystem\PhoreUri;
use RuntimeException;

/**
 * Interner Konfigurator fuer den Shiller-AiContext mit Projektkontext und Skills.
 * @internal
 *
 * Liest die .shiller.yml und bereitet die Kontextdateien nur einmal je Lauf vor.
 * Ein Workflow-Skill (etwa adapt-content) wird ausschliesslich explizit geladen.
 */
final class ShillerAiContextFactory
{
    private readonly PhoreDirectory $projectRoot;

    /**
     * Bindet das Website-Projekt an einen sicheren Phore-Dateisystem-Root.
     *
     * @param string $projectRoot Projektverzeichnis mit optionaler .shiller.yml.
     * @throws FilesystemException Bei unlesbarer Projektwurzel.
     * @see self::configure()
     *
     * Beispiel: $factory = new ShillerAiContextFactory('/srv/site');
     */
    public function __construct(string $projectRoot)
    {
        $path = (string) phore_uri($projectRoot)->abs();
        $this->projectRoot = phore_dir($path, ['rootDir' => $path])
            ->assertDirectory()
            ->assertReadable();
    }

    /**
     * Konfiguriert einen frischen AiContext direkt ohne Dateisystem-Freigaben.
     *
     * context_files in .shiller.yml bestimmt die Projektkontext-Dateien.
     * Ohne Angabe wird nur eine vorhandene .shiller-context.d/project.md geladen.
     * Weitere Dateien und Skills koennen explizit uebergeben werden.
     * Die AI-Konfiguration stammt aus .shiller.yml, die Argumente haben Vorrang.
     * Workflow-Skills werden nur fuer die dafuer vorgesehenen Actions geladen.
     *
     * @param AiContext $context Vom Aufrufer bereitgestellte, noch nicht gestartete Instanz.
     * @param list<string> $additionalContextFiles Projekt-relative oder absolute Kontextdateien.
     * @param string $model Explizites AI-Modell; leer nutzt ai.model oder Shiller-Default.
     * @param string|list<string> $skillsDir Skills-Pfade; leerer String nutzt skills_dir oder ThemeJS2.
     * @param string $workflowSkillFile Nur fuer den eigenen Workflow zulaessiger Basis-Skill.
     * @param string|null $reasoningEffort Expliziter Reasoning-Aufwand oder ai.reasoning_effort.
     * @param bool|null $webAccess Web-Tool aktivieren oder ai.web_access verwenden.
     * @param bool|null $debug Expliziter Debug-Override, null erhaelt den bisherigen Wert.
     * @param bool $requireContext Fehlt Projektkontext, eine Exception werfen.
     * @param bool $requireSkills Fehlt das Skill-Verzeichnis, eine Exception werfen.
     * @param array<string, mixed>|null $projectConfig Bereits geladene Projektkonfiguration.
     * @param string|null $instructionFile Optionale vertrauenswuerdige System-Anweisung relativ zur Projektwurzel.
     * Runtime-Defaults werden aus ai.client, ai.timeout und
     * ai.connect_timeout der .shiller.yml gelesen.
     * @return AiContext Dieselbe konfigurierte Instanz. Keine Dateisystem-Tools werden registriert.
     * @throws FilesystemException Bei unlesbaren Konfigurations- oder Kontextdateien.
     * @throws RuntimeException Bei ungueltiger Konfiguration oder fehlenden Pflichtquellen.
     * @see AiContext::skills()
     *
     * Beispiel: (new ShillerAiContextFactory('/srv/site'))->configure($context);
     */
    public function configure(
        AiContext $context,
        array $additionalContextFiles = [],
        string $model = '',
        string|array $skillsDir = '',
        string $workflowSkillFile = '',
        ?string $reasoningEffort = null,
        ?bool $webAccess = null,
        ?bool $debug = null,
        bool $requireContext = false,
        bool $requireSkills = false,
        ?array $projectConfig = null,
        ?string $instructionFile = null,
    ): AiContext {
        // Konfiguration wird am gebundenen Projekt-Root gelesen.
        $configFile = $this->projectRoot->withSubPath('.shiller.yml')->asFile();
        $config = $projectConfig !== null || $configFile->exists()
            ? ProjectConfig::validate($projectConfig ?? $configFile->get_yaml(), (string) $configFile)
            : [];
        $ai = $config['ai'] ?? [];
        if (!is_array($ai)) {
            throw new RuntimeException('Invalid ai configuration in ' . $configFile);
        }

        $selectedModel = $model !== '' ? $model : ($ai['model'] ?? ShillerAiDefaults::MODEL);
        $selectedReasoning = $reasoningEffort ?? ($ai['reasoning_effort'] ?? 'medium');
        $selectedWebAccess = $webAccess ?? ($ai['web_access'] ?? false);
        if (!is_string($selectedModel) || trim($selectedModel) === '') {
            throw new RuntimeException('Invalid AI model in ' . $configFile);
        }
        if (!is_string($selectedReasoning)
            || !in_array($selectedReasoning, ['none', 'minimal', 'low', 'medium', 'high', 'xhigh'], true)) {
            throw new RuntimeException('Invalid ai.reasoning_effort in ' . $configFile);
        }
        if (!is_bool($selectedWebAccess)) {
            throw new RuntimeException('Invalid ai.web_access in ' . $configFile);
        }

        // Projektinformationen werden als vorbereiteter Context geladen.
        $prompts = [];
        foreach ($this->contextFiles($config, $additionalContextFiles, $requireContext) as $file) {
            $prompts[] = new FilePrompt(
                (string) $file,
                $file->get_contents(),
                'text/markdown',
                instructions: 'Verbindlicher Shiller-Projektkontext; nicht als Quelldatei bearbeiten.',
                allowInstructions: true,
            );
        }
        $userRules = UserRulesFile::fromConfig((string) $this->projectRoot, $config);
        if ($userRules !== null) {
            $prompts[] = $userRules->prompt();
        }
        // Die konfigurierte System-Anweisung gilt fuer jeden Shiller-AI-Workflow.
        $onsite = $config['onsite_editor'] ?? [];
        if (!is_array($onsite)) {
            throw new RuntimeException('Invalid onsite_editor configuration in ' . $configFile);
        }
        $instructionFile ??= $onsite['instruction_file'] ?? null;
        if ($instructionFile !== null && !is_string($instructionFile)) {
            throw new RuntimeException('Invalid onsite_editor.instruction_file in ' . $configFile);
        }
        if ($instructionFile !== null && $instructionFile !== '') {
            $prompts[] = new SystemPrompt($this->resolveFile($instructionFile)->get_contents());
        }
        if ($selectedWebAccess) {
            $prompts[] = new WebAccessTool();
        }

        $client = $ai['client'] ?? null;
        if ($client !== null && (!is_string($client) || trim($client) === '')) {
            throw new RuntimeException('Invalid ai.client in ' . $configFile);
        }
        $timeout = $ai['timeout'] ?? null;
        $connectTimeout = $ai['connect_timeout'] ?? null;
        if (($timeout !== null && (!is_int($timeout) || $timeout <= 0))
            || ($connectTimeout !== null && (!is_int($connectTimeout) || $connectTimeout <= 0))) {
            throw new RuntimeException('Invalid AI timeout in ' . $configFile);
        }

        // Nur explizit konfigurierte Client-/Timeout-Werte ersetzen injizierte Optionen.
        $options = [
            'model' => $selectedModel,
            'reasoning' => ['effort' => $selectedReasoning],
        ];
        foreach (['client' => $client, 'timeout' => $timeout, 'connect_timeout' => $connectTimeout, 'debug_log' => $debug] as $key => $value) {
            if ($value !== null) {
                $options[$key] = $value;
            }
        }
        $context->configurePrepared($prompts, $options, [WebAccessTool::class]);

        // Skills stammen aus dem Theme. Workflow-interne Skills sind opt-in.
        if ($workflowSkillFile !== '') {
            $skill = $this->resolveFile($workflowSkillFile);
            $context->skills()->add((string) $skill->withParentDir(), ['initialLoad' => true]);
        }
        $configuredSkills = $skillsDir !== '' ? $skillsDir : ($config['skills_dir'] ?? '');
        $defaultSkills = $configuredSkills === '';
        $paths = $defaultSkills
            ? ['node_modules/@leuffen/themejs2/skills']
            : (is_string($configuredSkills) ? [$configuredSkills] : $configuredSkills);
        if (!is_array($paths) || !array_is_list($paths)) {
            throw new RuntimeException('Invalid skills_dir in ' . $configFile);
        }
        foreach ($paths as $path) {
            if (!is_string($path) || trim($path) === '') {
                throw new RuntimeException('Invalid skills_dir in ' . $configFile);
            }
            $skillsUri = $this->resolvePath($path);
            if ($skillsUri->exists() || !$defaultSkills || $requireSkills) {
                $skills = $skillsUri->assertDirectory()->assertReadable();
                $context->skills()->addDirectory((string) $skills);
            }
        }
        if ($requireSkills && $context->skills()->list() === []) {
            throw new RuntimeException('No skills available for ' . $configFile);
        }

        return $context;
    }

    /**
     * Create a context for existing standalone automation callers.
     *
     * @internal All project configuration is delegated to configure().
     * @see configure()
     */
    public function create(
        array $additionalContextFiles = [],
        string $model = '',
        string|array $skillsDir = '',
        string $workflowSkillFile = '',
        ?string $reasoningEffort = null,
        ?bool $webAccess = null,
        bool $debug = false,
        bool $requireContext = false,
        bool $requireSkills = false,
        ?array $projectConfig = null,
        ?string $instructionFile = null,
    ): AiContext {
        return $this->configure(
            new AiContext(),
            additionalContextFiles: $additionalContextFiles,
            model: $model,
            skillsDir: $skillsDir,
            workflowSkillFile: $workflowSkillFile,
            reasoningEffort: $reasoningEffort,
            webAccess: $webAccess,
            debug: $debug,
            requireContext: $requireContext,
            requireSkills: $requireSkills,
            projectConfig: $projectConfig,
            instructionFile: $instructionFile,
        );
    }

    /**
     * @param array<string, mixed> $config
     * @param list<string> $additionalContextFiles
     * @return list<PhoreFile>
     */
    private function contextFiles(array $config, array $additionalContextFiles, bool $required): array
    {
        $files = [];
        // Nur konfigurierte Dateien laden; der Default ist eine einzelne
        // optionale Datei, keine automatische Verzeichnissuche.
        if (array_key_exists('context_files', $config)) {
            $paths = $config['context_files'];
            $paths = is_string($paths) ? [$paths] : $paths;
            if (!is_array($paths) || !array_is_list($paths)) {
                throw new RuntimeException('Invalid context_files in ' . $this->projectRoot . '/.shiller.yml');
            }
            foreach ($paths as $path) {
                if (!is_string($path) || trim($path) === '') {
                    throw new RuntimeException('context_files entries must be non-empty file paths in ' . $this->projectRoot . '/.shiller.yml');
                }
                $file = $this->resolveFile($path);
                $files[(string) $file] = $file;
            }
        } else {
            $projectFile = $this->projectRoot->withSubPath('.shiller-context.d/project.md')->asFile();
            if ($projectFile->exists()) {
                $files[(string) $projectFile] = $projectFile->assertFile()->assertReadable();
            }
        }

        // Explizite Quellen eines einzelnen Auftrags folgen dem Projektkontext.
        foreach ($additionalContextFiles as $path) {
            if (!is_string($path) || trim($path) === '') {
                throw new RuntimeException('Context file paths must be non-empty strings.');
            }
            $file = str_starts_with($path, '/')
                ? $this->resolveFile($path)
                : $this->projectRoot
                    ->withSubPath($this->projectRoot->assertRelativePath($path))
                    ->asFile()
                    ->assertFile()
                    ->assertReadable();
            $files[(string) $file] = $file;
        }
        if ($required && $files === []) {
            throw new RuntimeException('No Shiller project context available in ' . $this->projectRoot);
        }
        return array_values($files);
    }

    private function resolveFile(string $path): PhoreFile
    {
        $uri = $this->resolvePath($path);
        return $uri->asFile()->assertFile()->assertReadable();
    }

    /**
     * Konfigurationspfade aus .shiller.yml koennen auch ../theme enthalten.
     * Relative Pfade sind auf die Projektwurzel bezogen; keine nativen FS-APIs.
     */
    private function resolvePath(string $path): PhoreUri
    {
        if (str_starts_with($path, '/')) {
            return phore_uri($path)->abs();
        }

        return phore_dir((string) $this->projectRoot)->withRelativePath($path);
    }
}
