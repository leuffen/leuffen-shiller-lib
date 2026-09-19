<?php
declare(strict_types=1);
namespace Leuffen\Schiller\Adapter;

use Leuffen\Schiller\{
 AccessDeniedException,Capabilities,Codec,ConflictException,Document,FieldDefinition,FieldSet,FileEntry,FileKind,
 NotFoundException,PageTree,SchillerTreeData,SiteConfig,SiteStorage,TranslationInfo,TreeNode,UnsupportedOperationException,UrlNotResolvableException
};

abstract class AbstractJekyllAdapter implements Adapter {
    protected ?SiteStorage $storage=null;
    private bool $bound=false;
    public function bind(SiteStorage $storage):void { if($this->bound)throw new \LogicException('Adapter already bound');$this->storage=$storage;$this->bound=true; }
    protected function s():SiteStorage{return $this->storage??throw new \LogicException('Adapter not bound');}
    protected function all(string $path=''):array{
        $out=[];foreach($this->s()->list($path) as $e){$p=($path===''?'':$path.'/').$e['name']; if(str_starts_with($e['name'],'.'))continue;
            if($e['type']==='directory')$out=array_merge($out,$this->all($p));else $out[]=$p;}return $out;
    }
    protected function readDoc(string $id,string $lang,string $path,bool $root):Document{
        [$h,$c]=Codec::frontMatter($this->s()->read($path),$path);
        return new Document($id,$lang,$root,new FileEntry($path,FileKind::page),$h,$c,[],true);
    }
    protected function commonFields(array $extra=[]):FieldSet{
        $fields=[
          new FieldDefinition('layout','Layout','select'),
          new FieldDefinition('published','Diese Seite veröffentlichen','checkbox'),
          new FieldDefinition('permalink','Permanent-Link','text'),
          new FieldDefinition('title','Seiten-Titel','text'),
          new FieldDefinition('description','Meta-Description','text',[],['maxlength'=>160]),
          new FieldDefinition('order','Sortierung','integer'),
          new FieldDefinition('ptags','Page-Tags','multiselect'),
        ];
        return new FieldSet(array_merge($fields,$extra));
    }
    protected function nodeTree(array $groups,string $rootId,SiteConfig $cfg,array $metadata=[]):PageTree{
        $nodes=['/'=>['children'=>[],'translations'=>[],'labels'=>[],'metadata'=>$metadata['/']??[]]];
        foreach($groups as $id=>$langs){
            $parts=$id==='/'?[]:explode('/',trim($id,'/'));$cur='';
            foreach($parts as $part){$parent=$cur===''?'/':$cur;$cur=$cur.'/'.$part;$nodes[$cur]??=['children'=>[],'translations'=>[],'labels'=>[],'metadata'=>$metadata[$cur]??[]];$nodes[$parent]??=['children'=>[],'translations'=>[],'labels'=>[],'metadata'=>$metadata[$parent]??[]];if(!in_array($cur,$nodes[$parent]['children'],true))$nodes[$parent]['children'][]=$cur;}
            $nodes[$id]['translations']=$langs;
            foreach($langs as $lang=>$d){$nodes[$id]['labels'][]=$d['title']??basename($id);}
        }
        $build=function(string $id) use (&$build,&$nodes,$cfg){
            $n=$nodes[$id]??['children'=>[],'translations'=>[],'labels'=>[],'metadata'=>[]];sort($n['children']);
            $trans=[];$file=null;$doc=null;
            foreach($cfg->languages as $lang){$x=$n['translations'][$lang]??null;$path=$x['path']??$this->getSourcePath($id,$lang);$exists=$x!==null;$trans[$lang]=new TranslationInfo($lang,$path,$exists,$lang===$cfg->defaultLanguage,$exists?(bool)($x['published']??true):null);if($file===null&&$exists){$file=new FileEntry($path,FileKind::page);try{$doc=$this->load($id,$lang);}catch(\Throwable){}}}
            $children=array_map($build,$n['children']);$label=$n['labels'][0]??($id==='/'?'/':basename($id));$kind=$file?FileKind::page:FileKind::directory;
            return new TreeNode($id,$label,$children,new SchillerTreeData(null,$kind,$file,$n['metadata'],$trans,!empty($children),true),$doc);
        };
        return new PageTree($build($rootId));
    }
    public function getEffectiveHeader(Document $document):array{return $document->header;}
    public function getUrl(Document $document,bool $absolute=false):string{
        $url=$document->header['permalink']??null;
        if(!is_string($url)||$url===''){$url=$document->id==='/'?'/':$document->id.'.html';}
        $cfg=$this->loadConfig();if($document->language!==$cfg->defaultLanguage)$url='/'.$document->language.($url==='/'?'':$url);
        $url='/'.ltrim($url,'/');return $absolute&&$cfg->url!==''?rtrim($cfg->url,'/').$url:$url;
    }
    public function getDocumentByUrl(string $url):Document{
        $path=parse_url($url,PHP_URL_PATH)?:'/';$cfg=$this->loadConfig();$lang=$cfg->defaultLanguage;
        foreach($cfg->languages as $l)if($l!==$cfg->defaultLanguage&&str_starts_with($path,'/'.$l.'/')){$lang=$l;$path=substr($path,strlen($l)+1);}
        $id=Codec::id(preg_replace('/\.html$/','',$path)??$path);
        try{return $this->load($id,$lang);}catch(NotFoundException){throw new UrlNotResolvableException("URL not resolvable: $url");}
    }
    public function delete(Document $document):void{throw new UnsupportedOperationException('Delete is not supported by this adapter');}
    public function rename(string $id,string $newId):void{throw new UnsupportedOperationException('Rename is not supported by this adapter');}
}

