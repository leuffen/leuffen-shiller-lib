<?php
declare(strict_types=1);
use Leuffen\Schiller\{AccessContext,SchillerDir,UnsupportedOperationException};
use Leuffen\Schiller\Adapter\JekyllLegacyAdapter;
use PHPUnit\Framework\TestCase;

final class SchillerRuntimeTest extends TestCase {
    private string $dir;
    protected function setUp():void{$this->dir=sys_get_temp_dir().'/schiller-'.bin2hex(random_bytes(4));mkdir($this->dir.'/home',0777,true);mkdir($this->dir.'/_data',0777,true);}
    protected function tearDown():void{$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->dir,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);foreach($it as $f){$f->isDir()?rmdir($f->getPathname()):unlink($f->getPathname());}rmdir($this->dir);}
    public function testLegacyReadsAndWritesExistingPage():void{
        file_put_contents($this->dir.'/_data/languages.yml',"- lang: de\n  name: Deutsch\n- lang: en\n  name: English\n");
        file_put_contents($this->dir.'/home/home.de.md',"---\npid: home/home\nlang: de\ntitle: Start\npublished: true\n---\nHallo\n");
        $site=new SchillerDir($this->dir,new JekyllLegacyAdapter(),new AccessContext('user'));
        $page=$site->getPage('/home/home','de');$this->assertSame('Start',$page->header['title']);$page->header['title']='Neu';$page->save();
        $this->assertStringContainsString('title: Neu',file_get_contents($this->dir.'/home/home.de.md'));
        $this->assertTrue($site->pages()->root->data->hasChildren);
        $this->expectException(UnsupportedOperationException::class);$page->getTranslation('en',true);
    }
    public function testPolyglotCreatesTranslation():void{
        file_put_contents($this->dir.'/schiller.yaml',"schema_version: 1\nlanguages: [de, en]\ndefault_lang: de\nadapter: {id: jekyll-polyglot, version: 1}\n");
        file_put_contents($this->dir.'/index.md',"---\ntitle: Start\n---\nHallo\n");
        $site=new SchillerDir($this->dir,null,new AccessContext('user'));$root=$site->getPage('/');$en=$root->getTranslation('en',true);$this->assertFalse($en->isPersisted());$en->header['title']='Home';$en->save();$this->assertFileExists($this->dir.'/en/index.md');
    }
}
