<?php

namespace KPT\Tests;

use KPT\Router;
use PHPUnit\Framework\TestCase;

class SimpleRouterTest extends TestCase
{
    public function testRouterCreation()
    {
        $router = new Router();
        $this->assertInstanceOf(Router::class, $router);
    }

    public function testRouterWithPaths()
    {
        $router = new Router('/api', '/tmp');
        $this->assertInstanceOf(Router::class, $router);
    }

    public function testSanitizePath()
    {
        $this->assertEquals('/', Router::sanitizePath(''));
        $this->assertEquals('/test', Router::sanitizePath('/test/'));
        $this->assertEquals('/test/path', Router::sanitizePath('//test///path//'));
    }

    public function testGetUserIp()
    {
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $ip = Router::getUserIp();
        $this->assertIsString($ip);
    }

    public function testRouteRegistration()
    {
        $router = new Router();
        $router->get('/test', function () {
            return 'test';
        });

        $routes = $router->getRoutes();
        $this->assertContains('/test', $routes['GET']);
    }
}
