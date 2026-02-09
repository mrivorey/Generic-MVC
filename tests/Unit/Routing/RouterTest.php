<?php

namespace Tests\Unit\Routing;

use App\Routing\Router;
use App\Routing\Route;
use Tests\TestCase;

class RouterTest extends TestCase
{
    public function testGetRegistersRoute(): void
    {
        $route = Router::get('/home', ['HomeController', 'index']);

        $this->assertInstanceOf(Route::class, $route);
        $routes = Router::getRoutes();
        $this->assertCount(1, $routes);
        $this->assertSame(['GET'], $routes[0]->getMethods());
    }

    public function testPostRegistersRoute(): void
    {
        Router::post('/login', ['AuthController', 'login']);

        $routes = Router::getRoutes();
        $this->assertSame(['POST'], $routes[0]->getMethods());
    }

    public function testPutRegistersRoute(): void
    {
        Router::put('/users/{id}', ['UserController', 'update']);

        $routes = Router::getRoutes();
        $this->assertSame(['PUT'], $routes[0]->getMethods());
    }

    public function testPatchRegistersRoute(): void
    {
        Router::patch('/users/{id}', ['UserController', 'patch']);

        $routes = Router::getRoutes();
        $this->assertSame(['PATCH'], $routes[0]->getMethods());
    }

    public function testDeleteRegistersRoute(): void
    {
        Router::delete('/users/{id}', ['UserController', 'destroy']);

        $routes = Router::getRoutes();
        $this->assertSame(['DELETE'], $routes[0]->getMethods());
    }

    public function testMatchRegistersMultiMethodRoute(): void
    {
        Router::match(['GET', 'POST'], '/form', ['FormController', 'handle']);

        $routes = Router::getRoutes();
        $this->assertSame(['GET', 'POST'], $routes[0]->getMethods());
    }

    public function testNamedRouteUrl(): void
    {
        Router::get('/dashboard', ['DashController', 'index'])->name('dashboard');

        $this->assertSame('/dashboard', Router::url('dashboard'));
    }

    public function testUrlWithParams(): void
    {
        Router::get('/users/{id}/edit', ['UserController', 'edit'])->name('users.edit');

        $this->assertSame('/users/42/edit', Router::url('users.edit', ['id' => 42]));
    }

    public function testUrlThrowsForUndefinedName(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Route [nonexistent] not defined');

        Router::url('nonexistent');
    }

    public function testGroupPrefix(): void
    {
        Router::group(['prefix' => '/admin'], function () {
            Router::get('/users', ['AdminController', 'users']);
        });

        $routes = Router::getRoutes();
        $this->assertSame('/admin/users', $routes[0]->getUri());
    }

    public function testGroupMiddleware(): void
    {
        Router::group(['middleware' => ['auth']], function () {
            Router::get('/profile', ['ProfileController', 'index']);
        });

        $routes = Router::getRoutes();
        $this->assertContains('auth', $routes[0]->getMiddleware());
    }

    public function testNestedGroups(): void
    {
        Router::group(['prefix' => '/admin', 'middleware' => ['auth']], function () {
            Router::group(['prefix' => '/api', 'middleware' => ['api_auth']], function () {
                Router::get('/users', ['ApiController', 'users']);
            });
        });

        $routes = Router::getRoutes();
        $this->assertSame('/api/admin/users', $routes[0]->getUri());
        $middleware = $routes[0]->getMiddleware();
        $this->assertContains('auth', $middleware);
        $this->assertContains('api_auth', $middleware);
    }

    public function testResourceGenerates7Routes(): void
    {
        Router::resource('/posts', 'PostController');

        $routes = Router::getRoutes();
        $this->assertCount(7, $routes);

        // Check named routes exist
        $this->assertSame('/posts', Router::url('posts.index'));
        $this->assertSame('/posts/create', Router::url('posts.create'));
        $this->assertSame('/posts/1', Router::url('posts.show', ['id' => 1]));
        $this->assertSame('/posts/1/edit', Router::url('posts.edit', ['id' => 1]));
    }

    public function testResetClearsState(): void
    {
        Router::get('/test', ['Controller', 'index'])->name('test');
        Router::reset();

        $this->assertEmpty(Router::getRoutes());
        $this->expectException(\RuntimeException::class);
        Router::url('test');
    }

    public function testDispatchMatchesCorrectRoute(): void
    {
        // Register a simple route that returns a string
        Router::get('/hello', [TestableController::class, 'hello']);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/hello';

        $result = Router::dispatch();
        $this->assertSame('Hello World', $result);
    }

    public function testDispatchReturns404ForUnmatched(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/nonexistent';

        $result = Router::dispatch();
        $this->assertStringContainsString('404', $result);
    }

    public function testDispatchHandlesMethodSpoofing(): void
    {
        Router::put('/items/{id}', [TestableController::class, 'update']);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/items/5';
        $_POST['_method'] = 'PUT';

        $result = Router::dispatch();
        $this->assertSame('Updated 5', $result);
    }
}

// Minimal controller for dispatch tests — no base class dependency
class TestableController
{
    public function hello(): string
    {
        return 'Hello World';
    }

    public function update(string $id): string
    {
        return 'Updated ' . $id;
    }
}
