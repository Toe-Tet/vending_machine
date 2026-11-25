<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use App\Auth\Auth;
use App\Auth\Csrf;
use App\Auth\Guard;
use App\Controllers\ProductsController;
use App\Core\ArraySession;
use App\Core\Request;
use App\Core\View;
use App\Models\Product;
use App\Models\ProductSort;
use App\Models\Role;
use App\Models\SortDirection;
use App\Models\User;
use App\Repositories\ProductRepository;
use App\Validation\ProductValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

final class ProductsControllerTest extends TestCase
{
    private Auth $auth;
    private ProductRepository&Stub $products;

    protected function setUp(): void
    {
        $session = new ArraySession();
        $this->auth = new Auth($session, new Csrf($session));
        $this->products = $this->createStub(ProductRepository::class);
    }

    /** The controller under test, wired to whatever $this->products is by now. */
    private function controller(): ProductsController
    {
        $auth = $this->auth;
        $view = new View(dirname(__DIR__, 3) . '/templates', static fn () => ['auth' => $auth, 'csrfToken' => 'test-token']);

        return new ProductsController($this->products, new ProductValidator(), new Guard($auth, $view), $view);
    }

    /** Swaps the stub repository for a mock, for tests that assert which calls are made. */
    private function mockProducts(): ProductRepository&MockObject
    {
        $mock = $this->createMock(ProductRepository::class);
        $this->products = $mock;

        return $mock;
    }

    public function test_the_list_defaults_to_name_ascending_on_the_first_page(): void
    {
        $products = $this->mockProducts();
        $products->method('count')->willReturn(3);
        $products->expects(self::once())->method('page')
            ->with(ProductSort::Name, SortDirection::Asc, 10, 0)
            ->willReturn(self::seeded());

        $response = $this->controller()->index(self::get('/products'));

        self::assertSame(200, $response->status);
        self::assertStringContainsString('Coke', $response->body);
        self::assertStringContainsString('6.885', $response->body);
    }

    /** @return iterable<string, array{array<string, mixed>, ProductSort, SortDirection, int}> */
    public static function listQueries(): iterable
    {
        yield 'price descending, page 2' => [['sort' => 'price', 'dir' => 'desc', 'page' => '2'], ProductSort::Price, SortDirection::Desc, 10];
        yield 'quantity ascending' => [['sort' => 'quantity', 'dir' => 'asc'], ProductSort::Quantity, SortDirection::Asc, 0];
        yield 'unknown sort column' => [['sort' => 'password_hash', 'dir' => 'desc'], ProductSort::Name, SortDirection::Desc, 0];
        yield 'unknown direction' => [['sort' => 'price', 'dir' => 'sideways'], ProductSort::Price, SortDirection::Asc, 0];
        yield 'SQL in sort' => [['sort' => 'name; DROP TABLE products'], ProductSort::Name, SortDirection::Asc, 0];
        yield 'page zero' => [['page' => '0'], ProductSort::Name, SortDirection::Asc, 0];
        yield 'negative page' => [['page' => '-3'], ProductSort::Name, SortDirection::Asc, 0];
        yield 'non-numeric page' => [['page' => 'abc'], ProductSort::Name, SortDirection::Asc, 0];
        yield 'array parameters' => [['sort' => ['price'], 'dir' => ['desc'], 'page' => ['2']], ProductSort::Name, SortDirection::Asc, 0];
        yield 'page past the end clamps to the last page' => [['page' => '99'], ProductSort::Name, SortDirection::Asc, 20];
        yield 'huge page clamps to the last page' => [['page' => '99999999999999999999'], ProductSort::Name, SortDirection::Asc, 20];
    }

    /** @param array<string, mixed> $query */
    #[DataProvider('listQueries')]
    public function test_the_list_honours_valid_sort_and_page_and_falls_back_on_bogus_values(
        array $query,
        ProductSort $sort,
        SortDirection $dir,
        int $offset,
    ): void {
        $products = $this->mockProducts();
        $products->method('count')->willReturn(25);
        $products->expects(self::once())->method('page')->with($sort, $dir, 10, $offset)->willReturn(self::seeded());

        self::assertSame(200, $this->controller()->index(self::get('/products', $query))->status);
    }

