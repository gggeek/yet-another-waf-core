<?php

namespace YAWAF\Core\Proxy;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Psr18Client;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use YAWAF\Core\Logger\PrivateLoggerTrait;
use YAWAF\Core\SocketClientInterface;

/// @todo rename to Proxy?
class UpstreamConnector implements RequestHandlerInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;
    use PrivateLoggerTrait;

    protected array $upstream;
    protected ?ClientInterface $client;

    /**
     * @throws \Exception
     */
    public function __construct(string $upstream, ClientInterface|SocketClientInterface|null $httpClient = null, LoggerInterface|null $logger = null)
    {
        // set first the logger
        $this->logger = $logger;
        $this->client = $this->setUpstream($upstream, $httpClient);
    }

    /**
     * @throws \Exception
     * @todo use more specific exceptions
     * @todo add support for 'http:' upstreams
     */
    protected function setUpstream(string $upstream, ClientInterface|SocketClientInterface|null $httpClient = null): ClientInterface
    {
        $upstream = trim($upstream);
        if ($upstream === '') {
            throw new \Exception('Empty upstream passed in');
        }
        if (!preg_match('#^(/|unix:/|tcp://|https?://)#', $upstream, $matches)) {
            throw new \Exception('Upstream not supported. Only unix sockets (paths starting with "/"), tcp sockets (urls starting with "tcp://") and http urls are');
        }
        if ($matches[1] === 'http://' || $matches[1] === 'https://') {
            $this->upstream = parse_url($upstream);
            if (!isset($this->upstream['port'])) {
                if ($this->upstream['scheme'] === 'https') {
                    $this->upstream['port'] = 443;
                } else {
                    $this->upstream['port'] = 80;
                }
            }
            if (!$httpClient) {
                $httpClient = new Psr18Client(HttpClient::create());
            }
            $this->info("Proxying http upstream '$upstream'");
        }
        else if ($matches[1] == '/' || $matches[1] === 'unix:/') {
            $this->upstream = parse_url($upstream);
            // in case we were given a plain fs path
            $this->upstream['scheme'] = 'unix';
            // 'port' is not parsed for unix urls - colons get in the path
            if (str_contains($this->upstream['path'], ':')) {
                throw new \Exception('Upstream not supported: can not have port for unix sockets');
            }
            if (!$httpClient) {
                $httpClient = new Psr18Client(HttpClient::create(['bindto' => $upstream]));
            } else {
                if (!$httpClient instanceof SocketClientInterface)
                {
                    throw new \Exception('The passed in HTTP Client does not support socket upstreams');
                }
                $httpClient->bindTo($upstream);
            }
            $this->info("Proxying unix socket upstream '$upstream'");
        }
        else if ($matches[1] === 'tcp://') {
            $this->upstream = parse_url($upstream);
            if (!isset($this->upstream['port'])) {
                throw new \Exception('Upstream not supported. Missing port');
            }
            if (!$httpClient) {
                $httpClient = new Psr18Client(HttpClient::create());
            }
            $this->info("Proxying tcp upstream '$upstream'");
        }

        if ($this->upstream['scheme'] === 'unix' || $this->upstream['scheme'] === 'tcp') {
            if (isset($this->upstream['user']) || isset($this->upstream['pass']) || isset($this->upstream['query']) ||
                (isset($this->upstream['fragment']))) {
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
        //try {
        $client = $this->client;

/// @todo should we disallow usage of anything else than HttpClientInterface for $this->client?
///       The standard ClientInterface is too limited for our needs, and we could drop SocketClientInterface...

        // avoid dns resolution, in case the http request we get uses a hostname
/// @todo... we are only doing this for unix-socket upstreams, but we should probably do this as well for tcp/http ones
        if (/*$this->upstream['scheme'] === 'unix') &&*/ method_exists($client, 'withOptions')) {
            $host = $request->getHeaderLine('Host');
/// @todo... match also IPV6 addresses (with optional port too!), see https://www.ietf.org/rfc/rfc2732.txt
            if (!preg_match('/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}(?::[0-9]{1,5})?$/', $host)) {
                $host = explode(':', $host, 2);
                $host = $host[0];
                $client = $client->withOptions([
                    'resolve' => [$host => '127.0.0.1'],
                ]);
            }
        }

        $request = $request->withHeader('User-Agent', $this->getUserAgent($client, $request));

        if ($this->upstream['scheme'] === 'http' || $this->upstream['scheme'] === 'https') {
            // fix the scheme, host, port and path
            $uri = $request->getUri();
            $absoluteUri = $uri
                ->withScheme($this->upstream['scheme'])
                ->withHost($this->upstream['host'])
                ->withPort($this->upstream['port']);
            if (isset($this->upstream['user'])) {
                $absoluteUri = $absoluteUri->withUserInfo($this->upstream['user'], @$this->upstream['pass']);
            }

/// @todo... what if both $this->upstream and $uri have a path? Prefix one to the other!
            $request = $request->withUri($absoluteUri);
        }

        $response = $client->sendRequest($request);
        $this->debug("Upstream returned HTTP/" . $response->getProtocolVersion() . ' ' . $response->getStatusCode() . ' ' .
            $response->getReasonPhrase());
        return $response;
        //} catch(ClientExceptionInterface $e) {
        //    return $this->errorResponse($request, $e);
        //}
    }

    protected function getUserAgent(ClientInterface|HttpClientInterface $client, ServerRequestInterface $request): string
    {
/// @todo extract the current user-agent from the client (use get_class), wrap it into our own user-agent. Also, add version nr.
        return "YAWAF Proxy HttpClient";
    }
}
