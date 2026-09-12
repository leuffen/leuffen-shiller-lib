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

// ENTWURF: Stümpfe, keine produktive Implementierung. Alle Rückgaben sind beschrieben.
final class PolyglotAdapter implements Adapter
{
    public function __construct(private SiteStorage $storage) {}

    /** Liest _config.yml/schiller.yaml und prüft zentrale Sprachverzeichnis-Defaults. */
    public function loadConfig(): SiteConfig
    {
        throw new \LogicException('Entwurfsstub: PolyglotAdapter::loadConfig');
    }

    /** ID /leistungen => leistungen.md oder leistungen/index.md; en entsprechend en/.... Mehrdeutige Quellen ablehnen. */
    public function load(string $id, ?string $language = null): Document
    {
        throw new \LogicException('Entwurfsstub: PolyglotAdapter::load');
    }

    /** ID /leistungen/allgemeinmedizin => transienter Root mit header/content, file=null. Noch keine Ordner oder Eltern bewegen. */
    public function create(string $id, array $header = [], string $content = ''): Document
    {
        throw new \LogicException('Entwurfsstub: PolyglotAdapter::create');
    }

    /** Legt beim ersten Kind Elternordner an und verschiebt Elternseiten aller vorhandenen Sprachen nach index.md/.html; schreibt Kind in derselben vorbereiteten Operation. */
    public function write(Document $document): void
    {
        throw new \LogicException('Entwurfsstub: PolyglotAdapter::write');
    }

    /** Endungslose IDs: /, /leistungen, /leistungen/allgemeinmedizin. Index am Kategorie-Knoten; keine eigene Index-ID. */
    public function buildTree(string $id = '/'): PageTree
    {
        throw new \LogicException('Entwurfsstub: PolyglotAdapter::buildTree');
    }

    /** Originalkopie mit allen eigenen Metadaten, unverändertem Inhalt und published=false; Dateiziel folgt aktueller Blatt-/Indexablage. */
    public function getTranslation(Document $document, ?string $language = null, bool $createIfMissing = false): ?Document
    {
        throw new \LogicException('Entwurfsstub: PolyglotAdapter::getTranslation');
    }

    /** Alle lesbaren Sprachen mit exists und aktuellen Kandidatenpfaden; keine Datei durch Listing erzeugen. */
    public function getTranslations(Document $document): array
    {
        throw new \LogicException('Entwurfsstub: PolyglotAdapter::getTranslations');
    }

    /** Header plus zentrale Jekyll-Defaults, ohne geerbtes lang in den Dateiheader zu schreiben. */
    public function getEffectiveHeader(Document $document): array
    {
        throw new \LogicException('Entwurfsstub: PolyglotAdapter::getEffectiveHeader');
    }

    /** Definierte Schlüssel validieren, zusätzliche manuelle YAML-Werte erhalten und in neue Übersetzungen übernehmen. */
    public function getHeaderDefinitions(Document $document): FieldSet
    {
        throw new \LogicException('Entwurfsstub: PolyglotAdapter::getHeaderDefinitions');
    }

    /** Natürliche Route anhand aktueller Datei: leistungen.md => /leistungen.html, Index => /leistungen/. Expliziten Permalink erhalten. */
    public function getUrl(Document $document, bool $absolute = false): string
    {
        throw new \LogicException('Entwurfsstub: PolyglotAdapter::getUrl');
    }

    /** Bereinigt URL, benutzt aktuellen Routenindex; liefert ID/Sprache des tatsächlichen Documents, auch bei bekanntem Root-Fallback. */
    public function getDocumentByUrl(string $url): Document
    {
        throw new \LogicException('Entwurfsstub: PolyglotAdapter::getDocumentByUrl');
    }

    /** Prüft betroffene Quellen/Ziele einschließlich Übersetzungen, Ordneranlage und nötiger Elternumstellung; keine reine globale Schreibfreigabe. */
    public function capabilities(string $id, ?string $language = null): Capabilities
    {
        throw new \LogicException('Entwurfsstub: PolyglotAdapter::capabilities');
    }

    /** Ganzer Teilbaum samt Begleitdateien und Sprachverzeichnissen. Blatt-Zieleltern zuvor zu Index umstellen. Details/Testplan: docs/verschieben.md. */
    public function rename(string $id, string $newId): void
    {
        throw new \LogicException('Entwurfsstub: PolyglotAdapter::rename');
    }

    /** Einzelne Übersetzung oder Blatt-Seitengruppe; vorhandene Nachfahren verhindern Kategorienlöschung. Keine automatische Rückwandlung von Index zu Blatt. */
    public function delete(Document $document): void
    {
        throw new \LogicException('Entwurfsstub: PolyglotAdapter::delete');
    }
}
