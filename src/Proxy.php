<?php
declare(strict_types=1);

namespace YAWAF\Core;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Psr18Client;
use YAWAF\Core\Filter\Bidirectional\BidirectionalFilterInterface;
use YAWAF\Core\Logger\PrivateLoggerTrait;

abstract class Proxy implements RequestHandlerInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;
    use PrivateLoggerTrait;

    protected string $upstream;
    protected ?ClientInterface $client;
    protected BidirectionalFilterInterface $filter;

    /**
     * @param BidirectionalFilterInterface $filter
     * @param string $upstream
     * @param ClientInterface|SocketClientInterface|null $httpClient
     * @param LoggerInterface|null $logger
     * @throws \Exception
     */
    public function __construct(BidirectionalFilterInterface $filter, string $upstream, ClientInterface|SocketClientInterface|null $httpClient = null, LoggerInterface|null $logger = null)
    {
        // set first the logger
        $this->logger = $logger;
        $this->client = $httpClient;
        $this->filter = $filter;
        $this->setUpstream($upstream);
    }

    /**
     * @throws \Exception
     * @todo use more specific exceptions
     * @todo add support for 'http:' upstreams
     */
    protected function setUpstream(string $upstream): void
    {
        $upstream = trim($upstream);
        if ($upstream === '') {
            throw new \Exception('Empty upstream passed in');
        }
        if (str_starts_with($upstream, '/')) {
            $this->upstream = $upstream;
            if (!$this->client) {
                $this->client = new Psr18Client(HttpClient::create(['bindto' => $upstream]));
            } else {
                if (!$this->client instanceof SocketClientInterface)
                {
                    throw new \Exception('The passed in HTTP Client does not support socket upstreams');
                }
                $this->client->bindTo($upstream);
            }
            $this->info("Proxying '$upstream' socket upstream");
            return;
        }
        /// @todo... make it possible to configure (enable/disable) support for tcp://, http://, https://
        if (str_starts_with($upstream, 'tcp://')) {
            $this->upstream = $upstream;
            if (!$this->client) {
                $this->client = new Psr18Client(HttpClient::create());
            }
            $this->info("Proxying '$upstream' tcp upstream");
            return;
        }
        throw new \Exception('Upstream not supported. Only unix sockets (paths starting with "/") and tcp sockets (urls starting with "tcp://") are');
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->debug("Received request: " . $this->request2Log($request));

        $filteredRequest = $this->filter->filterRequest($request);
        if (!$filteredRequest) {
            // Q: should we pass in $request or $filteredRequest?
            return $this->deniedResponse($request);
        }
        if ($filteredRequest instanceof ResponseInterface) {
            return $filteredRequest;
        }
        $response = $this->forward($filteredRequest);
        return $this->filter->filterResponse($response, $request);
    }

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    protected function forward(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $client = $this->client;
            // avoid dns resolution, in case the http request we get uses a hostname
/// @todo... we are only doing this for unix-socket upstreams, but we should probably do this as well for tcp/http ones
            if (str_starts_with($this->upstream, '/') && method_exists($this->client, 'withOptions')) {
                $host = $request->getHeaderLine('Host');
                /// @todo... match also IPV6 addresses (with optional port too!), see https://www.ietf.org/rfc/rfc2732.txt
                if (!preg_match('/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}(?::[0-9]{1,5})?$/', $host)) {
                    $host = explode(':', $host, 2);
                    $host = $host[0];
                    $client = $this->client->withOptions([
                        'resolve' => [$host => '127.0.0.1']
                    ]);
                }
            }
            $response = $client->sendRequest($request);
            $this->debug("Upstream returned HTTP/" . $response->getProtocolVersion() . ' ' . $response->getStatusCode() . ' ' .
                $response->getReasonPhrase());
            return $response;
        } catch(ClientExceptionInterface $e) {
            return $this->errorResponse($request, $e);
        }
    }

    /**
     * Generates an "access denied" response.
     * Make sure to mimic what the upstream API returns by default for not-accepted requests - but give a specific hint
     * so that these responses can be told apart from the upstream's "access denied" ones.
     * @todo make it easy to set this response via configuration
     */
    abstract protected function deniedResponse(ServerRequestInterface $request): ResponseInterface;

    /**
     * Generates an "error happened" response.
     * Make sure to mimic correctly what the upstream API returns by default for failed requests - but give a specific hint
     * so that these responses can be told apart from the upstream's "error happened" ones.
     * @todo make it easy to set this response via configuration
     */
    abstract protected function errorResponse(ServerRequestInterface $request, \Exception|null $e = null): ResponseInterface;

    // *** Logging ***

    protected function request2Log(ServerRequestInterface $request): string
    {
        return $request->getMethod() . ' ' . $request->getUri();
    }
}
