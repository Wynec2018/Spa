<?php
declare(strict_types=1);
// CP-LAND-04: rellena imagen_* del fixture con imágenes libres de Wikimedia Commons
// (URL directa, página de origen, autor, licencia) y verifica HTTP 200.
// Solo consulta HTTP; no toca la BD. Reproducible: php tools/fetch_images_commons.php
$root = dirname(__DIR__);
$fixture = $root . '/tests/fixtures/catalogo_bolivia.json';
$fecha = date('Y-m-d');
$ua = 'sistema-landing-image-check/1.0 (validacion CP-LAND-04)';

$map = [
  1 => ['quinoa grains bowl', 'quinoa'], 2 => ['quinoa grain', 'quinoa seeds'],
  3 => ['amaranth grain', 'amaranth seeds'], 4 => ['flour scoop', 'flour and rolling pin'],
  5 => ['roasted coffee beans', 'coffee beans'], 6 => ['honey jar', 'honey'],
  7 => ['purple corn', 'corn kernels'], 8 => ['dried peaches', 'dried fruit'],
  9 => ['roasted peanuts', 'peanuts'], 10 => ['granola bowl', 'granola'],
  11 => ['glass of water', 'pouring water into glass'], 12 => ['peach juice', 'peach fruit'],
  13 => ['orange juice', 'orange juice glass'], 14 => ['passion fruit juice', 'passion fruit'],
  15 => ['iced tea glass', 'iced tea'], 16 => ['peach tea', 'iced tea bottle'],
  17 => ['iced lemonade', 'lemon slices in glass'], 18 => ['barley water', 'barley grains'],
  19 => ['horchata glass', 'quinoa milk drink'], 20 => ['chocolate milk', 'milkshake'],
  21 => ['bread rolls', 'marraqueta bread'], 22 => ['whole wheat bread', 'bread loaf'],
  23 => ['cheese bread', 'bread'], 24 => ['empanadas', 'empanada'],
  25 => ['buñuelos', 'fritters'], 26 => ['oatmeal breakfast bowl', 'porridge with berries'],
  27 => ['corn flakes', 'cereal bowl'], 28 => ['strawberry jam', 'jam jar'],
  29 => ['butter slices on bread', 'butter pat'], 30 => ['cocoa powder bowl', 'hot chocolate cup'],
  31 => ['potato chips', 'chips snack'], 32 => ['plantain chips', 'banana chips'],
  33 => ['mixed nuts', 'nuts'], 34 => ['cereal bar', 'granola bar'],
  35 => ['oatmeal cookies', 'cookies'], 36 => ['corn nuts snack', 'roasted corn kernels'],
  37 => ['bowl of popcorn', 'popcorn snack'], 38 => ['peanuts and raisins', 'trail mix'],
  39 => ['crackers', 'biscuits'], 40 => ['snack mix bowl', 'pretzels and nuts'],
  41 => ['milk bottle', 'milk'], 42 => ['pouring milk into glass', 'milk carton'],
  43 => ['plain yogurt', 'yogurt bowl'], 44 => ['strawberry yogurt bowl', 'yogurt with strawberries'],
  45 => ['fresh cheese', 'cheese'], 46 => ['sliced cheese', 'cheese slices'],
  47 => ['butter block', 'butter'], 48 => ['sour cream', 'cream'],
  49 => ['flavored milk', 'milk drink'], 50 => ['greek yogurt', 'yogurt cup'],
  51 => ['clean dishes in sink', 'soap bubbles kitchen'], 52 => ['laundry soap', 'washing powder scoop'],
  53 => ['bar soap', 'soap bars'], 54 => ['cleaning spray bottle', 'all purpose cleaner'],
  55 => ['kitchen sponge', 'sponges'], 56 => ['toilet paper rolls', 'roll of toilet paper'],
  57 => ['garbage bags', 'black trash bag'], 58 => ['disinfectant spray', 'spray bottle'],
  59 => ['folded towels', 'stack of towels'], 60 => ['microfiber cloth', 'cleaning cloth'],
  61 => ['breakfast table', 'breakfast spread'], 62 => ['quinoa bowl', 'granola breakfast'],
  63 => ['cleaning supplies', 'household products'], 64 => ['soft drinks', 'assorted bottles'],
  65 => ['assorted bread', 'bread basket'], 66 => ['party snacks', 'snack platter'],
  67 => ['milk bottles table', 'cheese platter milk'], 68 => ['basket of groceries', 'fresh vegetables basket'],
  69 => ['cookies and tea', 'tea with cookies'], 70 => ['healthy food', 'fresh food basket'],
];

$ca = getenv('LANDING_CACERT') ?: 'C:\Portable\laragon\etc\ssl\cacert.pem';
if (!is_file($ca)) { $ca = getenv('TEMP') . '\cacert.pem'; }

function http(string $url, string $ua, bool $head = false, string $ca = ''): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 30, CURLOPT_USERAGENT => $ua,
        CURLOPT_NOBODY => $head, CURLOPT_HTTPHEADER => ['Accept: application/json, image/*'],
    ]);
    if ($ca !== '') { curl_setopt($ch, CURLOPT_CAINFO, $ca); }
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return [$code, is_string($body) ? $body : ''];
}

