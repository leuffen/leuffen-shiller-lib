<?php

declare(strict_types=1);

namespace Leuffen\Shiller\Access\Adapter;

use Leuffen\Shiller\Access\Info\Capabilities;
use Leuffen\Shiller\Access\DTO\Document;
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
    public function bind(SiteStorage $storage): void;

    public function loadConfig(): SiteConfig;

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
