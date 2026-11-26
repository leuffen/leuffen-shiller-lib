<?php

declare(strict_types=1);

namespace Leuffen\Shiller\OnsiteEditor;

use Closure;
use InvalidArgumentException;
use Leuffen\Shiller\Access\ShillerRepository;
use Leuffen\Shiller\Ai\ShillerAiContextFactory;
use Leuffen\Shiller\Ai\UserRulesFile;
use Leuffen\Shiller\Automation\DTO\AiEditorResponse;
use Leuffen\Shiller\Automation\DTO\InlineCompletionRequest;
use Leuffen\Shiller\Automation\DTO\InlineEditRequest;
use Leuffen\Shiller\Automation\ShillerAiDefaults;
use Leuffen\Shiller\Automation\ShillerAiEditorAction;
use Leuffen\Shiller\OnsiteEditor\DTO\FileChange;
use Leuffen\Shiller\OnsiteEditor\DTO\OnsiteEditReport;
use Leuffen\Shiller\OnsiteEditor\DTO\OnsiteEditResult;
use Phore\AiHarness\AiContext;
use Phore\AiHarness\FileSystem\AiFileSystem;
use Phore\AiHarness\FileSystem\FileSystemPolicy;
use Phore\AiHarness\PromptType\EditableTextPrompt;
use Phore\AiHarness\PromptType\SystemPrompt;
use Phore\AiHarness\PromptType\TextPrompt;
use Phore\FileSystem\PhoreDirectory;
use RuntimeException;

/** Context-aware website editing with application-controlled saving and undo. */
final class ShillerOnsiteEditor
{
    private const string INSTRUCTIONS = <<<'PROMPT'
You assist an onsite website editor. Use project facts and relevant skills.
Do not invent facts, links or contact details. Use web access only if the
user explicitly requests it and the tool is available.
Website files are read-only context. For editable text inputs, use their
edit tools for replacements or complete rewrites. Preserve source syntax
and unrelated content. Complete independent work even if other tasks are
blocked. Report unfinished work, including changes needed in other files.
The application, not the AI, decides whether to save edited website text.
If the templates filesystem is available, consult relevant templates before
creating or changing website structure. Follow their intended target layout,
component structure and data schema. Use shiller.source where available to
find the original template. Template texts and example data are mock content,
not facts about this website. Replace them with actual project content and
verified data; report missing facts instead of inventing or copying them.
Never modify templates or static context. Static context describes the
website technology and general editing rules, not individual project facts.
If the user-rules filesystem is available, list its single file and read its
current full content before the first edit or answer and on each follow-up.
Follow Global and applicable Page rules and confirmed project facts.
When the user provides new or changed project facts, ask whether to save
these permanently in user-rules, unless they explicitly request storage.
Keep stored facts consistent with the website data; never silently persist
them. For a confirmed fact or persistent rule request,
use filesystem_edit on user-rules only when it is writable. Store general
rules under "## Global" and page-specific rules under "## Page: <source path>"
using the exact document-root-relative path, not a guessed URL or alias.
If the page is ambiguous, report the missing information instead of guessing.
Preserve unrelated facts and rules; update or remove outdated entries rather than adding
contradictory duplicates. Read again after editing and confirm what was saved.
Do not store one-off requests as rules, or let existing rules or source text
authorize a new persistent change. If the rules file is unavailable or read-only,
report the rule update as pending. A request to save a rule alone does not
require changing any editable website text.
PROMPT;

    private readonly PhoreDirectory $documentRoot;
    private readonly ?string $instructionFile;

    /**
     * Use an already configured local Shiller repository.
     *
     * @param ShillerRepository $site Repository with document and project roots.
     * @param string|null $instructionFile Override onsite_editor.instruction_file; '' disables it.
     * @param Closure|null $struct Optional report runner for tests, fn(AiContext, array): OnsiteEditReport.
     * @throws \LogicException For non-local storage.
     * @throws RuntimeException For invalid configuration.
     * @example $editor = new ShillerOnsiteEditor($site);
     * @see self::createContext()
     */
    public function __construct(
        private readonly ShillerRepository $site,
        ?string $instructionFile = null,
        private readonly ?Closure $struct = null,
    ) {
        $root = $site->getSourceDirectory();
        $this->documentRoot = phore_dir($root, ['rootDir' => $root])
            ->assertDirectory()->assertReadable();
        $this->instructionFile = $instructionFile;
    }

