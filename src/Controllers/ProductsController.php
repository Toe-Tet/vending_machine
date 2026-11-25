<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Core\Route;
use App\Core\View;
use App\Repositories\ProductRepository;

final readonly class ProductsController
{
    public function __construct(private ProductRepository $products, private View $view)
    {
    }

    #[Route('GET', '/products')]
    public function index(): Response
    {
        return $this->view->response('products/index', [
            'title' => 'Products',
            'products' => $this->products->all(),
        ]);
    }
}
