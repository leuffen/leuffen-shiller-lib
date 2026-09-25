<?php

declare(strict_types=1);

namespace Leuffen\Schiller\Adapter;

use Leuffen\Schiller\Capabilities;
use Leuffen\Schiller\Document;
use Leuffen\Schiller\FieldSet;
use Leuffen\Schiller\PageTree;
use Leuffen\Schiller\SiteConfig;
use Leuffen\Schiller\SiteStorage;

/**
 * Adaptervertrag zwischen Schillers gemeinsamer Laufzeit und dem konkreten Ablageformat.
 *
 * Adapter normalisieren vorhandene Quellen und Mutationen, während Schiller
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

    public function getUrl(Document $document, bool $absolute = false): string;

    public function getDocumentByUrl(string $url): Document;

    public function capabilities(string $id, ?string $language = null): Capabilities;

    public function rename(string $id, string $newId): void;

    public function delete(Document $document): void;
}
