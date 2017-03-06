<?php declare(strict_types=1);

namespace ApiClients\Foundation\Transport;

use ApiClients\Foundation\Middleware\Locator\Locator;
use ApiClients\Foundation\Middleware\MiddlewareInterface;
use ApiClients\Foundation\Middleware\MiddlewareRunner;
use ApiClients\Foundation\Transport\CommandBus;
use Clue\React\Buzz\Browser;
use Interop\Container\ContainerInterface;
use InvalidArgumentException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;
use RingCentral\Psr7\Uri;
use function React\Promise\reject;
use function React\Promise\resolve;
use function WyriHaximus\React\futureFunctionPromise;

final class Client implements ClientInterface
{
    const DEFAULT_OPTIONS = [
        Options::SCHEMA => 'https',
        Options::PATH => '/',
        Options::HEADERS => [],
    ];

    /**
     * @var LoopInterface
     */
    protected $loop;

    /**
     * @var ContainerInterface
     */
    protected $locator;

    /**
     * @var Browser
     */
    protected $browser;

    /**
     * @var array
     */
    protected $options = [];

    /**
     * @var string[]
     */
    protected $middleware = [];

    /**
     * @param LoopInterface $loop
     * @param Locator|ContainerInterface $locator
     * @param Browser $buzz
     * @param array $options
     */
    public function __construct(
        LoopInterface $loop,
        Locator $locator,
        Browser $buzz,
        array $options = []
    ) {
        $this->loop = $loop;
        $this->locator = $locator;
        $this->browser = $buzz;
        $this->options = $options + self::DEFAULT_OPTIONS;

        if (isset($this->options[Options::MIDDLEWARE])) {
            $this->middleware = $this->options[Options::MIDDLEWARE];
        }
    }

    protected function constructMiddlewares(array $options): MiddlewareRunner
    {
        $set = $this->middleware;

        if (isset($options[Options::MIDDLEWARE])) {
            $set = $this->combinedMiddlewares($options[Options::MIDDLEWARE]);
        }

        $args = [];
        $args[] = $options;
        foreach ($set as $middleware) {
            $args[] = $this->locator->get($middleware);
        }

        return new MiddlewareRunner(...$args);
    }

    protected function combinedMiddlewares(array $extraMiddlewares): array
    {
        $set = $this->middleware;

        foreach ($extraMiddlewares as $middleware) {
            if (in_array($middleware, $set)) {
                continue;
            }

            $set[] = $middleware;
        }

        return $set;
    }

    /**
     * @param RequestInterface $request
     * @param array $options
     * @return PromiseInterface
     */
    public function request(RequestInterface $request, array $options = []): PromiseInterface
    {
        $request = $this->applyApiSettingsToRequest($request);
        $options = $this->applyRequestOptions($options);
        $executioner = $this->constructMiddlewares($options);

        return $executioner->pre($request)->then(function ($request) use ($options) {
            return resolve($this->browser->send(
                $request
            ));
        }, function (ResponseInterface $response) {
            return resolve($response);
        })->then(function (ResponseInterface $response) use ($executioner) {
            return $executioner->post($response);
        });
    }

    protected function applyApiSettingsToRequest(RequestInterface $request): RequestInterface
    {
        $uri = $request->getUri();
        if (strpos((string)$uri, '://') === false) {
            $uri = Uri::resolve(
                new Uri(
                    $this->options[Options::SCHEMA] .
                    '://' .
                    $this->options[Options::HOST] .
                    $this->options[Options::PATH]
                ),
                $request->getUri()
            );
        }

        foreach ($this->options[Options::HEADERS] as $key => $value) {
            $request = $request->withAddedHeader($key, $value);
        }

        return $request->withUri($uri)->withAddedHeader('User-Agent', $this->options[Options::USER_AGENT]);
    }

    public function applyRequestOptions(array $options): array
    {
        if (!isset($this->options[Options::DEFAULT_REQUEST_OPTIONS])) {
            return $options;
        }

        return array_merge_recursive(
            $this->options[Options::DEFAULT_REQUEST_OPTIONS],
            $options
        );
    }
}
