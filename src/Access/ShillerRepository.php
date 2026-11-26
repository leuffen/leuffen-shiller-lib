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
use Leuffen\Shiller\Access\Helper\PageIndexGenerator;
use Leuffen\Shiller\Access\Helper\ProjectConfig;
use Leuffen\Shiller\Access\Info\Capabilities;
use Leuffen\Shiller\Access\Info\FileKind;
use Leuffen\Shiller\Access\Info\TranslationInfo;
use Leuffen\Shiller\Access\Storage\SiteStorage;
use Leuffen\Shiller\Access\Adapter\Adapter;
use Leuffen\Shiller\Access\Storage\NativeSiteStorage;
use Leuffen\Shiller\Ai\ShillerAiContextFactory;
use Phore\AiHarness\AiContext;

final class ShillerRepository
{
    private SiteStorage $storage;
    private SiteStorage $projectStorage;
    private array $projectConfig;
    private AccessContext $access;
    private Adapter $adapter;
    private ?SiteConfig $config = null;

    /** @var array<string,Document> */
    private array $documents = [];

    public function __construct(
        SiteStorage|string $root,
        ?Adapter $adapter = null,
        ?AccessContext $access = null,
        SiteStorage|string|null $projectRoot = null,
    ) {
        $this->storage = is_string($root) ? new NativeSiteStorage($root) : $root;
        $projectStorage = is_string($projectRoot) ? new NativeSiteStorage($projectRoot) : ($projectRoot ?? $this->storage);
        $this->projectStorage = $projectStorage;
        $this->projectConfig = ProjectConfig::validate(
            $projectStorage->exists('.shiller.yml')
                ? Codec::yaml($projectStorage->read('.shiller.yml'), '.shiller.yml')
                : [],
        );
        $this->access = $access ?? new AccessContext();
        $this->adapter = $this->selectAdapter($adapter);
        // Adapter benötigen für projektweite Dateien denselben Root wie die .shiller.yml.
        $this->adapter->bind($this->storage, $this->projectConfig, $projectStorage);
    }

    private function selectAdapter(?Adapter $adapter): Adapter
    {
        $id = $this->projectConfig['adapter'] ?? null;

        $class = ProjectConfig::adapterClass($id);
        if ($adapter !== null && !$adapter instanceof $class) {
            throw new ConfigurationException("Explicit adapter does not match .shiller.yml: $id");
        }

        return $adapter ?? new $class();
    }

    /**
     * Returns the absolute source directory for filesystem-backed sites.
     *
     * @throws \LogicException When the storage has no local filesystem directory.
     */
    public function getSourceDirectory(): string
    {
        if (!$this->storage instanceof NativeSiteStorage) {
            throw new \LogicException('Site storage has no local source directory');
        }
        return $this->storage->getRootDirectory();
    }

    /**
     * Returns the local project root used to load .shiller.yml.
     *
     * @return string Absolute project directory, not necessarily the document root.
     * @throws \LogicException For non-local project storage.
     * @example $root = $site->getProjectDirectory();
     * @see self::getSourceDirectory()
     */
    public function getProjectDirectory(): string
    {
        if (!$this->projectStorage instanceof NativeSiteStorage) {
            throw new \LogicException('Project storage has no local directory');
        }
        return $this->projectStorage->getRootDirectory();
    }

    /**
     * Returns the already loaded project configuration, including AI settings.
     *
     * @return array<string, mixed> Trusted application configuration.
     * @example $config = $site->getProjectConfig();
     * @see self::config()
     */
    public function getProjectConfig(): array
    {
        return $this->projectConfig;
    }

    /**
     * Konfiguriert den uebergebenen AI Harness mit den AI-Einstellungen aus .shiller.yml.
     *
     * Projektkontext (Brain), User Rules, Skills, SystemPrompt und konfigurierte
     * Runtime-/Web-Einstellungen werden auf derselben Instanz vorbereitet.
     * Templates und Kontextdateien werden lesend registriert, User Rules nur
     * bei expliziter Freigabe schreibend. Der Aufruf startet keine Conversation.
     * Das Website-Dateisystem registriert der Aufrufer separat mit eigener Policy.
     *
     * @param AiContext $context Frischer, vom Aufrufer bereitgestellter AI Harness.
     * @param bool $allowUserRulesEdit Freigabe der einzelnen konfigurierten Regeldatei; Standard: read-only.
     * @return AiContext Dieselbe konfigurierte Instanz.
     * @throws \LogicException Bei nicht lokalem Projekt oder gestartetem Context.
     * @throws \RuntimeException Bei ungueltiger oder nicht lesbarer Konfiguration.
     * @example $site->configureAiHarness($context);
     * @see AiContext::configurePrepared()
     */
    public function configureAiHarness(AiContext $context, bool $allowUserRulesEdit = false): AiContext
    {
        return (new ShillerAiContextFactory($this->getProjectDirectory()))->configure(
            $context,
            projectConfig: $this->projectConfig,
            allowUserRulesEdit: $allowUserRulesEdit,
        );
    }

    /**
     * Liefert die aus der Shiller-Konfiguration abgeleiteten Schreibsperren.
     *
     * Diese Muster sind zusaetzlich zur auftragsbezogenen Website-Policy
     * anzuwenden. Damit koennen separate Ressourcen nicht ueber einen
     * breiteren Website-Root veraendert werden.
     *
     * @return list<string> Muster fuer FileSystemPolicy::writeIgnore.
     * @throws \LogicException Bei nicht lokalem Projekt.
     * @throws \RuntimeException Bei ungueltigen Ressourcen.
     * @example $deny = $site->getAiResourceWriteIgnore();
     * @see self::configureAiHarness()
     */
    public function getAiResourceWriteIgnore(): array
    {
        return (new ShillerAiContextFactory($this->getProjectDirectory()))
            ->protectedWritePatterns($this->projectConfig);
    }

    /**
     * Return a CSV index of existing Markdown pages, including unpublished translations.
     *
     * Paths are relative to the document root. URLs are supplied by the adapter
     * and remain empty for unpublished pages. No AI request or write is performed.
     *
     * @return string CSV with file, title, url, published and language columns.
     * @throws \Leuffen\Shiller\Access\Exception\ShillerException On invalid sources.
     * @example $csv = $site->getPageIndexCsv();
     * @see self::pages()
     */
    public function getPageIndexCsv(): string
    {
        return (new PageIndexGenerator())->generate($this);
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
