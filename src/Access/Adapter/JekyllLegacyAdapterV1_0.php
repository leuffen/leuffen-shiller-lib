<?php

declare(strict_types=1);

namespace Leuffen\Shiller\Access\Adapter;

use Leuffen\Shiller\Access\Info\Capabilities;
use Leuffen\Shiller\Access\Helper\Codec;
use Leuffen\Shiller\Access\Exception\ConflictException;
use Leuffen\Shiller\Access\DTO\Document;
use Leuffen\Shiller\Access\DTO\DocumentReference;
use Leuffen\Shiller\Access\DTO\FieldDefinition;
use Leuffen\Shiller\Access\DTO\FieldSet;
use Leuffen\Shiller\Access\Exception\NotFoundException;
use Leuffen\Shiller\Access\DTO\PageTree;
use Leuffen\Shiller\Access\DTO\TreeNode;
use Leuffen\Shiller\Access\DTO\ShillerTreeData;
use Leuffen\Shiller\Access\Info\FileKind;
use Leuffen\Shiller\Access\DTO\SiteConfig;
use Leuffen\Shiller\Access\Exception\UnsupportedOperationException;
use Leuffen\Shiller\Access\Exception\ValidationException;

final class JekyllLegacyAdapterV1_0 extends AbstractJekyllAdapter
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
            ['id' => 'jekyll-legacy', 'version' => '1.0'],
            $languages,
            $languages[0],
            $labels,
            (string) ($jekyll['url'] ?? ''),
            (string) ($jekyll['baseurl'] ?? ''),
        );
    }

    /**
     * Ermittelt die Legacy-Identität aus dem konfigurierten Sprachsuffix.
     *
     * Die Datei muss dem Format name.<sprache>.md oder name.<sprache>.html
     * entsprechen. Fehlende Varianten bleiben reine Referenzen.
     *
     * @param string $path Exakter Sourcepfad relativ zum Document Root.
     * @return DocumentReference Identität und Existenz ohne Neuanlage.
     * @throws ValidationException Bei vorhandenem Pfad mit unbekanntem Sprachsuffix oder ungültigem Dateiformat.
     * @throws NotFoundException Bei fehlender Datei ohne gültiges Sprachsuffix.
     * @throws \Leuffen\Shiller\Access\Exception\StorageException Bei einem Storage-Fehler.
     * @example $reference = $adapter->resolveDocumentPath('home/home.en.md');
     * @see Adapter::resolveDocumentPath()
     */
    public function resolveDocumentPath(string $path): DocumentReference
    {
        if (str_contains($path, '\\') || !preg_match('/^(.+)\.(md|html)$/D', $path, $matches)) {
            throw new ValidationException("Unsupported legacy page file: $path");
        }

        // Nur konfigurierte Sprachsuffixe gehören zum Legacy-Seitenformat.
        $language = null;
        $name = $matches[1];
        foreach ($this->loadConfig()->languages as $candidate) {
            if (str_ends_with($name, '.' . $candidate)) {
                $language = $candidate;
                $name = substr($name, 0, -strlen('.' . $candidate));
                break;
            }
        }
        if ($language === null || $name === '') {
            if (!$this->s()->exists($path)) {
                throw new NotFoundException("Page not found: $path");
            }
            throw new ValidationException("Unknown legacy page language: $path");
        }

        $index = $name === 'index' || str_ends_with($name, '/index');
        $id = Codec::id($index ? substr($name, 0, -5) : $name);
        $source = ($id === '/' ? 'index' : trim($id, '/') . ($index ? '/index' : ''))
            . '.' . $language . '.' . $matches[2];
        if ($source !== $path) {
            throw new ValidationException("Non-canonical legacy page file: $path");
        }

        // Existenz und Dateigrenzen verbleiben im zuständigen Storage.
        $exists = $this->s()->exists($path);
        if ($exists && $this->s()->isDirectory($path)) {
            throw new ValidationException("Expected a legacy page file: $path");
        }
        return new DocumentReference($path, $id, $language, $exists);
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

        // Pfadidentität und Sprachsuffix ersetzen Legacy-Header; gelesen wird ohne Dateischreibzugriff.
        $document->header['pid'] = ltrim($id, '/');
        $document->header['lang'] = $language;

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

            if (!str_ends_with($path, '.md') || preg_match('~(^|/)[_.]~', $path)) {
                continue;
            }

            $name = substr($path, 0, -3);
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

        $groups = array_filter($groups, static fn(array $languages): bool => isset($languages[$config->defaultLanguage]));

        $tree = $this->nodeTree($groups, $id, $config, $metadata);

        // Nur die Legacy-Baumansicht trennt Index-Dateien vom Ordner; Dokumentidentitäten bleiben erhalten.
        $project = function (TreeNode $node) use (&$project): TreeNode {
            $children = array_map($project, $node->children);
            $file = $node->data->file;
            $isIndex = $file !== null && preg_match('~(^|/)index\.[^.]+\.md$~D', $file->path) === 1;
            if ($isIndex) {
                $children[] = new TreeNode(
                    rtrim($node->id, '/') . '/index',
                    $node->label === '/' || $node->label === basename($node->id) ? 'index' : $node->label,
                    [],
                    new ShillerTreeData(null, FileKind::page, $file, [], $node->data->translations),
                    $node->getDocument(),
                );
                usort($children, static fn(TreeNode $a, TreeNode $b): int => strcmp($a->id, $b->id));
            }

            // Reine Ordner haben weder eine Datei noch synthetische Übersetzungslinks.
            $directory = $file === null || $isIndex;
            return new TreeNode(
                $node->id,
                $isIndex ? ($node->id === '/' ? '/' : basename($node->id)) : $node->label,
                $children,
                new ShillerTreeData(
                    $node->data->path,
                    $directory ? FileKind::directory : $node->data->kind,
                    $directory ? null : $file,
                    $node->data->metadata,
                    $directory ? [] : $node->data->translations,
                    $children !== [],
                    $node->data->childrenLoaded,
                ),
                $directory ? null : $node->getDocument(),
            );
        };

        return new PageTree($project($tree->root));
    }

    /**
     * Erstellt Editor-Felder aus der Legacy-Section nach dem MICX Page Builder.
     *
     * Die sieben Basisfelder entstehen in diesem Adapter. Sortierung und Tags
     * werden nur angeboten, wenn die Section sie konfiguriert. Die eigenen
     * forms-Einträge folgen danach. Ohne _section.yml gibt es keine Felder.
     *
     * @param string $id Seiten-ID innerhalb der Legacy-Section.
     * @param string|null $language Optionale Seitensprache.
     * @return FieldSet Der aktuellen Section zugeordnete Form-Definitionen.
     * @see FieldDefinition
     * @example $fields = $site->getHeaderDefinitions('/home/home')->fields;
     */
    public function getHeaderDefinitions(string $id, ?string $language = null): FieldSet
    {
        $section = explode('/', trim($id, '/'))[0] ?? '';
        $path = $section === '' ? '_section.yml' : $section . '/_section.yml';
        if (!$this->s()->exists($path)) {
            return new FieldSet();
        }

        $data = Codec::yaml($this->s()->read($path), $path);
        $layoutOptions = [];
        foreach (($data['layouts'] ?? []) as $layout) {
            if (!is_array($layout) || !isset($layout['layout'])) {
                continue;
            }
            $layoutOptions[] = [
                'text' => ($layout['text'] ?? $layout['layout']) . ' (' . $layout['layout'] . ')',
                'value' => $layout['layout'],
            ];
        }

        // Entspricht getDefaultSet(section) im bisherigen Page-Builder-Editor.
        $fields = [
            new FieldDefinition('pid', 'Page-Id', 'text', [], ['attrs' => ['disabled' => true]]),
            new FieldDefinition('lang', 'Sprache', 'text', [], ['attrs' => ['disabled' => true]]),
            new FieldDefinition('layout', 'Layout', 'select', $layoutOptions),
            new FieldDefinition('published', '', 'switch', [], [
                'checklabel' => 'Diese Seite veröffentlichen',
            ]),
            new FieldDefinition('permalink', 'Permanent-Link', 'text', [], [
                'desc' => 'Bitte freilassen, wenn links automatisch angelegt werden sollen.',
                'title' => "Festen Link in der Form '/sektion/seite' setzen. Bitte freilassen, um die Link-URL automatisch anzulegen",
            ]),
            new FieldDefinition('title', 'Seiten-Titel', 'text', [], [
                'title' => 'Titel im Header des Browsers und in Suchergebnissen',
            ]),
            new FieldDefinition('description', 'Meta-Description', 'text', [], [
                'attrs' => ['maxlength' => 160],
                'title' => 'Kurzbeschreibung: So erscheint Ihre Website in den Suchergebnissen. (Max. 160 Zeichen)',
            ]),
        ];
        if (($data['is_sortable'] ?? false) === true) {
            $fields[] = new FieldDefinition('order', 'Sortierung', 'text', [], [
                'attrs' => ['type' => 'number'],
                'title' => 'Kleinere Werte werden zuerst angezeigt (Nur für Listen)',
            ]);
        }
        if (isset($data['ptags']) && is_array($data['ptags']) && $data['ptags'] !== []) {
            $fields[] = new FieldDefinition('ptags', 'Page-Tags', 'multi', $data['ptags'], [
                'title' => 'Optionen, wie die Seite angezeigt wird',
            ]);
        }

        // Section-eigene Formularfelder bleiben in der YAML-Reihenfolge.
        foreach (($data['forms'] ?? []) as $field) {
            if (!is_array($field) || !isset($field['key'])) {
                continue;
            }
            $fields[] = new FieldDefinition(
                (string) $field['key'],
                (string) ($field['name'] ?? $field['key']),
                (string) ($field['type'] ?? 'text'),
                (array) ($field['options'] ?? []),
                array_merge(
                    ['attrs' => (array) ($field['attrs'] ?? [])],
                    array_intersect_key($field, array_flip(['title', 'desc', 'checklabel'])),
                ),
            );
        }

        return new FieldSet($fields);
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
