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

    /** @var list<string> Offene Fragen fuer fehlende Informationen bei Resolve. */
    public array $questions;

    /** Zusaetzliche manuelle Angaben des Administrators zur Aufgabenloesung. */
    public string $adminInformation;

    /**
     * @param string $id In Quotes referenzierte Task-ID.
     * @param string $summary Aenderungsanforderung ohne Loesung.
     * @param list<string> $questions Offene Fragen aus dem Resolve-Schritt.
     * @param string $adminInformation Manuelle Hinweise fuer die Planung.
     * @see T_Quote
     *
     * Beispiel: new T_Task('T-0001', 'Logo ersetzen').
     */
    public function __construct(string $id, string $summary, array $questions = [], string $adminInformation = '')
    {
        $this->id = $id;
        $this->summary = $summary;
        $this->questions = $questions;
        $this->adminInformation = $adminInformation;
    }
}
