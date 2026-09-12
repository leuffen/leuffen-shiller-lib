<?php

declare(strict_types=1);

namespace Leuffen\Schiller\Examples;

use Leuffen\Schiller\Capabilities;
use Leuffen\Schiller\Document;
use Leuffen\Schiller\FieldSet;
use Leuffen\Schiller\PageTree;
use Leuffen\Schiller\SiteConfig;
use Leuffen\Schiller\SiteStorage;

// ENTWURF: Dateiformat und Ablage werden vollständig hinter Schiller abstrahiert.
// Schiller bindet Adapter an kontrollierten Storage und gemeinsame Document-Verwaltung.
// Keine zusätzliche Übersetzungsschicht im Page Builder. Keine fertige Implementierung.
interface Adapter
{
    public function __construct(SiteStorage $storage);

    /** Liest Root-Konfiguration und liefert den normalisierten Adapter-/Sprachvertrag. */
    public function loadConfig(): SiteConfig;

    /** Lädt eine endungslose ID/Sprache; liefert Document mit physischer FileEntry oder wirft NotFoundException. */
    public function load(string $id, ?string $language = null): Document;

    /** Erzeugt einen ungespeicherten Root-Entwurf (file=null); schreibt nichts. @param array<string, mixed> $header */
    public function create(string $id, array $header = [], string $content = ''): Document;

    /** Speichert Header/Body samt nötiger interner Ablageänderungen; prüft alle Quellen/Ziele. */
    public function write(Document $document): void;

    /** Liefert logische TreeNodes mit ID, optionaler FileEntry/metadata und allen lesbaren Sprachvarianten. */
    public function buildTree(string $id = '/'): PageTree;

    /** Liefert Root bei null, vorhandene Variante oder null; optional ungespeicherte Originalkopie. */
    public function getTranslation(Document $document, ?string $language = null, bool $createIfMissing = false): ?Document;

    /** Liefert array<string, TranslationInfo>, einschließlich fehlender lesbarer Sprachdateien mit exists=false. */
    public function getTranslations(Document $document): array;

    /** Liefert array<string, YamlValue> aus gespeicherten Werten und wirksamen Defaults. */
    public function getEffectiveHeader(Document $document): array;

    /** Liefert Definitionen bekannter Header-Schlüssel; unbekannte Metadaten bleiben erlaubt. */
    public function getHeaderDefinitions(Document $document): FieldSet;

    /** Berechnet die URL aus tatsächlicher Ablage, Sprache und Config, nicht allein aus der ID. */
    public function getUrl(Document $document, bool $absolute = false): string;

    /** Liefert das konkrete Quelldokument oder UrlNotResolvableException mit bereinigter Diagnose. */
    public function getDocumentByUrl(string $url): Document;

    /** Liefert erlaubte Aktionen aus Adapter, Zustand, Projekt-/Hostrechten und Storage-Fähigkeiten. */
    public function capabilities(string $id, ?string $language = null): Capabilities;

    /** Verschiebt Root-Seite/Teilbaum und alle Sprachvarianten; ändert IDs und FileEntries. */
    public function rename(string $id, string $newId): void;

    /** Löscht eine Blatt-Seitengruppe oder einzelne Übersetzung; Kategorien mit Kindern derzeit abweisen. */
    public function delete(Document $document): void;
}
