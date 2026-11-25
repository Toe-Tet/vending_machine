<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers\Api;

use App\Api\BearerAuth;
use App\Auth\JwtTokens;
use App\Controllers\Api\ProductsController;
use App\Core\Request;
use App\Core\Response;
use App\Models\Product;
use App\Models\ProductSort;
use App\Models\Purchase;
use App\Models\Role;
use App\Models\SortDirection;
use App\Models\User;
use App\Repositories\ProductRepository;
use App\Services\InsufficientStockException;
use App\Services\ProductNotFoundException;
use App\Services\PurchaseService;
use App\Validation\ProductValidator;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

final class ProductsControllerTest extends TestCase
{
    private JwtTokens $tokens;
    private ProductRepository&Stub $products;
    private PurchaseService&Stub $purchases;

    protected function setUp(): void
    {
        $this->tokens = new JwtTokens('testing-secret');
        $this->products = $this->createStub(ProductRepository::class);
        $this->purchases = $this->createStub(PurchaseService::class);
    }

    private function controller(): ProductsController
    {
        return new ProductsController($this->products, $this->purchases, new ProductValidator(), new BearerAuth($this->tokens));
    }

    private function mockProducts(): ProductRepository&MockObject
    {
        $mock = $this->createMock(ProductRepository::class);
        $this->products = $mock;

        return $mock;
    }

    private function mockPurchases(): PurchaseService&MockObject
    {
        $mock = $this->createMock(PurchaseService::class);
        $this->purchases = $mock;

        return $mock;
    }

    public function test_anyone_can_list_products_with_prices_as_strings_and_page_meta(): void
    {
        $products = $this->mockProducts();
        $products->method('count')->willReturn(23);
        $products->expects(self::once())->method('page')
            ->with(ProductSort::Price, SortDirection::Desc, 10, 10)
            ->willReturn(self::seeded());

        $response = $this->controller()->index(self::request('GET', '/api/products', query: ['sort' => 'price', 'dir' => 'desc', 'page' => '2']));
        $json = self::json($response);

        self::assertSame(200, $response->status);
        self::assertSame(['id' => 2, 'name' => 'Pepsi', 'price' => '6.885', 'quantity_available' => 10], $json['data'][1]);
        self::assertSame(['page' => 2, 'per_page' => 10, 'total' => 23, 'last_page' => 3], $json['meta']);
    }

    public function test_anyone_can_show_a_product(): void
    {
        $this->products->method('find')->willReturnMap([[2, new Product(2, 'Pepsi', '6.885', 10)]]);

        $response = $this->controller()->show(2);

        self::assertSame(200, $response->status);
        self::assertSame(['data' => ['id' => 2, 'name' => 'Pepsi', 'price' => '6.885', 'quantity_available' => 10]], self::json($response));
    }

    public function test_a_missing_or_archived_product_is_404_in_the_error_envelope(): void
    {
        $this->products->method('find')->willReturn(null);

        $response = $this->controller()->show(99);

        self::assertSame(404, $response->status);
        self::assertSame(['error' => ['code' => 'not_found', 'message' => 'Not found.', 'fields' => []]], self::json($response));
    }

    public function test_an_admin_creates_a_product_and_gets_201_with_it(): void
    {
        $products = $this->mockProducts();
        $products->expects(self::once())->method('create')->with('Iced Tea', '6.885', 12)
            ->willReturn(new Product(7, 'Iced Tea', '6.885', 12));

        $response = $this->controller()->store(self::request('POST', '/api/products', self::validProduct(), $this->tokenFor(Role::Admin)));

        self::assertSame(201, $response->status);
        self::assertSame('/api/products/7', $response->headers['Location']);
        self::assertSame(['id' => 7, 'name' => 'Iced Tea', 'price' => '6.885', 'quantity_available' => 12], self::json($response)['data']);
    }

    /** @return iterable<string, array{array<string, string>}> */
    public static function unauthenticatedHeaders(): iterable
    {
        $tokens = new JwtTokens('testing-secret');
        $admin = new User(1, 'Admin', 'admin@example.com', 'hash', Role::Admin);
        [$header, , $signature] = explode('.', $tokens->issue(new User(2, 'User', 'user@example.com', 'hash', Role::User))->token);
        $promoted = rtrim(strtr(base64_encode('{"sub":"2","role":"admin","iat":' . time() . ',"exp":' . (time() + 3600) . '}'), '+/', '-_'), '=');

        yield 'no header' => [[]];
        yield 'not Bearer' => [['authorization' => 'Basic YWRtaW46c2VjcmV0']];
        yield 'empty Bearer' => [['authorization' => 'Bearer ']];
        yield 'malformed token' => [['authorization' => 'Bearer not.a.jwt']];
        yield 'tampered role' => [['authorization' => "Bearer {$header}.{$promoted}.{$signature}"]];
        yield 'expired' => [['authorization' => 'Bearer ' . (new JwtTokens('testing-secret', static fn (): int => time() - 7200))->issue($admin)->token]];
        yield 'another secret' => [['authorization' => 'Bearer ' . (new JwtTokens('another-secret'))->issue($admin)->token]];
    }

