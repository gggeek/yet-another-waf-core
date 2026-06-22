<?php
declare(strict_types=1);

namespace YAWAF\Core\Proxy;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use YAWAF\Core\UpstreamClient\UpstreamClientFactory;
use YAWAF\Core\UpstreamClient\UpstreamClientInterface;

class FixedUpstreamProxy extends Proxy
{
    protected array $upstream;

    /**
     * @throws \Exception
     */
    public function __construct(string $upstream, UpstreamClientInterface|null $httpClient = null, LoggerInterface|null $logger = null)
    {
        // set first the logger
        $this->logger = $logger;
        $this->client = $this->setUpstream($upstream, $httpClient);
    }

    /**
     * @throws \Exception
     * @todo use more specific exceptions
     */
    protected function setUpstream(string $upstream, UpstreamClientInterface|null $httpClient = null): UpstreamClientInterface
    {
        $upstream = trim($upstream);
        if ($upstream === '') {
            throw new \Exception('Empty upstream passed in');
        }
        if (!preg_match('#^(/|unix:/|tcp://|https?://)#', $upstream, $matches)) {
            throw new \Exception('Upstream not supported. Only unix sockets (paths starting with "/"), tcp sockets (urls starting with "tcp://") and http urls are');
        }
        switch($matches[1]) {
            case 'http://':
            case 'https://':
                $this->upstream = parse_url($upstream);
                if (!isset($this->upstream['port'])) {
                    if ($this->upstream['scheme'] === 'https') {
                        $this->upstream['port'] = 443;
                    } else {
                        $this->upstream['port'] = 80;
                    }
                }
                if (!$httpClient) {
                    $httpClient = (new UpstreamClientFactory())->createClient();
                }
                $this->info("Proxying http upstream '$upstream'");
                break;

            case 'tcp://':
                $this->upstream = parse_url($upstream);
                if (!isset($this->upstream['port'])) {
                    throw new \Exception('Upstream not supported. Missing port');
                }
                if (!$httpClient) {
                    $httpClient = (new UpstreamClientFactory())->createClient();
                }
                $this->info("Proxying tcp upstream '$upstream'");
                break;

            case '/':
            case 'unix':
                $this->upstream = parse_url($upstream);
                // in case we were given a plain fs path
                $this->upstream['scheme'] = 'unix';
                // 'port' is not parsed for unix urls - colons get in the path
                if (str_contains($this->upstream['path'], ':')) {
                    throw new \Exception('Upstream not supported: can not have port for unix sockets');
                }
                if (!$httpClient) {
                    $httpClient = (new UpstreamClientFactory())->createClient([UpstreamClientInterface::OPT_BINDTO => $upstream]);
                } else {
                    $httpClient = $httpClient->withOptions([UpstreamClientInterface::OPT_BINDTO => $upstream]);
                }
                $this->info("Proxying unix socket upstream '$upstream'");
                break;

            default:
                throw new \Exception("Unsupported upstream scheme: '{$this->upstream['scheme']}'");
        }

        if ($this->upstream['scheme'] === 'unix' || $this->upstream['scheme'] === 'tcp') {
            if (isset($this->upstream['user']) || isset($this->upstream['pass']) || isset($this->upstream['query']) ||
                (isset($this->upstream['fragment']))) {
                /// @todo review: is this actually needed? Could we proxy those infos to a tcp / socket?
                throw new \Exception("The upstream '$upstream' is not valid: either of user/pass/query/fragment is not supported for scheme '{$this->upstream['scheme']}'");
            }
        }

        return $httpClient;
    }

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     * @throws ClientExceptionInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $client = $this->client;

        $request = $this->withProxyHeaders($request, $client);

/// @todo... add x-forwarded headers and co.

        switch($this->upstream['scheme']) {
            case 'http':
            case 'https':
                // fix the scheme, host, port and path
                $uri = $request->getUri();
/// @todo... when acting as an open proxy, ie. one which is not bound to a single upstream, we should follow the rules
///          set out in https://httpwg.org/specs/rfc9112.html#rfc.section.3.2.2: use the host/port from the absolute
///          form of the uri to replace the value from Host header
                $absoluteUri = $uri
                    ->withScheme($this->upstream['scheme'])
                    ->withHost($this->upstream['host'])
                    ->withPort($this->upstream['port']);
                if (isset($this->upstream['user'])) {
                    $absoluteUri = $absoluteUri->withUserInfo($this->upstream['user'], @$this->upstream['pass']);
                }

/// @todo... what if both $this->upstream and $uri have a path? Prefix one to the other!
                $request = $request->withUri($absoluteUri);
                break;

            case 'tcp':
/// @todo... test the "rewriting" requests for this case (is it needed here or can/should it be done in setUpstream?)
                // fix the scheme, host, port and path
                $uri = $request->getUri();
                $absoluteUri = $uri
                    ->withHost($this->upstream['host'])
                    ->withPort($this->upstream['port']);
                //if (isset($this->upstream['user'])) {
                //    $absoluteUri = $absoluteUri->withUserInfo($this->upstream['user'], @$this->upstream['pass']);
                //}

/// @todo... what if both $this->upstream and $uri have a path? Prefix one to the other!
                $request = $request->withUri($absoluteUri);
                break;

            case 'unix':
                // In case the http request we get uses a hostname, avoid dns resolution so that the request goes to localhost
/// @todo... fix: what if this method does not exist?
                if (method_exists($client, 'withOptions')) {
                    $host = $request->getHeaderLine('Host');
/// @todo... match also IPV6 addresses (with optional port too!), see https://www.ietf.org/rfc/rfc2732.txt
                    if (!preg_match('/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}(?::[0-9]{1,5})?$/', $host)) {
                        $host = explode(':', $host, 2);
                        $host = $host[0];
                        /// @todo avoid doing this if $host is 'localhost'
/// @todo... what if $host is an IP but _not_ localhost?
                        $client = $client->withOptions([
                            'resolve' => [$host => '127.0.0.1'],
                        ]);
                    }
                }

                break;

            default:
                throw new \Exception("Unsupported upstream scheme: '{$this->upstream['scheme']}'");
        }

        $response = $client->sendRequest($request);
        $this->debug("Upstream returned HTTP/" . $response->getProtocolVersion() . ' ' . $response->getStatusCode() . ' ' .
            $response->getReasonPhrase());
        return $response;
    }
}
