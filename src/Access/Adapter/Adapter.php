<?php

declare(strict_types=1);

namespace Leuffen\Shiller\Access\Adapter;

use Leuffen\Shiller\Access\Info\Capabilities;
use Leuffen\Shiller\Access\DTO\Document;
use Leuffen\Shiller\Access\DTO\DocumentReference;
use Leuffen\Shiller\Access\DTO\FieldSet;
use Leuffen\Shiller\Access\DTO\PageTree;
use Leuffen\Shiller\Access\DTO\SiteConfig;
use Leuffen\Shiller\Access\Storage\SiteStorage;

/**
 * Adaptervertrag zwischen Shillers gemeinsamer Laufzeit und dem konkreten Ablageformat.
 *
 * Adapter normalisieren vorhandene Quellen und Mutationen, während Shiller
 * Dokumentidentität, Übersetzungen und den kontrollierten Storage verwaltet.
 */
interface Adapter
{
    public function bind(SiteStorage $storage, array $projectConfig = []): void;

    public function loadConfig(): SiteConfig;

    /**
     * Löst einen exakten Sourcepfad auch für eine noch fehlende Variante auf.
     *
     * Die Auflösung liest nur Konfiguration und Storage-Metadaten. Sie erzeugt
     * kein Dokument und wechselt nicht zu einer anderen Endung oder Indexdatei.
     *
     * @param string $path Dateipfad relativ zum Document Root.
     * @return DocumentReference Identität und Existenz der angefragten Datei.
     * @throws \Leuffen\Shiller\Access\Exception\ValidationException Bei ungültigem Dateiformat.
     * @throws \Leuffen\Shiller\Access\Exception\StorageException Bei einem Storage-Fehler.
     * @example $reference = $adapter->resolveDocumentPath('en/leistungen.md');
     * @see \Leuffen\Shiller\Access\ShillerRepository::resolveDocumentPath()
     */
    public function resolveDocumentPath(string $path): DocumentReference;

    public function getSourcePath(string $id, string $language): string;

    public function load(string $id, string $language): Document;

    public function create(
        string $id,
        string $language,
        array $header = [],
        string $content = '',
    ): Document;

    /**
     * Speichert die übergebenen Dokumente als gemeinsamen Endzustand.
     *
     * @param list<Document> $documents
     */
    public function write(array $documents): void;

    public function buildTree(string $id = '/'): PageTree;

    public function getEffectiveHeader(Document $document): array;

    public function getHeaderDefinitions(string $id, ?string $language = null): FieldSet;

    /** @return list<array<string, mixed>> */
    public function getEditableHeaders(Document $document): array;

    public function getUrl(Document $document, bool $absolute = false): string;

    public function getDocumentByUrl(string $url): Document;

    public function capabilities(string $id, ?string $language = null): Capabilities;

    public function rename(string $id, string $newId): void;

    public function delete(Document $document): void;
}