    public function test_the_list_with_no_products_asks_for_the_first_page(): void
    {
        $products = $this->mockProducts();
        $products->method('count')->willReturn(0);
        $products->expects(self::once())->method('page')->with(ProductSort::Name, SortDirection::Asc, 10, 0)->willReturn([]);

        self::assertStringContainsString('No products yet.', $this->controller()->index(self::get('/products', ['page' => '4']))->body);
    }

    public function test_the_current_column_header_toggles_direction_and_others_start_ascending(): void
    {
        $this->products->method('count')->willReturn(3);
        $this->products->method('page')->willReturn(self::seeded());

        $body = $this->controller()->index(self::get('/products', ['sort' => 'price', 'dir' => 'asc']))->body;

        self::assertStringContainsString('href="/products?sort=price&amp;dir=desc&amp;page=1"', $body);
        self::assertStringContainsString('href="/products?sort=name&amp;dir=asc&amp;page=1"', $body);
        self::assertStringContainsString('href="/products?sort=quantity&amp;dir=asc&amp;page=1"', $body);
    }

    public function test_pagination_links_keep_the_sort(): void
    {
        $this->products->method('count')->willReturn(25);
        $this->products->method('page')->willReturn(self::seeded());

        $body = $this->controller()->index(self::get('/products', ['sort' => 'quantity', 'dir' => 'desc', 'page' => '2']))->body;

        self::assertStringContainsString('href="/products?sort=quantity&amp;dir=desc&amp;page=1"', $body);
        self::assertStringContainsString('href="/products?sort=quantity&amp;dir=desc&amp;page=3"', $body);
        self::assertStringContainsString('Page 2 of 3', $body);
    }

    public function test_rows_show_three_decimal_prices_and_sold_out(): void
    {
        $this->products->method('count')->willReturn(2);
        $this->products->method('page')->willReturn([new Product(4, 'Juice', '2.5', 0), new Product(5, 'Tea', '1.000', 7)]);

        $body = $this->controller()->index(self::get('/products'))->body;

        self::assertStringContainsString('2.500', $body);
        self::assertStringContainsString('Sold out', $body);
        self::assertStringContainsString('>7<', $body);
    }

    public function test_guests_and_users_see_no_edit_or_delete_controls(): void
    {
        $this->products->method('count')->willReturn(3);
        $this->products->method('page')->willReturn(self::seeded());

        $guest = $this->controller()->index(self::get('/products'))->body;
        $this->signInAs(Role::User);
        $user = $this->controller()->index(self::get('/products'))->body;

        foreach ([$guest, $user] as $body) {
            self::assertStringNotContainsString('/products/1/edit', $body);
            self::assertStringNotContainsString('value="DELETE"', $body);
            self::assertStringNotContainsString('/products/create', $body);
        }
    }

    public function test_admins_see_edit_and_delete_on_every_row(): void
    {
        $this->signInAs(Role::Admin);
        $this->products->method('count')->willReturn(3);
        $this->products->method('page')->willReturn(self::seeded());

        $body = $this->controller()->index(self::get('/products'))->body;

        self::assertStringContainsString('href="/products/1/edit"', $body);
        self::assertStringContainsString('action="/products/3"', $body);
        self::assertSame(3, substr_count($body, 'value="DELETE"'));
        self::assertStringContainsString('href="/products/create"', $body);
    }

    public function test_anyone_can_view_a_product(): void
    {
        $this->products->method('find')->willReturnMap([[2, new Product(2, 'Pepsi', '6.885', 10)]]);

        $response = $this->controller()->show(2);

        self::assertSame(200, $response->status);
        self::assertStringContainsString('Pepsi', $response->body);
        self::assertStringContainsString('6.885', $response->body);
        self::assertStringNotContainsString('/products/2/edit', $response->body);
    }

