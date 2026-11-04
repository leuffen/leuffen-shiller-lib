<?php

declare(strict_types=1);

namespace Leuffen\Shiller\Types;

/** Alle Originaltextbloecke einer einzelnen Quelldatei in ihrer Reihenfolge. */
final class T_QuoteFile
{
    /** Dateiname relativ zur Shiller-Projektwurzel; stdin fuer direkte Eingaben. */
    public string $fileName;

    /**
     * Lueckenlose, unveraenderte Originaltextbloecke in Dateireihenfolge.
     * Auch belangloser Text bleibt als Quote mit taskIds = [] erhalten.
     *
     * @var list<T_Quote>
     */
    public array $quotes;

    /**
     * @param string $fileName Eindeutiger Quellpfad.
     * @param list<T_Quote> $quotes Vollstaendige Originaltextabdeckung.
     * @see T_Quote
     * @see T_Changes
     *
     * Beispiel: new T_QuoteFile('feedback.txt', [new T_Quote("Hallo\n", [])]).
     */
    public function __construct(string $fileName, array $quotes)
    {
        $this->fileName = $fileName;
        $this->quotes = $quotes;
    }
}