    /** @param array<string, string> $headers */
    #[DataProvider('unauthenticatedHeaders')]
    public function test_admin_endpoints_without_a_valid_token_are_401_and_change_nothing(array $headers): void
    {
        $products = $this->mockProducts();
        $products->expects(self::never())->method('create');
        $request = new Request('POST', '/api/products', headers: $headers, content: json_encode(self::validProduct()));

        $response = $this->controller()->store($request);

        self::assertSame(401, $response->status);
        self::assertSame('Bearer', $response->headers['WWW-Authenticate']);
        self::assertSame('unauthenticated', self::json($response)['error']['code']);
    }

    public function test_a_user_token_on_an_admin_endpoint_is_403_and_changes_nothing(): void
    {
        $products = $this->mockProducts();
        $products->expects(self::never())->method('create');

        $response = $this->controller()->store(self::request('POST', '/api/products', self::validProduct(), $this->tokenFor(Role::User)));

        self::assertSame(403, $response->status);
        self::assertSame('forbidden', self::json($response)['error']['code']);
    }

    /** @return iterable<string, array{array<string, mixed>, string, string}> */
    public static function invalidProducts(): iterable
    {
        yield 'missing name' => [['name' => null], 'name', 'Enter a name.'];
        yield 'zero price' => [['price' => '0'], 'price', 'Price must be greater than 0.'];
        yield 'negative price as a number' => [['price' => -1.5], 'price', 'Price must be greater than 0.'];
        yield 'more than 3 decimals' => [['price' => '1.2345'], 'price', 'Price can have at most 3 decimal places.'];
        yield 'negative quantity' => [['quantity_available' => -1], 'quantity_available', 'Quantity cannot be negative.'];
        yield 'fractional quantity' => [['quantity_available' => 1.5], 'quantity_available', 'Quantity must be a whole number.'];
    }

    /** @param array<string, mixed> $override */
    #[DataProvider('invalidProducts')]
    public function test_an_invalid_product_is_422_with_the_field_message_and_nothing_is_created(array $override, string $field, string $message): void
    {
        $products = $this->mockProducts();
        $products->expects(self::never())->method('create');

        $response = $this->controller()->store(self::request('POST', '/api/products', $override + self::validProduct(), $this->tokenFor(Role::Admin)));
        $error = self::json($response)['error'];

        self::assertSame(422, $response->status);
        self::assertSame('validation_failed', $error['code']);
        self::assertSame([$field => $message], $error['fields']);
    }

    public function test_malformed_json_is_400(): void
    {
        $request = new Request('POST', '/api/products', headers: ['authorization' => 'Bearer ' . $this->tokenFor(Role::Admin)], content: '{"name": ');

        $response = $this->controller()->store($request);

        self::assertSame(400, $response->status);
        self::assertSame('malformed_json', self::json($response)['error']['code']);
    }

    public function test_an_admin_updates_a_product_and_gets_it_back(): void
    {
        $products = $this->mockProducts();
        $products->method('find')->willReturn(new Product(2, 'Pepsi', '6.885', 10));
        $products->expects(self::once())->method('update')->with(2, 'Pepsi Max', '7.000', 4)->willReturn(true);

        $body = ['name' => ' Pepsi Max ', 'price' => 7, 'quantity_available' => '4'];
        $response = $this->controller()->update(self::request('PUT', '/api/products/2', $body, $this->tokenFor(Role::Admin)), 2);

        self::assertSame(200, $response->status);
        self::assertSame(['id' => 2, 'name' => 'Pepsi Max', 'price' => '7.000', 'quantity_available' => 4], self::json($response)['data']);
    }

    public function test_updating_needs_an_admin_token(): void
    {
        $products = $this->mockProducts();
        $products->expects(self::never())->method('update');

        self::assertSame(401, $this->controller()->update(self::request('PUT', '/api/products/2', self::validProduct()), 2)->status);
        self::assertSame(403, $this->controller()->update(self::request('PUT', '/api/products/2', self::validProduct(), $this->tokenFor(Role::User)), 2)->status);
    }

