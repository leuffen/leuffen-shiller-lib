<?php

declare(strict_types=1);

namespace Leuffen\Shiller\Access;

use Leuffen\Shiller\Access\DTO\AccessContext;
use Leuffen\Shiller\Access\DTO\Document;
use Leuffen\Shiller\Access\DTO\DocumentReference;
use Leuffen\Shiller\Access\DTO\FieldSet;
use Leuffen\Shiller\Access\DTO\FileEntry;
use Leuffen\Shiller\Access\DTO\FileListing;
use Leuffen\Shiller\Access\DTO\PageTree;
use Leuffen\Shiller\Access\DTO\ShillerTreeData;
use Leuffen\Shiller\Access\DTO\SiteConfig;
use Leuffen\Shiller\Access\DTO\TreeNode;
use Leuffen\Shiller\Access\Exception\AccessDeniedException;
use Leuffen\Shiller\Access\Exception\AlreadyExistsException;
use Leuffen\Shiller\Access\Exception\ConfigurationException;
use Leuffen\Shiller\Access\Exception\ConflictException;
use Leuffen\Shiller\Access\Exception\NotFoundException;
use Leuffen\Shiller\Access\Exception\ValidationException;
use Leuffen\Shiller\Access\Helper\Codec;
use Leuffen\Shiller\Access\Info\Capabilities;
use Leuffen\Shiller\Access\Info\FileKind;
use Leuffen\Shiller\Access\Info\TranslationInfo;
use Leuffen\Shiller\Access\Storage\SiteStorage;
use Leuffen\Shiller\Access\Adapter\Adapter;
use Leuffen\Shiller\Access\Adapter\JekyllLegacyAdapter;
use Leuffen\Shiller\Access\Adapter\JekyllPolyglotAdapter;
use Leuffen\Shiller\Access\Storage\NativeSiteStorage;

final class ShillerRepository
{
    private SiteStorage $storage;
    private AccessContext $access;
    private Adapter $adapter;
    private ?SiteConfig $config = null;

    /** @var array<string,Document> */
    private array $documents = [];

    public function __construct(
        SiteStorage|string $root,
        ?Adapter $adapter = null,
        ?AccessContext $access = null,
    ) {
        $this->storage = is_string($root) ? new NativeSiteStorage($root) : $root;
        $this->access = $access ?? new AccessContext();
        $this->adapter = $adapter ?? $this->selectAdapter();
        $this->adapter->bind($this->storage);
    }

    private function selectAdapter(): Adapter
    {
        if ($this->storage->exists('shiller.yaml')) {
            $config = Codec::yaml($this->storage->read('shiller.yaml'), 'shiller.yaml');
            $id = $config['adapter']['id'] ?? $config['adapter'] ?? null;

            if ($id === 'micx-legacy') {
                return new JekyllLegacyAdapter();
            }

            if ($id !== null && $id !== 'jekyll-polyglot') {
                throw new ConfigurationException("Unknown adapter: $id");
            }
        }

        return new JekyllPolyglotAdapter();
    }

    public function storage(): SiteStorage
    {
        return $this->storage;
    }

    public function adapter(): Adapter
    {
        return $this->adapter;
    }

    public function access(): AccessContext
    {
        return $this->access;
    }

    public function config(): SiteConfig
    {
        return $this->config = $this->adapter->loadConfig();
    }

    public function pages(string $id = '/'): PageTree
    {
        return $this->adapter->buildTree(Codec::id($id));
    }

    public function files(string $path = '', bool $recursive = false): FileListing
    {
        $entries = [];

        foreach ($this->storage->list(trim($path, '/')) as $entry) {
            $basePath = trim($path, '/');
            $entryPath = ($basePath === '' ? '' : $basePath . '/') . $entry['name'];
            $kind = $entry['type'] === 'directory' ? FileKind::directory : FileKind::other;
            $children = $recursive && $entry['type'] === 'directory'
                ? $this->files($entryPath, true)->entries
                : [];

            $entries[] = new TreeNode(
                'file:' . $entryPath,
                $entry['name'],
                $children,
                new ShillerTreeData(
                    $entryPath,
                    $kind,
                    null,
                    [],
                    [],
                    !empty($children),
                    $recursive || $entry['type'] !== 'directory',
                ),
            );
        }

        return new FileListing($entries);
    }

    public function capabilities(string $id, ?string $language = null): Capabilities
    {
        return $this->adapter->capabilities(Codec::id($id), $language);
    }

    public function getPage(string $id, ?string $language = null): Document
    {
        $config = $this->config();
        $language ??= $config->defaultLanguage;
        $id = Codec::id($id);
        $key = "$id|$language";

        return $this->documents[$key] ??= $this->adapter->load($id, $language)->attach($this);
    }

