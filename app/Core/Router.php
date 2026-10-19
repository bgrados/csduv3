<?php

declare(strict_types=1);

namespace App\Core;

use Closure;

/**
 * Enrutador HTTP sencillo.
 *
 * Los parámetros dinámicos se escriben entre llaves: '/socios/{id}/editar'.
 */
final class Router
{
    /** @var array<string, list<array{pattern: string, handler: mixed, middleware: array<int, callable>}>> */
    private array $routes = [];

    /** @var callable|null */
    private $notFoundHandler = null;

    /** @var array<int, callable> */
    private array $globalMiddleware = [];

    public function get(string $path, mixed $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    public function post(string $path, mixed $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    public function put(string $path, mixed $handler, array $middleware = []): void
    {
        $this->add('PUT', $path, $handler, $middleware);
    }

    public function delete(string $path, mixed $handler, array $middleware = []): void
    {
        $this->add('DELETE', $path, $handler, $middleware);
    }

    /**
     * @param array<int, callable> $middleware
     */
    public function add(string $method, string $path, mixed $handler, array $middleware = []): void
    {
        $method = strtoupper($method);

        $this->routes[$method][] = [
            'pattern'    => $this->compile($path),
            'handler'    => $handler,
            'middleware' => $middleware,
        ];
    }

    public function notFound(callable $handler): void
    {
        $this->notFoundHandler = $handler;
    }

    /**
     * Middleware que se ejecuta antes de resolver cualquier ruta.
     *
     * @param array<int, callable> $middleware
     */
    public function middleware(array $middleware): void
    {
        $this->globalMiddleware = array_merge($this->globalMiddleware, $middleware);
    }

    public function dispatch(Request $request): void
    {
        foreach ($this->globalMiddleware as $middleware) {
            if ($middleware($request) === false) {
                return;
            }
        }

        $method = $request->method();
        $path = $request->path();

        foreach ($this->routes[$method] ?? [] as $route) {
            if (preg_match($route['pattern'], $path, $matches) !== 1) {
                continue;
            }

            $params = [];

            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $params[$key] = $value;
                }
            }

            foreach ($route['middleware'] as $middleware) {
                $result = $middleware($request);

                if ($result === false) {
                    return;
                }
            }

            $this->invoke($route['handler'], $request, $params);

            return;
        }

        $this->handleNotFound($request);
    }

    /**
     * @param array<string, string> $params
     */
    private function invoke(mixed $handler, Request $request, array $params): void
    {
        if (is_array($handler) && count($handler) === 2 && is_string($handler[0])) {
            $class = $handler[0];
            $action = (string) $handler[1];
            $controller = new $class();

            $controller->{$action}($request, ...array_values($params));

            return;
        }

        if ($handler instanceof Closure || is_callable($handler)) {
            $handler($request, ...array_values($params));

            return;
        }

        throw new \RuntimeException('Manejador de ruta no válido.');
    }

    private function handleNotFound(Request $request): void
    {
        if ($this->notFoundHandler !== null) {
            ($this->notFoundHandler)($request);

            return;
        }

        abort(404);
    }

    private function compile(string $path): string
    {
        $path = '/' . trim($path, '/');

        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        $pattern = preg_replace_callback(
            '#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#',
            static fn (array $matches): string => '(?P<' . $matches[1] . '>[^/]+)',
            $path
        );

        if (!is_string($pattern)) {
            throw new \RuntimeException('No se pudo compilar la ruta: ' . $path);
        }

        return '#^' . $pattern . '$#';
    }
}