function clean(string $s): string
{
    $s = trim(strip_tags($s));
    $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
    return mb_substr($s, 0, 160);
}

$bad = '/logo|diagram|map|icon|chart|cover|screenshot|poster|stamp|coin|flag|silhouette|illustration|drawing|church|reservoir|museum|painting|advertis|poster|still life of the foods|banner|Rockharz|portrait|engraving|etching|WGA|ABDAG|botanical|herbarium/i';
$data = json_decode(file_get_contents($fixture), true);
if (!is_array($data)) { fwrite(STDERR, "fixture ilegible\n"); exit(1); }

$used = []; $errors = []; $ok = 0;
foreach ($data['categories'] as $ci => $cat) {
    foreach ($cat['products'] as $pi => $p) {
        if (!empty($p['imagen_url']) && !empty($p['imagen_verificada'])) { $ok++; continue; }
        $terms = $map[$p['id']] ?? null;
        if (!$terms) { $errors[] = "sin términos: id {$p['id']}"; continue; }
        $picked = null;
        foreach ($terms as $term) {
            $q = 'https://commons.wikimedia.org/w/api.php?action=query&generator=search'
               . '&gsrsearch=' . rawurlencode('filetype:bitmap ' . $term)
               . '&gsrnamespace=6&gsrlimit=10&prop=imageinfo'
               . '&iiprop=url|extmetadata|mime&iiurlwidth=900&format=json';
            [$code, $body] = http($q, $ua, false, $ca);
            for ($r = 1; $r < 3 && $code !== 200; $r++) { sleep(3); [$code, $body] = http($q, $ua, false, $ca); }
            if ($code !== 200) { continue; }
            $j = json_decode($body, true);
            $pages = $j['query']['pages'] ?? [];
            uasort($pages, fn($a, $b) => ($a['index'] ?? 0) <=> ($b['index'] ?? 0));
            foreach ($pages as $pg) {
                $ii = $pg['imageinfo'][0] ?? null;
                if (!$ii) continue;
                $mime = $ii['mime'] ?? '';
                if (!in_array($mime, ['image/jpeg', 'image/png'], true)) continue;
                if (preg_match($bad, $pg['title'] ?? '')) continue;
                $url = preg_replace('/\?.*$/', '', $ii['thumburl'] ?? '');
                if ($url === '' || isset($used[$url])) continue;
                $em = $ii['extmetadata'] ?? [];
                $lic = clean($em['LicenseShortName']['value'] ?? '');
                $aut = clean($em['Artist']['value'] ?? '');
                if ($lic === '' || $aut === '') continue;
                $picked = [
                    'url' => $url,
                    'page' => $ii['descriptionurl'] ?? '',
                    'title' => $pg['title'],
                    'lic' => $lic, 'aut' => $aut,
                ];
                break;
            }
            if ($picked) break;
        }
        if (!$picked) { $errors[] = "sin imagen: {$p['nombre']}"; continue; }
        $data['categories'][$ci]['products'][$pi]['imagen_url'] = $picked['url'];
        $data['categories'][$ci]['products'][$pi]['imagen_fuente'] = 'Wikimedia Commons';
        $data['categories'][$ci]['products'][$pi]['imagen_fuente_url'] = $picked['page'];
        $data['categories'][$ci]['products'][$pi]['imagen_autor'] = $picked['aut'];
        $data['categories'][$ci]['products'][$pi]['imagen_licencia'] = $picked['lic'];
        $data['categories'][$ci]['products'][$pi]['imagen_fecha_verificacion'] = $fecha;
        $data['categories'][$ci]['products'][$pi]['imagen_verificada'] = true;
        $used[$picked['url']] = $p['id'];
        $ok++;
        // guardado incremental para poder reanudar si se interrumpe
        file_put_contents($fixture, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
        echo "{$p['id']}\t{$picked['title']}\t{$picked['lic']}\n";
    }
}

// verificación final HTTP 200 de cada imagen_url
$badHttp = [];
foreach ($data['categories'] as $cat) {
    foreach ($cat['products'] as $p) {
        if (empty($p['imagen_url'])) continue;
                [$code] = http($p['imagen_url'], $ua, true, $ca);
                if ($code !== 200) { for ($r = 1; $r < 3 && $code !== 200; $r++) { sleep(2); [$code] = http($p['imagen_url'], $ua, true, $ca); } }
                if ($code !== 200) { $badHttp[] = "{$p['id']} => HTTP $code"; }
    }
}

if ($errors || $badHttp) {
    fwrite(STDERR, "PROBLEMAS:\n- " . implode("\n- ", $errors + $badHttp) . "\n");
    exit(1);
}
file_put_contents($fixture, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
echo "OK: $ok imágenes (70 esperadas), URLs únicas=" . count($used) . ", HTTP 200 en todas\n";
exit($ok === 70 ? 0 : 1);
