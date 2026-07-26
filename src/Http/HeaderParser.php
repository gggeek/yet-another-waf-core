<?php

namespace YAWAF\Core\Http;

use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use YAWAF\Core\Exception\InvalidHeaderValue;
use YAWAF\Core\Logger\PrivateLoggerTrait;
use YAWAF\Core\Stdlib;

/// @todo implement plugins/subclasses to add support for headers used in well-known protocols such as eg. webdav
class HeaderParser
{
    use LoggerAwareTrait;
    use PrivateLoggerTrait;

/// @todo... allow other productions than token, quoted-string
    const IS_SINGLETON = 1;
    const ALLOWS_QUOTED_STRINGS = 2;

    const IS_TOKEN = 4;
    const ALLOWS_TRAILING_COMMENT = 8;
    const IS_COOKIE = 16;
    const IS_DATE = 32;
    const IS_INTEGER = 64;

    /**
     * @var int[] keys should be lowercase, and values be a bitmask of the class constants
     *
     * Info taken from: https://en.wikipedia.org/wiki/List_of_HTTP_header_fields,
     * https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers
     *
     * @todo... complete list of known headers: singletons, non-csv-lists, double-quoted, dates, cookies, other formats...
     */
    protected static array $defaultKnownHeaders = [
        // req
        'a-im' => 0,
        'accept' => 0,
        'accept-charset' => 0,
        'accept-datetime' => 0, // singleton?
        'accept-encoding' => 0,
        'accept-language' => 0,
        'access-control-request-headers' => 0,
        'access-control-request-method' => 0, // singleton?
        'alt-used' => 0,
        'authorization' => 0, // singleton?
        'cache-control' => 0,
        'connection' => 0,
        'content-digest' => 0,
        'content-encoding' => 0,
        'content-length' => 0, // singleton
        'content-md5' => 0, // singleton
        'content-type' => 0, // not a csv list?
        'cookie' => self::IS_COOKIE, // not a csv list
        'date' => 0, // singleton
        'expect' => 0,
        'forwarded' => 0,
        'from' => 0, // singleton
        'host' => self::IS_SINGLETON,
        'http2-settings' => 0, // singleton?
        'if-match' => 0,
        'if-modified-since' => 0, // singleton?
        'if-none-match' => 0,
        'if-range' => 0, // singleton?
        'if-unmodified-since' => 0, // singleton?
        'keep-alive' => 0,
        'max-forwards' => 0, // singleton?
        'origin' => 0, // singleton?
        'pragma' => 0, // singleton?
        'prefer' => 0, // not a csv list?
        'priority' => 0,
        'proxy-authorization' => 0, // singleton?
        'range' => 0,
        'referer' => 0, // singleton?
        'repr-digest' => 0,
        'sec-fetch-dest' => 0,
        'sec-fetch-mode' => 0,
        'sec-fetch-site' => 0,
        'sec-fetch-storage-access' => 0,
        'sec-fetch-user' => 0,
        'sec-gpc' => 0, // non-standard?
        'sec-purpose' => 0,
        'sec-websocket-extensions' => 0,
        'sec-websocket-key' => 0,
        'sec-websocket-protocol' => 0,
        'service-worker' => 0,
        'service-worker-navigation-preload' => 0,
        'te' => 0,
        'trailer' => 0,
        'transfer-encoding' => 0,
        'user-agent' => 0, // singleton?
        'upgrade' => 0,
        'upgrade-insecure-requests' => 0, // non-standard?
        'via' => 0,
        'want-content-digest' => 0,
        'want-repr-digest' => 0,
        'x-forwarded-for' => 0,
        'x-forwarded-host' => 0,
        'x-forwarded-proto' => 0,

        // req. non-standard
        'attribution-reporting-eligible' => 0,
        'attribution-reporting-register-source' => 0,
        'attribution-reporting-register-trigger' => 0,
        'available-dictionary' => 0,
        'correlation-id' => 0,
        'device-memory' => 0,
        'dictionary-id' => 0,
        'dnt' => 0,
        'downlink' => 0,
        'dpr' => 0,
        'early-data' => 0,
        'ect' => 0,
        'front-end-https' => 0,
        'idempotency-key' => 0,
        'proxy-connection' => 0,
        'rtt' => 0,
        'save-data' => 0,
        'sec-browsing-topics' => 0,
        'sec-ch-device-memory' => 0,
        'sec-ch-dpr' => 0,
        'sec-ch-prefers-color-scheme' => 0,
        'sec-ch-prefers-reduced-motion' => 0,
        'sec-ch-prefers-reduced-transparency' => 0,
        'sec-ch-ua' => 0,
        'sec-ch-ua-arch' => 0,
        'sec-ch-ua-bitness' => 0,
        'sec-ch-ua-form-factors' => 0,
        'sec-ch-ua-full-version' => 0,
        'sec-ch-ua-full-version-list' => 0,
        'sec-ch-ua-mobile' => 0,
        'sec-ch-ua-model' => 0,
        'sec-ch-ua-platform' => 0,
        'sec-ch-ua-platform-version' => 0,
        'sec-ch-ua-wow64' => 0,
        'sec-ch-viewport-height' => 0,
        'sec-ch-viewport-width' => 0,
        'sec-ch-width' => 0,
        'sec-private-state-token' => 0,
        'sec-private-state-token-crypto-version' => 0,
        'sec-private-state-token-lifetime' => 0,
        'sec-redemption-record' => 0,
        'sec-speculation-tags' => 0,
        'warning' => 0, // deprecated
        'viewport-width' => 0,
        'width' => 0,
        'x-att-deviceid' => 0,
        'x-csrf-token' => 0,
        'x-correlation-id' => 0,
        'x-http-method-override' => 0,
        'x-request-id' => 0,
        'x-requested-with' => 0,
        'x-uidh' => 0,
        'x-wap-profile' => 0,

        // resp
        'accept-ch' => 0,
        'access-control-allow-credentials' => 0,
        'access-control-allow-headers' => 0,
        'access-control-allow-methods' => 0,
        'access-control-allow-origin' => 0,
        'access-control-expose-headers' => 0,
        'access-control-max-age' => 0,
        'accept-patch' => 0,
        'accept-post' => 0,
        'accept-ranges' => 0,
        'activate-storage-access' => 0,
        'age' => 0,
        'allow' => 0,
        'alt-svc' => 0,
        'clear-site-data' => 0,
        'content-disposition' => 0,
        'content-language' => 0,
        'content-location' => 0,
        'content-range' => 0,
        'content-security-policy' => 0, // non-standard?
        'content-security-policy-report-only' => 0,
        'cross-origin-embedder-policy' => 0,
        'cross-origin-embedder-policy-report-only' => 0,
        'cross-origin-opener-policy' => 0,
        'cross-origin-resource-policy' => 0,
        'delta-base' => 0,
        'etag' => 0,
        'expires' => 0,
        'im' => 0,
        'integrity-policy' => 0,
        'integrity-policy-report-only' => 0,
        'last-modified' => 0,
        'link' => 0,
        'location' => 0,
        'origin-agent-cluster' => 0,
        'p3p' => 0,
        'preference-applied' => 0,
        'proxy-authenticate' => 0,
        'public-key-pins' => 0,
        'referrer-policy' => 0,
        'refresh' => 0, // non-standard?
        'reporting-endpoints' => 0,
        'retry-after' => 0,
        'sec-websocket-accept' => 0,
        'sec-websocket-version' => 0,
        'server' => 0,
        'server-timing' => 0,
        'service-worker-allowed' => 0,
        'set-cookie' => 0,
        'set-login' => 0,
        'sourcemap' => 0,
        'speculation-rules' => 0,
        'strict-transport-security' => 0,
        'supports-loading-mode' => 0,
        'timing-allow-origin' => 0, // non-standard?
        'vary' => 0,
        'www-authenticate' => 0,
        'x-content-type-options' => 0, // non-standard?
        'x-frame-options' => 0,

        // Resp. non-standard
        'content-dpr' => 0,
        'critical-ch' => 0,
        'expect-ct' => 0,
        'nel' => 0,
        'no-vary-search' => 0,
        'observe-browsing-topics' => 0,
        'permissions-policy' => 0,
        'permissions-policy-report-only' => 0,
        'status' => 0,
        'report-to' => 0,
        'tk' => 0,
        'use-as-dictionary' => 0,
        'x-content-duration' => 0,
        'x-content-security-policy' => 0,
        'x-dns-prefetch-control' => 0,
        'x-permitted-cross-domain-policies' => 0,
        'x-powered-by' => 0,
        'x-redirect-by' => 0,
        'x-robots-tag' => 0,
        'x-webkit-csp' => 0,
        'x-ua-compatible' => 0,
        'x-xss-protection' => 0,
    ];