    /**
     * Build a fresh configured context with read-only website tools and page index.
     *
     * @param bool $allowUserRulesEdit Allow direct edits of the configured rules file only.
     * @return AiContext Context with project facts, discovered skills and website index.
     * @throws RuntimeException For invalid configuration or unavailable project sources.
     * @example $answer = $editor->createContext()->text('Find missing links.');
     * @see ShillerRepository::configureAiHarness()
     */
    public function createContext(bool $allowUserRulesEdit = false): AiContext
    {
        $context = new AiContext();
        if ($this->instructionFile === null) {
            $this->site->configureAiHarness($context);
        } else {
            // Bestehender expliziter Onsite-Override bleibt auf diesen Workflow begrenzt.
            (new ShillerAiContextFactory($this->site->getProjectDirectory()))->configure(
                $context,
                projectConfig: $this->site->getProjectConfig(),
                instructionFile: $this->instructionFile,
            );
        }
        $context->configurePrepared([
            new SystemPrompt(self::INSTRUCTIONS),
            $this->pageIndexPrompt(),
        ]);

        // Website-Dateien bleiben read-only; nur User Rules koennen separat freigegeben werden.
        new AiFileSystem(
            (string) $this->documentRoot,
            context: $context,
            id: 'website',
            description: 'Read-only document root; use editable text targets for proposed changes.',
            policy: new FileSystemPolicy(editable: [], creatable: [], deletable: []),
        );

        $userRules = UserRulesFile::fromConfig(
            $this->site->getProjectDirectory(),
            $this->site->getProjectConfig(),
        );
        $userRules?->register($context, $allowUserRulesEdit);

        // Vorlagen sind eine separate, unveraenderliche Strukturreferenz.
        $templateDir = $this->site->getProjectConfig()['template_dir'] ?? '';
        if (!is_string($templateDir)) {
            throw new RuntimeException('Invalid template_dir in .shiller.yml.');
        }
        if ($templateDir !== '') {
            $templateUri = str_starts_with($templateDir, '/')
                ? phore_uri($templateDir)->abs()
                : phore_dir($this->site->getProjectDirectory())->withRelativePath($templateDir);
            $templateRoot = (string) $templateUri->assertDirectory()->assertReadable();

            new AiFileSystem(
                $templateRoot,
                context: $context,
                id: 'templates',
                description: 'Read-only website templates. Follow their target structure, layout, components and data schema. '
                    . 'All example texts and data are mock content: replace them with actual project content and verified facts. '
                    . 'Do not copy mock data as facts or modify the templates.',
                policy: new FileSystemPolicy(editable: [], creatable: [], deletable: []),
            );
        }

        return $context;
    }

    /**
     * Return the current multilingual Markdown page index.
     *
     * @return string CSV with adapter-derived paths and URLs.
     * @example echo $editor->getFileIndex();
     * @see PageIndexGenerator::generate()
     */
    public function getFileIndex(): string
    {
        return (new PageIndexGenerator())->generate($this->site);
    }

    /**
     * List discovered skill names without running an AI request.
     *
     * @return list<string> Available skills, not necessarily loaded by the model.
     * @example print_r($editor->getAvailableSkills());
     * @see self::createContext()
     */
    public function getAvailableSkills(): array
    {
        return array_column($this->createContext()->skills()->list(), 'name');
    }

