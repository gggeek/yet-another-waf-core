<?php
declare(strict_types=1);

namespace YAWAF\Core\UpstreamClient;

use Psr\Http\Client\ClientInterface;

interface UpstreamClientInterface extends ClientInterface
{
    // Used to force the client to enable/disable accepting encoded (compressed) responses.
    // List of valid values: https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/Accept-Encoding
    const OPT_ACCEPT_ENCODING = 'accept_encoding';
    // Used to send requests to a unix socket
    const OPT_BINDTO = 'bindto';
    // Used to force dns resolution of a host to an IP. Array of hostname => IP mappings
    const OPT_RESOLVE = 'resolve';
    // Used to specify a preferred transport when the client allow using different ones, eg. 'curl' or 'native'.
    // Note that not all transports do support all possible proxy configurations, eg. connecting to a unix socket upstream requires curl
    const OPT_TRANSPORT = 'transport';

    const OPT_TIMEOUT = 'timeout';
    const OPT_CONNECT_TIMEOUT = 'connect_timeout';

    /**
     * @throws \Exception
     */
    public function withOptions(array $options): UpstreamClientInterface;

//    public function getUserAgent(): string;
}