    public function test_an_invalid_update_is_422_and_changes_nothing(): void
    {
        $products = $this->mockProducts();
        $products->method('find')->willReturn(new Product(2, 'Pepsi', '6.885', 10));
        $products->expects(self::never())->method('update');

        $response = $this->controller()->update(self::request('PUT', '/api/products/2', ['price' => '-1'] + self::validProduct(), $this->tokenFor(Role::Admin)), 2);

        self::assertSame(422, $response->status);
        self::assertSame(['price' => 'Price must be greater than 0.'], self::json($response)['error']['fields']);
    }

    public function test_updating_a_missing_or_archived_product_is_404(): void
    {
        $this->products->method('find')->willReturn(null);

        $response = $this->controller()->update(self::request('PUT', '/api/products/99', self::validProduct(), $this->tokenFor(Role::Admin)), 99);

        self::assertSame(404, $response->status);
    }

    public function test_a_product_archived_mid_update_is_404(): void
    {
        $this->products->method('find')->willReturn(new Product(2, 'Pepsi', '6.885', 10));
        $this->products->method('update')->willReturn(false);

        $response = $this->controller()->update(self::request('PUT', '/api/products/2', self::validProduct(), $this->tokenFor(Role::Admin)), 2);

        self::assertSame(404, $response->status);
    }

    public function test_deleting_archives_the_product_with_an_empty_204(): void
    {
        $products = $this->mockProducts();
        $products->expects(self::once())->method('archive')->with(2)->willReturn(true);

        $response = $this->controller()->destroy(self::request('DELETE', '/api/products/2', token: $this->tokenFor(Role::Admin)), 2);

        self::assertSame(204, $response->status);
        self::assertSame('', $response->body);
    }

    public function test_deleting_a_missing_or_archived_product_is_404(): void
    {
        $this->products->method('archive')->willReturn(false);

        self::assertSame(404, $this->controller()->destroy(self::request('DELETE', '/api/products/9', token: $this->tokenFor(Role::Admin)), 9)->status);
    }

    public function test_deleting_needs_an_admin_token(): void
    {
        $products = $this->mockProducts();
        $products->expects(self::never())->method('archive');

        self::assertSame(401, $this->controller()->destroy(self::request('DELETE', '/api/products/2'), 2)->status);
        self::assertSame(403, $this->controller()->destroy(self::request('DELETE', '/api/products/2', token: $this->tokenFor(Role::User)), 2)->status);
    }

    public function test_a_user_buys_as_the_token_subject_and_gets_201_with_the_purchase(): void
    {
        $this->products->method('find')->willReturn(new Product(2, 'Pepsi', '6.885', 10));
        $purchases = $this->mockPurchases();
        $purchases->expects(self::once())->method('purchase')->with(2, 2, 2)
            ->willReturn(new Purchase(41, 2, 2, 2, '6.885', '13.770', new DateTimeImmutable('2026-11-25 10:30:00')));

        $response = $this->controller()->purchase(self::request('POST', '/api/products/2/purchase', ['quantity' => 2], $this->tokenFor(Role::User)), 2);

        self::assertSame(201, $response->status);
        self::assertSame(
            ['id' => 41, 'product_id' => 2, 'quantity' => 2, 'unit_price' => '6.885', 'total' => '13.770', 'created_at' => '2026-11-25T10:30:00+00:00'],
            self::json($response)['data'],
        );
    }

    public function test_an_admin_can_buy_too(): void
    {
        $this->products->method('find')->willReturn(new Product(2, 'Pepsi', '6.885', 10));
        $this->purchases->method('purchase')->willReturn(new Purchase(41, 1, 2, 1, '6.885', '6.885', new DateTimeImmutable()));

        self::assertSame(201, $this->controller()->purchase(self::request('POST', '/api/products/2/purchase', ['quantity' => '1'], $this->tokenFor(Role::Admin)), 2)->status);
    }

    public function test_a_guest_cannot_buy(): void
    {
        $purchases = $this->mockPurchases();
        $purchases->expects(self::never())->method('purchase');

        $response = $this->controller()->purchase(self::request('POST', '/api/products/2/purchase', ['quantity' => 1]), 2);

        self::assertSame(401, $response->status);
    }

