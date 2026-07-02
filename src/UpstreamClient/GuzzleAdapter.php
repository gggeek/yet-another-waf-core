<?php
declare(strict_types=1);

namespace YAWAF\Core\UpstreamClient;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\Handler\StreamHandler;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class GuzzleAdapter implements UpstreamClientInterface
{
    protected ClientInterface $guzzleClient;

    /**
     * @throws \Exception
     */
    public function __construct(array $options = [], ClientInterface|null $guzzleClient = null) {
        if ($guzzleClient === null) {
/// @todo... when there is no 'handler' specified in the options, the Client constructor goes overkill: it gives back
///          a client with a full stack of handlers, which we do not need (two of those get neutered in any case inside
///          the `sendRequest` method, and the 'cookies' middleware we never initialize), and a series of 2-3 handlers
///          proxying each other (curl-multi/curl-exec/stream).
///          We would probably get a faster client by removing the middlewares, and we should check if the proxied handlers
///          bring any value...
            $this->guzzleClient = new Client($this->mapOptions($options));
        } else {
            /// @todo... we should validate the existing client options plus add our own on top
            $mappedOptions = $this->mapOptions($options);
            if ($mappedOptions) {
                throw new \Exception("Starting out with an existing Client is not implemented  yet by the Guzzle Adapter, sorry");
            }
            $this->guzzleClient = $guzzleClient;
        }
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        if ($request->getMethod() === 'HEAD') {
            // reimplement inline `sendRequest`, with extra options for curl, to avoid hangs. Fixes guzzle issue #3728
            /// @todo we could avoid doing this when the handler within $this->guzzleClient is the stream one - but it might
            ///       be hard telling that apart...
/// @todo... this probably just leaves curl waiting until it times out - even if it does not report a failure any more.
///          It helped fix a timeout issue when running tests against frankenphp, but it does not fix them for apache/nginx
            $options = [];
            $options[RequestOptions::SYNCHRONOUS] = true;
            $options[RequestOptions::ALLOW_REDIRECTS] = false;
            $options[RequestOptions::HTTP_ERRORS] = false;
            $options['curl'] = [CURLOPT_IGNORE_CONTENT_LENGTH => 1];
            return $this->guzzleClient->sendAsync($request, $options)->wait();
        }
        return $this->guzzleClient->sendRequest($request);
    }

    /// @todo...
    public function withOptions(array $options): UpstreamClientInterface
    {
        throw new \Exception("withOptions is not implemented yet by the Guzzle Adapter, sorry");
    }

    /**
     * @see \GuzzleHttp\RequestOptions
     * @todo is it worth moving to Symfony option resolver?
     */
    protected function mapOptions(array $options): array
    {
        $mappedOptions = [];
        foreach ($options as $name => $value) {
            switch ($name) {
                case UpstreamClientInterface::OPT_BINDTO:
                    if (!defined('CURLOPT_UNIX_SOCKET_PATH')) {
                        throw new \Exception("Client option: '$name' requires availability of the Curl php extension");
                    }
                    $mappedOptions['curl'] = [CURLOPT_UNIX_SOCKET_PATH => $value] + ($mappedOptions['curl'] ?? []);
                    break;
                case UpstreamClientInterface::OPT_CONNECT_TIMEOUT:
                    $mappedOptions[RequestOptions::CONNECT_TIMEOUT] = $value;
                    break;
                case UpstreamClientInterface::OPT_TIMEOUT:
                    $mappedOptions[RequestOptions::TIMEOUT] = $value;
                    break;
                case UpstreamClientInterface::OPT_TRANSPORT:
                    switch($value) {
                        case 'native':
                            $mappedOptions['handler'] = new StreamHandler();
                            break;
                        case 'curl':
/// @todo... check (and try to match?) the default options used in creating the guzzle curl handler in HandlerStack::create
                            $mappedOptions['handler'] = new CurlHandler();
                            break;
                        case 'default':
                            break;
                        default:
                            throw new \Exception("Client option: '$name' has invalid value '$value'");
                    }
                    break;
                default:
                    throw new \Exception("Unsupported client option: '$name'");
            }
        }
        return $mappedOptions;
    }

    public function getUserAgent(): string
    {
        return 'GuzzleHttp/Client';
    }
}
