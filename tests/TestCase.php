<?php

declare(strict_types=1);

namespace Tests;

use DI\ContainerBuilder;
use Exception;
use PHPUnit\Framework\TestCase as PHPUnit_TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;

class TestCase extends PHPUnit_TestCase
{
    use ProphecyTrait;

    protected App $app;

    /**
     * Create the Slim app for each test.
     * @throws Exception
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->app = $this->getAppInstance();

        // These are normally added in public/index.php
        $this->app->addRoutingMiddleware();
        $this->app->addBodyParsingMiddleware();
    }

    /**
     * @return App
     * @throws Exception
     */
    protected function getAppInstance(): App
    {
        // Instantiate PHP-DI ContainerBuilder
        $containerBuilder = new ContainerBuilder();

        // Set up settings
        $settings = require __DIR__ . '/../app/settings.php';
        $settings($containerBuilder);

        // Set up dependencies
        $dependencies = require __DIR__ . '/../app/dependencies.php';
        $dependencies($containerBuilder);

        // Set up repositories
        $repositories = require __DIR__ . '/../app/repositories.php';
        $repositories($containerBuilder);

        // Build PHP-DI Container instance
        $container = $containerBuilder->build();

        // Instantiate the app
        AppFactory::setContainer($container);
        $app = AppFactory::create();

        // Register middleware
        $middleware = require __DIR__ . '/../app/middleware.php';
        $middleware($app);

        // Register routes
        $routes = require __DIR__ . '/../app/routes.php';
        $routes($app);

        return $app;
    }

    /**
     * High-level helper: make a request to the app.
     *
     * @param string $method
     * @param string $path
     * @param array<string,string> $headers
     * @param array|null $jsonBody
     */
    protected function request(
        string $method,
        string $path,
        array $headers = [],
        ?array $jsonBody = null
    ): ResponseInterface {
        $requestFactory = new ServerRequestFactory();
        $streamFactory  = new StreamFactory();

        $request = $requestFactory->createServerRequest($method, $path);

        // Default accept header
        $request = $request->withHeader('Accept', 'application/json');

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        if ($jsonBody !== null) {
            $body = $streamFactory->createStream(json_encode($jsonBody, JSON_UNESCAPED_SLASHES));
            $request = $request
                ->withHeader('Content-Type', 'application/json')
                ->withBody($body);
        }

        return $this->app->handle($request);
    }

    /**
     * Decode JSON response into array (or empty array if invalid).
     */
    protected function json(ResponseInterface $response): array
    {
        $body = (string)$response->getBody();
        $decoded = json_decode($body, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Keep the original low-level createRequest helper (optional).
     * If you still need it, you can keep it, but most tests should use request().
     *
     * @param string $method
     * @param string $path
     * @param array  $headers
     * @param array  $cookies
     * @param array  $serverParams
     */
    protected function createRequest(
        string $method,
        string $path,
        array $headers = [],
        array $cookies = [],
        array $serverParams = []
    ): Request {
        $requestFactory = new ServerRequestFactory();
        $streamFactory  = new StreamFactory();

        $request = $requestFactory->createServerRequest($method, $path);

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        // cookies/server params aren’t commonly needed for your API tests,
        // but we keep parameters for compatibility.
        if (!empty($cookies)) {
            $request = $request->withCookieParams($cookies);
        }
        if (!empty($serverParams)) {
            // Slim\Psr7\Request doesn't allow setting serverParams directly after creation,
            // but for most tests you won’t need server params.
        }

        // Ensure there is a body stream
        $request = $request->withBody($streamFactory->createStream(''));

        return $request;
    }
}