    public function test_admins_see_edit_and_delete_on_the_product_page(): void
    {
        $this->signInAs(Role::Admin);
        $this->products->method('find')->willReturn(new Product(2, 'Pepsi', '6.885', 10));

        $body = $this->controller()->show(2)->body;

        self::assertStringContainsString('href="/products/2/edit"', $body);
        self::assertStringContainsString('value="DELETE"', $body);
    }

    /** The repository hides Archived Products, so missing and archived look the same here. */
    public function test_a_missing_or_archived_product_is_not_found(): void
    {
        $this->products->method('find')->willReturn(null);

        self::assertSame(404, $this->controller()->show(99)->status);
    }

    public function test_an_admin_creates_a_product_and_is_taken_to_it(): void
    {
        $this->signInAs(Role::Admin);
        $products = $this->mockProducts();
        $products->expects(self::once())->method('create')
            ->with('Iced Tea', '6.885', 12)
            ->willReturn(new Product(4, 'Iced Tea', '6.885', 12));

        $response = $this->controller()->store(self::post('/products', ['name' => '  Iced Tea ', 'price' => '6.885', 'quantity_available' => '12']));

        self::assertSame(302, $response->status);
        self::assertSame('/products/4', $response->headers['Location']);
    }

    public function test_prices_are_stored_with_exactly_three_decimals(): void
    {
        $this->signInAs(Role::Admin);
        $products = $this->mockProducts();
        $products->expects(self::once())->method('create')
            ->with('Gum', '2.500', 0)
            ->willReturn(new Product(5, 'Gum', '2.500', 0));

        $this->controller()->store(self::post('/products', ['name' => 'Gum', 'price' => '2.5', 'quantity_available' => '0']));
    }

    /** @return iterable<string, array{array<string, mixed>, string, string}> */
    public static function invalidProducts(): iterable
    {
        yield 'missing name' => [['name' => ''], 'name', 'Enter a name.'];
        yield 'blank name' => [['name' => '    '], 'name', 'Enter a name.'];
        yield 'name over 100 characters' => [['name' => str_repeat('é', 101)], 'name', 'Name must be 100 characters or fewer.'];
        yield 'name sent as an array' => [['name' => ['Coke']], 'name', 'Enter a name.'];
        yield 'missing price' => [['price' => ''], 'price', 'Enter a price.'];
        yield 'non-numeric price' => [['price' => 'free'], 'price', 'Price must be a number.'];
        yield 'price in exponent form' => [['price' => '1e3'], 'price', 'Price must be a number.'];
        yield 'price with a plus sign' => [['price' => '+5'], 'price', 'Price must be a number.'];
        yield 'price ending in a point' => [['price' => '5.'], 'price', 'Price must be a number.'];
        yield 'zero price' => [['price' => '0'], 'price', 'Price must be greater than 0.'];
        yield 'zero price with decimals' => [['price' => '0.000'], 'price', 'Price must be greater than 0.'];
        yield 'negative price' => [['price' => '-1.5'], 'price', 'Price must be greater than 0.'];
        yield 'price over the maximum' => [['price' => '1000000'], 'price', 'Price must be 999,999.999 or less.'];
        yield 'price with four decimals' => [['price' => '6.8851'], 'price', 'Price can have at most 3 decimal places.'];
        yield 'missing quantity' => [['quantity_available' => ''], 'quantity_available', 'Enter a quantity.'];
        yield 'fractional quantity' => [['quantity_available' => '1.5'], 'quantity_available', 'Quantity must be a whole number.'];
        yield 'quantity with a plus sign' => [['quantity_available' => '+5'], 'quantity_available', 'Quantity must be a whole number.'];
        yield 'non-numeric quantity' => [['quantity_available' => 'lots'], 'quantity_available', 'Quantity must be a whole number.'];
        yield 'negative quantity' => [['quantity_available' => '-1'], 'quantity_available', 'Quantity cannot be negative.'];
        yield 'quantity over the maximum' => [['quantity_available' => '1000001'], 'quantity_available', 'Quantity must be 1,000,000 or less.'];
        yield 'huge quantity' => [['quantity_available' => '99999999999999999999'], 'quantity_available', 'Quantity must be 1,000,000 or less.'];
    }

