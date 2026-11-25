<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Api\BearerAuth;
use App\Api\Json;
use App\Core\Request;
use App\Core\Response;
use App\Core\Route;
use App\Models\Product;
use App\Models\ProductListQuery;
use App\Repositories\ProductRepository;
use App\Services\InsufficientStockException;
use App\Services\ProductNotFoundException;
use App\Services\PurchaseService;
use App\Validation\ProductInput;
use App\Validation\ProductValidator;
use App\Validation\PurchaseQuantity;
use DateTimeInterface;

/**
 * The Product catalogue and Purchases as JSON (spec §6). Shares the web
 * layer's repository, PurchaseService and validator, so the rules are the same.
 */
final readonly class ProductsController
{
    public function __construct(
        private ProductRepository $products,
        private PurchaseService $purchases,
        private ProductValidator $validator,
        private BearerAuth $bearer,
    ) {
    }

    #[Route('GET', '/api/products')]
    public function index(Request $request): Response
    {
        $query = ProductListQuery::fromQuery($request->query);
        $total = $this->products->count();
        $lastPage = ProductListQuery::lastPage($total);
        $query = $query->onPage(min($query->page, $lastPage));
        $page = $this->products->page($query->sort, $query->dir, ProductListQuery::PER_PAGE, $query->offset());

        return Response::json([
            'data' => array_map(self::product(...), $page),
            'meta' => ['page' => $query->page, 'per_page' => ProductListQuery::PER_PAGE, 'total' => $total, 'last_page' => $lastPage],
        ]);
    }

    #[Route('GET', '/api/products/{id}')]
    public function show(int $id): Response
    {
        $product = $this->products->find($id);

        return $product === null ? Json::notFound() : Response::json(['data' => self::product($product)]);
    }

    #[Route('POST', '/api/products')]
    public function store(Request $request): Response
    {
        $caller = $this->bearer->admin($request);
        if ($caller instanceof Response) {
            return $caller;
        }

        $input = $this->productInput($request);
        if ($input instanceof Response) {
            return $input;
        }

        $product = $this->products->create($input->name, $input->price, $input->quantityAvailable);
        return Response::json(['data' => self::product($product)], 201)
            ->withHeader('Location', "/api/products/{$product->id}");
    }

    #[Route('PUT', '/api/products/{id}')]
    public function update(Request $request, int $id): Response
    {
        $caller = $this->bearer->admin($request);
        if ($caller instanceof Response) {
            return $caller;
        }
        if ($this->products->find($id) === null) {
            return Json::notFound();
        }

        $input = $this->productInput($request);
        if ($input instanceof Response) {
            return $input;
        }

        // false: archived by another Admin since the find() above.
        if (!$this->products->update($id, $input->name, $input->price, $input->quantityAvailable)) {
            return Json::notFound();
        }

        return Response::json(['data' => self::product(new Product($id, $input->name, $input->price, $input->quantityAvailable))]);
    }

    /** "Delete" archives the Product: it leaves sale for good but its Purchases remain (ADR-0003). */
    #[Route('DELETE', '/api/products/{id}')]
    public function destroy(Request $request, int $id): Response
    {
        $caller = $this->bearer->admin($request);
        if ($caller instanceof Response) {
            return $caller;
        }

        return $this->products->archive($id) ? Response::json(null, 204) : Json::notFound();
    }

    /** Any signed-in User or Admin buys as the token's subject; all-or-nothing (spec §4 Purchase). */
    #[Route('POST', '/api/products/{id}/purchase')]
    public function purchase(Request $request, int $id): Response
    {
        $caller = $this->bearer->user($request);
        if ($caller instanceof Response) {
            return $caller;
        }
        $product = $this->products->find($id);
        if ($product === null) {
            return Json::notFound();
        }

        $body = Json::body($request);
        if ($body instanceof Response) {
            return $body;
        }

        $submitted = $body['quantity'] ?? null;
        // Sold Out wins over a bad quantity: any attempt is a 409 (spec §4). The service re-checks under the lock.
        if ($product->isSoldOut()) {
            return self::insufficientStock(new InsufficientStockException(0));
        }
        if ($error = PurchaseQuantity::error($submitted)) {
            return Json::invalid(['quantity' => $error]);
        }

        try {
            // Past PHP_INT_MAX the cast saturates, which is still more than any stock.
            $purchase = $this->purchases->purchase($caller->userId, $id, (int) PurchaseQuantity::normalise($submitted));
        } catch (ProductNotFoundException) {
            return Json::notFound(); // archived since the find() above
        } catch (InsufficientStockException $e) {
            return self::insufficientStock($e);
        }

        return Response::json(['data' => [
            'id' => $purchase->id,
            'product_id' => $purchase->productId,
            'quantity' => $purchase->quantity,
            'unit_price' => $purchase->unitPrice,
            'total' => $purchase->total,
            'created_at' => $purchase->createdAt->format(DateTimeInterface::ATOM),
        ]], 201);
    }

    private static function insufficientStock(InsufficientStockException $e): Response
    {
        return Json::error(409, $e->available === 0 ? 'sold_out' : 'insufficient_stock', $e->getMessage());
    }

    /** The validated Product fields from the JSON body, or the 400/422 to send instead. */
    private function productInput(Request $request): ProductInput|Response
    {
        $body = Json::body($request);
        if ($body instanceof Response) {
            return $body;
        }

        $result = $this->validator->validate($body);

        return $result->input ?? Json::invalid($result->errors);
    }

    /** @return array{id: int, name: string, price: string, quantity_available: int} */
    private static function product(Product $product): array
    {
        // A string, not a float: 6.885 has no exact binary form (ADR-0002).
        return ['id' => $product->id, 'name' => $product->name, 'price' => $product->displayPrice(), 'quantity_available' => $product->quantityAvailable];
    }
}
