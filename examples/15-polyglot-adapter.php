<?php

declare(strict_types=1);

namespace Leuffen\Schiller\Examples;

use Leuffen\Schiller\Document;
use Leuffen\Schiller\PageTree;
use Leuffen\Schiller\SiteConfig;
use Leuffen\Schiller\SiteStorage;

require_once __DIR__ . '/Adapter.php';

// ENTWURFSIMPLEMENTIERUNG: nur Signaturen, Aufgaben und erwartete Rückgaben.
// Alle Methoden sind absichtlich Stümpfe und werfen statt Dummy-Daten zu liefern.
final class PolyglotAdapter implements Adapter
{
    /**
     * Liest _config.yml und Schiller-Adapterauswahl. Prüft languages/default_lang sowie zentrale Verzeichnis-Defaults; liefert SiteConfig.
     */
    public function loadConfig(SiteStorage $storage): SiteConfig
    {
        throw new \LogicException('Entwurfsstub: PolyglotAdapter::loadConfig');
    }

    /**
     * Liefert Root mit index.md, Kategorien mit optionaler index.md und Kindern. Sprachordner werden am neutralen Knoten gruppiert; FileEntry zeigt z.B. leistungen/diagnostik.md, fr wird auch mit exists=false gelistet.
     */
    public function buildPageTree(SiteStorage $storage, SiteConfig $config, string $path = ''): PageTree
    {
        throw new \LogicException('Entwurfsstub: PolyglotAdapter::buildPageTree');
    }

    /**
     * Liefert für de z.B. leistungen/diagnostik.md, für en en/leistungen/diagnostik.md. Keine Sprach-/ID-Header auswerten; keine Anlage.
     */
    public function getTranslationPath(Document $document, string $language, SiteConfig $config): string
    {
        throw new \LogicException('Entwurfsstub: PolyglotAdapter::getTranslationPath');
    }

    /**
     * Liefert Header plus zentrale Jekyll-Defaults, z.B. geerbtes lang=en. Keine Übernahme dieser Defaults in den gespeicherten Header.
     * @return array<string, mixed> Wirksame YAML-Headerwerte.
     */
    public function getEffectiveHeader(Document $document, SiteConfig $config): array
    {
        throw new \LogicException('Entwurfsstub: PolyglotAdapter::getEffectiveHeader');
    }

    /**
     * Liefert z.B. /en/leistungen/diagnostik.html oder mit absolute=true die konfigurierte absolute URL. Index, baseurl und Ausnahme-Permalink beachten.
     */
    public function getUrl(Document $document, SiteConfig $config, bool $absolute = false): string
    {
        throw new \LogicException('Entwurfsstub: PolyglotAdapter::getUrl');
    }

    /**
     * Liefert für dieses Profil grundsätzlich true. Tatsächliche Anlage/Rename/Delete hängen zusätzlich von Rechten, Validierung und Storage-Fähigkeiten ab.
     */
    public function supportsWriting(): bool
    {
        throw new \LogicException('Entwurfsstub: PolyglotAdapter::supportsWriting');
    }
}