    /** @param array<string, mixed> $override */
    #[DataProvider('invalidProducts')]
    public function test_an_invalid_product_is_rejected_inline_and_nothing_is_created(array $override, string $field, string $message): void
    {
        $this->signInAs(Role::Admin);
        $this->mockProducts()->expects(self::never())->method('create');

        $response = $this->controller()->store(self::post('/products', $override + self::validProduct()));

        self::assertSame(422, $response->status);
        self::assertStringContainsString('<small id="' . $field . '-error" data-error-for="' . $field . '">' . e($message) . '</small>', $response->body);
        self::assertStringContainsString('id="' . $field . '"', $response->body);
        self::assertMatchesRegularExpression('/id="' . $field . '"[^>]*aria-invalid="true"/', $response->body);
    }

    public function test_a_rejected_product_keeps_what_was_typed(): void
    {
        $this->signInAs(Role::Admin);

        $body = $this->controller()->store(self::post('/products', ['name' => 'Iced <Tea>', 'price' => '6.8851', 'quantity_available' => '-4']))->body;

        self::assertStringContainsString('value="Iced &lt;Tea&gt;"', $body);
        self::assertStringContainsString('value="6.8851"', $body);
        self::assertStringContainsString('value="-4"', $body);
    }

    public function test_every_bad_field_is_reported_at_once(): void
    {
        $this->signInAs(Role::Admin);

        $body = $this->controller()->store(self::post('/products', []))->body;

        self::assertStringContainsString('Enter a name.', $body);
        self::assertStringContainsString('Enter a price.', $body);
        self::assertStringContainsString('Enter a quantity.', $body);
    }

    /** @return iterable<string, array{array<string, string>, string, int}> */
    public static function boundaryProducts(): iterable
    {
        yield 'smallest price, zero quantity' => [['price' => '0.001', 'quantity_available' => '0'], '0.001', 0];
        yield 'largest price and quantity' => [['price' => '999999.999', 'quantity_available' => '1000000'], '999999.999', 1000000];
        yield 'trailing zeros beyond three decimals' => [['price' => '1.5000'], '1.500', 12];
        yield 'whole price' => [['price' => '7'], '7.000', 12];
    }

    /** @param array<string, string> $override */
    #[DataProvider('boundaryProducts')]
    public function test_values_on_the_boundaries_are_accepted(array $override, string $price, int $quantity): void
    {
        $this->signInAs(Role::Admin);
        $products = $this->mockProducts();
        $products->expects(self::once())->method('create')
            ->with('Iced Tea', $price, $quantity)
            ->willReturn(new Product(4, 'Iced Tea', $price, $quantity));

        self::assertSame(302, $this->controller()->store(self::post('/products', $override + self::validProduct()))->status);
    }

    public function test_a_name_of_exactly_100_characters_is_accepted(): void
    {
        $this->signInAs(Role::Admin);
        $name = str_repeat('é', 100);
        $products = $this->mockProducts();
        $products->expects(self::once())->method('create')->with($name)->willReturn(new Product(4, $name, '6.885', 12));

        $this->controller()->store(self::post('/products', ['name' => $name] + self::validProduct()));
    }

    public function test_the_edit_form_is_filled_with_the_product(): void
    {
        $this->signInAs(Role::Admin);
        $this->products->method('find')->willReturnMap([[2, new Product(2, 'Pepsi', '6.885', 10)]]);

        $response = $this->controller()->edit(2);

        self::assertSame(200, $response->status);
        self::assertStringContainsString('action="/products/2"', $response->body);
        self::assertStringContainsString('name="_method" value="PUT"', $response->body);
        self::assertStringContainsString('value="Pepsi"', $response->body);
        self::assertStringContainsString('value="6.885"', $response->body);
        self::assertStringContainsString('value="10"', $response->body);
    }

    public function test_an_admin_updates_a_product(): void
    {
        $this->signInAs(Role::Admin);
        $products = $this->mockProducts();
        $products->method('find')->willReturn(new Product(2, 'Pepsi', '6.885', 10));
        $products->expects(self::once())->method('update')->with(2, 'Pepsi Max', '7.250', 0)->willReturn(true);

        $response = $this->controller()->update(self::post('/products/2', ['name' => 'Pepsi Max', 'price' => '7.25', 'quantity_available' => '0']), 2);

        self::assertSame('/products/2', $response->headers['Location']);
    }

