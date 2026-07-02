<?php
declare(strict_types=1);

namespace YAWAF\Core\UpstreamClient;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpClient\CurlHttpClient;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\NativeHttpClient;
use Symfony\Component\HttpClient\Psr18Client;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class SymfonyHttpClientAdapter implements UpstreamClientInterface
{
    protected HttpClientInterface $httpClient;
    protected Psr18Client $psr18Client;

    /**
     * @throws \Exception
     */
    public function __construct(array $options = [], HttpClientInterface|null $httpClient = null)
    {
        if ($httpClient === null) {
            $mappedOptions = $this->mapOptions($options);
            if (isset($mappedOptions[UpstreamClientInterface::OPT_TRANSPORT])) {
                $transport = $mappedOptions[UpstreamClientInterface::OPT_TRANSPORT];
                unset($mappedOptions[UpstreamClientInterface::OPT_TRANSPORT]);
                switch ($transport) {
                    case 'curl':
                        $this->httpClient = new CurlHttpClient($mappedOptions);
                        break;
                    case 'native':
                        if (isset($mappedOptions['bindto'])) {
                            throw new \Exception("Client option: 'bindto' requires usage of the Curl HttpClient");
                        }
                        $this->httpClient = new NativeHttpClient($mappedOptions);
                        break;
                }
            } else {
                $this->httpClient = HttpClient::create($mappedOptions);
            }
        } else {
            /// @todo... we should validate the existing client options and add our own on top
            $mappedOptions = $this->mapOptions($options);
            if ($mappedOptions) {
                throw new \Exception("Starting out with an existing Client is not implemented yet by the Symfony HttpClient Adapter, sorry");
            }
            $this->httpClient = $httpClient;
        }

        $this->psr18Client = new Psr18Client($this->httpClient);
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        return $this->psr18Client->sendRequest($request);
    }

    public function withOptions(array $options): UpstreamClientInterface
    {
        $mappedOptions = $this->mapOptions($options);
        if (isset($mappedOptions[UpstreamClientInterface::OPT_TRANSPORT])) {
            throw new \Exception(UpstreamClientInterface::OPT_TRANSPORT . " is not supported by Symfony HttpClient Adapter withOptions");
        }
        if (isset($mappedOptions['bindto']) && $this->httpClient instanceof NativeHttpClient) {
            throw new \Exception("Client option: 'bindto' requires usage of the Curl HttpClient");
        }
        $clone = clone($this);
        $clone->httpClient = $this->httpClient->withOptions($mappedOptions);
        $clone->psr18Client = new Psr18Client($clone->httpClient);
        return $clone;
    }

    /**
     * @see Symfony\Contracts\HttpClient\HttpClientInterface
     * @todo is it worth moving to Symfony option resolver?
     */
    protected function mapOptions(array $options): array
    {
        $mappedOptions = [];
        foreach ($options as $name => $value) {
            switch ($name) {
                case UpstreamClientInterface::OPT_BINDTO:
                    $mappedOptions['bindto'] = $value;
                    break;
                case UpstreamClientInterface::OPT_RESOLVE:
/// @todo check the implementation of the client to see if 'resolve' is supported for the native client
                    //if (!defined('CURLOPT_UNIX_SOCKET_PATH')) {
                    //    throw new \Exception("Client option: '$name' requires availability of the Curl php extension");
                    //}
                    $mappedOptions['resolve'] = $value;
                    break;
                case UpstreamClientInterface::OPT_CONNECT_TIMEOUT:
/// @todo... this is only used in 8.1.0 and up. throw if sfhc version is lower
                    $mappedOptions['max_connect_duration'] = $value;
                    break;
                case UpstreamClientInterface::OPT_TIMEOUT:
                    $mappedOptions['max_duration'] = $value;
                    break;
                case UpstreamClientInterface::OPT_TRANSPORT:
                    if (!in_array($value, ['curl', 'native', 'default'])) {
                        throw new \Exception("Client option: '$name' has invalid value '$value'");
                    }
                    if ($value !== 'default') {
                        $mappedOptions[UpstreamClientInterface::OPT_TRANSPORT] = $value;
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
        return 'Symfony/' . substr(strrchr(get_class($this->httpClient), '\\'), 1);
    }
}
