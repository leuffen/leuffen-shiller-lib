<?php

declare(strict_types=1);

namespace Leuffen\Shiller\Ai;

use Leuffen\Shiller\Automation\ShillerAiDefaults;
use Phore\AiHarness\AiContext;
use Phore\AiHarness\PromptType\FilePrompt;
use Phore\AiHarness\PromptType\PromptDirectory;
use Phore\AiHarness\ToolType\WebAccessTool;
use Phore\FileSystem\Exception\FilesystemException;
use Phore\FileSystem\PhoreDirectory;
use Phore\FileSystem\PhoreFile;
use Phore\FileSystem\PhoreUri;
use RuntimeException;

/**
 * Gemeinsamer Builder fuer einen Shiller-AiContext mit Projektkontext und Skills.
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
     * @see self::create()
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
     * Erstellt einen wiederverwendbaren AiContext fuer eine Shiller-Action.
     *
     * Automatisch aktive Projektkontexte sind project.md und weitere Markdown-
     * Dateien direkt unter .shiller-context.d, aber keine raw/-Quellen oder
     * *.rules.md. Weitere Dateien und Skills koennen uebergeben werden.
     * Die AI-Konfiguration stammt aus .shiller.yml, die Argumente haben Vorrang.
     * Workflow-Skills werden nur fuer die dafuer vorgesehenen Actions geladen.
     *
     * @param list<string> $additionalContextFiles Projekt-relative oder absolute Kontextdateien.
     * @param string $model Explizites AI-Modell; leer nutzt ai.model oder Shiller-Default.
     * @param string $skillsDir Skills-Pfad; leer nutzt skills_dir oder ThemeJS2.
     * @param string $workflowSkillFile Nur fuer den eigenen Workflow zulaessiger Basis-Skill.
     * @param string|null $reasoningEffort Expliziter Reasoning-Aufwand oder ai.reasoning_effort.
     * @param bool|null $webAccess Web-Tool aktivieren oder ai.web_access verwenden.
     * @param bool $debug Debug-Logging des Harness aktivieren.
     * @param bool $requireContext Fehlt Projektkontext, eine Exception werfen.
     * @param bool $requireSkills Fehlt das Skill-Verzeichnis, eine Exception werfen.
     * Eingabeverzeichnisse und Runtime-Defaults werden aus ai.input_dirs,
     * ai.client, ai.timeout und ai.connect_timeout der .shiller.yml gelesen.
     * @return AiContext Konfigurierter AI-Harness-Kontext.
     * @throws FilesystemException Bei unlesbaren Konfigurations- oder Kontextdateien.
     * @throws RuntimeException Bei ungueltiger Konfiguration oder fehlenden Pflichtquellen.
     * @see AiContext::skills()
     *
     * Beispiel: $context = (new ShillerAiContextFactory('/srv/site'))->create(['briefing.md']);
     */
    public function create(
        array $additionalContextFiles = [],
        string $model = '',
        string $skillsDir = '',
        string $workflowSkillFile = '',
        ?string $reasoningEffort = null,
        ?bool $webAccess = null,
        bool $debug = false,
        bool $requireContext = false,
        bool $requireSkills = false,
    ): AiContext {
        // Konfiguration wird am gebundenen Projekt-Root gelesen.
        $configFile = $this->projectRoot->withSubPath('.shiller.yml')->asFile();
        $config = $configFile->exists() ? $configFile->get_yaml() : [];
        if (!is_array($config)) {
            throw new RuntimeException('Invalid Shiller config: ' . $configFile);
        }
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
        foreach ($this->contextFiles($additionalContextFiles, $requireContext) as $file) {
            $prompts[] = new FilePrompt(
                (string) $file,
                $file->get_contents(),
                'text/markdown',
                instructions: 'Verbindlicher Shiller-Projektkontext; nicht als Quelldatei bearbeiten.',
                allowInstructions: true,
            );
        }
        // Zusätzliche Quellen sind rein deklarativ in der Shiller-YAML
        // konfigurierbar: ai.input_dirs enthält Pfad und optionale Beschreibung.
        $inputDirectories = $ai['input_dirs'] ?? [];
        if (!is_array($inputDirectories) || !array_is_list($inputDirectories)) {
            throw new RuntimeException('Invalid ai.input_dirs in ' . $configFile);
        }
        foreach ($inputDirectories as $entry) {
            $source = is_string($entry) ? ['path' => $entry] : $entry;
            if (!is_array($source) || !is_string($source['path'] ?? null)
                || trim($source['path']) === ''
                || array_diff(array_keys($source), ['path', 'description', 'recursive']) !== []) {
                throw new RuntimeException('Invalid ai.input_dirs entry in ' . $configFile);
            }
            $description = $source['description'] ?? null;
            $recursive = $source['recursive'] ?? true;
            if (($description !== null && (!is_string($description) || trim($description) === ''))
                || !is_bool($recursive)) {
                throw new RuntimeException('Invalid ai.input_dirs metadata in ' . $configFile);
            }
            $uri = $this->resolvePath($source['path']);
            $prompts[] = new PromptDirectory(
                (string) $uri,
                description: $description,
                recursive: $recursive,
            );
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

        $context = new AiContext($prompts, [
            'client' => $client,
            'model' => $selectedModel,
            'reasoning' => ['effort' => $selectedReasoning],
            'timeout' => $timeout,
            'connect_timeout' => $connectTimeout,
            'debug_log' => $debug,
        ]);

        // Skills stammen aus dem Theme. Workflow-interne Skills sind opt-in.
        if ($workflowSkillFile !== '') {
            $skill = $this->resolveFile($workflowSkillFile);
            $context->skills()->add((string) $skill->withParentDir(), ['initialLoad' => true]);
        }
        $configuredSkills = $skillsDir !== '' ? $skillsDir : ($config['skills_dir'] ?? '');
        if (!is_string($configuredSkills)) {
            throw new RuntimeException('Invalid skills_dir in ' . $configFile);
        }
        $defaultSkills = $configuredSkills === '';
        $skillsPath = $defaultSkills
            ? 'node_modules/@leuffen/themejs2/skills'
            : $configuredSkills;
        $skillsUri = $this->resolvePath($skillsPath);

        if ($skillsUri->exists() || !$defaultSkills || $requireSkills) {
            $skills = $skillsUri->assertDirectory()->assertReadable();
            $context->skills()->addDirectory((string) $skills);
        }
        if ($requireSkills && $context->skills()->list() === []) {
            throw new RuntimeException('No skills available in ' . $skillsUri);
        }

        return $context;
    }

    /**
     * @param list<string> $additionalContextFiles
     * @return list<PhoreFile>
     */
    private function contextFiles(array $additionalContextFiles, bool $required): array
    {
        $files = [];
        $contextPath = $this->projectRoot->withSubPath('.shiller-context.d');
        if ($contextPath->exists()) {
            $dir = $contextPath->assertDirectory()->assertReadable();
            $projectFile = $dir->withSubPath('project.md')->asFile();
            if ($projectFile->exists()) {
                $files[(string) $projectFile] = $projectFile->assertReadable();
            }
            foreach ($dir->listFiles(recursive: false, sort: 'path') as $file) {
                $relative = str_replace('\\', '/', (string) $file->getRelPath($dir));
                if ($relative === 'project.md' || !str_ends_with(strtolower($relative), '.md')
                    || str_ends_with(strtolower($relative), '.rules.md')) {
                    continue;
                }
                $files[(string) $file] = $file->assertReadable();
            }
        }
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