    public function test_an_invalid_update_re_renders_the_edit_form_and_changes_nothing(): void
    {
        $this->signInAs(Role::Admin);
        $products = $this->mockProducts();
        $products->method('find')->willReturn(new Product(2, 'Pepsi', '6.885', 10));
        $products->expects(self::never())->method('update');

        $response = $this->controller()->update(self::post('/products/2', ['name' => 'Pepsi', 'price' => '-1', 'quantity_available' => '10']), 2);

        self::assertSame(422, $response->status);
        self::assertStringContainsString('Price must be greater than 0.', $response->body);
        self::assertStringContainsString('value="-1"', $response->body);
        self::assertStringContainsString('name="_method" value="PUT"', $response->body);
    }

    public function test_an_admin_archives_a_product_and_returns_to_the_list(): void
    {
        $this->signInAs(Role::Admin);
        $this->mockProducts()->expects(self::once())->method('archive')->with(3)->willReturn(true);

        self::assertSame('/products', $this->controller()->destroy(3)->headers['Location']);
    }

    public function test_managing_a_missing_or_archived_product_is_not_found(): void
    {
        $this->signInAs(Role::Admin);
        $products = $this->mockProducts();
        $products->method('find')->willReturn(null);
        $products->method('archive')->willReturn(false);
        $products->expects(self::never())->method('update');
        $controller = $this->controller();

        self::assertSame(404, $controller->edit(9)->status);
        self::assertSame(404, $controller->update(self::post('/products/9', self::validProduct()), 9)->status);
        self::assertSame(404, $controller->destroy(9)->status);
    }

    public function test_a_product_archived_while_being_edited_is_not_found(): void
    {
        $this->signInAs(Role::Admin);
        $this->products->method('find')->willReturn(new Product(2, 'Pepsi', '6.885', 10));
        $this->products->method('update')->willReturn(false);

        self::assertSame(404, $this->controller()->update(self::post('/products/2', self::validProduct()), 2)->status);
    }

    /** @return iterable<string, array{Role|null, int, string|null}> */
    public static function nonAdmins(): iterable
    {
        yield 'guest' => [null, 302, '/login'];
        yield 'user' => [Role::User, 403, null];
    }

    #[DataProvider('nonAdmins')]
    public function test_non_admins_cannot_reach_any_management_action(?Role $role, int $status, ?string $location): void
    {
        if ($role !== null) {
            $this->signInAs($role);
        }
        $products = $this->mockProducts();
        $products->method('find')->willReturn(new Product(1, 'Coke', '3.990', 10));
        $products->expects(self::never())->method('create');
        $products->expects(self::never())->method('update');
        $products->expects(self::never())->method('archive');
        $controller = $this->controller();
        $body = self::validProduct();

        $responses = [
            'create' => $controller->create(),
            'store' => $controller->store(self::post('/products', $body)),
            'edit' => $controller->edit(1),
            'update' => $controller->update(self::post('/products/1', $body + ['_method' => 'PUT']), 1),
            'destroy' => $controller->destroy(1),
        ];

        foreach ($responses as $action => $response) {
            self::assertSame($status, $response->status, $action);
            self::assertSame($location, $response->headers['Location'] ?? null, $action);
        }
    }

    private static function get(string $path, array $query = []): Request
    {
        return new Request('GET', $path, $query);
    }

    /** @param array<string, mixed> $body */
    private static function post(string $path, array $body): Request
    {
        return new Request('POST', $path, body: $body);
    }

    /** @return array<string, string> */
    private static function validProduct(): array
    {
        return ['name' => 'Iced Tea', 'price' => '6.885', 'quantity_available' => '12'];
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

    private function signInAs(Role $role): void
    {
        $this->auth->login(new User(1, 'Pat', 'pat@example.com', 'hash', $role));
    }
}