    /**
     * Löst eine Source-Datei ohne vorherige Seiten-ID oder Sprache auf.
     *
     * Die Referenz beschreibt auch fehlende Übersetzungen. Die Auflösung ist
     * lesend und benötigt keine Schreibrechte; es wird keine Datei angelegt.
     *
     * @param string $path Exakter Dateipfad relativ zum Document Root.
     * @return DocumentReference Vom Adapter bestimmte Identität und Existenz.
     * @throws ValidationException Bei einem für den Adapter ungültigen Dateipfad.
     * @throws \Leuffen\Shiller\Access\Exception\StorageException Bei einem Storage-Fehler.
     * @example $reference = $site->resolveDocumentPath('en/leistungen.md');
     * @see Adapter::resolveDocumentPath()
     * @see self::translations()
     */
    public function resolveDocumentPath(string $path): DocumentReference
    {
        return $this->adapter->resolveDocumentPath($path);
    }

    /**
     * Lädt genau die angefragte Source-Datei frisch aus dem Storage.
     *
     * Saubere Cache-Einträge werden ersetzt, damit ein anschließender Patch
     * zwischenzeitliche Änderungen an nicht gesendeten Feldern erhält.
     * Ungespeicherte Dokumente werden niemals still verworfen. Fehlende Dateien
     * werden weder angelegt noch durch eine andere Endung/Indexdatei ersetzt.
     *
     * @param string $path Exakter Dateipfad relativ zum Document Root.
     * @return Document Frischer, an dieses Repository gebundener Dokumentzustand.
     * @throws NotFoundException Wenn genau diese Source-Datei nicht existiert.
     * @throws ConflictException Bei ungespeichertem Cache-Zustand oder mehrdeutiger Quelle.
     * @throws ValidationException Bei ungültigem Pfad oder Dokumentinhalt.
     * @throws \Leuffen\Shiller\Access\Exception\StorageException Bei einem Lesefehler.
     * @example $page = $site->getDocumentByPath('leistungen.md');
     * @see self::resolveDocumentPath()
     * @see Document::patch()
     */
    public function getDocumentByPath(string $path): Document
    {
        $reference = $this->resolveDocumentPath($path);
        if (!$reference->exists) {
            throw new NotFoundException("Page file not found: $path");
        }

        // Bewusst bearbeitete Library-Dokumente vor einem Reload schützen.
        $key = $reference->id . '|' . $reference->language;
        $cached = $this->documents[$key] ?? null;
        if ($cached !== null && (!$cached->isPersisted() || $cached->hasChanges())) {
            throw new ConflictException("Unsaved document prevents file reload: $path");
        }

        $document = $this->adapter->load($reference->id, $reference->language);
        if ($document->file?->path !== $reference->path) {
            throw new ConflictException("Source file changed while loading: $path");
        }
        return $this->documents[$key] = $document->attach($this);
    }

    public function createPage(string $id, array $header = [], string $content = ''): Document
    {
        if (!$this->access->canWrite()) {
            throw new AccessDeniedException('Write access required');
        }

        $id = Codec::id($id);
        $language = $this->config()->defaultLanguage;
        $key = "$id|$language";

        if (
            isset($this->documents[$key])
            || $this->storage->exists($this->adapter->getSourcePath($id, $language))
        ) {
            throw new AlreadyExistsException("Page exists: $id");
        }

        return $this->documents[$key] = $this->adapter
            ->create($id, $language, $header, $content)
            ->attach($this);
    }

    public function translation(Document $root, ?string $language, bool $create): ?Document
    {
        $config = $this->config();
        $language ??= $config->defaultLanguage;

        try {
            return $this->getPage($root->id, $language);
        } catch (NotFoundException) {
            if (!$create) {
                return null;
            }

            if (!$this->access->canWrite()) {
                throw new AccessDeniedException('Write access required');
            }

            // Fehlende Variante vorbereiten, ohne sie vor dem expliziten save() zu persistieren.
            $document = $this->adapter
                ->create($root->id, $language, $root->header, $root->content)
                ->attach($this);
            $document->header['published'] = false;
            $this->documents[$root->id . '|' . $language] = $document;

            return $document;
        }
    }

