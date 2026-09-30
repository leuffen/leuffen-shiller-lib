<?php

declare(strict_types=1);

namespace Leuffen\Shiller\Automation;

/**
 * Beschreibt eine fuer genau eine Zieldatei aufgeloeste Shiller-Rule.
 */
final readonly class ShillerRuleMatch
{
    /**
     * @param string $source Relativer Pfad der Rule im Document Root.
     * @param string $selector Der fuer diese Zieldatei spezifischste passende Selector.
     * @param int $matchCount Anzahl editierbarer Dateien, die dieser Selector aktuell trifft.
     * @param float $specificity Berechnete Spezifitaet als 1 / matchCount.
     * @param bool $important true, wenn diese Rule normale Regeln ueberstimmt.
     * @param list<string> $events Explizite on-Events; leer bedeutet alle Events.
     * @param string $content Markdown-Body der Rule ohne Front Matter.
     * @see ShillerRuleSetManager::getRulesFor()
     * @example $rule = new ShillerRuleMatch('_rules.d/index.md', 'index.md', 1, 1.0, true, ['edit'], 'Titel erhalten.'); assert($rule->specificity === 1.0);
     */
    public function __construct(
        public string $source,
        public string $selector,
        public int $matchCount,
        public float $specificity,
        public bool $important,
        public array $events,
        public string $content,
    ) {}
}
