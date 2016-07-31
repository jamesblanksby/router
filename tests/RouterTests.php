<?php

require_once __DIR__ . '/../vendor/autoload.php';

class RouterTest extends PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    public function testInit()
    {
        $this->assertInstanceOf('\Blanksby\Router\Router', new \Blanksby\Router\Router);
    }

    public function testStaticRoute()
    {
        $r = new \Blanksby\Router\Router;
        $r->match('GET', '/index', function() {
            echo 'index';
        });

        ob_start();
        $_SERVER['REQUEST_URI'] = '/index';
        $r->run();
        $this->assertEquals('index', ob_get_contents());

        ob_end_clean();
    }

    public function testStaticRouteUsingShorthand()
    {
        $r = new \Blanksby\Router\Router;
        $r->get('/index', function() {
            echo 'index';
        });

        ob_start();
        $_SERVER['REQUEST_URI'] = '/index';
        $r->run();
        $this->assertEquals('index', ob_get_contents());

        ob_end_clean();
    }

    public function testDynamicRoute()
    {
        $r = new \Blanksby\Router\Router;
        $r->get('/category/[a]', function($name) {
            echo 'Category: ' . $name;
        });

        ob_start();
        $_SERVER['REQUEST_URI'] = '/category/bmx';
        $r->run();
        $this->assertEquals('Category: bmx', ob_get_contents());

        ob_end_clean();
    }

    public function testDynamicRouteWithMultipleParams()
    {
        $r = new \Blanksby\Router\Router;
        $r->get('/category/[a]/page/[i]', function($name, $page) {
            echo 'Category: ' . $name . ' | Page: ' . $page;
        });

        ob_start();
        $_SERVER['REQUEST_URI'] = '/category/bmx/page/1';
        $r->run();
        $this->assertEquals('Category: bmx | Page: 1', ob_get_contents());

        ob_end_clean();
    }

    public function testDynamicRouteWithOptionalSubPatterns()
    {
        $r = new \Blanksby\Router\Router;
        $r->get('/category(/[a])?', function ($name = null) {
            echo 'Category: ' . ($name ? $name : 'all');
        });

        ob_start();
        $_SERVER['REQUEST_URI'] = '/category';
        $r->run();
        $this->assertEquals('Category: all', ob_get_contents());

        ob_clean();
        $_SERVER['REQUEST_URI'] = '/category/bmx';
        $r->run();
        $this->assertEquals('Category: bmx', ob_get_contents());

        ob_end_clean();
    }

    public function testBeforeRouteMiddlware()
    {
        $r = new \Blanksby\Router\Router;
        $r->before('GET', '/', function () {
            echo 'before ';
        });
        $r->get('/', function () {
            echo 'index';
        });
        $r->get('/category', function () {
            echo 'category';
        });

        ob_start();
        $_SERVER['REQUEST_URI'] = '/';
        $r->run();
        $this->assertContains('before', ob_get_contents());

        ob_clean();
        $_SERVER['REQUEST_URI'] = '/category';
        $r->run();
        $this->assertNotContains('before', ob_get_contents());

        ob_end_clean();
    }

    public function testRoutingToController()
    {
        $r = new \Blanksby\Router\Router;
        $r->get('/product/[*]', 'TestProductController@show');

        ob_start();
        $_SERVER['REQUEST_URI'] = '/product/0001';
        $r->run();
        $this->assertEquals('0001', ob_get_contents());

        ob_end_clean();
    }

    /**
     * @runInSeparateProcess
     */
    public function testDefault404()
    {
        $r = new \Blanksby\Router\Router;
        $r->get('/', function () {
            echo 'index';
        });

        ob_start();
        $_SERVER['REQUEST_URI'] = '/category';
        $r->run();
        $this->assertEquals('', ob_get_contents());

        ob_end_clean();
    }

    public function test404()
    {
        $r = new \Blanksby\Router\Router;
        $r->get('/', function () {
            echo 'index';
        });

        $r->set404(function() {
            echo 'not found';
        });

        ob_start();
        $_SERVER['REQUEST_URI'] = '/category';
        $r->run();
        $this->assertEquals('not found', ob_get_contents());

        ob_end_clean();
    }
}


class TestProductController
{
    public function show($id) {
        echo $id;
    }
}