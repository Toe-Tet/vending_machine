<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Container;
use App\Core\Request;
use App\Core\Response;
use App\Core\Route;
use App\Core\Router;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router(new Container());
        $this->router->registerController(WidgetsController::class);
    }

    public function test_dispatches_to_the_controller_method_declared_by_its_route_attribute(): void
    {
        $response = $this->router->dispatch(new Request('GET', '/widgets'));

        self::assertSame(200, $response->status);
        self::assertSame('index', $response->body);
    }

    public function test_passes_path_parameters_to_the_action_as_typed_arguments(): void
    {
        $response = $this->router->dispatch(new Request('GET', '/widgets/42'));

        self::assertSame('show int 42', $response->body);
    }

    public function test_passes_the_request_to_actions_that_ask_for_it(): void
    {
        $response = $this->router->dispatch(new Request('GET', '/widgets/7/echo', ['q' => 'hi']));

        self::assertSame('7:hi', $response->body);
    }

    public function test_a_post_form_can_override_its_method_with_the_method_field(): void
    {
        $response = $this->router->dispatch(new Request('POST', '/widgets/3', body: ['_method' => 'delete']));

        self::assertSame('destroyed 3', $response->body);
    }

    public function test_method_field_is_ignored_on_non_post_requests(): void
    {
        $response = $this->router->dispatch(new Request('GET', '/widgets/3', query: ['_method' => 'DELETE']));

        self::assertSame('show int 3', $response->body);
    }

    public function test_unknown_path_returns_404(): void
    {
        self::assertSame(404, $this->router->dispatch(new Request('GET', '/nope'))->status);
    }

    public function test_non_numeric_id_for_an_int_parameter_returns_404(): void
    {
        self::assertSame(404, $this->router->dispatch(new Request('GET', '/widgets/abc'))->status);
    }

    public function test_known_path_with_unsupported_method_returns_405_listing_allowed_methods(): void
    {
        $response = $this->router->dispatch(new Request('PATCH', '/widgets/3'));

        self::assertSame(405, $response->status);
        self::assertSame('GET, DELETE', $response->headers['Allow']);
    }
}

final class WidgetsController
{
    #[Route('GET', '/widgets')]
    public function index(): Response
    {
        return new Response('index');
    }

    #[Route('GET', '/widgets/{id}')]
    public function show(int $id): Response
    {
        return new Response('show ' . get_debug_type($id) . ' ' . $id);
    }

    #[Route('DELETE', '/widgets/{id}')]
    public function destroy(int $id): Response
    {
        return new Response('destroyed ' . $id);
    }

    #[Route('GET', '/widgets/{id}/echo')]
    public function echo(Request $request, int $id): Response
    {
        return new Response($id . ':' . $request->query('q'));
    }
}