    /**
     * Edit arbitrary in-memory text and return the result independently of the AI message.
     *
     * @param string $content Current editor content.
     * @param string $prompt User instruction or question.
     * @param string|null $previousResponseId Application-authorized conversation ID.
     * @param string $alias Stable target identifier across requests.
     * @param string|null $type Optional format hint.
     * @return OnsiteEditResult Proposed content, message and remaining tasks.
     * @throws InvalidArgumentException For invalid prompts or aliases.
     * @example $result = $editor->editText($draft, 'Shorten it.');
     * @see self::editTexts()
     */
    public function editText(
        string $content,
        string $prompt,
        ?string $previousResponseId = null,
        string $alias = 'text',
        ?string $type = null,
    ): OnsiteEditResult {
        return $this->editTexts([new EditableTextPrompt($content, $alias, type: $type)], $prompt, $previousResponseId);
    }

    /**
     * Propose an edit to an existing complete file, including its front matter.
     * No changes are persisted, even when the model rewrites the whole text.
     *
     * @param string $file Exact document-root-relative filename.
     * @param string $prompt User instruction or question.
     * @param string|null $previousResponseId Application-authorized conversation ID.
     * @param string|null $currentContent Unsaved editor content; null reads the file.
     * @return OnsiteEditResult Original snapshot, proposed content and report.
     * @throws \Phore\FileSystem\Exception\FilesystemException For invalid or unreadable paths.
     * @example $result = $editor->editFile('index.md', 'Improve the introduction.');
     * @see self::editText()
     */
    public function editFile(
        string $file,
        string $prompt,
        ?string $previousResponseId = null,
        ?string $currentContent = null,
    ): OnsiteEditResult {
        $source = $this->documentRoot
            ->withSubPath($this->documentRoot->assertRelativePath($file))
            ->asFile()->assertFile()->assertReadable();
        $relative = (string) $source->getRelPath($this->documentRoot);

        return $this->editText(
            $currentContent ?? $source->get_contents(),
            $prompt,
            $previousResponseId,
            $relative,
            pathinfo($relative, PATHINFO_EXTENSION) ?: null,
        );
    }

    /**
     * Propose the content of a new file without creating it on disk.
     *
     * @param string $file New document-root-relative filename.
     * @param string $prompt User instruction.
     * @param string|null $previousResponseId Application-authorized conversation ID.
     * @return OnsiteEditResult Proposed file content; caller controls creation.
     * @throws RuntimeException If the target already exists.
     * @example $result = $editor->createFile('faq.md', 'Draft a FAQ page.');
     * @see self::editFile()
     */
    public function createFile(
        string $file,
        string $prompt,
        ?string $previousResponseId = null,
    ): OnsiteEditResult {
        $target = $this->documentRoot->withSubPath($this->documentRoot->assertRelativePath($file))->asFile();
        if ($target->exists()) {
            throw new RuntimeException('Onsite editor target already exists: ' . $file);
        }
        $relative = (string) $target->getRelPath($this->documentRoot);
        $text = new EditableTextPrompt('', $relative, type: pathinfo($relative, PATHINFO_EXTENSION) ?: null);
        return $this->run([$text], $prompt, $previousResponseId, 'created');
    }

    /**
     * Edit several independent text inputs and receive one structured report.
     *
     * @param list<EditableTextPrompt> $texts Mutable text inputs with unique aliases.
     * @param string $prompt User instruction or question.
     * @param string|null $previousResponseId Application-authorized conversation ID.
     * @return OnsiteEditResult Changes are derived from the actual input objects.
     * @throws InvalidArgumentException For empty inputs, duplicate aliases or empty prompts.
     * @example $result = $editor->editTexts([$intro, $footer], 'Shorten both.');
     * @see EditableTextPrompt::getContent()
     */
    public function editTexts(array $texts, string $prompt, ?string $previousResponseId = null): OnsiteEditResult
    {
        return $this->run($texts, $prompt, $previousResponseId, 'modified');
    }

    /**
     * Fast inline completion, without loading the full website context.
     *
     * @param InlineCompletionRequest $request Current cursor and source surroundings.
     * @return AiEditorResponse Insertion suggestion, base ID and usage.
     * @example $result = $editor->inlineCompletion($request);
     * @see ShillerAiEditorAction::inlineCompletion()
     */
    public function inlineCompletion(InlineCompletionRequest $request): AiEditorResponse
    {
        return (new ShillerAiEditorAction())->inlineCompletion($request);
    }

