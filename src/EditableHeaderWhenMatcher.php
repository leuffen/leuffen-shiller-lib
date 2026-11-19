<?php

declare(strict_types=1);

namespace Leuffen\Shiller;

/**
 * Wiederverwendbarer Matcher für bedingte Formularfelder.
 *
 * Die Headerwerte sind ein assoziatives Array; der Pfad ist relativ zum
 * Document Root. Bedingungen werden vollständig validiert, auch wenn eine
 * frühere Bedingung nicht zutrifft.
 */
final class EditableHeaderWhenMatcher
{
    /**
     * Prüft include/exclude am Quellpfad und on/not an bestehenden Headerwerten.
     *
     * Regex- und Wertelisten sind ODER-verknüpft, verschiedene Bedingungen
     * sowie Header-Schlüssel UND-verknüpft. Fehlende Werte erfüllen "not",
     * aber niemals "on". Bei leerer Regel ist das Ergebnis true.
     *
     * @param array<string, mixed> $fields Aktuelle Headerwerte der Page.
     * @param array<string, mixed> $when Sichtbarkeitsregeln aus YAML.
     * @param string $path Quellpfad relativ zum Document Root.
     * @param string $source Bezeichnung der Konfigurationsdatei für Fehler.
     * @return bool Ob das Feld sichtbar sein soll.
     * @throws ValidationException Bei unbekannter Bedingung, falschen Typen oder ungültigem Regex.
     * @see Document::getEditableHeaders()
     * @example (new EditableHeaderWhenMatcher())->matches(['published' => true], ['on' => ['published' => true]], 'index.md', 'editable-headers.conf.yaml');
     */
    public function matches(array $fields, array $when, string $path, string $source = '<inline>'): bool
    {
        $matches = true;

        foreach ($when as $condition => $criteria) {
            // PHP ext-yaml interpretiert den unquoted YAML-Schlüssel "on" als true (Key 1).
            if ($condition === 1) {
                $condition = 'on';
            }

            if (!in_array($condition, ['include', 'exclude', 'on', 'not'], true)) {
                throw new ValidationException("$source: when.$condition: unknown condition");
            }

            if ($condition === 'include' || $condition === 'exclude') {
                $patterns = is_string($criteria) ? [$criteria] : $criteria;
                if (!is_array($patterns) || !array_is_list($patterns) || $patterns === []) {
                    throw new ValidationException("$source: when.$condition: expected regex or non-empty regex list");
                }

                $any = false;
                foreach ($patterns as $index => $pattern) {
                    if (!is_string($pattern) || @preg_match($pattern, '') === false) {
                        throw new ValidationException("$source: when.{$condition}[$index]: invalid PCRE regex");
                    }
                    if (preg_match($pattern, $path) === 1) {
                        $any = true;
                    }
                }

                if (($condition === 'include' && !$any) || ($condition === 'exclude' && $any)) {
                    $matches = false;
                }
                continue;
            }

            if (!is_array($criteria) || array_is_list($criteria) || $criteria === []) {
                throw new ValidationException("$source: when.$condition: expected non-empty field mapping");
            }
            foreach ($criteria as $key => $expected) {
                if (!is_string($key) || $key === '') {
                    throw new ValidationException("$source: when.$condition: field name must be a string");
                }
                if (is_array($expected) && (!array_is_list($expected) || $expected === [])) {
                    throw new ValidationException("$source: when.$condition.$key: expected scalar or non-empty value list");
                }
                $values = is_array($expected) ? $expected : [$expected];
                $found = array_key_exists($key, $fields)
                    && in_array($fields[$key], $values, true);
                if (($condition === 'on' && !$found) || ($condition === 'not' && $found)) {
                    $matches = false;
                }
            }
        }

        return $matches;
    }
}
