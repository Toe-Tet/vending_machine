<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Core\Route;

final class HomeController
{
    #[Route('GET', '/')]
    public function index(): Response
    {
        return Response::redirect('/products');
    }
}