    protected array $knownHeaders;

    public function __construct(array $customHeadersSpec = [], LoggerInterface|null $logger = null)
    {
        if (! Stdlib::array_of_int($customHeadersSpec)) {
            throw new \InvalidArgumentException('customHeadersSpec argument to HeaderParser constructor must be an array of ints');
        }
        $this->knownHeaders = $customHeadersSpec + static::getDefaultKnownHeaders();

        $this->logger = $logger;
    }

    /**
     * Parses the value of a header into a list of strings, based on its known syntax. Custom headers f.e. just get
     * values split on commas and trimmed of space/tab. The format for specific headers can be set in the parser constructor.
     * NB: assumes that the header name and value(s) come from a web-server, meaning that some basic validation has
     * already happened , eg. there are no ctrl characters or \n or \r in its name (we test that this is true for all
     * supported webservers via BA_ServerRequestCreatorTest tests)
     *
     * @param string $name lowercase
     * @param string[] $values as obtained by a psr-7 message
     * @return string[]
     * @throws InvalidHeaderValue only when $onErrors == HeaderParserOnError::Throw
     */
    public function normalizeHeaderValue(string $name, array $values, HeaderParserOnError $onErrors = HeaderParserOnError::Throw): array
    {
        try {
            return $this->parseHeaderValue($values, $this->knownHeaders[$name] ?? 0, $onErrors);
        } catch (InvalidHeaderValue $e) {
            throw new InvalidHeaderValue("Error parsing header '$name': " . lcfirst($e->getMessage()));
        }
    }

