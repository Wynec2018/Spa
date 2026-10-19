<?php
declare(strict_types=1);
$cp=$argv[1]??''; $root=dirname(__DIR__); $errors=[];
function addIf($c,$m,&$e){if($c)$e[]=$m;}
$fp=$root.'/tests/fixtures/catalogo_bolivia.json';
$d=is_file($fp)?json_decode(file_get_contents($fp),true):null;
if(in_array($cp,['CP-LAND-01','CP-LAND-03','CP-LAND-04','CP-LAND-16'],true))
 addIf(!is_array($d),'Fixture inválido o inexistente.',$errors);

if(is_array($d)){
 $cats=$d['categories']??[]; addIf(count($cats)!==7,'Debe haber 7 categorías.',$errors); $total=0;
 foreach($cats as $cat){
  $items=$cat['products']??[]; $total+=count($items);
  addIf(count($items)!==10,'Cada categoría debe tener 10 productos: '.($cat['nombre']??'?'),$errors);
 }
 addIf($total!==70,"Debe haber 70 productos; encontrados: $total.",$errors);
 if(in_array($cp,['CP-LAND-04','CP-LAND-16'],true)){
  foreach($cats as $cat) foreach(($cat['products']??[]) as $p){
   addIf(empty($p['imagen_url']),'Sin imagen_url: '.($p['nombre']??'?'),$errors);
   addIf(empty($p['imagen_fuente']),'Sin imagen_fuente: '.($p['nombre']??'?'),$errors);
   addIf(empty($p['imagen_fuente_url']),'Sin página origen: '.($p['nombre']??'?'),$errors);
   addIf(empty($p['imagen_licencia']),'Sin licencia/condición: '.($p['nombre']??'?'),$errors);
   addIf(($p['imagen_verificada']??false)!==true,'Imagen no verificada: '.($p['nombre']??'?'),$errors);
  }
  //$src=$root.'/docs/fuentes_imagenes.md';
  //addIf(!is_file($src)||filesize($src)<500,'Fuentes de imágenes sin evidencia suficiente.',$errors);
 }
}
if($errors){fwrite(STDERR,"$cp: FAILED\n- ".implode("\n- ",$errors)."\n");exit(1);}
echo "$cp: PASSED\n"; exit(0);
