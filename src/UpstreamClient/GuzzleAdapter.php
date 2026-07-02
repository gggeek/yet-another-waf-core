<?php
declare(strict_types=1);

namespace YAWAF\Core\UpstreamClient;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class GuzzleAdapter implements UpstreamClientInterface
{
    protected ClientInterface $guzzleClient;
    protected array $forcedHeaders = [];

    /**
     * @throws \Exception
     */
    public function __construct(array $options = [], ClientInterface|null $guzzleClient = null) {
        if ($guzzleClient === null) {
            $this->guzzleClient = new Client($this->mapOptions($options));
        } else {
            /// @todo... we should validate the existing client options plus add our own on top
            //$this->guzzleClient = $httpClient;
            //$this->withOptions($options);
            throw new \Exception("Starting out with an existing Client is not implemented  yet by the Guzzle Adapter, sorry");
        }
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $request = $request->withHeader('User-Agent', $this->getUserAgent());
        foreach ($this->forcedHeaders as $header => $value) {
            $request = $request->withHeader($header, $value);
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
                case UpstreamClientInterface::OPT_ACCEPT_ENCODING:
                    $this->forcedHeaders['Accept-Encoding'] = $value;
                    break;
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
                    $mappedOptions['handler'] = function ($request, $options) use ($value) {
                        /// @todo implement this if not too hard...
                        throw new \Exception("Client option: '" . UpstreamClientInterface::OPT_TRANSPORT . "' is not implemented  yet by the Guzzle Adapter, sorry");
                    };
                    break;
                default:
                    throw new \Exception("Unsupported client option: '$name'");
            }
        }
        return $mappedOptions;
    }

    protected function getUserAgent(): string
    {
        return 'YAWAF Proxy HttpClient (GuzzleHttp/Client)';
    }
}
