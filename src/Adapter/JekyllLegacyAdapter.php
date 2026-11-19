<?php

declare(strict_types=1);

namespace Leuffen\Shiller\Adapter;

use Leuffen\Shiller\Capabilities;
use Leuffen\Shiller\Codec;
use Leuffen\Shiller\ConflictException;
use Leuffen\Shiller\Document;
use Leuffen\Shiller\FieldDefinition;
use Leuffen\Shiller\FieldSet;
use Leuffen\Shiller\NotFoundException;
use Leuffen\Shiller\PageTree;
use Leuffen\Shiller\SiteConfig;
use Leuffen\Shiller\UnsupportedOperationException;

final class JekyllLegacyAdapter extends AbstractJekyllAdapter
{
    public function loadConfig(): SiteConfig
    {
        $languages = [];
        $labels = [];

        if ($this->s()->exists('_data/languages.yml')) {
            $configuredLanguages = Codec::yaml(
                $this->s()->read('_data/languages.yml'),
                '_data/languages.yml',
            );

            foreach ($configuredLanguages as $entry) {
                if (!is_array($entry) || !isset($entry['lang'])) {
                    continue;
                }

                $language = (string) $entry['lang'];
                $languages[] = $language;
                $labels[$language] = (string) ($entry['name'] ?? $language);
            }
        }

        if (!$languages) {
            $languages = ['de'];
            $labels = ['de' => 'Deutsch'];
        }

        $jekyll = $this->s()->exists('_config.yml')
            ? Codec::yaml($this->s()->read('_config.yml'), '_config.yml')
            : [];

        return new SiteConfig(
            ['id' => 'micx-legacy', 'version' => 1],
            $languages,
            $languages[0],
            $labels,
            (string) ($jekyll['url'] ?? ''),
            (string) ($jekyll['baseurl'] ?? ''),
        );
    }

    public function getSourcePath(string $id, string $language): string
    {
        $base = trim($id, '/');

        if ($id === '/') {
            $candidates = ["index.$language.md", "index.$language.html"];
        } else {
            $candidates = [
                "$base.$language.md",
                "$base.$language.html",
                "$base/index.$language.md",
                "$base/index.$language.html",
            ];
        }

        $found = array_values(array_filter(
            $candidates,
            fn(string $path): bool => $this->s()->exists($path),
        ));

        if (count($found) > 1) {
            throw new ConflictException("Ambiguous legacy page: $id [$language]");
        }

        return $found[0] ?? $candidates[0];
    }

    public function load(string $id, string $language): Document
    {
        $path = $this->getSourcePath($id, $language);
        if (!$this->s()->exists($path)) {
            throw new NotFoundException("Page not found: $id [$language] ($path)");
        }

        $document = $this->readDoc(
            $id,
            $language,
            $path,
            $language === $this->loadConfig()->defaultLanguage,
        );

        if (
            isset($document->header['pid'])
            && Codec::id((string) $document->header['pid']) !== $id
        ) {
            throw new ConflictException("pid mismatch: $path");
        }

        if (
            isset($document->header['lang'])
            && (string) $document->header['lang'] !== $language
        ) {
            throw new ConflictException("lang mismatch: $path");
        }

        return $document;
    }

    public function create(
        string $id,
        string $language,
        array $header = [],
        string $content = '',
    ): Document {
        throw new UnsupportedOperationException(
            'Legacy format does not create pages or translations',
        );
    }

    public function write(array $documents): void
    {
        $changes = [];

        foreach ($documents as $document) {
            $path = $this->getSourcePath($document->id, $document->language);
            if (!$this->s()->exists($path)) {
                throw new UnsupportedOperationException("Legacy file does not exist: $path");
            }

            $header = $document->header;
            $header['pid'] = ltrim($document->id, '/');
            $header['lang'] = $document->language;
            $changes[$path] = Codec::emitFrontMatter($header, $document->content);
        }

        $this->s()->writeBatch($changes);
    }

    public function buildTree(string $id = '/'): PageTree
    {
        $config = $this->loadConfig();
        $groups = [];
        $metadata = [];

        // Legacy-Seiten und _section.yml-Metadaten in die gemeinsame Baumstruktur überführen.
        foreach ($this->all() as $path) {
            if (basename($path) === '_section.yml') {
                $directory = dirname($path);
                $sectionId = $directory === '.' ? '/' : Codec::id($directory);

                try {
                    $metadata[$sectionId] = Codec::yaml($this->s()->read($path), $path);
                } catch (\Throwable) {
                    // Ungültige optionale Section-Metadaten blockieren das Seitenlisting nicht.
                }

                continue;
            }

            if (!preg_match('/\.(md|html)$/', $path)) {
                continue;
            }

            $name = preg_replace('/\.(md|html)$/', '', $path);
            if (!$name) {
                continue;
            }

            $matchedLanguage = null;
            foreach ($config->languages as $language) {
                if (str_ends_with($name, '.' . $language)) {
                    $matchedLanguage = $language;
                    $name = substr($name, 0, -strlen('.' . $language));
                    break;
                }
            }

            if (!$matchedLanguage) {
                continue;
            }

            $pageId = Codec::id($name);
            if (str_ends_with($pageId, '/index')) {
                $pageId = Codec::id(substr($pageId, 0, -6));
            }

            try {
                [$header] = Codec::frontMatter($this->s()->read($path), $path);
            } catch (\Throwable) {
                $header = [];
            }

            $groups[$pageId][$matchedLanguage] = [
                'path' => $path,
                'title' => $header['title'] ?? basename($pageId),
                'published' => $header['published'] ?? true,
            ];
        }

        return $this->nodeTree($groups, $id, $config, $metadata);
    }

    public function getHeaderDefinitions(string $id, ?string $language = null): FieldSet
    {
        $extra = [];
        $section = explode('/', trim($id, '/'))[0] ?? '';
        $path = $section === '' ? '_section.yml' : $section . '/_section.yml';

        if ($this->s()->exists($path)) {
            $sectionData = Codec::yaml($this->s()->read($path), $path);

            foreach (($sectionData['forms'] ?? []) as $field) {
                if (!is_array($field) || !isset($field['key'])) {
                    continue;
                }

                $extra[] = new FieldDefinition(
                    (string) $field['key'],
                    (string) ($field['name'] ?? $field['key']),
                    (string) ($field['type'] ?? 'text'),
                    (array) ($field['options'] ?? []),
                    (array) ($field['attrs'] ?? []),
                );
            }
        }

        return $this->commonFields($extra);
    }

    public function capabilities(string $id, ?string $language = null): Capabilities
    {
        $path = $this->getSourcePath(
            $id,
            $language ?? $this->loadConfig()->defaultLanguage,
        );

        return new Capabilities(
            true,
            $this->s()->exists($path),
            false,
            false,
            false,
        );
    }
}
