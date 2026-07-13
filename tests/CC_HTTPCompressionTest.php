<?php
declare(strict_types=1);

namespace YAWAF\Core\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use YAWAF\Core\Filter\Bidirectional\BodyCompressorTrait;

class CC_HTTPCompressionTest extends ProxyTestCase
{
    use BodyCompressorTrait;

    #[DataProvider('passingCompressionRulesDataProvider')]
    public function testPassingCompressionRules(string $configFileName, string|null $clientType = null, string $proxyScheme = 'http',
        string|null $upstreamClientType = null, string $serverScheme = 'http', string $clientAcceptEncoding = '', string $proxyAcceptEncoding = '')
    {
        $response = $this->request(
            [
                'headers' => [
                        'X-YAWAF-Config-File' => $configFileName,
                        'X-YAWAF-Force-Accept-Encoding' => $proxyAcceptEncoding
                    ]
            ],
            'GET',
            static::getServerPath(),
            ['client_type' => $clientType, 'upstream_client_type' => $upstreamClientType, 'proxy_scheme' => $proxyScheme, 'server_scheme' => $serverScheme]
        );
        try {
            $failureMessage = $this->getTestDetails($response);
            $this->assertEquals(200, $response->getStatusCode(), $failureMessage);
            $this->assertEquals(TestServer::DEFAULT_RESPONSE['result'], $response->toArray(false)['result'], $failureMessage);
            // NB: for this to work, the target webserver has to be set up to serve gzip-compressed responses
            if ($proxyAcceptEncoding === 'gzip') {
                $this->assertEquals('gzip', $response->getHeaders()['content-encoding'][0], $failureMessage);
            }
        } catch (ExceptionInterface $e) {
            $this->assertEquals(200, null, 'Exception thrown by the test client while communicating to the proxy: ' . $e->getMessage());
        }
    }

    public static function passingCompressionRulesDataProvider(): array
    {
        $out = [];
        foreach (self::getRuleBasedTestDataProviderOptions('compression', 'passing') as $args) {
            foreach (self::getClientAllowedCompressionSchemes() as $clientAllowedCompressionScheme) {
                foreach (self::getProxyAllowedCompressionSchemes() as $proxyAllowedCompressionScheme) {
                    $out[] = array_merge($args, [$clientAllowedCompressionScheme, $proxyAllowedCompressionScheme]);
                }
            }
        }
        return $out;
    }

    #[DataProvider('failingCompressionRulesDataProvider')]
    public function testFailingCompressionRules(string $configFileName, string|null $clientType = null, string $proxyScheme = 'http',
        string|null $upstreamClientType = null, string $serverScheme = 'http', string $clientAcceptEncoding = '', string $proxyAcceptEncoding = '')
    {
        $response = $this->request(
            [
                'headers' => [
                        'X-YAWAF-Config-File' => $configFileName,
                        'X-YAWAF-Force-Accept-Encoding' => $proxyAcceptEncoding
                    ]
            ],
            'GET',
            static::getServerPath(),
            ['client_type' => $clientType, 'upstream_client_type' => $upstreamClientType, 'proxy_scheme' => $proxyScheme, 'server_scheme' => $serverScheme]
        );
        try {
            $failureMessage = $this->getTestDetails($response);
            $this->assertEquals(TestProxy::ACCESS_DENIED_STATUS_CODE, $response->getStatusCode(), $failureMessage);
            $this->assertSame(TestProxy::ACCESS_DENIED_RESPONSE, $response->toArray(false), $failureMessage);
        } catch (ExceptionInterface $e) {
            $this->assertSame(TestProxy::ACCESS_DENIED_RESPONSE, null, 'Exception thrown by the test client while communicating to the proxy: ' . $e->getMessage());
        }
    }

    public static function failingCompressionRulesDataProvider(): array
    {
        $out = [];
        foreach (self::getRuleBasedTestDataProviderOptions('compression', 'failing') as $args) {
            foreach (self::getClientAllowedCompressionSchemes() as $clientAllowedCompressionScheme) {
                foreach (self::getProxyAllowedCompressionSchemes() as $proxyAllowedCompressionScheme) {
                    $out[] = array_merge($args, [$clientAllowedCompressionScheme, $proxyAllowedCompressionScheme]);
                }
            }
        }
        return $out;
    }

    #[DataProvider('passingRequestCompressionRulesDataProvider')]
    public function testPassingRequestCompressionRules(string $configFileName, string|null $clientType = null, string $proxyScheme = 'http',
        string|null $upstreamClientType = null, string $serverScheme = 'http', string $requestEncoding = '', string $verb = 'POST')
    {
        $requestCompressionHeaders = [];
        if (! in_array($requestEncoding, ['', 'none', 'identity'])) {
            $requestCompressionHeaders = ['Content-Encoding' => $requestEncoding];
        }

        $response = $this->request(
            [
                'headers' => [
                    'X-YAWAF-Config-File' => $configFileName,
                    'X-YAWAF-Force-Accept-Encoding' => 'identity',
                    'Content-Type' => 'application/json'
                ] + $requestCompressionHeaders,
                'body' => $this->getRequestBody($requestEncoding)
            ],
            $verb,
            static::getServerPath(),
            ['client_type' => $clientType, 'upstream_client_type' => $upstreamClientType, 'proxy_scheme' => $proxyScheme, 'server_scheme' => $serverScheme]
        );
        try {
            $failureMessage = $this->getTestDetails($response);
            $this->assertEquals(200, $response->getStatusCode(), $failureMessage);
            $data = $response->toArray(false);
            $this->assertEquals(TestServer::DEFAULT_RESPONSE['result'], $data['result'], $failureMessage);
            $this->assertEquals(['test' => 'localhost'], $data['requestBody'], $failureMessage);
        } catch (ExceptionInterface $e) {
            $this->assertEquals(200, null, 'Exception thrown by the test client while communicating to the proxy: ' . $e->getMessage());
        }
    }

