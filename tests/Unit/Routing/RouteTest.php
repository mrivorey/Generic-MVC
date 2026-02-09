<?php

namespace Tests\Unit\Routing;

use App\Routing\Route;
use Tests\TestCase;

class RouteTest extends TestCase
{
    public function testMatchesExactUri(): void
    {
        $route = new Route(['GET'], '/users', ['Controller', 'index']);
        $params = $route->matches('GET', '/users');

        $this->assertIsArray($params);
        $this->assertEmpty($params);
    }

    public function testMatchesParameterizedUri(): void
    {
        $route = new Route(['GET'], '/users/{id}', ['Controller', 'show']);
        $params = $route->matches('GET', '/users/42');

        $this->assertSame(['id' => '42'], $params);
    }

    public function testMatchesMultipleParameters(): void
    {
        $route = new Route(['GET'], '/posts/{post}/comments/{comment}', ['Controller', 'show']);
        $params = $route->matches('GET', '/posts/5/comments/10');

        $this->assertSame(['post' => '5', 'comment' => '10'], $params);
    }

    public function testReturnsNullForWrongMethod(): void
    {
        $route = new Route(['POST'], '/users', ['Controller', 'store']);

        $this->assertNull($route->matches('GET', '/users'));
    }

    public function testReturnsNullForNonMatchingUri(): void
    {
        $route = new Route(['GET'], '/users', ['Controller', 'index']);

        $this->assertNull($route->matches('GET', '/posts'));
    }

    public function testWhereConstraint(): void
    {
        $route = new Route(['GET'], '/users/{id}', ['Controller', 'show']);
        $route->where('id', '[0-9]+');

        $this->assertIsArray($route->matches('GET', '/users/42'));
        $this->assertNull($route->matches('GET', '/users/abc'));
    }

    public function testNameAndGetName(): void
    {
        $route = new Route(['GET'], '/home', ['Controller', 'index']);
        $result = $route->name('home');

        $this->assertSame($route, $result); // fluent
        $this->assertSame('home', $route->getName());
    }

    public function testMiddlewareAndGetMiddleware(): void
    {
        $route = new Route(['GET'], '/admin', ['Controller', 'index']);
        $route->middleware('auth');
        $route->middleware(['csrf', 'role:admin']);

        $this->assertSame(['auth', 'csrf', 'role:admin'], $route->getMiddleware());
    }

    public function testSetPrefix(): void
    {
        $route = new Route(['GET'], '/users', ['Controller', 'index']);
        $route->setPrefix('/admin');

        $this->assertSame('/admin/users', $route->getUri());
    }

    public function testSetPrefixNormalizesSlashes(): void
    {
        $route = new Route(['GET'], '/users', ['Controller', 'index']);
        $route->setPrefix('/admin/');

        $this->assertSame('/admin/users', $route->getUri());
    }

    public function testAddMiddlewarePrepends(): void
    {
        $route = new Route(['GET'], '/dashboard', ['Controller', 'index']);
        $route->middleware('csrf');
        $route->addMiddleware(['auth']);

        // addMiddleware prepends, so auth should come before csrf
        $this->assertSame(['auth', 'csrf'], $route->getMiddleware());
    }

    public function testGetUri(): void
    {
        $route = new Route(['GET'], '/test', ['Controller', 'index']);
        $this->assertSame('/test', $route->getUri());
    }

    public function testGetMethods(): void
    {
        $route = new Route(['GET', 'POST'], '/test', ['Controller', 'index']);
        $this->assertSame(['GET', 'POST'], $route->getMethods());
    }

    public function testGetAction(): void
    {
        $route = new Route(['GET'], '/test', ['MyController', 'myAction']);
        $this->assertSame(['MyController', 'myAction'], $route->getAction());
    }

    public function testGetNameReturnsNullByDefault(): void
    {
        $route = new Route(['GET'], '/test', ['Controller', 'index']);
        $this->assertNull($route->getName());
    }
}
