<?php

declare(strict_types=1);

namespace Leuffen\Shiller\Types;

/** Umsetzungsplan fuer ein oder mehrere Tasks aus dem Resolve-Schritt. */
final class T_Resolution
{
    /** Eindeutige Resolution-ID, z. B. R-0001. */
    public string $id;

    /** Zugehoerige IDs mehrerer Aenderungswuensche.
     * @var list<string>
     */
    public array $taskIds;

    /** Beschreibung der geplanten Loesung. */
    public string $description;

    /** Alle betroffenen Dateien, auch mehrere pro Resolution, relativ zur Projektwurzel.
     * @var list<string>
     */
    public array $files;

    /** Geplante, noch nicht ausgefuehrte Schritte.
     * @var list<string>
     */
    public array $changes;

    /**
     * @param string $id Eindeutige Resolution-ID.
     * @param list<string> $taskIds Tasks dieser Resolution.
     * @param string $description Beschreibung der Loesung.
     * @param list<string> $files Eine oder mehrere Projektdateien des gemeinsamen Plans.
     * @param list<string> $changes Geplante Aenderungen.
     * @see T_Changes
     *
     * Beispiel: new T_Resolution('R-0001', ['T-0001'], 'Logo tauschen', ['docs/index.md', 'docs/_data/general.yml'], ['Bildreferenz ersetzen']).
     */
    public function __construct(string $id, array $taskIds, string $description, array $files, array $changes)
    {
        $this->id = $id;
        $this->taskIds = $taskIds;
        $this->description = $description;
        $this->files = $files;
        $this->changes = $changes;
    }
}
