<?php
declare(strict_types=1);
namespace Leuffen\Schiller\Storage;
use Leuffen\Schiller\{SiteStorage,StorageException,ValidationException};
final class NativeSiteStorage implements SiteStorage {
    private string $root;
    public function __construct(string $root){$real=realpath($root);if($real===false||!is_dir($real))throw new StorageException("Root directory not found: $root");$this->root=rtrim($real,DIRECTORY_SEPARATOR);}
    private function path(string $path):string{$path=str_replace('\\','/',$path);if(str_starts_with($path,'/')||preg_match('#(^|/)\.\.(/|$)#',$path))throw new ValidationException("Invalid path: $path");return $this->root.($path===''?'':DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$path));}
    public function exists(string $path):bool{return file_exists($this->path($path));}
    public function isDirectory(string $path):bool{return is_dir($this->path($path));}
    public function read(string $path):string{$p=$this->path($path);if(!is_file($p)||!is_readable($p))throw new StorageException("File is not readable: $path");$c=file_get_contents($p);if($c===false)throw new StorageException("Cannot read file: $path");return $c;}
    public function list(string $path=''):array{$p=$this->path($path);if(!is_dir($p))throw new StorageException("Directory not found: $path");$out=[];foreach(new \FilesystemIterator($p) as $e){$out[]=['name'=>$e->getFilename(),'type'=>$e->isDir()?'directory':'file'];}usort($out,fn($a,$b)=>strcmp($a['name'],$b['name']));return $out;}
    public function writeBatch(array $changes):void{$backups=[];try{foreach($changes as $path=>$content){$p=$this->path((string)$path);$backups[$path]=is_file($p)?file_get_contents($p):null;if($content===null){if(is_file($p)&&!unlink($p))throw new StorageException("Cannot delete file: $path");continue;}if(!is_dir(dirname($p))&&!mkdir(dirname($p),0770,true)&&!is_dir(dirname($p)))throw new StorageException("Cannot create directory for: $path");$tmp=$p.'.schiller-'.bin2hex(random_bytes(4));if(file_put_contents($tmp,(string)$content,LOCK_EX)===false||!rename($tmp,$p))throw new StorageException("Cannot write file: $path");}}catch(\Throwable $e){foreach($backups as $path=>$old){$p=$this->path((string)$path);if($old===null){if(is_file($p))@unlink($p);}else{@mkdir(dirname($p),0770,true);@file_put_contents($p,$old,LOCK_EX);}}throw $e;}}
}
