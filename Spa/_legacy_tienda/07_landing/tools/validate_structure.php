<?php
declare(strict_types=1);
$root=dirname(__DIR__); $errors=[];
$required=[
'app/Core','app/Repositories','app/Services','app/Views/layouts','app/Views/sections',
'config','database','public/assets/css','public/assets/js','docs','tests/fixtures','tests/results','tools',
'public/index.php','database/01_landing_schema.sql','database/02_landing_seed.sql'
];
foreach($required as $rel){
  $p=$root.DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$rel);
  if(!file_exists($p)) $errors[]="Falta: $rel";
}
$index=$root.'/public/index.php';
if(is_file($index)){
  $t=file_get_contents($index) ?: ''; $lines=substr_count($t,"\n")+1;
  if($lines>140) $errors[]="public/index.php supera 140 líneas ($lines).";
  if(preg_match('/\b(SELECT|INSERT|UPDATE|DELETE)\b/i',$t)) $errors[]='public/index.php contiene SQL.';
  if(preg_match('/new\s+PDO\s*\(|PDO::prepare/i',$t)) $errors[]='public/index.php contiene PDO.';
  if(stripos($t,'<style')!==false) $errors[]='public/index.php contiene <style>.';
}
$vd=$root.'/app/Views';
if(is_dir($vd)){
 $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($vd));
 foreach($it as $f){
  if(!$f->isFile()||strtolower($f->getExtension())!=='php') continue;
  $t=file_get_contents($f->getPathname()) ?: '';
  if(preg_match('/\b(SELECT|INSERT|UPDATE|DELETE)\b/i',$t)) $errors[]='View con SQL: '.$f->getPathname();
  if(preg_match('/new\s+PDO\s*\(|PDO::prepare/i',$t)) $errors[]='View con PDO: '.$f->getPathname();
  if(stripos($t,'<style')!==false) $errors[]='View con <style>: '.$f->getPathname();
 }
}
if($errors){fwrite(STDERR,"STRUCTURE: FAILED\n- ".implode("\n- ",$errors)."\n");exit(1);}
echo "STRUCTURE: PASSED\n"; exit(0);
