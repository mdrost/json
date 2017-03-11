<?php declare(strict_types=1);

namespace ApiClients\Tests\Foundation\Transport\CommandBus\Command;

use ApiClients\Foundation\Transport\CommandBus\Command\JsonEncodeCommand;
use ApiClients\Tools\TestUtilities\TestCase;

class JsonEncodeCommandTest extends TestCase
{
    public function testCommand()
    {
        $json = [];
        $command = new JsonEncodeCommand($json);
        self::assertSame($json, $command->getJson());
    }
}
