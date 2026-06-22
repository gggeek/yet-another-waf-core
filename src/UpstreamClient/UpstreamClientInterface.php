<?php
declare(strict_types=1);

namespace YAWAF\Core\UpstreamClient;

use Psr\Http\Client\ClientInterface;

interface UpstreamClientInterface extends ClientInterface
{
    // Used to send requests to a unix socket
    const OPT_BINDTO = 'bindto';
    // Used to specify a preferred transport when the client allow using different ones, eg. 'curl' or 'native'.
    // Note that not all transports do support all possible proxy configurations, eg. connecting to a unix socket upstream requires curl
    const OPT_TRANSPORT = 'transport';

    /**
     * @throws \Exception
     */
    public function withOptions(array $options): UpstreamClientInterface;
}
