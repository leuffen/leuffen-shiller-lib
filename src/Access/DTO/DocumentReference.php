<?php

declare(strict_types=1);

namespace Leuffen\Shiller\Access\DTO;

final class DocumentReference
{
    /**
     * Beschreibt eine vorhandene oder fehlende Source-Datei ohne Neuanlage.
     *
     * Der Adapter bestimmt Identität und Sprache anhand des Dateiformats.
     * exists ist eine Momentaufnahme des Storage, kein vorbereitetes Dokument.
     *
     * @param string $path Kanonischer Dateipfad relativ zum Document Root.
     * @param string $id Sprachneutrale Seiten-ID einschließlich führendem Slash.
     * @param string $language Aus dem Sourcepfad ermittelte konfigurierte Sprache.
     * @param bool $exists Ob genau diese Source-Datei im Storage vorhanden ist.
     * @example $reference = $site->resolveDocumentPath('en/leistungen.md');
     * @see \Leuffen\Shiller\Access\ShillerRepository::resolveDocumentPath()
     */
    public function __construct(
        public readonly string $path,
        public readonly string $id,
        public readonly string $language,
        public readonly bool $exists,
    ) {}
}
