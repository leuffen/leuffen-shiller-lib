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
final class JekyllPolyglotAdapter implements Adapter
{
    private SiteStorage $storage;

    /** Wird durch SchillerDir einmalig nach der argumentlosen Konstruktion aufgerufen. */
    public function bind(SiteStorage $storage): void
    {
        throw new \LogicException('Entwurfsstub: JekyllPolyglotAdapter::bind');
    }

    /** Liest Root-Konfiguration und normalisiert Adapter, Sprachen und Anzeigenamen. */
    public function loadConfig(): SiteConfig
    {
        throw new \LogicException('Entwurfsstub: JekyllPolyglotAdapter::loadConfig');
    }

    /** /leistungen + en => en/leistungen.md oder en/leistungen/index.md; auch fehlenden Kandidaten aus Gruppenablage bestimmen. Mehrdeutigkeit ist ein Konflikt. */
    public function getSourcePath(string $id, string $language): string
    {
        throw new \LogicException('Entwurfsstub: JekyllPolyglotAdapter::getSourcePath');
    }

    /** Liest exakt ID/Sprache aus gespiegelter Ablage ohne PID/lang im Header; adapterState darf zunächst leer bleiben. */
    public function load(string $id, string $language): Document
    {
        throw new \LogicException('Entwurfsstub: JekyllPolyglotAdapter::load');
    }

    /** Bereitet Root oder Übersetzung anhand expliziter Sprache vor; übernimmt Header/Body, erzeugt eigenen Anlagezustand. file=null; reine Kategorie bekommt später Index. */
    public function create(string $id, string $language, array $header = [], string $content = ''): Document
    {
        throw new \LogicException('Entwurfsstub: JekyllPolyglotAdapter::create');
    }

    /** Prüft gemeinsamen Endzustand ohne verpflichtenden Revisionsvergleich; nötige Elternpromotionen aller vorhandenen Sprachen zusammen planen. Fehler: kein Teilerfolg. @param list<Document> $documents */
    public function write(array $documents): void
    {
        throw new \LogicException('Entwurfsstub: JekyllPolyglotAdapter::write');
    }

    /** Liefert PageTree nach TreeNode-Konvention v1: Ordner/Index als ein id/label/children/data-Knoten. Quelle in data.file (reiner Ordner: null), vorhandene Varianten in data.translations. children vollständig; fehlende Sprachen ergänzt Schiller. */
    public function buildTree(string $id = '/'): PageTree
    {
        throw new \LogicException('Entwurfsstub: JekyllPolyglotAdapter::buildTree');
    }

    /** Liefert aktuellen Header plus Jekyll-Defaults ohne Rückschreiben. @return array<string, mixed> */
    public function getEffectiveHeader(Document $document): array
    {
        throw new \LogicException('Entwurfsstub: JekyllPolyglotAdapter::getEffectiveHeader');
    }

    /** Liefert bekannte Felder und presentation für bestehende/geplante IDs; eigene Metadaten bleiben erlaubt. */
    public function getHeaderDefinitions(string $id, ?string $language = null): FieldSet
    {
        throw new \LogicException('Entwurfsstub: JekyllPolyglotAdapter::getHeaderDefinitions');
    }

    /** Berechnet Route aus Ablage, aktuellem Header, Sprache und Config. */
    public function getUrl(Document $document, bool $absolute = false): string
    {
        throw new \LogicException('Entwurfsstub: JekyllPolyglotAdapter::getUrl');
    }

    /** Liefert tatsächliche Quelle oder UrlNotResolvableException mit bereinigter Diagnose. */
    public function getDocumentByUrl(string $url): Document
    {
        throw new \LogicException('Entwurfsstub: JekyllPolyglotAdapter::getDocumentByUrl');
    }

    /** Liefert erlaubte Quellaktionen; rename=true garantiert kein noch unbekanntes Ziel. */
    public function capabilities(string $id, ?string $language = null): Capabilities
    {
        throw new \LogicException('Entwurfsstub: JekyllPolyglotAdapter::capabilities');
    }

    /** Verschiebt gesamten Teilbaum samt Begleitdateien und Übersetzungen; Ziel-Eltern nötigenfalls promovieren. Siehe docs/verschieben.md. */
    public function rename(string $id, string $newId): void
    {
        throw new \LogicException('Entwurfsstub: JekyllPolyglotAdapter::rename');
    }

    /** Übersetzung: nur diese Datei, auch Kategorieindex. Original: nur ohne Nachfahren in allen Sprachen; / abweisen. Kein Index-Rückbau. */
    public function delete(Document $document): void
    {
        throw new \LogicException('Entwurfsstub: JekyllPolyglotAdapter::delete');
    }
}
