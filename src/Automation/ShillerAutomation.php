<?php

declare(strict_types=1);

namespace Leuffen\Shiller\Automation;

use Phore\FileSystem\Exception\FilesystemException;
use Phore\FileSystem\PhoreDirectory;
use Phore\FileSystem\PhoreFile;
use Phore\Log\PhoreLogger;
use RuntimeException;

/** Installiert Dateien aus einem _tpl-Verzeichnis. */
final class ShillerAutomation
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
     * @see ShillerAutomationFactory
     * @example $automation = new ShillerAutomation('/srv/site', '/srv/theme/_tpl', 'docs'); assert($automation instanceof ShillerAutomation);
     */
    public function __construct(string $projectRoot, string $templateDir, string $documentRoot = 'docs', private readonly ?PhoreLogger $logger = null)
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
     * @throws RuntimeException Bei ungueltigen Shiller-Daten.
     * @see self::install()
     * @example $written = $automation->init(['raven']); assert(is_array($written));
     */
    public function init(array $tags = []): array
    {
        $this->logger?->step('Initialisiere Shiller-Projekt aus Template.');
        $plan = [];
        $root = $this->templateDir->withSubPath('_root')->assertDirectory();

        foreach ($root->listFiles(recursive: true, sort: 'path') as $source) {
            $relative = (string) $source->getRelPath($root);
            $destination = str_starts_with($relative, 'docs/')
                ? $this->documentRoot . substr($relative, strlen('docs'))
                : $relative;
            $plan[$destination] = str_starts_with($relative, 'docs/')
                ? $this->installedContent($source, $source->get_contents())
                : $source->get_contents();
        }

        return $this->apply($this->selected($tags) + $plan);
    }

    /**
     * Installiert nur ausgewaehlte Vorlagen.
     *
     * @param list<string> $tags Auswahl der Tags.
     * @return list<string> Geschriebene relative Pfade.
     * @throws FilesystemException Bei Dateisystemfehlern.
     * @throws RuntimeException Bei ungueltigen Shiller-Daten.
     * @see self::init()
     * @example $written = $automation->install(['theme:osman']); assert(is_array($written));
     */
    public function install(array $tags): array
    {
        $this->logger?->step('Installiere Shiller-Templates fuer {} Tags.', [count($tags)]);
        return $this->apply($this->selected($tags));
    }

    /**
     * Stellt ausgewaehlte installierte Content-Dateien aus den Originalvorlagen wieder her.
     *
     * Markdown-Varianten werden anhand ihrer erhaltenen shiller.tags auf die
     * installierte Template-Variante zurueckgefuehrt. Dateien aus _root/docs,
     * insbesondere _data-YAML, werden direkt aus _root wiederhergestellt.
     *
     * @param string|list<string> $selectors Relative Dateinamen, Globs oder tag:<name>.
     * @return list<string> Wiederhergestellte Pfade relativ zur Projektwurzel.
     * @throws FilesystemException Bei Dateisystemfehlern.
     * @throws RuntimeException Wenn keine eindeutige Originalvorlage gefunden wird.
     * @see ShillerContentSelector::select()
     * @see self::init()
     * @example $restored = $automation->revert(['index.md', '_data/*.yml']); assert(in_array('docs/index.md', $restored, true));
     */
    public function revert(string|array $selectors): array
    {
        $this->logger?->step('Stelle ausgewaehlte Shiller-Dateien wieder her.');
        $documentDir = $this->projectRoot
            ->withSubPath($this->documentRoot)
            ->assertDirectory()
            ->assertReadable();
        $targets = (new ShillerContentSelector((string) $documentDir))->select($selectors);

        $plan = [];
        foreach ($targets as $target) {
            $relative = str_replace('\\', '/', (string) $target->getRelPath($documentDir));
            $plan[$this->documentRoot . '/' . $relative] = $this->originalContent($relative, $target);
        }

        return $this->apply($plan);
    }

    /**
     * Ermittelt den installierten Originalinhalt fuer eine einzelne Content-Datei.
     */
    private function originalContent(string $targetRelative, PhoreFile $currentTarget): string
    {
        $header = $currentTarget->get_front_matter(required: false)?->header;
        $config = is_array($header) ? ($header['shiller'] ?? null) : null;
        $source = is_array($config) ? ($config['source'] ?? null) : null;

        if (is_string($source) && $source !== '') {
            $path = $this->templateDir->assertRelativePath($source);
            $original = $this->templateDir->withSubPath($path)->asFile()->assertFile()->assertReadable();
            return $this->installedContent($original, $original->get_contents());
        }

        $rootSource = $this->templateDir->withSubPath('_root/docs/' . $targetRelative);
        if ($rootSource->exists()) {
            $original = $rootSource->asFile()->assertFile()->assertReadable();
            return $this->installedContent($original, $original->get_contents());
        }

        throw new RuntimeException("No shiller.source found for $targetRelative");
    }

    /** Gibt kopierten Markdown-Dateien ausschliesslich die Herkunft im Shiller-Block. */
    private function installedContent(PhoreFile $source, string $content): string
    {
        $relative = str_replace('\\', '/', (string) $source->getRelPath($this->templateDir));
        if (!str_ends_with(strtolower($relative), '.md')) {
            return $content;
        }

        $frontMatter = $source->get_front_matter(required: false);
        if ($frontMatter === null) {
            return "---\nshiller:\n  source: " . $relative . "\n---\n" . $content;
        }

        $header = $frontMatter->header;
        if (!is_array($header)) {
            throw new RuntimeException("Invalid YAML header: $source");
        }
        $header['shiller'] = ['source' => $relative];
        $frontMatter->header = $header;
        return $frontMatter->render();
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
            if (!isset($header['shiller'])) {
                if ($wrapped) {
                    throw new RuntimeException("Shiller header missing: $source");
                }
                continue;
            }

            $config = $header['shiller'];
            if (!is_array($config)) {
                throw new RuntimeException("Invalid shiller options: $source");
            }

            $fileTags = $config['tags'] ?? [];
            $fileTags = is_string($fileTags) ? [$fileTags] : $fileTags;
            if (!is_array($fileTags) || array_filter($fileTags, fn($tag): bool => !is_string($tag) || $tag === '')) {
                throw new RuntimeException("Invalid shiller tags: $source");
            }
            if (!array_intersect($tags, $fileTags)) {
                continue;
            }

            $target = $config['target'] ?? substr($relative, 0, $wrapped ? -strlen('.template') : null);
            if (!is_string($target)) {
                throw new RuntimeException("Invalid target: $source");
            }
            $target = $this->documentRoot . '/' . $this->projectRoot->assertRelativePath($target);

            if (array_key_exists($target, $plan)) {
                throw new RuntimeException("Multiple templates target $target: $source");
            }

            if ($wrapped) {
                $plan[$target] = $frontMatter->content;
            } else {
                $plan[$target] = $this->installedContent($source, $source->get_contents());
            }
        }

        return $plan;
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
            $this->logger?->success('Geschrieben: {}', [$relative]);
        }

        return array_keys($plan);
    }
}
