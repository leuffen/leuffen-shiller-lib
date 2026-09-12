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
final class LegacyAdapter implements Adapter
{
    public function __construct(private SiteStorage $storage) {}

    /** Normalisiert Legacy-Konfiguration, _section.yml und Sprachquelle; keine automatische Migration. */
    public function loadConfig(): SiteConfig
    {
        throw new \LogicException('Entwurfsstub: LegacyAdapter::loadConfig');
    }

    /** ID /leistungen/diagnostik + de => vorhandene leistungen/diagnostik.de.md oder .html. PID/lang prüfen, Endung intern auflösen. */
    public function load(string $id, ?string $language = null): Document
    {
        throw new \LogicException('Entwurfsstub: LegacyAdapter::load');
    }

    /** Wirft UnsupportedOperationException: keine neuen Seiten oder Ordner im Legacy-Vertrag. */
    public function create(string $id, array $header = [], string $content = ''): Document
    {
        throw new \LogicException('Entwurfsstub: LegacyAdapter::create');
    }

    /** Bearbeitet ausschließlich bestehende Datei; erhält unbekannte Metadaten und konsistente PID/lang. Fehlende Datei nicht neu anlegen. */
    public function write(Document $document): void
    {
        throw new \LogicException('Entwurfsstub: LegacyAdapter::write');
    }

    /** Entfernt Sprachsuffix und Endung aus IDs. _section.yml erzeugt auch Kategorien mit file=null und erhaltenen Metadaten. */
    public function buildTree(string $id = '/'): PageTree
    {
        throw new \LogicException('Entwurfsstub: LegacyAdapter::buildTree');
    }

    /** null liefert Root. Vorhandene Sprachdatei normal liefern; fehlend: null oder bei createIfMissing UnsupportedOperationException. */
    public function getTranslation(Document $document, ?string $language = null, bool $createIfMissing = false): ?Document
    {
        throw new \LogicException('Entwurfsstub: LegacyAdapter::getTranslation');
    }

    /** Alle lesbaren konfigurierten Sprachen mit exists, auch wenn im Legacy-Profil keine Neuanlage erlaubt ist. */
    public function getTranslations(Document $document): array
    {
        throw new \LogicException('Entwurfsstub: LegacyAdapter::getTranslations');
    }

    /** Alte Jekyll-Defaults plus Dateiheader einschließlich PID/lang; Originalheader nicht verändern. */
    public function getEffectiveHeader(Document $document): array
    {
        throw new \LogicException('Entwurfsstub: LegacyAdapter::getEffectiveHeader');
    }

    /** Bildet bekannte Legacy-Formdefinitionen ab. Manuelle unbekannte Headerwerte bleiben beim Schreiben erhalten. */
    public function getHeaderDefinitions(Document $document): FieldSet
    {
        throw new \LogicException('Entwurfsstub: LegacyAdapter::getHeaderDefinitions');
    }

    /** Nur belegbare Legacy-Ausgabewege oder Permalinks verwenden; unbekannte Build-Semantik ausdrücklich melden. */
    public function getUrl(Document $document, bool $absolute = false): string
    {
        throw new \LogicException('Entwurfsstub: LegacyAdapter::getUrl');
    }

    /** Ordnet eine belegbare Legacy-Route ihrer ID und tatsächlichen Sprache zu; keine Polyglot-Route erfinden. */
    public function getDocumentByUrl(string $url): Document
    {
        throw new \LogicException('Entwurfsstub: LegacyAdapter::getDocumentByUrl');
    }

    /** read/write bei erlaubtem Bestand; createFile/createDirectory/createTranslation/rename/delete immer false, auch für Admin. */
    public function capabilities(string $id, ?string $language = null): Capabilities
    {
        throw new \LogicException('Entwurfsstub: LegacyAdapter::capabilities');
    }

    /** Wirft UnsupportedOperationException: keine Legacy-Strukturänderungen. */
    public function rename(string $id, string $newId): void
    {
        throw new \LogicException('Entwurfsstub: LegacyAdapter::rename');
    }

    /** Wirft UnsupportedOperationException: nur vorhandene Legacy-Inhalte bearbeiten. */
    public function delete(Document $document): void
    {
        throw new \LogicException('Entwurfsstub: LegacyAdapter::delete');
    }
}