    /**
     * Liefert vorhandene und fehlende Sprachvarianten mit ihren Sourcepfaden.
     *
     * Eine Referenz auf eine fehlende Datei genügt; es wird weder eine Seite
     * angelegt noch Schreibzugriff benötigt. Vorhandene Dokumente verwenden
     * weiterhin den Repository-Cache.
     *
     * @param Document|DocumentReference $root Dokument oder aufgelöste Dateireferenz.
     * @return array<string, TranslationInfo> Varianten nach konfigurierter Sprache.
     * @throws \Leuffen\Shiller\Access\Exception\ShillerException Bei einer ungültigen oder unlesbaren Quelle.
     * @example $translations = $site->translations($site->resolveDocumentPath('en/leistungen.md'));
     * @see Document::getTranslations()
     * @see self::resolveDocumentPath()
     */
    public function translations(Document|DocumentReference $root): array
    {
        $translations = [];
        $config = $this->config();

        foreach ($config->languages as $language) {
            try {
                $document = $this->getPage($root->id, $language);
                $translations[$language] = new TranslationInfo(
                    $language,
                    $document->file?->path
                        ?? $this->adapter->getSourcePath($root->id, $language),
                    true,
                    $language === $config->defaultLanguage,
                    (bool) ($document->header['published'] ?? true),
                );
            } catch (NotFoundException) {
                $translations[$language] = new TranslationInfo(
                    $language,
                    $this->adapter->getSourcePath($root->id, $language),
                    false,
                    $language === $config->defaultLanguage,
                    null,
                );
            }
        }

        return $translations;
    }

    public function getDocumentByUrl(string $url): Document
    {
        return $this->adapter->getDocumentByUrl($url)->attach($this);
    }

    public function getHeaderDefinitions(string $id, ?string $language = null): FieldSet
    {
        return $this->adapter->getHeaderDefinitions(Codec::id($id), $language);
    }

    public function saveDocuments(array $documents): void
    {
        if (!$this->access->canWrite()) {
            throw new AccessDeniedException('Write access required');
        }

        foreach ($documents as $document) {
            if (!$document instanceof Document) {
                throw new ValidationException('Document expected');
            }
            foreach ($this->parentIds($document->id) as $parentId) {
                foreach ($this->documents as $loaded) {
                    if ($loaded->id === $parentId
                        && !in_array($loaded, $documents, true)
                        && (!$loaded->isPersisted() || $loaded->hasChanges())) {
                        throw new ConflictException('Unsaved parent prevents page creation');
                    }
                }
            }
        }

        // Erst den gemeinsamen Adapter-Batch schreiben, danach lokale Dokumente als persistiert markieren.
        $this->adapter->write($documents);

        foreach ($documents as $document) {
            $path = $this->adapter->getSourcePath($document->id, $document->language);
            $document->markPersisted(new FileEntry($path, FileKind::page));
        }
        foreach ($this->documents as $document) {
            if ($document->isPersisted()) {
                $path = $this->adapter->getSourcePath($document->id, $document->language);
                $document->file = new FileEntry($path, FileKind::page);
            }
        }
    }

    public function restoreDocument(array $data): Document
    {
        $id = Codec::id((string) ($data['id'] ?? ''));
        $language = (string) ($data['language'] ?? '');

        if ($id === '' || $language === '') {
            throw new ValidationException('Document identity missing');
        }

        $persisted = (bool) ($data['persisted'] ?? true);
        $document = $persisted
            ? $this->getPage($id, $language)
            : $this->adapter->create($id, $language)->attach($this);

        // Nur die transportierten bearbeitbaren Felder in den serverseitig bestimmten Zustand übernehmen.
        $document->header = is_array($data['header'] ?? null) ? $data['header'] : [];
        $document->content = (string) ($data['content'] ?? '');
        $document->adapterState = is_array($data['adapterState'] ?? null)
            ? $data['adapterState']
            : [];

        return $document;
    }

    public function rename(string $id, string $newId): void
    {
        if (!$this->access->canWrite()) {
            throw new AccessDeniedException('Write access required');
        }

        $id = Codec::id($id);
        $newId = Codec::id($newId);
        foreach ($this->documents as $document) {
            $affected = $document->id === $id
                || str_starts_with($document->id, $id . '/')
                || in_array($document->id, $this->parentIds($newId), true);
            if ($affected && (!$document->isPersisted() || $document->hasChanges())) {
                throw new ConflictException('Unsaved documents prevent a page move');
            }
        }

        $this->adapter->rename($id, $newId);
        if ($id === $newId) {
            return;
        }
        $updated = [];
        foreach ($this->documents as $document) {
            if ($document->id === $id || str_starts_with($document->id, $id . '/')) {
                $document->id = $newId . substr($document->id, strlen($id));
            }
            if ($document->isPersisted()) {
                $path = $this->adapter->getSourcePath($document->id, $document->language);
                $document->file = new FileEntry($path, FileKind::page);
            }
            $updated[$document->id . '|' . $document->language] = $document;
        }
        $this->documents = $updated;
    }

    /** @return list<string> */
    private function parentIds(string $id): array
    {
        $parts = explode('/', trim($id, '/'));
        array_pop($parts);
        $ids = [];
        while ($parts) {
            $ids[] = '/' . implode('/', $parts);
            array_pop($parts);
        }
        return $ids;
    }
}
