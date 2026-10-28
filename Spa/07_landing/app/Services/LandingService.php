<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\LandingRepository;
use Throwable;

final class LandingService
{
    private static ?\NumberFormatter $priceFormatter = null;

    public function __construct(
        private array $config,
        private ?LandingRepository $repository = null
    ) {
    }

    public function pageData(): array
    {
        $images = is_array($this->config['images'] ?? null) ? $this->config['images'] : [];
        $alts = is_array($this->config['image_alt'] ?? null) ? $this->config['image_alt'] : [];
        $cta = is_array($this->config['cta'] ?? null) ? $this->config['cta'] : [];
        $contact = is_array($this->config['contact'] ?? null) ? $this->config['contact'] : [];
        $currency = (string) ($this->config['currency'] ?? 'Bs');
        $heroImage = (string) ($images['hero'] ?? 'assets/img/hero/spa-hero.jpg');
        $servicesError = false;
        $servicios = [];
        $bedsError = false;
        $camas = [];

        if ($this->repository instanceof LandingRepository) {
            try {
                $servicios = $this->mapServicios($this->repository->serviciosActivos(), $currency);
            } catch (Throwable $exception) {
                $servicesError = true;
            }

            try {
                $camas = $this->mapCamas($this->repository->resumenCamas());
            } catch (Throwable $exception) {
                $bedsError = true;
            }
        } else {
            $servicesError = true;
            $bedsError = true;
        }

        $totalCamas = array_sum(array_column($camas, 'total'));
        $camasLibres = 0;
        foreach ($camas as $cama) {
            if ($cama['estado'] === 'libre') {
                $camasLibres = $cama['total'];
                break;
            }
        }

        return [
            'brand' => (string) ($this->config['brand_name'] ?? 'Spa Cielo'),
            'currency' => $currency,
            'cta' => [
                'label' => (string) ($cta['label'] ?? 'Ver servicios'),
                'url' => $this->safeCtaUrl((string) ($cta['url'] ?? '#servicios')),
            ],
            'contact' => [
                'whatsapp' => $this->formatWhatsapp((string) ($contact['whatsapp'] ?? '')),
                'email' => $this->formatEmail((string) ($contact['email'] ?? '')),
            ],
            'hero' => [
                'image' => $heroImage,
                'srcset' => $this->formatSrcset($heroImage, $images['hero_srcset'] ?? []),
                'sizes' => (string) ($images['hero_sizes'] ?? '(max-width: 900px) calc(100vw - 2.5rem), 34rem'),
                'alt' => (string) ($alts['hero'] ?? ''),
                'fallback' => (string) ($images['fallback'] ?? 'assets/img/fallback.svg'),
            ],
            'servicios' => $servicios,
            'services_error' => $servicesError,
            'camas' => $camas,
            'beds_error' => $bedsError,
            'total_camas' => $totalCamas,
            'camas_libres' => $camasLibres,
            'meta' => [
                'title' => (string) ($this->config['meta']['title'] ?? 'Spa Cielo'),
                'description' => (string) ($this->config['meta']['description'] ?? ''),
            ],
            'year' => date('Y'),
        ];
    }

    private function mapServicios(array $rows, string $currency): array
    {
        $servicios = [];

        foreach ($rows as $row) {
            $nombre = trim((string) ($row['nombre'] ?? ''));
            $duracion = (int) ($row['duracion_min'] ?? 0);
            $precio = (float) ($row['precio'] ?? 0);

            if ($nombre === '' || $duracion <= 0 || $precio < 0) {
                continue;
            }

            $servicios[] = [
                'id' => (string) ($row['id'] ?? ''),
                'nombre' => $nombre,
                'duracion' => $this->formatDuracion($duracion),
                'precio' => $this->formatPrecio($precio, $currency),
            ];
        }

        return $servicios;
    }

    private function mapCamas(array $rows): array
    {
        $labels = [
            'libre' => 'Libres',
            'ocupada' => 'Ocupadas',
            'no_disponible' => 'No disponibles',
        ];
        $order = array_flip(array_keys($labels));
        $camas = [];

        foreach ($rows as $row) {
            $estado = (string) ($row['estado'] ?? '');
            $total = (int) ($row['total'] ?? 0);

            if (!isset($labels[$estado]) || $total < 0) {
                continue;
            }

            $camas[] = [
                'estado' => $estado,
                'label' => $labels[$estado],
                'total' => $total,
            ];
        }

        usort(
            $camas,
            static fn (array $a, array $b): int => $order[$a['estado']] <=> $order[$b['estado']]
        );

        return $camas;
    }

    private function safeCtaUrl(string $value): string
    {
        $url = trim($value);

        if ($url === '') {
            return '#servicios';
        }

        if ($url[0] === '#') {
            return $url;
        }

        if (preg_match('#^/(?!/)#', $url) === 1) {
            return $url;
        }

        if (preg_match('#^https?://#i', $url) === 1) {
            return $url;
        }

        return '#servicios';
    }

    private function formatSrcset(string $image, mixed $candidates): string
    {
        if ($image !== 'assets/img/hero/spa-hero.jpg' || !is_array($candidates)) {
            return '';
        }

        $parts = [];
        $root = dirname(__DIR__, 2);

        foreach ($candidates as $path => $width) {
            if (!is_string($path) || !is_int($width) || $width <= 0) {
                continue;
            }

            if (!is_file($root . '/public/' . $path)) {
                continue;
            }

            $parts[] = $path . ' ' . $width . 'w';
        }

        return implode(', ', $parts);
    }

    private function formatWhatsapp(string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if (strlen($digits) < 8 || strlen($digits) > 15) {
            return null;
        }

        return 'https://wa.me/' . $digits;
    }

    private function formatEmail(string $value): ?string
    {
        $email = trim($value);

        if ($email === '' || strlen($email) > 254) {
            return null;
        }

        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : null;
    }

    private function formatDuracion(int $minutos): string
    {
        if ($minutos < 60) {
            return $minutos . ' min';
        }

        $horas = intdiv($minutos, 60);
        $resto = $minutos % 60;

        if ($resto === 0) {
            return $horas . ' h';
        }

        return $horas . ' h ' . $resto . ' min';
    }

    private function formatPrecio(float $precio, string $currency): string
    {
        if (class_exists(\NumberFormatter::class)) {
            if (self::$priceFormatter === null) {
                $formatter = new \NumberFormatter('es_BO', \NumberFormatter::DECIMAL);
                $formatter->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, 2);
                $formatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, 2);
                self::$priceFormatter = $formatter;
            }

            $formatted = self::$priceFormatter->format($precio);

            if ($formatted !== false) {
                return $currency . ' ' . $formatted;
            }
        }

        return $currency . ' ' . number_format($precio, 2, ',', '.');
    }
}
