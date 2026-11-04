<?php

declare(strict_types=1);

namespace Leuffen\Shiller\Types;

/** Originaltextblock oder Dateiname eines referenzierten Assets. */
final class T_Quote
{
    /** Originalwortlaut des Absatzes oder Asset-Dateiname. */
    public string $text;

    /** Task-IDs fuer alle Aenderungswuensche dieser Textstelle.
     * @var list<string>
     */
    public array $taskIds;

    /**
     * @param string $text Originaltext oder Asset-Dateiname.
     * @param list<string> $taskIds Zugeordnete Tasks.
     * @see T_Task
     *
     * Beispiel: new T_Quote('Logo austauschen', ['T-0001']).
     */
    public function __construct(string $text, array $taskIds)
    {
        $this->text = $text;
        $this->taskIds = $taskIds;
    }
}
