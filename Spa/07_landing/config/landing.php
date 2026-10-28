<?php

declare(strict_types=1);

return [
    'brand_name' => 'Spa Cielo',
    'currency' => 'Bs',
    'branch_id' => getenv('LANDING_BRANCH_ID') ?: '',
    'cta' => [
        'label' => getenv('LANDING_CTA_LABEL') ?: 'Ver servicios',
        'url' => getenv('LANDING_CTA_URL') ?: '#servicios',
    ],
    'contact' => [
        'whatsapp' => getenv('LANDING_WHATSAPP') ?: null,
        'email' => getenv('LANDING_CONTACT_EMAIL') ?: null,
    ],
    'images' => [
        'hero' => getenv('LANDING_HERO_IMAGE') ?: 'assets/img/hero/spa-hero.jpg',
        'hero_srcset' => [
            'assets/img/hero/spa-hero-640.jpg' => 640,
            'assets/img/hero/spa-hero-960.jpg' => 960,
            'assets/img/hero/spa-hero-1200.jpg' => 1200,
            'assets/img/hero/spa-hero.jpg' => 1400,
        ],
        'hero_sizes' => '(max-width: 900px) calc(100vw - 2.5rem), 34rem',
        'sala_masajes' => getenv('LANDING_SALA_MASAJES_IMAGE') ?: 'assets/img/instalaciones/sala-masajes.jpg',
        'sauna' => getenv('LANDING_SAUNA_IMAGE') ?: 'assets/img/instalaciones/sauna.jpg',
        'fallback' => 'assets/img/fallback.svg',
    ],
    'image_alt' => [
        'hero' => 'Interior de spa con zonas de descanso y luz cálida',
        'sala_masajes' => 'Sala de masajes con camilla junto a un ventanal',
        'sauna' => 'Interior de sauna de madera con iluminación cálida',
    ],
    'meta' => [
        'title' => 'Spa Cielo - Bienestar y descanso',
        'description' => 'Servicios de spa en Bolivia. Consulta servicios y disponibilidad referencial de camas.',
    ],
];
