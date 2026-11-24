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
        self::assertSame('legacy:1.0', $config['adapter']);
        self::assertSame('docs', $config['doc_root']);
        self::assertSame([], $config['context_files']);
        self::assertTrue($config['ai']['web_access']);
        self::assertSame('.shiller-user-rules.md', $config['user_rules_file']);
        self::assertSame('Alter Kontext', $oldContext->get_contents());

        $rules = phore_file($this->root . '/.shiller-user-rules.md');
        self::assertSame("## Global\n", $rules->get_contents());
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
        $config->set_contents("version: 1.0\nadapter: polyglot:1.0\n");

        self::assertFalse((new LegacyUpdater())->update($this->root));
        self::assertSame("version: 1.0\nadapter: polyglot:1.0\n", $config->get_contents());
        self::assertFalse(phore_file($this->root . '/.shiller-user-rules.md')->exists());
    }

    public function testKeepsExistingUserRulesWhenCreatingConfiguration(): void
    {
        $rules = phore_file($this->root . '/.shiller-user-rules.md');
        $rules->set_contents('Bestehende Regeln');

        self::assertTrue((new LegacyUpdater())->update($this->root));
        self::assertSame('Bestehende Regeln', $rules->get_contents());
    }
}
