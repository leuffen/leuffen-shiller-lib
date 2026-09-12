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
final class LegacyAdapter implements Adapter
{
    /**
     * Liest alte Konfigurationsquellen und normalisiert sie zu SiteConfig, z.B. Sprachen de/en/fr. Unbekannte Konfigurationsformen melden einen Fehler.
     */
    public function loadConfig(SiteStorage $storage): SiteConfig
    {
        throw new \LogicException('Entwurfsstub: LegacyAdapter::loadConfig');
    }

    /**
     * Liest _section.yml und <pid>.<lang>.md/html. Prüft pid/lang im Header. Liefert z.B. Knoten leistungen mit file=null oder index.de.md und Kind diagnostik.md mit FileEntry diagnostik.de.md. Fehlendes fr: exists=false.
     */
    public function buildPageTree(SiteStorage $storage, SiteConfig $config, string $path = ''): PageTree
    {
        throw new \LogicException('Entwurfsstub: LegacyAdapter::buildPageTree');
    }

    /**
     * Liefert z.B. leistungen/diagnostik.en.md. Vorhandene md/html-Varianten werden über den Bestandsindex erkannt; ein neuer Kandidat übernimmt die Root-Endung.
     */
    public function getTranslationPath(Document $document, string $language, SiteConfig $config): string
    {
        throw new \LogicException('Entwurfsstub: LegacyAdapter::getTranslationPath');
    }

    /**
     * Liefert wirksame alte Jekyll-Defaults und Dateiwerte inklusive pid/lang. Diese bleiben im Legacy-Header zulässig.
     * @return array<string, mixed> Wirksame YAML-Headerwerte.
     */
    public function getEffectiveHeader(Document $document, SiteConfig $config): array
    {
        throw new \LogicException('Entwurfsstub: LegacyAdapter::getEffectiveHeader');
    }

    /**
     * Liefert eine anhand der alten Build-Regeln belegte Route, z.B. einen expliziten Permalink. Unbelegbare Routen werfen UnsupportedOperationException; keine Polyglot-Route erfinden.
     */
    public function getUrl(Document $document, SiteConfig $config, bool $absolute = false): string
    {
        throw new \LogicException('Entwurfsstub: LegacyAdapter::getUrl');
    }

    /**
     * Liefert im ersten Legacy-Vertrag false: zunächst nur Lesen. Dies ist keine Aussage über den alten Page Builder selbst.
     */
    public function supportsWriting(): bool
    {
        throw new \LogicException('Entwurfsstub: LegacyAdapter::supportsWriting');
    }
}
