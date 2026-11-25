<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\Auth;
use App\Auth\Guard;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Response;
use App\Core\Route;
use App\Core\View;
use App\Models\Product;
use App\Models\ProductListQuery;
use App\Repositories\ProductRepository;
use App\Services\InsufficientStockException;
use App\Services\ProductNotFoundException;
use App\Services\PurchaseService;
use App\Validation\ProductValidator;
use App\Validation\PurchaseQuantity;

final readonly class ProductsController
{
    public function __construct(
        private ProductRepository $products,
        private PurchaseService $purchases,
        private ProductValidator $validator,
        private Guard $guard,
        private Auth $auth,
        private Flash $flash,
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

    #[Route('GET', '/products/{id}/purchase')]
    public function purchaseForm(int $id): Response
    {
        if ($denied = $this->guard->signedIn()) {
            return $denied;
        }

        $product = $this->products->find($id);
        if ($product === null) {
            return $this->notFound();
        }

        return $this->purchaseView($product);
    }

    #[Route('POST', '/products/{id}/purchase')]
    public function purchase(Request $request, int $id): Response
    {
        if ($denied = $this->guard->signedIn()) {
            return $denied;
        }

        $product = $this->products->find($id);
        if ($product === null) {
            return $this->notFound();
        }

        $submitted = $request->input('quantity');
        $old = ['quantity' => PurchaseQuantity::normalise($submitted)];
        // Sold Out wins over a bad quantity: any attempt is a 409 (spec §4). The service re-checks under the lock.
        if ($product->isSoldOut()) {
            return $this->purchaseView($product, $old, (new InsufficientStockException(0))->getMessage(), 409);
        }
        if ($error = PurchaseQuantity::error($submitted)) {
            return $this->purchaseView($product, $old, $error, 422);
        }

        // Past PHP_INT_MAX the cast saturates, which is still more than any stock.
        $quantity = (int) $old['quantity'];
        try {
            $purchase = $this->purchases->purchase((int) $this->auth->id(), $id, $quantity);
        } catch (ProductNotFoundException) {
            return $this->notFound(); // archived since the find() above
        } catch (InsufficientStockException $e) {
            return $this->purchaseView($product->withQuantityAvailable($e->available), $old, $e->getMessage(), 409);
        }
        $this->flash->put("Bought {$purchase->quantity} × {$product->name}. Purchase Total: {$purchase->total} USD.");

        return Response::redirect("/products/{$id}/purchase");
    }

    /** @param array<string, string> $old */
    private function purchaseView(Product $product, array $old = [], ?string $error = null, int $status = 200): Response
    {
        return $this->view->response('products/purchase', [
            'title' => "Buy {$product->name}",
            'product' => $product,
            'old' => $old,
            'error' => $error,
        ], $status);
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
