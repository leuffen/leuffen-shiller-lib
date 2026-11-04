<?php

declare(strict_types=1);

namespace Leuffen\Shiller\Automation;

use RuntimeException;

/** Erstellt reproduzierbare Zero-Context-Unified-Diffs mit alten und neuen Zeilenangaben. */
final class ShillerEditDiff
{
    /**
     * Vergleicht vollstaendige Textdateien zeilenweise.
     *
     * Die Hunks enthalten nur geaenderte Zeilen. Der 0-Kontext erlaubt
     * eine eindeutige Zuordnung zu einem Task auch bei spaeteren Aenderungen.
     *
     * @param string $before Inhalt vor dem Task.
     * @param string $after Inhalt nach dem Task.
     * @return list<array{oldStart:int,oldLines:int,newStart:int,newLines:int,diff:string}>
     * @throws RuntimeException Bei Dateien, die das Diff-Limit ueberschreiten.
     * @see ShillerExecuteAction::execute()
     *
     * Beispiel: ShillerEditDiff::between("Alt\n", "Neu\n").
     */
    public static function between(string $before, string $after): array
    {
        if ($before === $after) {
            return [];
        }

        $a = self::lines($before);
        $b = self::lines($after);
        $n = count($a);
        $m = count($b);
        if ($n * $m > 2000000) {
            throw new RuntimeException('File too large for line-accurate edit diff.');
        }

        // Longest-common-subsequence trennt selbst mehrere weit entfernte
        // Aenderungsstellen in unabhaengige, zeilenexakte Hunks.
        $matrix = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));
        for ($i = $n - 1; $i >= 0; $i--) {
            for ($j = $m - 1; $j >= 0; $j--) {
                $matrix[$i][$j] = $a[$i] === $b[$j]
                    ? $matrix[$i + 1][$j + 1] + 1
                    : max($matrix[$i + 1][$j], $matrix[$i][$j + 1]);
            }
        }

        $i = 0;
        $j = 0;
        $oldLine = 1;
        $newLine = 1;
        $hunks = [];
        while ($i < $n || $j < $m) {
            if ($i < $n && $j < $m && $a[$i] === $b[$j]) {
                $i++;
                $j++;
                $oldLine++;
                $newLine++;
                continue;
            }

            // Ein Hunk enthaelt nur den zusammenhaengenden Block bis zur
            // naechsten unveraenderten Zeile. Einfuegungen haben oldLines=0.
            $startOld = $oldLine;
            $startNew = $newLine;
            $removed = [];
            $added = [];
            while ($i < $n || $j < $m) {
                if ($i < $n && $j < $m && $a[$i] === $b[$j]) {
                    break;
                }
                if ($i < $n && ($j === $m || $matrix[$i + 1][$j] >= $matrix[$i][$j + 1])) {
                    $removed[] = '-' . rtrim($a[$i], "\r\n");
                    $i++;
                    $oldLine++;
                } else {
                    $added[] = '+' . rtrim($b[$j], "\r\n");
                    $j++;
                    $newLine++;
                }
            }

            $oldCount = count($removed);
            $newCount = count($added);
            $oldStart = $oldCount === 0 ? $startOld - 1 : $startOld;
            $newStart = $newCount === 0 ? $startNew - 1 : $startNew;
            $header = "@@ -{$oldStart},{$oldCount} +{$newStart},{$newCount} @@";
            $hunks[] = [
                'oldStart' => $oldStart,
                'oldLines' => $oldCount,
                'newStart' => $newStart,
                'newLines' => $newCount,
                'diff' => implode("\n", [$header, ...$removed, ...$added]) . "\n",
            ];
        }
        return $hunks;
    }

    /** @return list<string> */
    private static function lines(string $content): array
    {
        return $content === '' ? [] : preg_split('/(?<=\n)/', $content, -1, PREG_SPLIT_NO_EMPTY);
    }
}