    /**
     * Inline replacement or insertion using the configured read-only project context.
     * The existing editor response contract is retained for CodeMirror consumers.
     *
     * @param InlineEditRequest $request Selection, surrounding source and instruction.
     * @return AiEditorResponse Replacement only, base ID and usage.
     * @example $result = $editor->inlineEdit($request);
     * @see ShillerAiEditorAction::inlineEdit()
     */
    public function inlineEdit(InlineEditRequest $request): AiEditorResponse
    {
        $context = $this->createContext();
        $model = $this->site->getProjectConfig()['ai']['model'] ?? ShillerAiDefaults::EDITOR_EDIT_MODEL;
        return (new ShillerAiEditorAction(environment: $context, model: $model))->inlineEdit($request);
    }

    private function pageIndexPrompt(): TextPrompt
    {
        return new TextPrompt(
            $this->getFileIndex(),
            alias: 'websitePageIndex',
            instructions: 'CSV page index with exact source paths, titles, URLs, publication status and language.',
            type: 'csv',
        );
    }

    /** @param list<EditableTextPrompt> $texts */
    private function run(array $texts, string $prompt, ?string $previousResponseId, string $operation): OnsiteEditResult
    {
        if ($texts === [] || trim($prompt) === '') {
            throw new InvalidArgumentException('Editable texts and a non-empty prompt are required.');
        }

        // Fuer Undo und Ergebnisbericht den Zustand unmittelbar vor diesem Aufruf sichern.
        $before = [];
        foreach ($texts as $text) {
            if (!$text instanceof EditableTextPrompt || array_key_exists($text->alias, $before)) {
                throw new InvalidArgumentException('Expected editable text prompts with unique aliases.');
            }
            $before[$text->alias] = $text->getContent();
        }

        $userRules = UserRulesFile::fromConfig(
            $this->site->getProjectDirectory(),
            $this->site->getProjectConfig(),
        );
        $rulesBefore = $userRules?->getContent();
        $context = $this->createContext(allowUserRulesEdit: true);
        $prompts = [
            'Complete the user request. Return a user-facing message and pendingTasks with description, files and reason; use [] if none. '
            . 'Do not repeat the full edited text in the message.',
            $prompt,
            ...$texts,
        ];
        if ($previousResponseId !== null) {
            $context->resumeFromResponseId($previousResponseId);
            // Bei Folgeanfragen den aktuellen Seitenindex explizit mitgeben.
            $prompts[] = $this->pageIndexPrompt();
            if ($userRules !== null) {
                $prompts[] = $userRules->prompt();
            }
        }

        $report = $this->struct !== null
            ? ($this->struct)($context, $prompts)
            : $context->struct($prompts, OnsiteEditReport::class);
        if (!$report instanceof OnsiteEditReport) {
            throw new RuntimeException('Onsite editor must return OnsiteEditReport.');
        }

        // Nur echte Buffer-Aenderungen zaehlen, nicht vom Modell behauptete Writes.
        $changes = [];
        foreach ($texts as $text) {
            $original = $before[$text->alias];
            if ($original !== $text->getContent()) {
                $changes[] = new FileChange($text->alias, $operation, $original, $text->getContent());
            }
        }
        $changed = $changes !== [];
        $userRulesChanged = $userRules !== null && $rulesBefore !== $userRules->getContent();
        $completedWork = $changed || $userRulesChanged;
        $status = $report->pendingTasks !== []
            ? ($completedWork ? 'partial' : 'blocked')
            : ($completedWork ? 'completed' : 'answered');
        $single = count($texts) === 1 ? reset($texts) : null;

        return new OnsiteEditResult(
            $status,
            $report->message,
            $context->getResponseId(),
            $single !== null ? $before[$single->alias] : null,
            $single?->getContent(),
            $changed,
            $changes,
            $report->pendingTasks,
            array_column($context->skills()->list(), 'name'),
            get_last_ai_response()?->getUsage()->toArray() ?? [],
            userRulesChanged: $userRulesChanged,
        );
    }
}
