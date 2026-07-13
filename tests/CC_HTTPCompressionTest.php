<?php
declare(strict_types=1);

namespace YAWAF\Core\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;

class CC_HTTPCompressionTest extends ProxyTestCase
{
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
        } catch (ExceptionInterface $e) {
            $this->assertEquals(200, null, 'Exception thrown by the test client while communicating to the proxy: ' . $e->getMessage());
        }
    }

    public static function passingCompressionRulesDataProvider(): array
    {
        $out = [];
        foreach (self::getRuleBasedTestDataProviderOptions('compression', 'passing') as $args) {
            foreach (self::getAllowedClientCompressionSchemes() as $clientCompressionScheme) {
                foreach (self::getAllowedProxyCompressionSchemes() as $proxyCompressionScheme) {
                    $out[] = array_merge($args, [$clientCompressionScheme, $proxyCompressionScheme]);
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
            foreach (self::getAllowedClientCompressionSchemes() as $clientCompressionScheme) {
                foreach (self::getAllowedProxyCompressionSchemes() as $proxyCompressionScheme) {
                    $out[] = array_merge($args, [$clientCompressionScheme, $proxyCompressionScheme]);
                }
            }
        }
        return $out;
    }

    /**
     * @return string[] '' for "do not mess with defaults", 'none' for "please remove accept-encodings headers"
     * @todo... test also compression schemes with weights
     */
    protected static function getAllowedProxyCompressionSchemes(): array
    {
        // @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/Accept-Encoding
        // @see https://www.iana.org/assignments/http-parameters/http-parameters.xhtml
        // We might as well drop 'deflate', as that is not supported by Apache, because of flaky support by browsers
        // and most likely also neither by Nginx nor FrankenPHP (see https://zlib.net/zlib_faq.html#faq39)
        /// @todo add br, dcb, dcz (brotli), zstd if the relevant php extensions are available (ideally check proxy-side)
        return ['', 'none', 'identity', '*', 'compress', 'gzip', 'deflate'];

    }

    /**
     * @return string[] '' for "do not mess with defaults", 'none' for "please remove accept-encodings headers"
     * @todo... test also compression schemes with weights
     */
    protected static function getAllowedClientCompressionSchemes(): array
    {
/// @todo...
return [''];

        // @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/Accept-Encoding
        // @see https://www.iana.org/assignments/http-parameters/http-parameters.xhtml
        // We might as well drop 'deflate', as that is not supported by Apache, because of flaky support by browsers
        // and most likely also neither by Nginx nor FrankenPHP (see https://zlib.net/zlib_faq.html#faq39)
        /// @todo add br, dcb, dcz (brotli), zstd if the relevant php extensions are available
        return ['', 'none', 'identity', '*', 'compress', 'gzip', 'deflate'];
    }
}
