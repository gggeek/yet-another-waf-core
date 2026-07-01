<?php
declare(strict_types=1);

namespace YAWAF\Core\Tests;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

abstract class ProxyTestCase extends ServerTestCase
{
    /**
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     * @throws \Exception
     */
    protected function request(array $requestOptions, string $method = 'GET', string $path = '', array $testOptions = []): ResponseInterface
    {
        $client = $this->getProxyClient([], $testOptions);
        return $client->request($method, static::getServerBaseUri() . (trim($path) === '' ? static::getServerPath() : $path), $requestOptions);
    }

    /**
     * Creates an http client with the given options, making its requests go _through_ the proxy
     * @throws \Exception
     * @todo check and add if needed support for tests iterating over http features, such as req/resp compression, charsets, etc...
     */
    protected function getProxyClient(array $clientOptions = [], array $testOptions = []): HttpClientInterface
    {
        $clientOptions = [
            'proxy' => static::getProxyBaseUri(),
        ] + $clientOptions;
        if (@$testOptions['proxy_scheme'] === 'unix') {
            $clientOptions['bindto'] = $_ENV['PROXY_SOCKET'];
        }
        if (@$testOptions['upstream_client_type'] !== null) {
            $clientOptions['headers'] = ['X-YAWAF-Upstream-Client-Type' => $testOptions['upstream_client_type']] + ($clientOptions['headers'] ?? []);
        }
        if (@$testOptions['server_scheme'] !== null) {
            $clientOptions['headers'] = ['X-YAWAF-Upstream-Scheme' => $testOptions['server_scheme']] + ($clientOptions['headers'] ?? []);
            unset($testOptions['server_scheme']);
        }

        return $this->getTestClient($clientOptions, $testOptions);
    }

    /**
     * Creates an http client with the given options, adding to its requests the cookies and custom http headers used by
     * the test proxy page to drive its operations. Useful basically to test direct access to the proxy page.
     * @throws \Exception
     */
    protected function getTestClient(array $clientOptions = [], array $testOptions = []): HttpClientInterface
    {
        $clientOptions['headers'] = [
            'X-YAWAF-Server-Type' => $_ENV['SERVER_TYPE'],
            'X-YAWAF-Log-File' => $this->testId . '.log',
            'X-YAWAF-Trace-File' => $this->testId . '.trace',
        ] + ($clientOptions['headers'] ?? []);

        return parent::getTestClient($clientOptions, $testOptions);
    }

    /**
     * @throws \Exception
     */
    protected static function getProxyBaseUri(string $scheme = 'http'): string
    {
        switch ($scheme) {
            case 'http':
            case 'https':
                return static::buildUrl([
                    'scheme' => $scheme,
                    'host' => $_ENV['PROXY_HOST'],
                    'port' => $_ENV['PROXY_PORT'],
                ]);
            //case 'unix':
            //    return 'unix:' . $_ENV['PROXY_SOCKET'];
            default:
                throw new \Exception("Unsupported proxy scheme: $scheme");
        }
    }

    /**
     * Only to be used for accessing the proxy endpoint directly
     */
    protected static function getProxyPath(): string
    {
        return $_ENV['PROXY_PATH'];
    }

    protected static function getSupportedProxySchemes(): array
    {
        $schemes = [];
        if (isset($_ENV['PROXY_HOST']) && trim($_ENV['PROXY_HOST']) !== '') {
            $schemes[] = 'http';
        }
        if (isset($_ENV['PROXY_SOCKET']) && trim($_ENV['PROXY_SOCKET']) !== '') {
            $schemes[] = 'unix';
        }
        return $schemes;
    }

    /**
     * NB: we _presume_ that the proxy used to run the tests has installed php-curl, sf-http-client and guzzle
     * @return string[]
     */
    protected static function getSupportedProxyClientTypes(): array
    {
        return ['sfhc_native', 'sfhc_curl', 'guzzle'];
    }

    protected function getTestDetails(ResponseInterface $response): string
    {
        $out = '';
        if (@$_ENV['VERBOSITY'] >= 1) {
            $out .= $this->getProxyRequestTrace();
            if ($out != '') {
                $out = "\nRequest received by the proxy (and possibly response generated):\n$out";
            } else {
                $out = (string)$out;
            }
            $log = $this->getProxyTestLog();
            if ($log != '') {
                $out .= "\nServer log:\n$log";
            }
            $out .= "\nResponse received by the test code:\n" . $this->response2Log($response) . "\n";

            /// @todo... also check the error-log file of the webserver under test (if known) - if its modification date is "now",
            ///          it most likely means that there were server-side php errors or warnings
        }

        return $out;
    }

    protected function getProxyRequestTrace(): string|null|false
    {
        $serverSideTraceFile = sys_get_temp_dir() . '/' . $this->testId . '.trace';
        if (file_exists($serverSideTraceFile)) {
            return file_get_contents($serverSideTraceFile);
        }
        return null;
    }

    protected function getProxyTestLog(): string|null|false
    {
        $serverSideLogFile = sys_get_temp_dir() . '/' . $this->testId . '.log';

        if (file_exists($serverSideLogFile)) {
            return file_get_contents($serverSideLogFile);
        }
        return null;
    }
}