    /**
     * @param string[] $values as obtained by a psr-7 message. Keys must be numerically indexed from 0
     * @param int $options
     * @param HeaderParserOnError $onErrors
     * @return string[]
     * @throws InvalidHeaderValue only when $onErrors == HeaderParserOnError::Throw
     *
     * @todo introduce a version of this that does extra validation, eg. checking for NUL, CR, LF cahrs
     */
    protected function parseHeaderValue(array $values, int $options, HeaderParserOnError $onErrors = HeaderParserOnError::Throw): array
    {
        $throwOnErrors = ($onErrors === HeaderParserOnError::Throw);

        /// @todo... allow parsing of non-csv lists, eg. for Set-Cookie
        $allowsQuotedStrings = $options & self::ALLOWS_QUOTED_STRINGS;
        $isToken = $options & self::IS_TOKEN;
        $isSingleton = $options & self::IS_SINGLETON;
        $isCookie = false;
        $isDate = false;
        //$allowsCommasInSingleValue = $isCookie || $isDate;
        //$allowsComments = $options & self::ALLOWS_TRAILING_COMMENT;

        // This check is done at the end of processing, as it makes more sense to do so
        // for singleton values, throw if count($values) > 1
        //if ($isSingleton && count($values) > 1) {
        //    if ($throwOnErrors) {
        //        throw new InvalidHeaderValue("Multiple values to parse for singleton header");
        //    } else {
        //        $this->debug("Multiple values to parse for singleton header");
        /// @todo... should we nullify the returned value(s)?
        //    }
        //}

        $out = [];
        foreach ($values as $value) {

            // @see https://www.rfc-editor.org/info/rfc9112/#section-5
            // @see https://www.rfc-editor.org/info/rfc9110/#section-5.5

            // some webservers (I am looking at you, Nginx...) do not _always apply this stripping
            $value = trim($value, " \t");

            // The following test and filter were disabled after checking that the (supported) webservers will not allow
            // such headers to reach php anyway
/// @todo... add more specific tests for this in BA_ServerRequestCreatorTest - esp. some sending these chars within a known quoted-string header

            /// @todo would a regexp be faster?
            /// @todo also throw if any CTRL chars are found
            //if ($onErrors === HeaderParserOnError::Throw && (str_contains("\n", $value) || str_contains("\r", $value) || str_contains("\x00", $value))) {
            //    throw new InvalidHeaderValue("Found invalid character: CR, LF or NUL");
            //}
            //
            // we do not reject CR, LF and NUL, but transform them to SP
            /// @todo log a message if transforming any chars
            //$value = str_replace(["\n", "\r", "\x00"], ' ', $value);

/// @todo... which one of the two approaches is better for singletons: split on commas and check how many headers we got,
///          or keep a single-value while parsing?

/*            if ($isSingleton) {

                if ($allowsQuotedStrings && ($len = strlen($value)) >= 2 && $value[0] === '"' && $value[$len-1] === '"') {
                    $out[] = $this->parseQuotedStingContents($value, $len, $onErrors);
                } else {
                    if ($allowsCommasInSingleValue) {
                        /// ...
                        if ($isCokie) {

                        } elseif ($isDate) {

                        } else {

                        }
                    } else {
                        $pieces =  $this->parseGeneric($value, $onErrors);
                    }


                    if ($isToken) {
                        /// ...
                    } else {
                        $out = array_merge($out, $pieces);
                        //$out[] = $value;
                    }
                }

            } else {
*/
                if ($allowsQuotedStrings) {
                    $pieces = $this->parseMaybeQuotedString($value, $onErrors);
                } else {
                    if ($isCookie) {
                        $pieces = $this->parseCookie($value, $onErrors);
                    } elseif ($isDate) {
                        $pieces = $this->parseDate($value, $onErrors);
                    } else {
                        // non-singleton, no quoted strings
                        $pieces = $this->parseGeneric($value, $onErrors);
                    }
                }

/// @todo... allow stripping of trailing comments

                $out = array_merge($out, $pieces);

//            } // non-singleton
        }

        if ($isToken) {
            $this->validateTokenHeader($out, $onErrors);
        }

        if ($isSingleton) {
            $this->validateSingletonHeader($out, $onErrors);
        }

        return $out;
    }

