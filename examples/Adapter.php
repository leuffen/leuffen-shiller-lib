<?php

declare(strict_types=1);

namespace Leuffen\Schiller\Examples;

use Leuffen\Schiller\Document;
use Leuffen\Schiller\PageTree;
use Leuffen\Schiller\SiteConfig;
use Leuffen\Schiller\SiteStorage;

// ENTWURF: gemeinsamer Formatadapter; SiteStorage und DTOs sind vorgeschlagene Typen.
// Storage ist bereits rootgebunden und durch Schiller auf erlaubte Zugriffe begrenzt.
// Document::getHeaderDefinitions() wertet Schiller-Headerdefinitionen gemeinsam aus.
// CRUD, createIfMissing/isPersisted, Rechte, Root-Zuordnung und URL-Rückwärtssuche
// orchestriert Schiller; der Adapter gibt Formatregeln und Vorwärtsrouten vor.
interface Adapter
{
    /**
     * Liest die Formatkonfiguration im Root und liefert normalisierte Sprachen, Defaults und Adapterversion.
     */
    public function loadConfig(SiteStorage $storage): SiteConfig;

    /**
     * Liefert TreeNode-Hierarchie mit optionalem FileEntry, allen lesbaren Sprachen und exists; keine Bodies.
     */
    public function buildPageTree(SiteStorage $storage, SiteConfig $config, string $path = ''): PageTree;

    /**
     * Liefert den tatsächlichen oder berechneten Root-relativen Sprachdateipfad; legt keine Datei an.
     */
    public function getTranslationPath(Document $document, string $language, SiteConfig $config): string;

    /**
     * Liefert gespeicherte Headerwerte plus formatspezifische Defaults, ohne das Dokument zu ändern.
     * @return array<string, mixed> YAML-Werte inklusive wirksamer Defaults.
     */
    public function getEffectiveHeader(Document $document, SiteConfig $config): array;

    /**
     * Liefert den lokalen oder absoluten Ziel-URL-String der konkreten Sprachdatei.
     */
    public function getUrl(Document $document, SiteConfig $config, bool $absolute = false): string;

    /**
     * Liefert die grundsätzliche Format-Schreibunterstützung; ersetzt keine Pfad-/Aktionsrechte.
     */
    public function supportsWriting(): bool;
}
