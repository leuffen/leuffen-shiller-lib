<?php

declare(strict_types=1);

namespace Leuffen\Schiller\Examples;

use Leuffen\Schiller\Capabilities;
use Leuffen\Schiller\Document;
use Leuffen\Schiller\FieldSet;
use Leuffen\Schiller\PageTree;
use Leuffen\Schiller\SiteConfig;
use Leuffen\Schiller\SiteStorage;

require_once __DIR__ . '/Adapter.php';

// Implementierungsskizze für Framework-Entwickler; Verhalten steht jeweils am Methodenstumpf.
final class LegacyAdapter implements Adapter
{
    public function __construct(private SiteStorage $storage) {}

    /** Liest Root-Konfiguration und normalisiert Adapter, Sprachen und Anzeigenamen. */
    public function loadConfig(): SiteConfig
    {
        throw new \LogicException('Entwurfsstub: LegacyAdapter::loadConfig');
    }

    /** Ordnet ID/Sprache vorhandenen pid.lang.md/html-Dateien zu; für fehlende Sprache nur eindeutig ableitbaren Kandidaten liefern, sonst UnsupportedOperationException. Keine Anlage. */
    public function getSourcePath(string $id, string $language): string
    {
        throw new \LogicException('Entwurfsstub: LegacyAdapter::getSourcePath');
    }

    /** Liest vorhandene Variante samt PID/lang und eigenen Metadaten; setzt adapterState für den gelesenen Speicherstand. */
    public function load(string $id, string $language): Document
    {
        throw new \LogicException('Entwurfsstub: LegacyAdapter::load');
    }

    /** Wirft UnsupportedOperationException für jede neue Seite/Sprachdatei, auch für Admin. */
    public function create(string $id, string $language, array $header = [], string $content = ''): Document
    {
        throw new \LogicException('Entwurfsstub: LegacyAdapter::create');
    }

    /** Prüft eigenen adapterState aller Documents, bearbeitet nur vorhandene Dateien als gemeinsamen Batch, erhält PID/lang und unbekannte Headerwerte. Niemals anlegen. @param list<Document> $documents */
    public function write(array $documents): void
    {
        throw new \LogicException('Entwurfsstub: LegacyAdapter::write');
    }

    /** Liest _section.yml-Kategorien und vorhandene Sprachdateien. Keine fehlenden Varianten erzeugen; das ergänzt Schiller. */
    public function buildTree(string $id = '/'): PageTree
    {
        throw new \LogicException('Entwurfsstub: LegacyAdapter::buildTree');
    }

    /** Liefert aktuellen Header plus Jekyll-Defaults ohne Rückschreiben. @return array<string, mixed> */
    public function getEffectiveHeader(Document $document): array
    {
        throw new \LogicException('Entwurfsstub: LegacyAdapter::getEffectiveHeader');
    }

    /** Normalisiert Legacy-Formen, Layouts, order und ptags samt Darstellungshinweisen; keine Datei anlegen. */
    public function getHeaderDefinitions(string $id, ?string $language = null): FieldSet
    {
        throw new \LogicException('Entwurfsstub: LegacyAdapter::getHeaderDefinitions');
    }

    /** Berechnet belegbare Legacy-Ausgabewege/Permalinks; unbekannte Build-Semantik ausdrücklich melden. */
    public function getUrl(Document $document, bool $absolute = false): string
    {
        throw new \LogicException('Entwurfsstub: LegacyAdapter::getUrl');
    }

    /** Ordnet belegbare Legacy-Route der tatsächlichen ID/Sprache zu; keine Polyglot-Routen erfinden. */
    public function getDocumentByUrl(string $url): Document
    {
        throw new \LogicException('Entwurfsstub: LegacyAdapter::getDocumentByUrl');
    }

    /** read/write bei erlaubtem Bestand; sämtliche Anlage-/Strukturaktionen false, auch für Admin. */
    public function capabilities(string $id, ?string $language = null): Capabilities
    {
        throw new \LogicException('Entwurfsstub: LegacyAdapter::capabilities');
    }

    /** Wirft UnsupportedOperationException: keine Legacy-Strukturänderungen. */
    public function rename(string $id, string $newId): void
    {
        throw new \LogicException('Entwurfsstub: LegacyAdapter::rename');
    }

    /** Wirft UnsupportedOperationException: keine Legacy-Löschungen. */
    public function delete(Document $document): void
    {
        throw new \LogicException('Entwurfsstub: LegacyAdapter::delete');
    }
}
