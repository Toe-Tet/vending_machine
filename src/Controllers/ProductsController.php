<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\Guard;
use App\Core\Request;
use App\Core\Response;
use App\Core\Route;
use App\Core\View;
use App\Models\Product;
use App\Models\ProductListQuery;
use App\Repositories\ProductRepository;
use App\Validation\ProductValidator;

final readonly class ProductsController
{
    public function __construct(
        private ProductRepository $products,
        private ProductValidator $validator,
        private Guard $guard,
        private View $view,
    ) {
    }

    #[Route('GET', '/products')]
    public function index(Request $request): Response
    {
        $query = ProductListQuery::fromQuery($request->query);
        $lastPage = ProductListQuery::lastPage($this->products->count());
        $query = $query->onPage(min($query->page, $lastPage));

        return $this->view->response('products/index', [
            'title' => 'Products',
            'products' => $this->products->page($query->sort, $query->dir, ProductListQuery::PER_PAGE, $query->offset()),
            'query' => $query,
            'lastPage' => $lastPage,
        ]);
    }

    #[Route('GET', '/products/{id}')]
    public function show(int $id): Response
    {
        $product = $this->products->find($id);
        if ($product === null) {
            return $this->notFound();
        }

        return $this->view->response('products/show', ['title' => $product->name, 'product' => $product]);
    }

    #[Route('GET', '/products/create')]
    public function create(): Response
    {
        return $this->guard->admin() ?? $this->form(null);
    }

    #[Route('POST', '/products')]
    public function store(Request $request): Response
    {
        if ($denied = $this->guard->admin()) {
            return $denied;
        }

        $result = $this->validator->validate($request->body);
        if ($result->input === null) {
            return $this->form(null, $result->old, $result->errors, 422);
        }

        $product = $this->products->create($result->input->name, $result->input->price, $result->input->quantityAvailable);

        return Response::redirect("/products/{$product->id}");
    }

    #[Route('GET', '/products/{id}/edit')]
    public function edit(int $id): Response
    {
        if ($denied = $this->guard->admin()) {
            return $denied;
        }

        $product = $this->products->find($id);
        if ($product === null) {
            return $this->notFound();
        }

        return $this->form($product, [
            'name' => $product->name,
            'price' => $product->displayPrice(),
            'quantity_available' => (string) $product->quantityAvailable,
        ]);
    }

    #[Route('PUT', '/products/{id}')]
    public function update(Request $request, int $id): Response
    {
        if ($denied = $this->guard->admin()) {
            return $denied;
        }

        $product = $this->products->find($id);
        if ($product === null) {
            return $this->notFound();
        }

        $result = $this->validator->validate($request->body);
        if ($result->input === null) {
            return $this->form($product, $result->old, $result->errors, 422);
        }

        // false: archived by another Admin since the find() above.
        if (!$this->products->update($id, $result->input->name, $result->input->price, $result->input->quantityAvailable)) {
            return $this->notFound();
        }

        return Response::redirect("/products/{$id}");
    }

    /** "Delete" archives the Product: it leaves sale for good but its Purchases remain (ADR-0003). */
    #[Route('DELETE', '/products/{id}')]
    public function destroy(int $id): Response
    {
        if ($denied = $this->guard->admin()) {
            return $denied;
        }

        return $this->products->archive($id) ? Response::redirect('/products') : $this->notFound();
    }

    /**
     * The create form when $product is null, else its edit form.
     *
     * @param array<string, string> $old
     * @param array<string, string> $errors field => message
     */
    private function form(?Product $product, array $old = [], array $errors = [], int $status = 200): Response
    {
        return $this->view->response('products/form', [
            'title' => $product === null ? 'New Product' : "Edit {$product->name}",
            'product' => $product,
            'old' => $old,
            'errors' => $errors,
        ], $status);
    }

    private function notFound(): Response
    {
        return $this->view->response('errors/404', ['title' => 'Not found'], 404);
    }
}