final class JekyllLegacyAdapter extends AbstractJekyllAdapter {
    public function loadConfig():SiteConfig{
        $langs=[];$labels=[];
        if($this->s()->exists('_data/languages.yml')){foreach(Codec::yaml($this->s()->read('_data/languages.yml'),'_data/languages.yml') as $x){if(is_array($x)&&isset($x['lang'])){$langs[]=(string)$x['lang'];$labels[(string)$x['lang']]=(string)($x['name']??$x['lang']);}}}
        if(!$langs){$langs=['de'];$labels=['de'=>'Deutsch'];}
        $jek=$this->s()->exists('_config.yml')?Codec::yaml($this->s()->read('_config.yml'),'_config.yml'):[];
        return new SiteConfig(['id'=>'micx-legacy','version'=>1],$langs,$langs[0],$labels,(string)($jek['url']??''),(string)($jek['baseurl']??''));
    }
    public function getSourcePath(string $id,string $language):string{
        $base=trim($id,'/');$candidates=[];
        if($id==='/')$candidates=["index.$language.md","index.$language.html"];
        else{$candidates=["$base.$language.md","$base.$language.html","$base/index.$language.md","$base/index.$language.html"];}
        $found=array_values(array_filter($candidates,fn($p)=>$this->s()->exists($p)));
        if(count($found)>1)throw new ConflictException("Ambiguous legacy page: $id [$language]");
        return $found[0]??$candidates[0];
    }
    public function load(string $id,string $language):Document{
        $p=$this->getSourcePath($id,$language);if(!$this->s()->exists($p))throw new NotFoundException("Page not found: $id [$language] ($p)");
        $d=$this->readDoc($id,$language,$p,$language===$this->loadConfig()->defaultLanguage);
        if(isset($d->header['pid'])&&Codec::id((string)$d->header['pid'])!==$id)throw new ConflictException("pid mismatch: $p");
        if(isset($d->header['lang'])&&(string)$d->header['lang']!==$language)throw new ConflictException("lang mismatch: $p");
        return $d;
    }
    public function create(string $id,string $language,array $header=[],string $content=''):Document{throw new UnsupportedOperationException('Legacy format does not create pages or translations');}
    public function write(array $documents):void{
        $changes=[];foreach($documents as $d){$p=$this->getSourcePath($d->id,$d->language);if(!$this->s()->exists($p))throw new UnsupportedOperationException("Legacy file does not exist: $p");$h=$d->header;$h['pid']=ltrim($d->id,'/');$h['lang']=$d->language;$changes[$p]=Codec::emitFrontMatter($h,$d->content);} $this->s()->writeBatch($changes);
    }
    public function buildTree(string $id='/'):PageTree{
        $cfg=$this->loadConfig();$groups=[];$meta=[];
        foreach($this->all() as $p){
            if(basename($p)==='_section.yml'){ $dir=dirname($p);$sid=$dir==='.'?'/':Codec::id($dir);try{$meta[$sid]=Codec::yaml($this->s()->read($p),$p);}catch(\Throwable){} continue; }
            if(!preg_match('/\.(md|html)$/',$p))continue;
            $name=preg_replace('/\.(md|html)$/','',$p);if(!$name)continue;$matched=null;
            foreach($cfg->languages as $lang)if(str_ends_with($name,'.'.$lang)){$matched=$lang;$name=substr($name,0,-strlen('.'.$lang));break;}
            if(!$matched)continue;$pageId=Codec::id($name);if(str_ends_with($pageId,'/index'))$pageId=Codec::id(substr($pageId,0,-6));
            try{[$h]=Codec::frontMatter($this->s()->read($p),$p);}catch(\Throwable){$h=[];}
            $groups[$pageId][$matched]=['path'=>$p,'title'=>$h['title']??basename($pageId),'published'=>$h['published']??true];
        }
        return $this->nodeTree($groups,$id,$cfg,$meta);
    }
    public function getHeaderDefinitions(string $id,?string $language=null):FieldSet{
        $extra=[];$section=explode('/',trim($id,'/'))[0]??'';$path=$section===''?'_section.yml':$section.'/_section.yml';
        if($this->s()->exists($path)){ $s=Codec::yaml($this->s()->read($path),$path);foreach(($s['forms']??[]) as $f)if(is_array($f)&&isset($f['key']))$extra[]=new FieldDefinition((string)$f['key'],(string)($f['name']??$f['key']),(string)($f['type']??'text'),(array)($f['options']??[]),(array)($f['attrs']??[]));}
        return $this->commonFields($extra);
    }
    public function capabilities(string $id,?string $language=null):Capabilities{$p=$this->getSourcePath($id,$language??$this->loadConfig()->defaultLanguage);return new Capabilities(true,$this->s()->exists($p),false,false,false);}
}

