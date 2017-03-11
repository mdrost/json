<?php declare(strict_types=1);

namespace ApiClients\Tests\Foundation\Transport\CommandBus\Handler;

use ApiClients\Foundation\Transport\ClientInterface;
use ApiClients\Foundation\Transport\CommandBus\Command\StreamingRequestCommand;
use ApiClients\Foundation\Transport\CommandBus\Handler\StreamingRequestHandler;
use ApiClients\Foundation\Transport\Service\StreamingRequestService;
use ApiClients\Foundation\Transport\StreamingResponse;
use ApiClients\Tools\TestUtilities\TestCase;
use Prophecy\Argument;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use React\EventLoop\Factory;
use React\Promise\FulfilledPromise;
use RingCentral\Psr7\Request;
use function Clue\React\Block\await;

class StreamingRequestHandlerTest extends TestCase
{
    public function testHandler()
    {
        $path = '/foo/bar.json';
        $request = new Request('GET', $path);
        $response = $this->prophesize(ResponseInterface::class)->reveal();
        $client = $this->prophesize(ClientInterface::class);
        $promise = new FulfilledPromise($response);
        $client->request(Argument::that(function (RequestInterface $request) use ($path) {
            return $request->getUri()->getPath() === $path;
        }), [])->willReturn($promise);
        $command = new StreamingRequestCommand($request);
        $handler = new StreamingRequestHandler(new StreamingRequestService($client->reveal()));
        $result = await($handler->handle($command), Factory::create());
        self::assertInstanceOf(StreamingResponse::class, $result);
        self::assertSame($response, $result->getResponse());
    }
}
