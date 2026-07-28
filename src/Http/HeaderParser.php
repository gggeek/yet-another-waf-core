<?php
declare(strict_types=1);

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

    const IS_SINGLETON = 1;

    /// @todo... allow other productions than token, quoted-string
    const ALLOWS_QUOTED_STRINGS = 2;
    const ALLOWS_TRAILING_COMMENT = 4;
    /// @todo is it useful to add a constant for the common 'parameter' abnf definition?
    const IS_TOKEN = 8;
    const IS_COOKIE = 16;
    const IS_DATE = 32;
    const IS_INTEGER = 64;

    protected static array $defaultKnownHeaders = [];
    protected array $knownHeaders;

    public function __construct(array $customHeadersSpec = [], LoggerInterface|null $logger = null)
    {
        if (! Stdlib::array_of_int($customHeadersSpec)) {
            throw new \InvalidArgumentException('customHeadersSpec argument to HeaderParser constructor must be an array of ints');
        }

        // speed/memory optimization: remove all headers which have no specific format and allow multiple values

        if (!self::$defaultKnownHeaders) {
            self::$defaultKnownHeaders = array_filter(require __DIR__ . '/KnownHttpHeaders.php');
        }

        $this->knownHeaders = array_filter($customHeadersSpec) + self::$defaultKnownHeaders;

        $this->logger = $logger;
    }

    /**
     * Normalizes the value of a header, as obtained by a psr-7 message, by splitting it into a list of strings and
     * removing quoted-encodings, based on its known syntax.
     * Custom headers f.e. just get values split on commas and trimmed of space/tab.
     * The format for specific headers can be set in the parser constructor.
     * NB: assumes that the header name and value(s) come from a web-server, meaning that some basic validation has
     * already happened, eg. there are no ctrl characters or \n or \r in its name, or 7F in either (we test that this
     * is true for all supported webservers via BA_ServerRequestCreatorTest tests)
     *
     * @param string $name has to be lowercase
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
     * @todo introduce a version of this that does extra validation, eg. checking for NUL, CR, LF chars
     * @todo is there a better name for this function?
     */
    protected function parseHeaderValue(array $values, int $options, HeaderParserOnError $onErrors = HeaderParserOnError::Throw): array
    {
/// @todo... allow parsing of non-csv-separated lists, eg. for Set-Cookie
        $isToken = $options & self::IS_TOKEN;
        $isCookie = false;
        $isDate = false;
        $allowsQuotedStrings = $options & self::ALLOWS_QUOTED_STRINGS;
        //$allowsComments = $options & self::ALLOWS_TRAILING_COMMENT;

        $isSingleton = $options & self::IS_SINGLETON;

        $splitValuesOnCommas = !($isCookie || $isDate);

        // This check is done at the end of processing, as it makes more sense to do so
        // for singleton values, throw if count($values) > 1
        //if ($isSingleton && count($values) > 1) {
        //    if ($onErrors === HeaderParserOnError::Throw) {
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
/// @todo... add more tests specifically for this in BA_ServerRequestCreatorTest - esp. some sending these chars within a known quoted-string header

            /// @todo would a regexp be faster?
            /// @todo also check if any CTRL chars are found
            //if (str_contains("\n", $value) || str_contains("\r", $value)
            //    || str_contains("\x00", $value) || str_contains("\x7F", $value)) {
            //    if ($onErrors === HeaderParserOnError::Throw) {
            //        throw new InvalidHeaderValue("Found invalid character: CR, LF, NUL or DEL");
            //    } else {
            //        /// @todo log a debug message
            //        $value = str_replace(["\n", "\r", "\x00", "\x7F"], ' ', $value);
            //    }
            //}

            // Which approach is better for singleton headers: split on commas and check how many headers we got, or
            // do not split and keep a single-value?
            //
            // The constraints are:
            // - the http spec says that for _any header_, unless otherwise specified, the values of multiple header
            //   occurrences can be concatenated with a comma as separator
            // - the PSR APIs we use to retrieve the header values fed to this method give an array as value for each header...
            // - ...but in the end, for the most common scenario (PHP running as a FCGI app, or via a webserver), the
            //   values for http headers are gotten from $_SERVER['HTTP_***'], meaning that for each http header there
            //   will be _only 1 value_, not many. Which, in turn, means that the webserver is most likely to concatenate
            //   together multiple values using the comma rule.
            //
            // This means that, given header "X-Custom", defined as singleton, it is factually impossible for php to tell
            // apart these 2 cases:
            //     X-Custom: hello
            //     X-Custom: world
            // and
            //     X-custom: hello, world
            // which is of course not the best position to be in for a firewall.
            //
            // The best solution seems to be to start out with a-priori knowledge of the headers that should not be split
            // on quotes, and act on that - even though ...
            //
            // See: https://github.com/php/frankenphp/discussions/2575

            if ($splitValuesOnCommas) {

                if ($allowsQuotedStrings) {
                    $pieces = $this->parseMaybeQuotedString($value, $onErrors);
                } else {
                    // non-singleton, no quoted strings
                    $pieces = $this->parseGeneric($value, $onErrors);
                }

/// @todo... allow stripping of trailing comments

                $out = array_merge($out, $pieces);

            } else {

                if ($isCookie) {
                    $out[] = $this->parseCookie($value, $onErrors);
                } elseif ($isDate) {
                    $out[] = $this->parseDate($value, $onErrors);
                } else {
                    $out[] = $value;
                }

            }
        }

        if ($isToken) {
            $out = $this->validateTokenHeader($out, $onErrors);
        }

        if ($isSingleton) {
            // the 'Cookie' req. header is a singleton, but if we allow parseCookie to split it, we need this weird handling
            if ($isCookie) {
                if ($onErrors === HeaderParserOnError::Ignore) {
                    $this->validateSingletonHeader($values, $onErrors);
                } else {
                    $out = $this->validateSingletonHeader($values, $onErrors);
                }
            } else {
                $out = $this->validateSingletonHeader($out, $onErrors);
            }

        }

        return $out;
    }

    /**
     * @throws InvalidHeaderValue only when $onErrors == HeaderParserOnError::Throw
     * @todo should we split this in a list of cookie/value?
     * @todo... throw based on $onErrors - see the rationale below for parseDate
     */
    protected function parseCookie(string $value, HeaderParserOnError $onErrors = HeaderParserOnError::Throw): string
    {
        return $value;
    }

    /**
     * @throws InvalidHeaderValue only when $onErrors == HeaderParserOnError::Throw
     */
    protected function parseDate(string $value, HeaderParserOnError $onErrors = HeaderParserOnError::Throw): string
    {
/// @todo... on non-conforming date strings log a debug message / throw based on $onErrors. This is needed in order to
///          catch a client sending eg. 2 "Date" headers, which, thanks to cgi/fcgi collating them into a single string,
///          would possibly be matched by a fw rule set up by the user, which might result in request smuggling
        return $value;
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

        /// @todo can we optimize the handling of $trailingSpaces?

        $piece = '';
        $quoted = false;
        $notStarted = true;
        $trailingSpaces = '';

        for ($i = 0; $i < $len; $i++) {

            if ($notStarted) {
                switch($value[$i]) {
                    case '"':
                        $quoted = true;
                        $notStarted = false;
                        $trailingSpaces = '';
                        continue 2; // advance to next char
                    case ' ':
                    case "\t":
                    case ",":
                        continue 2; // advance to next char
                    default:
                        $piece .= $value[$i];
                        $notStarted = false;
                        $trailingSpaces = '';
                        continue 2; // advance to next char
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
                            switch($onErrors) {
                                case HeaderParserOnError::ReturnNull:
                                    $pieces[] = '';
                                    break;
                                case HeaderParserOnError::ReplaceWithSpace:
                                    $pieces[] = $piece .' ';
                                    break;
/// @todo... should we add the final, spurious backslash to the returned value instead of dropping it?
                                case HeaderParserOnError::Ignore:
                                    $pieces[] = $piece;
                                    break;
                            }
                            $quoted = false;
                            $piece = '';
                        }
                        break;
                    case '"':
                        $quoted = false;
                        // in case the last chars where spaces or tabs, we need to preserve them
                        $trailingSpaces = '';
                        for ($j = strlen($piece) - 1; $j >= 0; $j--) {
                            if ($piece[$j] === ' ' || $piece[$j] === "\t") {
                                $trailingSpaces = $piece[$j] . $trailingSpaces;
                            } else {
                                break;
                            }
                        }
                        break;
                    default:
                        $piece .= $value[$i];
                }
            } else {
                switch($value[$i]) {
                    case ',':
                        // the comma is the separator of multiple header values
                        $pieces[] = rtrim($piece, " \t") . $trailingSpaces;
                        $piece = '';
                        $trailingSpaces = '';
                        $notStarted = true;
                        break;
                    case '"':
                        $quoted = true;
                        break;
                    case ' ':
                    case "\t":
                        $piece .= $value[$i];
                        break;
                    default:
                        if ($trailingSpaces !== '') {
                            $trailingSpaces = '';
                        }
                        $piece .= $value[$i];
                }
            }
        }

        if ($quoted) {
            if ($onErrors === HeaderParserOnError::Throw) {
                throw new InvalidHeaderValue("Quoted string has no final quote");
            }
            $this->debug("Found invalid quoted string: no final quote");
            switch($onErrors) {
                case HeaderParserOnError::ReturnNull:
                    $pieces[] = '';
                    break;
/// @todo should we do some specific processing in case of ReplaceWithSpace ?
                case HeaderParserOnError::ReplaceWithSpace:
                case HeaderParserOnError::Ignore:
                    $pieces[] = $piece;
                    break;
            }
            $piece = '';
            $notStarted = true;
        }

        if (!$notStarted) {
            $pieces[] = rtrim($piece, " \t") . $trailingSpaces;
        }

        return $pieces;
    }

    protected function validateSingletonHeader(array $values, HeaderParserOnError $onErrors = HeaderParserOnError::Throw): array
    {
        if (count($values) < 2) {
            return $values;
        }

        if ($onErrors === HeaderParserOnError::Throw) {
            throw new InvalidHeaderValue(count($values) . " values received but only 1 allowed");
        } else {
            $this->debug("Multiple values parsed for singleton header");
            switch($onErrors) {
                case HeaderParserOnError::ReturnNull:
                    return [''];
                case HeaderParserOnError::ReplaceWithSpace:
/// @todo... what to do? This does not make a lot of sense...
                    //$out[$i] = str_replace(['...'], ' ', $value);
                    //break;
                case HeaderParserOnError::Ignore:
                    return $values;
            }
        }
    }

    /**
     * @param string[] $values
     * @throws InvalidHeaderValue only when $onErrors == HeaderParserOnError::Throw
     */
    protected function validateTokenHeader(array $values, HeaderParserOnError $onErrors = HeaderParserOnError::Throw): array
    {
/// @todo...
        $out = $values;
        foreach ($values as $i => $value) {
            if (preg_match('...', $value)) {
                if ($onErrors === HeaderParserOnError::Throw) {
                    throw new InvalidHeaderValue('Non allowed characters for Token');
                }
                $this->debug("Found invalid Token: non allowed characters");
                switch($onErrors) {
                    case HeaderParserOnError::ReturnNull:
                        $out[$i] = '';
                        break;
                    case HeaderParserOnError::ReplaceWithSpace:
                        $out[$i] = str_replace(['...'], ' ', $value);
                        break;
                    case HeaderParserOnError::Ignore:
                        break;
                }
            }
        }
        return $out;
    }

    protected static function getDefaultKnownHeaders(): array
    {
        return static::$defaultKnownHeaders;
    }
}