    public static function passingRequestCompressionRulesDataProvider(): array
    {
        $out = [];
        foreach (self::getRuleBasedTestDataProviderOptions('request_compression', 'passing') as $args) {
            foreach (self::getAllowedRequestCompressionSchemes() as $requestCompressionScheme) {
                foreach (self::getAllowedRequestVerbs() as $requestVerb) {
                    $out[] = array_merge($args, [$requestCompressionScheme, $requestVerb]);
                }
            }
        }
        return $out;
    }

    #[DataProvider('failingRequestCompressionRulesDataProvider')]
    public function testFailingRequestCompressionRules(string $configFileName, string|null $clientType = null, string $proxyScheme = 'http',
        string|null $upstreamClientType = null, string $serverScheme = 'http', string $requestEncoding = '', string $verb = 'POST')
    {
        $requestCompressionHeaders = [];
        if (! in_array($requestEncoding, ['', 'none', 'identity'])) {
            $requestCompressionHeaders = ['Content-Encoding' => $requestEncoding];
        }
        $response = $this->request(
            [
                'headers' => [
                    'X-YAWAF-Config-File' => $configFileName,
                    'X-YAWAF-Force-Accept-Encoding' => 'identity',
                    'content-type' => 'application/json'
                ] + $requestCompressionHeaders,
                'body' => $this->getRequestBody($requestEncoding)
            ],
            $verb,
            static::getServerPath(),
            ['client_type' => $clientType, 'upstream_client_type' => $upstreamClientType, 'proxy_scheme' => $proxyScheme, 'server_scheme' => $serverScheme]
        );
        try {
            $failureMessage = $this->getTestDetails($response);
            $this->assertEquals(TestProxy::ACCESS_DENIED_STATUS_CODE, $response->getStatusCode(), $failureMessage);
            $this->assertSame(TestProxy::ACCESS_DENIED_RESPONSE, $response->toArray(false), $failureMessage);
        } catch (ExceptionInterface $e) {
            $this->assertSame(TestProxy::ACCESS_DENIED_RESPONSE, null, 'Exception thrown by the test client while communicating to the proxy: ' . $e->getMessage());
        }
    }

    public static function failingRequestCompressionRulesDataProvider(): array
    {
        $out = [];
        foreach (self::getRuleBasedTestDataProviderOptions('request_compression', 'failing') as $args) {
            foreach (self::getAllowedRequestCompressionSchemes() as $requestCompressionScheme) {
                foreach (self::getAllowedRequestVerbs() as $requestVerb) {
                    $out[] = array_merge($args, [$requestCompressionScheme, $requestVerb]);
                }
            }
        }
        return $out;
    }

    protected function getRequestBody(string $requestCompressionScheme): string
    {
        $out = json_encode(['test' => 'localhost']);

        switch ($requestCompressionScheme) {
            case 'deflate':
            case 'gzip':
                $out = $this->compressPayload($out, [$requestCompressionScheme], $actualScheme);
                $this->assertEquals($requestCompressionScheme, $actualScheme, "Failed to compress the request to desired scheme '$requestCompressionScheme'");
                break;
            case '':
            case 'identity':
            case 'none':
                break;
            default:
                throw new \InvalidArgumentException("Unsupported request compression scheme: '$requestCompressionScheme'");
        }

        return $out;
    }

    /**
     * @return string[] '' for "do not mess with defaults", 'none' for "please remove accept-encodings headers"
     * @todo... test also compression schemes with weights
     */
    protected static function getProxyAllowedCompressionSchemes(): array
    {
        // @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/Accept-Encoding
        // @see https://www.iana.org/assignments/http-parameters/http-parameters.xhtml
        // We might as well drop 'deflate', as that is not supported by Apache, because of flaky support by browsers
        // and most likely also neither by Nginx nor FrankenPHP (see https://zlib.net/zlib_faq.html#faq39)
        /// @todo add br, dcb, dcz (brotli), zstd if the relevant php extensions are available (ideally check both
        ///       proxy-side for decoding it, and upstream-server-side for serving it (or do the server-side encoding in php))
        return ['', 'none', 'identity', '*', 'compress', 'gzip', 'deflate'];

    }

    /**
     * @return string[] '' for "do not mess with defaults", 'none' for "please remove accept-encodings headers"
     * @todo... test also compression schemes with weights
     */
    protected static function getClientAllowedCompressionSchemes(): array
    {
/// @todo...
return [''];

        // @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/Accept-Encoding
        // @see https://www.iana.org/assignments/http-parameters/http-parameters.xhtml
        // We might as well drop 'deflate', as that is not supported by Apache, because of flaky support by browsers
        // and most likely also neither by Nginx nor FrankenPHP (see https://zlib.net/zlib_faq.html#faq39)
        /// @todo add br, dcb, dcz (brotli), zstd if the relevant php extensions are available (ideally check both
        ///       client-side and proxy-side)
        return ['', 'none', 'identity', '*', 'compress', 'gzip', 'deflate'];
    }

    protected static function getAllowedRequestCompressionSchemes(): array
    {
        /// @todo... add tests for 'compress' support, brotli, zstd
        return ['', 'identity', 'gzip', 'deflate'];
    }

    protected static function getAllowedRequestVerbs(): array
    {
        return ['POST', 'PUT'];
    }
}