    /** @return iterable<string, array{mixed, string}> */
    public static function invalidQuantities(): iterable
    {
        yield 'missing' => [null, 'Enter a quantity.'];
        yield 'zero' => [0, 'Quantity must be at least 1.'];
        yield 'negative' => [-2, 'Quantity must be at least 1.'];
        yield 'fraction' => [1.5, 'Quantity must be a whole number.'];
        yield 'words' => ['two', 'Quantity must be a whole number.'];
        yield 'boolean' => [true, 'Enter a quantity.'];
    }

    #[DataProvider('invalidQuantities')]
    public function test_an_invalid_quantity_is_422_and_nothing_is_bought(mixed $quantity, string $message): void
    {
        $this->products->method('find')->willReturn(new Product(2, 'Pepsi', '6.885', 10));
        $purchases = $this->mockPurchases();
        $purchases->expects(self::never())->method('purchase');

        $response = $this->controller()->purchase(self::request('POST', '/api/products/2/purchase', ['quantity' => $quantity], $this->tokenFor(Role::User)), 2);

        self::assertSame(422, $response->status);
        self::assertSame(['quantity' => $message], self::json($response)['error']['fields']);
    }

    public function test_buying_a_sold_out_product_is_409_whatever_the_quantity(): void
    {
        $this->products->method('find')->willReturn(new Product(3, 'Water', '0.500', 0));
        $purchases = $this->mockPurchases();
        $purchases->expects(self::never())->method('purchase');

        $response = $this->controller()->purchase(self::request('POST', '/api/products/3/purchase', ['quantity' => 0], $this->tokenFor(Role::User)), 3);

        self::assertSame(409, $response->status);
        self::assertSame(['code' => 'sold_out', 'message' => 'Sold out.', 'fields' => []], self::json($response)['error']);
    }

    public function test_buying_more_than_is_available_is_409_with_the_quantity_available(): void
    {
        $this->products->method('find')->willReturn(new Product(2, 'Pepsi', '6.885', 10));
        $this->purchases->method('purchase')->willThrowException(new InsufficientStockException(3));

        $response = $this->controller()->purchase(self::request('POST', '/api/products/2/purchase', ['quantity' => 5], $this->tokenFor(Role::User)), 2);

        self::assertSame(409, $response->status);
        self::assertSame(['code' => 'insufficient_stock', 'message' => 'Only 3 available.', 'fields' => []], self::json($response)['error']);
    }

    public function test_buying_a_missing_or_archived_product_is_404(): void
    {
        $this->products->method('find')->willReturn(null);

        self::assertSame(404, $this->controller()->purchase(self::request('POST', '/api/products/9/purchase', ['quantity' => 1], $this->tokenFor(Role::User)), 9)->status);
    }

    public function test_a_product_archived_mid_purchase_is_404(): void
    {
        $this->products->method('find')->willReturn(new Product(2, 'Pepsi', '6.885', 10));
        $this->purchases->method('purchase')->willThrowException(new ProductNotFoundException(2));

        self::assertSame(404, $this->controller()->purchase(self::request('POST', '/api/products/2/purchase', ['quantity' => 1], $this->tokenFor(Role::User)), 2)->status);
    }

    /**
     * @param array<string, mixed>|null $body JSON-encoded into the request
     * @param array<string, mixed> $query
     */
    private static function request(string $method, string $path, ?array $body = null, ?string $token = null, array $query = []): Request
    {
        return new Request(
            $method,
            $path,
            $query,
            headers: $token === null ? [] : ['authorization' => "Bearer {$token}"],
            content: $body === null ? '' : json_encode($body, JSON_THROW_ON_ERROR),
        );
    }

    private function tokenFor(Role $role): string
    {
        return $this->tokens->issue(new User($role === Role::Admin ? 1 : 2, 'Pat', 'pat@example.com', 'hash', $role))->token;
    }

    /** @return array<string, mixed> */
    private static function json(Response $response): array
    {
        self::assertSame('application/json', $response->headers['Content-Type']);

        return json_decode($response->body, true, flags: JSON_THROW_ON_ERROR);
    }

    /** @return array<string, mixed> as an API client would send it: price a string, quantity a number */
    private static function validProduct(): array
    {
        return ['name' => 'Iced Tea', 'price' => '6.885', 'quantity_available' => 12];
    }

    /** @return list<Product> */
    private static function seeded(): array
    {
        return [
            new Product(1, 'Coke', '3.990', 10),
            new Product(2, 'Pepsi', '6.885', 10),
            new Product(3, 'Water', '0.500', 0),
        ];
    }
}
