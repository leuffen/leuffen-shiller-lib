<?php

declare(strict_types=1);

namespace Leuffen\Schiller\Adapter;

use Leuffen\Schiller\Capabilities;
use Leuffen\Schiller\Document;
use Leuffen\Schiller\FieldSet;
use Leuffen\Schiller\PageTree;
use Leuffen\Schiller\SiteConfig;
use Leuffen\Schiller\SiteStorage;

require_once __DIR__ . '/Adapter.php';

// Implementierungsskizze für Framework-Entwickler; Verhalten steht jeweils am Methodenstumpf.
final class JekyllLegacyAdapter implements Adapter
{
    private SiteStorage $storage;

    /** Wird durch SchillerDir einmalig nach der argumentlosen Konstruktion aufgerufen. */
    public function bind(SiteStorage $storage): void
    {
        throw new \LogicException('Entwurfsstub: JekyllLegacyAdapter::bind');
    }

    /** Liest Root-Konfiguration und normalisiert Adapter, Sprachen und Anzeigenamen. */
    public function loadConfig(): SiteConfig
    {
        throw new \LogicException('Entwurfsstub: JekyllLegacyAdapter::loadConfig');
    }

    /** Ordnet ID/Sprache vorhandenen pid.lang.md/html-Dateien zu; für fehlende Sprache nur eindeutig ableitbaren Kandidaten liefern, sonst UnsupportedOperationException. Keine Anlage. */
    public function getSourcePath(string $id, string $language): string
    {
        throw new \LogicException('Entwurfsstub: JekyllLegacyAdapter::getSourcePath');
    }

    /** Liest vorhandene Variante samt PID/lang und eigenen Metadaten; adapterState darf zunächst leer bleiben. */
    public function load(string $id, string $language): Document
    {
        throw new \LogicException('Entwurfsstub: JekyllLegacyAdapter::load');
    }

    /** Wirft UnsupportedOperationException für jede neue Seite/Sprachdatei, auch für Admin. */
    public function create(string $id, string $language, array $header = [], string $content = ''): Document
    {
        throw new \LogicException('Entwurfsstub: JekyllLegacyAdapter::create');
    }

    /** Bearbeitet nur vorhandene Dateien als gemeinsamen Batch; keine verpflichtende Revisionsprüfung im ersten Ausbau, erhält PID/lang und unbekannte Headerwerte. Niemals anlegen. @param list<Document> $documents */
    public function write(array $documents): void
    {
        throw new \LogicException('Entwurfsstub: JekyllLegacyAdapter::write');
    }

    /** Liefert PageTree nach TreeNode-Konvention v1: _section.yml-Bezeichnung als label, Zusatzwerte in data.metadata, Quelle in data.file (Kategorie ohne Seite: null), Bestand in data.translations. children vollständig; fehlende Sprachen ergänzt Schiller. */
    public function buildTree(string $id = '/'): PageTree
    {
        throw new \LogicException('Entwurfsstub: JekyllLegacyAdapter::buildTree');
    }

    /** Liefert aktuellen Header plus Jekyll-Defaults ohne Rückschreiben. @return array<string, mixed> */
    public function getEffectiveHeader(Document $document): array
    {
        throw new \LogicException('Entwurfsstub: JekyllLegacyAdapter::getEffectiveHeader');
    }

    /** Normalisiert Legacy-Formen, Layouts, order und ptags samt Darstellungshinweisen; keine Datei anlegen. */
    public function getHeaderDefinitions(string $id, ?string $language = null): FieldSet
    {
        throw new \LogicException('Entwurfsstub: JekyllLegacyAdapter::getHeaderDefinitions');
    }

    /** Berechnet belegbare Legacy-Ausgabewege/Permalinks; unbekannte Build-Semantik ausdrücklich melden. */
    public function getUrl(Document $document, bool $absolute = false): string
    {
        throw new \LogicException('Entwurfsstub: JekyllLegacyAdapter::getUrl');
    }

    /** Ordnet belegbare Legacy-Route der tatsächlichen ID/Sprache zu; keine Polyglot-Routen erfinden. */
    public function getDocumentByUrl(string $url): Document
    {
        throw new \LogicException('Entwurfsstub: JekyllLegacyAdapter::getDocumentByUrl');
    }

    /** read/write bei erlaubtem Bestand; sämtliche Anlage-/Strukturaktionen false, auch für Admin. */
    public function capabilities(string $id, ?string $language = null): Capabilities
    {
        throw new \LogicException('Entwurfsstub: JekyllLegacyAdapter::capabilities');
    }

    /** Wirft UnsupportedOperationException: keine Legacy-Strukturänderungen. */
    public function rename(string $id, string $newId): void
    {
        throw new \LogicException('Entwurfsstub: JekyllLegacyAdapter::rename');
    }

    /** Wirft UnsupportedOperationException: keine Legacy-Löschungen. */
    public function delete(Document $document): void
    {
        throw new \LogicException('Entwurfsstub: JekyllLegacyAdapter::delete');
    }
}
