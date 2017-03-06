<?php declare(strict_types=1);

namespace ApiClients\Foundation\Transport;

use ApiClients\Foundation\Middleware\Locator\Locator;
use Clue\React\Buzz\Browser;
use Clue\React\Buzz\Io\Sender;
use Interop\Container\ContainerInterface;
use InvalidArgumentException;
use React\Dns\Resolver\Factory as ResolverFactory;
use React\Dns\Resolver\Resolver;
use React\EventLoop\LoopInterface;
use React\HttpClient\Client as HttpClient;
use React\HttpClient\Factory as HttpClientFactory;

class Factory
{
    /**
     * @param ContainerInterface $container
     * @param LoopInterface $loop
     * @param array $options
     * @return Client
     */
    public static function create(
        ContainerInterface $container,
        LoopInterface $loop,
        array $options = []
    ): Client {
        if (!isset($options[Options::DNS])) {
            $options[Options::DNS] = '8.8.8.8';
        }

        $resolver = (new ResolverFactory())->createCached($options[Options::DNS], $loop);
        $httpClient = (new HttpClientFactory())->create($loop, $resolver);

        return self::createFromReactHttpClient(
            $container,
            $httpClient,
            $resolver,
            $loop,
            self::determineUserAgent($container, $options)
        );
    }

    /**
     * @param ContainerInterface $container
     * @param HttpClient $httpClient
     * @param Resolver $resolver
     * @param LoopInterface $loop
     * @param array $options
     * @return Client
     */
    public static function createFromReactHttpClient(
        ContainerInterface $container,
        HttpClient $httpClient,
        Resolver $resolver,
        LoopInterface $loop,
        array $options = []
    ): Client {
        return self::createFromBuzz(
            $container,
            $loop,
            (new Browser($loop, Sender::createFromLoopDns($loop, $resolver)))->withOptions([
                'streaming' => true,
            ]),
            self::determineUserAgent($container, $options)
        );
    }

    /**
     * @param ContainerInterface $container
     * @param LoopInterface $loop
     * @param Browser $buzz
     * @param array $options
     * @return Client
     */
    public static function createFromBuzz(
        ContainerInterface $container,
        LoopInterface $loop,
        Browser $buzz,
        array $options = []
    ): Client {
        return new Client(
            $loop,
            $container->get(Locator::class),
            $buzz,
            self::determineUserAgent($container, $options)
        );
    }

    private static function determineUserAgent(ContainerInterface $container, array $options) : array
    {
        if (!isset($options[Options::USER_AGENT]) && !isset($options[Options::USER_AGENT_STRATEGY])) {
            throw new InvalidArgumentException('No way to determine user agent');
        }

        if (!isset($options[Options::USER_AGENT_STRATEGY])) {
            return $options;
        }

        $strategy = $options[Options::USER_AGENT_STRATEGY];

        if (!class_exists($strategy)) {
            throw new InvalidArgumentException(sprintf('Strategy "%s", doesn\'t exist', $strategy));
        }

        if (!is_subclass_of($strategy, UserAgentStrategyInterface::class)) {
            throw new InvalidArgumentException(sprintf(
                'Strategy "%s", doesn\'t implement "%s"',
                $strategy,
                UserAgentStrategyInterface::class
            ));
        }

        $options[Options::USER_AGENT] = $container->get($strategy)->determineUserAgent($options);

        return $options;
    }
}
