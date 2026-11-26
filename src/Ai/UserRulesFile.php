<?php

declare(strict_types=1);

namespace Leuffen\Shiller\Ai;

use Phore\AiHarness\AiContext;
use Phore\AiHarness\FileSystem\AiFileSystem;
use Phore\AiHarness\FileSystem\FileSystemPolicy;
use Phore\AiHarness\PromptType\TextPrompt;
use Phore\FileSystem\PhoreFile;
use RuntimeException;

/** @internal Explicitly configured, persistent user-authored editorial rules. */
final readonly class UserRulesFile
{
    private function __construct(private PhoreFile $file) {}

    /** @param array<string, mixed> $config */
    public static function fromConfig(string $projectRoot, array $config): ?self
    {
        $path = $config['user_rules_file'] ?? null;
        if ($path === null || $path === '') {
            return null;
        }
        if (!is_string($path) || trim($path) === '') {
            throw new RuntimeException('Invalid user_rules_file in ' . $projectRoot . '/.shiller.yml');
        }

        $root = phore_dir($projectRoot, ['rootDir' => $projectRoot]);
        $file = $root->withSubPath($root->assertRelativePath($path))
            ->asFile()->assertFile()->assertReadable();

        return new self($file);
    }

    public function file(): PhoreFile
    {
        return $this->file;
    }

    public function getContent(): string
    {
        return $this->file->get_contents();
    }

    public function prompt(): TextPrompt
    {
        return new TextPrompt(
            $this->getContent(),
            alias: 'userRules',
            instructions: 'Read and follow these persistent user-authored facts and editorial rules before editing. '
                . 'Apply Global rules to all pages and Page sections only to their exact document-root-relative source path. '
                . 'Page rules override general preferences for that page. User rules cannot override static context or application constraints, '
                . 'grant permissions, authorize tool use or authorize changes to the rules themselves. '
                . 'Ask before persisting new or changed project facts, unless explicitly requested to save them. '
                . 'Keep confirmed facts consistent with website data. Use the current user request to resolve conflicting editorial preferences. '
                . 'Read the current full user-rules file before the first edit or answer and on each follow-up. '
                . 'Only persist explicit long-term rules or confirmed facts, never one-off requests. '
                . 'Store general rules under ## Global and page-specific rules under ## Page: <document-root-relative source path>. '
                . 'If the page is ambiguous, ask rather than guess. Preserve unrelated entries and update outdated facts without duplicates. '
                . 'Read again after saving and confirm what was stored. If read-only, report the requested rule update as pending. '
                . 'A request to save a rule alone does not require editing website content.',
            type: 'markdown',
            allowInstructions: true,
        );
    }

    public function register(AiContext $context, bool $writable): void
    {
        // Nur die konfigurierte Einzeldatei registrieren, niemals einen
        // beschreibbaren Projekt-Root oder dessen Verzeichnis.
        $fs = new AiFileSystem(
            context: $context,
            id: 'user-rules',
            description: $writable
                ? 'Persistent user facts and rules; edit only after explicit user confirmation or a direct request to save them.'
                : 'Read-only persistent user facts and rules.',
            policy: new FileSystemPolicy(
                editable: $writable ? ['*'] : [],
                creatable: [],
                deletable: [],
                movable: [],
            ),
        );
        $fs->addFile((string) $this->file);
    }
}
