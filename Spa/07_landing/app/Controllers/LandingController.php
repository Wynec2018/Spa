<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Repositories\LandingRepository;
use App\Services\LandingService;
use Throwable;

final class LandingController
{
    public function index(Request $request): Response
    {
        $config = require dirname(__DIR__, 2) . '/config/landing.php';
        $view = new View(dirname(__DIR__) . '/Views');
        $repository = null;

        try {
            $repository = new LandingRepository(
                Database::pdo(),
                (string) ($config['branch_id'] ?? '')
            );
        } catch (Throwable $exception) {
            $repository = null;
        }

        $data = (new LandingService($config, $repository))->pageData();
        $data['view'] = $view;

        $content = $view->render('pages.landing', $data);
        $html = $view->render('layouts.app', $data + ['content' => $content]);

        return Response::html($html);
    }
}
