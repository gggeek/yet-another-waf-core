<?php
declare(strict_types=1);

namespace YAWAF\Core;

use Psr\Http\Client\ClientInterface;

interface SocketClientInterface extends ClientInterface
{
    /**
     * @param string $socket
     * @return void
     * @throws \Exception
     */
    public function bindTo(string $socket): void;
}
