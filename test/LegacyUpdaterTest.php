<?php

declare(strict_types=1);

use Leuffen\Shiller\Access\ShillerRepository;
use Leuffen\Shiller\LegacyUpdate\LegacyUpdater;
use PHPUnit\Framework\TestCase;

final class LegacyUpdaterTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/shiller-legacy-update-' . bin2hex(random_bytes(6));
        phore_dir($this->root . '/docs')->mkdir();
    }

    protected function tearDown(): void
    {
        phore_dir($this->root)->rmDir(true);
    }

    public function testInitializesCheckoutForRepositoryAccess(): void
    {
        $oldContext = phore_file($this->root . '/.shiller-context.md');
        $oldContext->set_contents('Alter Kontext');
        $updater = new LegacyUpdater();

        self::assertTrue($updater->update($this->root));
        $site = new ShillerRepository($this->root . '/docs', projectRoot: $this->root);
        $config = $site->getProjectConfig();
        self::assertSame('jekyll-legacy:1.0', $config['adapter']);
        self::assertSame('docs', $config['doc_root']);
        self::assertSame('.shiller.d/static-context.md', $config['context_files']);
        self::assertTrue($config['ai']['web_access']);
        self::assertSame('.shiller.d/user-rules.md', $config['user_rules_file']);
        self::assertSame('Alter Kontext', $oldContext->get_contents());

        $rules = phore_file($this->root . '/.shiller.d/user-rules.md');
        $template = phore_file(__DIR__ . '/../src/LegacyUpdate/_tpl/.shiller.d/user-rules.md');
        self::assertSame($template->get_contents(), $rules->get_contents());
        self::assertStringContainsString('## Global', $rules->get_contents());
        self::assertStringContainsString('ausdrücklicher Zustimmung', $rules->get_contents());
        $static = phore_file($this->root . '/.shiller.d/static-context.md');
        $staticTemplate = phore_file(__DIR__ . '/../src/LegacyUpdate/_tpl/.shiller.d/static-context.md');
        self::assertSame($staticTemplate->get_contents(), $static->get_contents());
        self::assertStringContainsString('Kicker', $static->get_contents());
        self::assertStringContainsString('Kramdown', $static->get_contents());
        self::assertStringNotContainsString('Kramdown', $rules->get_contents());
        $rules->set_contents("## Global\n- Bestehende Regel\n");
        $configFile = phore_file($this->root . '/.shiller.yml');
        $before = $configFile->get_contents();

        self::assertFalse($updater->update($this->root));
        self::assertSame($before, $configFile->get_contents());
        self::assertSame("## Global\n- Bestehende Regel\n", $rules->get_contents());
    }

    public function testLeavesExistingConfigurationAndRulesUntouched(): void
    {
        $config = phore_file($this->root . '/.shiller.yml');
        $config->set_contents("version: 1.0\nadapter: jekyll-polyglot:1.0\n");

        self::assertFalse((new LegacyUpdater())->update($this->root));
        self::assertSame("version: 1.0\nadapter: jekyll-polyglot:1.0\n", $config->get_contents());
        self::assertFalse(phore_file($this->root . '/.shiller.d/user-rules.md')->exists());
        self::assertFalse(phore_file($this->root . '/.shiller.d/static-context.md')->exists());
    }

    // Unversionierte Projekte erhalten Defaults, ohne eigene Pfade oder AI-Optionen zu verlieren.
    public function testMigratesExistingConfigurationWithoutVersion(): void
    {
        $config = phore_file($this->root . '/.shiller.yml');
        $config->set_contents("doc_root: public\ntemplate_dir: custom/templates\nlanguages: [de, en]\ncontext_files: []\nskills_dir: custom/skills\nai:\n  model: custom-model\n  web_access: false\n");
        $rules = phore_file($this->root . '/.shiller.d/user-rules.md');
        $rules->mkdir()->set_contents('Eigene Regeln');
        $updater = new LegacyUpdater();

        self::assertTrue($updater->update($this->root));
        $updated = $config->get_yaml();
        self::assertSame('1.0', (string) $updated['version']);
        self::assertSame('jekyll-legacy:1.0', $updated['adapter']);
        self::assertSame('public', $updated['doc_root']);
        self::assertSame('custom/templates', $updated['template_dir']);
        self::assertSame(['de', 'en'], $updated['languages']);
        self::assertSame([], $updated['context_files']);
        self::assertSame('custom/skills', $updated['skills_dir']);
        self::assertSame('custom-model', $updated['ai']['model']);
        self::assertFalse($updated['ai']['web_access']);
        self::assertSame('Eigene Regeln', $rules->get_contents());
        self::assertTrue(phore_file($this->root . '/.shiller.d/static-context.md')->exists());

        // Der zweite Aufruf lässt Konfiguration und vorhandene Regeln bytegenau unverändert.
        $before = $config->get_contents();
        self::assertFalse($updater->update($this->root));
        self::assertSame($before, $config->get_contents());
        self::assertSame('Eigene Regeln', $rules->get_contents());
    }

    // Fehlende verschachtelte Defaults werden ergänzt, vorhandene Adapter bleiben erhalten.
    public function testAddsMissingAiDefaultsWithoutReplacingExistingOptions(): void
    {
        $config = phore_file($this->root . '/.shiller.yml');
        $config->set_contents("adapter: jekyll-polyglot:1.0\nai:\n  model: custom-model\n");

        self::assertTrue((new LegacyUpdater())->update($this->root));
        $updated = $config->get_yaml();
        self::assertSame('jekyll-polyglot:1.0', $updated['adapter']);
        self::assertSame('custom-model', $updated['ai']['model']);
        self::assertTrue($updated['ai']['web_access']);
    }

    // Auch eine bereits angelegte, noch leere Konfiguration benötigt die Initialisierung.
    public function testInitializesEmptyConfiguration(): void
    {
        $config = phore_file($this->root . '/.shiller.yml');
        $config->set_contents('');

        self::assertTrue((new LegacyUpdater())->update($this->root));
        self::assertArrayHasKey('version', $config->get_yaml());
        self::assertFalse((new LegacyUpdater())->update($this->root));
    }

    // Eine restriktive umask darf die geforderten Rechte nicht verkleinern; Bestandsrechte bleiben erhalten.
    public function testCreatesFilesWith0666AndDirectoriesWith0777(): void
    {
        $previousUmask = umask(0077);
        try {
            $existing = phore_file($this->root . '/existing/keep.txt');
            $existing->mkdir()->set_contents('Unverändert')->chmod(0640);
            $config = phore_file($this->root . '/.shiller.yml');
            $config->set_contents("doc_root: docs\n")->chmod(0600);

            self::assertTrue((new LegacyUpdater())->update($this->root));
            self::assertSame(0077, umask());
            self::assertSame(0666, $this->permissions('.shiller.yml'));
            self::assertSame(0666, $this->permissions('.shiller.d/user-rules.md'));
            self::assertSame(0666, $this->permissions('.shiller.d/static-context.md'));
            self::assertSame(0777, $this->permissions('.shiller.d'));
            self::assertSame(0700, $this->permissions('existing'));
            self::assertSame(0640, $this->permissions('existing/keep.txt'));
        } finally {
            umask($previousUmask);
        }
    }

    // fstat nutzt den Phore-Dateistream auch für Verzeichnisse und umgeht einen veralteten Stat-Cache.
    private function permissions(string $relative): int
    {
        $stream = phore_file($this->root . '/' . $relative)->fopen('r');
        try {
            return $stream->fstat()['mode'] & 0777;
        } finally {
            $stream->close();
        }
    }

    public function testKeepsExistingUserRulesWhenCreatingConfiguration(): void
    {
        $rules = phore_file($this->root . '/.shiller.d/user-rules.md');
        $rules->mkdir()->set_contents('Bestehende Regeln');

        self::assertTrue((new LegacyUpdater())->update($this->root));
        self::assertSame('Bestehende Regeln', $rules->get_contents());
    }
}