final class JekyllPolyglotAdapter extends AbstractJekyllAdapter {
    public function loadConfig():SiteConfig{
        $s=$this->s()->exists('schiller.yaml')?Codec::yaml($this->s()->read('schiller.yaml'),'schiller.yaml'):[];
        $j=$this->s()->exists('_config.yml')?Codec::yaml($this->s()->read('_config.yml'),'_config.yml'):[];
        $langs=array_values(array_map('strval',(array)($s['languages']??$j['languages']??[$s['default_lang']??'de','en'])));
        $default=(string)($s['default_lang']??$s['default_language']??$langs[0]??'de');if(!in_array($default,$langs,true))array_unshift($langs,$default);
        $labels=[];foreach($langs as $l)$labels[$l]=(string)(($s['language_labels'][$l]??$l));
        return new SiteConfig(['id'=>'jekyll-polyglot','version'=>1],array_values(array_unique($langs)),$default,$labels,(string)($j['url']??''),(string)($j['baseurl']??''));
    }
    private function prefix(string $language):string{return $language===$this->loadConfig()->defaultLanguage?'':$language.'/';}
    public function getSourcePath(string $id,string $language):string{
        $prefix=$this->prefix($language);$base=trim($id,'/');$candidates=$id==='/'?[$prefix.'index.md',$prefix.'index.html']:[$prefix.$base.'.md',$prefix.$base.'.html',$prefix.$base.'/index.md',$prefix.$base.'/index.html'];
        $found=array_values(array_filter($candidates,fn($p)=>$this->s()->exists($p)));if(count($found)>1)throw new ConflictException("Ambiguous polyglot page: $id [$language]");return $found[0]??$candidates[0];
    }
    public function load(string $id,string $language):Document{$p=$this->getSourcePath($id,$language);if(!$this->s()->exists($p))throw new NotFoundException("Page not found: $id [$language] ($p)");$d=$this->readDoc($id,$language,$p,$language===$this->loadConfig()->defaultLanguage);if(isset($d->header['pid'])||isset($d->header['lang']))throw new ConflictException("Polyglot pages must not contain pid/lang identity headers: $p");return $d;}
    public function create(string $id,string $language,array $header=[],string $content=''):Document{if($this->s()->exists($this->getSourcePath($id,$language)))throw new ConflictException("Page exists: $id [$language]");$header['published']??=false;return new Document($id,$language,$language===$this->loadConfig()->defaultLanguage,null,$header,$content,[],false);}
    public function write(array $documents):void{$changes=[];foreach($documents as $d){$p=$this->getSourcePath($d->id,$d->language);$h=$d->header;unset($h['pid'],$h['lang']);$changes[$p]=Codec::emitFrontMatter($h,$d->content);} $this->s()->writeBatch($changes);}
    public function buildTree(string $id='/'):PageTree{
        $cfg=$this->loadConfig();$groups=[];
        foreach($this->all() as $p){if(!preg_match('/\.(md|html)$/',$p)||str_starts_with(basename($p),'_'))continue;$lang=$cfg->defaultLanguage;$rel=$p;
            foreach($cfg->languages as $l)if($l!==$cfg->defaultLanguage&&str_starts_with($rel,$l.'/')){$lang=$l;$rel=substr($rel,strlen($l)+1);break;}
            $name=preg_replace('/\.(md|html)$/','',$rel);if(!$name)continue;$pid=Codec::id($name);if(str_ends_with($pid,'/index'))$pid=Codec::id(substr($pid,0,-6));
            try{[$h]=Codec::frontMatter($this->s()->read($p),$p);}catch(\Throwable){$h=[];}
            $groups[$pid][$lang]=['path'=>$p,'title'=>$h['title']??($pid==='/'?'/':basename($pid)),'published'=>$h['published']??true];
        }
        return $this->nodeTree($groups,$id,$cfg);
    }
    public function getHeaderDefinitions(string $id,?string $language=null):FieldSet{return $this->commonFields();}
    public function capabilities(string $id,?string $language=null):Capabilities{return new Capabilities(true,true,true,true,true);}
    public function rename(string $id,string $newId):void{
        $cfg=$this->loadConfig();$changes=[];foreach($cfg->languages as $lang){$src=$this->getSourcePath($id,$lang);if(!$this->s()->exists($src))continue;$content=$this->s()->read($src);$dst=$this->getSourcePath($newId,$lang);if($this->s()->exists($dst))throw new ConflictException("Rename target exists: $dst");$changes[$dst]=$content;$changes[$src]=null;}if(!$changes)throw new NotFoundException("Page not found: $id");$this->s()->writeBatch($changes);
    }
    public function delete(Document $document):void{$p=$this->getSourcePath($document->id,$document->language);if($document->id==='/')throw new UnsupportedOperationException('Root deletion is not allowed');$this->s()->writeBatch([$p=>null]);}
}
