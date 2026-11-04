<?php

declare(strict_types=1);

namespace Leuffen\Shiller\Types;

/** Vollstaendiger Abschnitt aus der Originaldatei, einschliesslich Leertext. */
final class T_Quote
{
    /** Originaltextblock ohne Paraphrase, Normalisierung oder Auslassung. */
    public string $text;

    /**
     * Verknuepfte Task-IDs. Leer, wenn der Text keine Aenderung beschreibt.
     *
     * @var list<string>
     */
    public array $taskIds;

    /**
     * @param string $text Exakter Abschnitt des Originaltexts.
     * @param list<string> $taskIds Zugeordnete Tasks; [] fuer Fuellelemente.
     * @see T_QuoteFile
     *
     * Beispiel: new T_Quote("Vielen Dank.\n", []).
     */
    public function __construct(string $text, array $taskIds)
    {
        $this->text = $text;
        $this->taskIds = $taskIds;
    }
}