    /**
     * @param string[] $values
     * @throws InvalidHeaderValue only when $onErrors == HeaderParserOnError::Throw
     * @return string[]
     */
    protected function parseCookie(string $value, HeaderParserOnError $onErrors = HeaderParserOnError::Throw): array
    {
/// @todo...
        return [];
    }

    /**
     * @param string[] $values
     * @throws InvalidHeaderValue only when $onErrors == HeaderParserOnError::Throw
     * @return string[]
     */
    protected function parseDate(string $value, HeaderParserOnError $onErrors = HeaderParserOnError::Throw): array
    {
/// @todo...
        return [];
    }

    /**
     * @return string[]
     */
    protected function parseGeneric(string $value, HeaderParserOnError $onErrors = HeaderParserOnError::Throw): array
    {
        return preg_split("/[ \\t]*,[ \\t]*/", $value, -1, PREG_SPLIT_NO_EMPTY);
    }

    /**
     * @return string[]
     * @throws InvalidHeaderValue only when $onErrors == HeaderParserOnError::Throw
     */
    protected function parseMaybeQuotedString(string $value, HeaderParserOnError $onErrors = HeaderParserOnError::Throw): array
    {
        $pieces = [];

        $len = strlen($value);

        $piece = '';
        $quoted = false;
        $start = true;

        for ($i = 0; $i < $len; $i++) {

            if ($start) {
                switch($value[$i]) {
                    case '"':
                        $quoted = true;
                        $start = false;
                        continue 2;
                    case ' ':
                    case "\t":
                    case ",":
                        continue 2;
                    default:
                        $piece .= $value[$i];
                        $start = false;
                        continue 2;
                }
            }

            if ($quoted) {
                switch($value[$i]) {
                    case '\\':
                        if (($j = $i+1) < $len) {
                            $piece .= $value[$j];
                            $i++;
                        } else {
                            if ($onErrors === HeaderParserOnError::Throw) {
                                throw new InvalidHeaderValue("Quoted string has backslash before final quote");
                            }
                            $this->debug("Found invalid quoted string: the final double quote is escaped by a backslash");
                            $quoted = false;
/// @todo... allow whitespace replacement
                            $pieces[] = '';
                            $piece = '';
                        }
                        break;
                    case '"':
                        $quoted = false;
                        $pieces[] = $piece;
                        $piece = '';
                        $start = true;
                        // we do an 'advance' step here to avoid 2 double quotes back to back
                        for ($j = $i+1; $j < $len; $j++) {
                            if ($value[$j] === ',' || $value[$j] === ' ' || $value[$j] === "\t") {
                                $i++;
                            }
                        }
                        break;
                    default:
                        $piece .= $value[$i];
                }
            } else {
                switch($value[$i]) {
                    case ',':
                        $pieces[] = rtrim($piece, " \t");
                        $piece = '';
                        // no need to advance, as we set $start
                        //for ($j = $i+1; $j < $len; $j++) {
                        //    if ($value[$j] === ',' || $value[$j] === ' ' || $value[$j] === "\t") {
                        //        $i++;
                        //    }
                        //}
                        $start = true;
                        break;
                    case '"':
                        if ($onErrors === HeaderParserOnError::Throw) {
                            throw new InvalidHeaderValue("Double quote found within non-quoted value");
                        }
                        $this->debug("Found invalid possibly quoted string: double quote found within non-quoted value");
                        $pieces[] = '';
                        $piece = '';
                        $start = true;
                        break 2;
                    default:
                        $piece .= $value[$i];
                }
            }
        }

        if ($quoted) {
            if ($onErrors === HeaderParserOnError::Throw) {
                throw new InvalidHeaderValue("Quoted string has no final quote");
            }
            $this->debug("Found invalid quoted string: no final quote");
/// @todo... allow whitespace replacement
            $pieces[] = '';
            $piece = '';
            $start = true;
        }

        if (!$start) {
            $pieces[] = $piece;
        }

        return $pieces;
    }

    protected function validateSingletonHeader(array &$values, HeaderParserOnError $onErrors = HeaderParserOnError::Throw): void
    {
        if (count($values) > 1) {
            if ($onErrors === HeaderParserOnError::Throw) {
                throw new InvalidHeaderValue(count($values) . " values received but only 1 allowed");
            } else {
                $this->debug("Multiple values parsed for singleton header");
/// @todo... should we nullify the returned value(s)?
            }
        }
    }

    /**
     * @param string[] $values
     * @throws InvalidHeaderValue only when $onErrors == HeaderParserOnError::Throw
     */
    protected function validateTokenHeader(array &$values, HeaderParserOnError $onErrors = HeaderParserOnError::Throw): void
    {
/// @todo...
        foreach ($values as &$value) {
            if (preg_match('...', $value)) {
                if ($onErrors === HeaderParserOnError::Throw) {
                    throw new InvalidHeaderValue('Non allowed characters for Token');
                }
                $this->debug("Found invalid Token: non allowed characters");
                $value = '';
            }
        }
    }

    protected static function getDefaultKnownHeaders(): array
    {
        return static::$defaultKnownHeaders;
    }
}
