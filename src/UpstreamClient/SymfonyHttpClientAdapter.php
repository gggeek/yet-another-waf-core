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
                        $this->httpClient = new NativeHttpClient($mappedOptions);
                        break;
                }
            } else {
                $this->httpClient = HttpClient::create($mappedOptions);
            }
        } else {
            /// @todo... we should validate the existing client options and add our own on top
            //$this->httpClient = $httpClient;
            //$this->withOptions($options);
            throw new \Exception("Starting out with an existing Client is not implemented yet by the Symfony HttpClient Adapter, sorry");
        }

        $this->psr18Client = new Psr18Client($this->httpClient);
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        return $this->psr18Client->sendRequest($request);
    }

    /// @todo...
    public function withOptions(array $options): UpstreamClientInterface
    {
        $mappedOptions = $this->mapOptions($options);
        if (isset($mappedOptions[UpstreamClientInterface::OPT_TRANSPORT])) {
            throw new \Exception(UpstreamClientInterface::OPT_TRANSPORT . " is not supported by Symfony HttpClient Adapter withOptions");
        }
        $this->httpClient = $this->httpClient->withOptions($mappedOptions);
        $this->psr18Client = new Psr18Client($this->httpClient);
    }

    /// @todo is it worth moving to Symfony option resolver?
    protected function mapOptions($options)
    {
        $mappedOptions = [];
        foreach ($options as $name => $value) {
            switch ($name) {
                case UpstreamClientInterface::OPT_BINDTO:
                    if (!defined('CURLOPT_UNIX_SOCKET_PATH')) {
                        throw new \Exception("Client option: '$name' requires availability of the Curl php extension");
                    }
                    $mappedOptions['bindto'] = $value;
                    break;
                case UpstreamClientInterface::OPT_TRANSPORT:
                    if (!in_array($value, ['curl', 'native'])) {
                        throw new \Exception("Client option: '$name' has invalid value '$value'");
                    }
                    $mappedOptions[UpstreamClientInterface::OPT_TRANSPORT] = $value;
                    break;
                default:
                    throw new \Exception("Unsupported client option: '$name'");
            }
        }
        return $mappedOptions;
    }

    public function getUserAgent(): string
    {
        return 'Sf' . strrchr(get_class($this->httpClient), '\\');
    }
}
