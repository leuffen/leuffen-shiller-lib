<?php

declare(strict_types=1);

namespace Leuffen\Shiller;

interface MoveCapableStorage extends SiteStorage
{
    /**
     * Verschiebt Dateien oder Verzeichnisse und schreibt Dokumente als einen rücknehmbaren Auftrag.
     *
     * Alle Pfade sind relativ zum Site-Root. Vorhandene Ziele werden nie überschrieben.
     * Bei einem Fehler wird der vorherige Zustand wiederhergestellt oder ein unvollständiger
     * Rollback ausdrücklich als StorageException gemeldet.
     *
     * Beispiel: $storage->moveBatch(['seite.md' => 'seite/index.md'], ['seite/kind.md' => $body]);
     *
     * @param array<string,string> $moves Quellpfad => Zielpfad.
     * @param array<string,?string> $changes Zielpfad => Inhalt; null löscht.
     *
     * @throws StorageException Wenn I/O oder Rückabwicklung fehlschlagen.
     * @throws ValidationException Bei ungültigem Pfad oder Symlink.
     *
     * @see SiteStorage::writeBatch()
     */
    public function moveBatch(array $moves, array $changes = []): void;
}
