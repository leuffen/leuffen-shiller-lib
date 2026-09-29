<?php

declare(strict_types=1);

namespace Leuffen\Schiller\Automation;

use Phore\FileSystem\Exception\FilesystemException;
use Phore\FileSystem\PhoreDirectory;
use Phore\FileSystem\PhoreFile;
use RuntimeException;

/** Installiert Dateien aus einem _tpl-Verzeichnis. */
final class SchillerAutomation
{
    private readonly PhoreDirectory $projectRoot;
    private readonly PhoreDirectory $templateDir;
    private readonly string $documentRoot;

    /**
     * Bindet Projekt-, Template- und Document-Root.
     *
     * @param string $projectRoot Projektwurzel.
     * @param string $templateDir Template-Wurzel.
     * @param string $documentRoot Relativer Document Root.
     * @throws FilesystemException Bei Dateisystemfehlern.
     * @see SchillerAutomationFactory
     * @example $automation = new SchillerAutomation('/srv/site', '/srv/theme/_tpl', 'docs'); assert($automation instanceof SchillerAutomation);
     */
    public function __construct(string $projectRoot, string $templateDir, string $documentRoot = 'docs')
    {
        $projectPath = (string) phore_uri($projectRoot)->abs();
        $templatePath = (string) phore_uri($templateDir)->abs();

        $this->projectRoot = phore_dir($projectPath, ['rootDir' => $projectPath])
            ->assertDirectory()
            ->assertReadable();
        $this->templateDir = phore_dir($templatePath, ['rootDir' => $templatePath])
            ->assertDirectory()
            ->assertReadable();
        $this->documentRoot = $this->projectRoot->assertRelativePath($documentRoot);
    }

    /**
     * Installiert Grundstruktur und ausgewaehlte Varianten.
     *
     * @param list<string> $tags Auswahl der Tags.
     * @return list<string> Geschriebene relative Pfade.
     * @throws FilesystemException Bei Dateisystemfehlern.
     * @throws RuntimeException Bei ungueltigen Schiller-Daten.
     * @see self::install()
     * @example $written = $automation->init(['raven']); assert(is_array($written));
     */
    public function init(array $tags = []): array
    {
        $plan = [];
        $root = $this->templateDir->withSubPath('_root')->assertDirectory();

        foreach ($root->listFiles(recursive: true, sort: 'path') as $source) {
            $relative = (string) $source->getRelPath($root);
            $destination = str_starts_with($relative, 'docs/')
                ? $this->documentRoot . substr($relative, strlen('docs'))
                : $relative;
            $plan[$destination] = $source->get_contents();
        }

        return $this->apply($this->selected($tags) + $plan);
    }

    /**
     * Installiert nur ausgewaehlte Vorlagen.
     *
     * @param list<string> $tags Auswahl der Tags.
     * @return list<string> Geschriebene relative Pfade.
     * @throws FilesystemException Bei Dateisystemfehlern.
     * @throws RuntimeException Bei ungueltigen Schiller-Daten.
     * @see self::init()
     * @example $written = $automation->install(['theme:osman']); assert(is_array($written));
     */
    public function install(array $tags): array
    {
        return $this->apply($this->selected($tags));
    }

    private function selected(array $tags): array
    {
        $plan = [];

        foreach ($this->templateDir->listFiles(recursive: true, sort: 'path') as $source) {
            $relative = (string) $source->getRelPath($this->templateDir);
            if (str_starts_with($relative, '_root/')) {
                continue;
            }

            $wrapped = str_ends_with($relative, '.template');
            if (!$wrapped && !str_ends_with($relative, '.md')) {
                continue;
            }

            $frontMatter = $source->get_front_matter(required: false);
            if ($frontMatter === null) {
                if ($wrapped) {
                    throw new RuntimeException("Template header missing: $source");
                }
                continue;
            }

            $header = $frontMatter->header;
            if (!is_array($header)) {
                throw new RuntimeException("Invalid YAML header: $source");
            }
            if (!isset($header['schiller'])) {
                if ($wrapped) {
                    throw new RuntimeException("Schiller header missing: $source");
                }
                continue;
            }

            $config = $header['schiller'];
            if (!is_array($config)) {
                throw new RuntimeException("Invalid schiller options: $source");
            }

            $fileTags = $config['tags'] ?? [];
            $fileTags = is_string($fileTags) ? [$fileTags] : $fileTags;
            if (!is_array($fileTags) || array_filter($fileTags, fn($tag): bool => !is_string($tag) || $tag === '')) {
                throw new RuntimeException("Invalid schiller tags: $source");
            }
            if (!array_intersect($tags, $fileTags)) {
                continue;
            }

            $target = $config['target'] ?? substr($relative, 0, $wrapped ? -strlen('.template') : null);
            if (!is_string($target)) {
                throw new RuntimeException("Invalid target: $source");
            }
            $target = $this->documentRoot . '/' . $this->projectRoot->assertRelativePath($target);

            $instructions = $config['instructions'] ?? [];
            $instructions = is_string($instructions) ? [$instructions] : $instructions;
            if (!is_array($instructions)) {
                throw new RuntimeException("Invalid schiller instructions: $source");
            }
            foreach ($instructions as $key => $reference) {
                if (!is_string($reference)) {
                    throw new RuntimeException("Invalid instruction reference: $source");
                }
                $instructions[$key] = $this->instruction($reference, $relative);
            }

            if (array_key_exists($target, $plan)) {
                throw new RuntimeException("Multiple templates target $target: $source");
            }

            if ($wrapped) {
                $plan[$target] = $frontMatter->content;
            } elseif ($instructions !== ($config['instructions'] ?? [])) {
                $header['schiller']['instructions'] = array_values($instructions);
                $frontMatter->header = $header;
                $plan[$target] = $frontMatter->render();
            } else {
                $plan[$target] = $frontMatter->render();
            }
        }

        return $plan;
    }

    private function instruction(string $reference, string $source): string
    {
        if (str_starts_with($reference, 'tpl:/')) {
            $path = substr($reference, 5);
        } elseif (str_starts_with($reference, './')) {
            $path = dirname($source) . '/' . substr($reference, 2);
        } else {
            throw new RuntimeException("Invalid instruction reference $reference in $source");
        }

        $path = $this->templateDir->assertRelativePath($path);
        $this->templateDir->withSubPath($path)->assertFile()->assertReadable();

        return 'tpl:/' . $path;
    }

    private function apply(array $plan): array
    {
        $targets = [];
        foreach ($plan as $relative => $_) {
            $targets[$relative] = $this->projectRoot
                ->withSubPath($relative)
                ->assertFileTarget();
        }

        $paths = array_keys($targets);
        foreach ($paths as $path) {
            foreach ($paths as $other) {
                if ($path !== $other && str_starts_with($other, $path . '/')) {
                    throw new RuntimeException("Planned file blocks target directory: $path");
                }
            }
        }

        foreach ($plan as $relative => $content) {
            /** @var PhoreFile $target */
            $target = $targets[$relative];
            $target->mkdir()->set_contents($content);
        }

        return array_keys($plan);
    }
}
