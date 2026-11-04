<?php

declare(strict_types=1);

namespace Leuffen\Shiller\Types;

/** Einzelner Aenderungswunsch des Kunden ohne Loesung. */
final class T_Task
{
    /** Stabile Task-ID, z. B. T-0001. */
    public string $id;

    /** Kurzbeschreibung dessen, was geaendert werden soll. */
    public string $summary;

    /**
     * @param string $id In Quotes referenzierte Task-ID.
     * @param string $summary Aenderungsanforderung ohne Loesung.
     * @see T_Quote
     *
     * Beispiel: new T_Task('T-0001', 'Logo ersetzen').
     */
    public function __construct(string $id, string $summary)
    {
        $this->id = $id;
        $this->summary = $summary;
    }
}
