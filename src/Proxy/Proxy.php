<?php
declare(strict_types=1);

namespace YAWAF\Core\Proxy;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use YAWAF\Core\Logger\PrivateLoggerTrait;
use YAWAF\Core\UpstreamClient\UpstreamClientFactory;
use YAWAF\Core\UpstreamClient\UpstreamClientInterface;

class Proxy implements RequestHandlerInterface, LoggerAwareInterface
{
    // Used to force the client to enable/disable accepting encoded (compressed) responses.
    // List of valid values: https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/Accept-Encoding
    const OPT_FORCE_ACCEPT_ENCODING = 'force_accept_encoding';

    use LoggerAwareTrait;
    use PrivateLoggerTrait;

    protected UpstreamClientInterface $client;
    protected array $overrideHeaders = [];
    protected array $overriddenHeaders = [];

    /**
     * @todo fold the $logger arg into the options?
     * @todo what about unifying the arrays of options for $this and for the $httpClient?
     * @throws \Exception
     */
    public function __construct(array $options = [], UpstreamClientInterface|array|null $httpClient = null, LoggerInterface|null $logger = null)
    {
        // set first the logger
        $this->logger = $logger;
        if ($httpClient === null || is_array($httpClient)) {
            $httpClient = (new UpstreamClientFactory())->createClient((array)$httpClient);
        }
        $this->client = $httpClient;
        $this->overrideHeaders['User-Agent'] = 'YAWAF Proxy HttpClient' . (
            ($cua = $this->$this->client->getUserAgent()) !== '' ? ' (' . $cua . ')' : ''
        );
        $this->setOptions($options);
    }

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     * @throws ClientExceptionInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $request = $this->filterRequest($request, $this->client);

/// @todo... we should follow the rules set out in https://httpwg.org/specs/rfc9112.html#rfc.section.3.2.2: use the
///          host/port from the absolute form of the uri to replace the value from Host header

        $response = $this->client->sendRequest($request);
        $this->debug("Upstream returned HTTP/" . $response->getProtocolVersion() . ' ' . $response->getStatusCode() . ' ' .
            $response->getReasonPhrase());

        return $this->filterResponse($response, $request);
    }

    /**
     * @throws \Exception
     */
    protected function setOptions(array $options): void
    {
        foreach ($options as $name => $value) {
            switch ($name) {
                case self::OPT_FORCE_ACCEPT_ENCODING:
                    $this->overrideHeaders['Accept-Encoding'] = $value;
                    break;
                default:
                    throw new \Exception("unsupported option: '$name'");
            }
        }
    }

    protected function filterRequest(ServerRequestInterface $request): ServerRequestInterface
    {
/// @todo... add x-forwarded headers and co., strip/massage hop-by-hop headers (use a dedicated function)

        foreach ($this->overrideHeaders as $name => $value) {
            if ($request->hasHeader($name)) {
                $this->overriddenHeaders[$name] = $request->getHeader($name);
            }
            $request = $request->withHeader($name, $value);
        }

        return $request;
    }

    protected function filterResponse(ResponseInterface $response, ServerRequestInterface $request): ResponseInterface
    {
        if (array_key_exists('Accept-Encoding', $this->overriddenHeaders) && $response->hasHeader('Content-Encoding')) {
/// @todo... recode the response body using a compression which was part of the accepted ones
///          NB: the content could have been multiple-encoded, such as `deflate, gzip`
        }

        return $response;
    }
}
