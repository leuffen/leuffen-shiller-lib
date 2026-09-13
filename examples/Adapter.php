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
 * Internes Format-Interface: Schiller normalisiert ID/Sprache und verwaltet Documents.
 * Übersetzungen auswählen/listen/klonen ist gemeinsame Schiller-Logik.
 * Der Adapter entscheidet allein über Ablage, eigenen adapterState und zulässige Mutationen.
 */
interface Adapter
{
    /** Bindet kontrollierten Storage; keine direkte Umgehung der Rechteprüfung. */
    public function bind(SiteStorage $storage): void;

    /** Liest Root-Konfiguration und normalisiert Adapter, Sprachen und Anzeigenamen. */
    public function loadConfig(): SiteConfig;

    /** Berechnet den Root-relativen bestehenden Quellpfad oder den eindeutigen Anlagekandidaten; schreibt nichts. Kein Existenzbeweis. */
    public function getSourcePath(string $id, string $language): string;

    /** Lädt genau diese gespeicherte Variante; adapterState darf zunächst leer sein; fehlend/gesperrt: NotFoundException. Objektidentität verwaltet Schiller. */
    public function load(string $id, string $language): Document;

    /** Bereitet eine neue Variante vor (file=null), setzt frischen adapterState, prüft Anlagerechte; kein Schreiben. @param array<string, mixed> $header */
    public function create(string $id, string $language, array $header = [], string $content = ''): Document;

    /** Speichert die explizite Liste als gemeinsamen Endzustand; plant nötige Promotionen; Revisionsprüfung/ConflictException erst als spätere Erweiterung. @param list<Document> $documents */
    public function write(array $documents): void;

    /** Liefert Kategorien und vorhandene lesbare Varianten mit ID/Sprache/Datei. Schiller ergänzt fehlende Sprachen über getSourcePath. */
    public function buildTree(string $id = '/'): PageTree;

    /** Liefert aktuellen Header plus Jekyll-Defaults ohne Rückschreiben. @return array<string, mixed> */
    public function getEffectiveHeader(Document $document): array;

    /** Liefert Headerdefinitionen für bestehende/geplante Seite ohne Anlage; Zusatzmetadaten bleiben erlaubt. */
    public function getHeaderDefinitions(string $id, ?string $language = null): FieldSet;

    /** Berechnet Route aus Ablage, aktuellem Header, Sprache und Config. */
    public function getUrl(Document $document, bool $absolute = false): string;

    /** Liefert tatsächliche Quelle oder UrlNotResolvableException mit bereinigter Diagnose. */
    public function getDocumentByUrl(string $url): Document;

    /** Liefert erlaubte Quellaktionen; rename=true garantiert kein noch unbekanntes Ziel. */
    public function capabilities(string $id, ?string $language = null): Capabilities;

    /** Verschiebt Teilbaum und Varianten sofort; prüft Quellen/Ziele und Rechte, erhält Header und Body. Kein verpflichtender Revisionsvergleich. */
    public function rename(string $id, string $newId): void;

    /** Löscht Blattgruppe am Original oder einzelne Sprachdatei; / schützen, Nachfahren/Begleitdateien nicht rekursiv löschen. */
    public function delete(Document $document): void;
}
