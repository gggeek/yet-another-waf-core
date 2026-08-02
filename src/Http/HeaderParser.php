<?php
declare(strict_types=1);

namespace YAWAF\Core\Http;

use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use YAWAF\Core\Exception\ConfigurationError;
use YAWAF\Core\Exception\InvalidHeaderValue;
use YAWAF\Core\Http\HeaderFormat as HF;
use YAWAF\Core\Http\HeaderQuotedSpansFormat as HQSF;
use YAWAF\Core\Http\HeaderSpec as HS;
use YAWAF\Core\Logger\PrivateLoggerTrait;
use YAWAF\Core\Stdlib;

/// @todo do we need to implement plugins/subclasses to add support for headers used in well-known protocols such as eg. webdav?
class HeaderParser
{
    use LoggerAwareTrait;
    use PrivateLoggerTrait;

    protected static array $defaultKnownHeaders = [];
    /** @var HS[] */
    protected array $knownHeaders;

    /**
     * @param HeaderSpec[] $customHeadersSpecs
     * @throws \InvalidArgumentException
     * @throws ConfigurationError
     */
    public function __construct(array $customHeadersSpecs = [], LoggerInterface|null $logger = null)
    {
        if (! Stdlib::array_of($customHeadersSpecs, HeaderSpec::class)) {
            throw new \InvalidArgumentException('customHeadersSpec argument to HeaderParser constructor must be an array of HeaderSpec objects');
        }

        $this->knownHeaders = HeaderSpecFactory::getHeadersSpecifications($customHeadersSpecs);
        $this->logger = $logger;
    }

    /**
     * Normalizes the value of a header, as obtained by a psr-7 message, by splitting it into a list of strings and
     * removing excess whitespace and quoted-encodings, based on its known syntax.
     * Custom headers f.e. just get values split on commas and trimmed of space/tab.
     * The format for specific headers can be set via the parser constructor.
     * NB: assumes that the header name and value(s) come from a web-server, meaning that some basic validation has
     * already happened, eg. there are no ctrl characters or \n or \r in its name, or x7F in either name or value
     * (we test that this is true for all supported webservers via BA_ServerRequestCreatorTest tests)
     *
     * @param string $name has to be lowercase
     * @param string[] $values as obtained by a psr-7 message
     * @return string[]
     * @throws InvalidHeaderValue only when $onErrors == HeaderParserOnError::Throw
     */
    public function normalizeHeaderValue(string $name, array $values, array|null &$errorsFound = []): array
    {
        //try {
            return $this->parseHeaderValue($values, $this->knownHeaders[$name] ?? null, $errorsFound);
        //} catch (InvalidHeaderValue $e) {
        //    throw new InvalidHeaderValue("Error parsing header '$name': " . lcfirst($e->getMessage()));
        //}
    }

    /**
     * Checks that a header value is fully compliant with its RFC specification
     * @param string $name has to be lowercase
     * @param string[] $values
     * @return bool true if the header is compliant
     */
    public function validateHeaderValue(string $name, array $values): bool
    {
        // The following was disabled after checking that the (supported) webservers will not allow such headers
        // to reach php anyway
/// @todo... add more tests specifically for this in BA_ServerRequestCreatorTest - esp. some sending these chars within a known quoted-string header
        /// @todo introduce a version of this that does extra validation, eg. checking for NUL, CR, LF, DEL chars in header name + value,
        ///       and CTRL chars or > 127 in header name
        /// @todo introduce a version of this that does also check for chars !#$%&\'*+.^_`|~ in the header name
        /// @todo would a regexp be faster?
        //foreach ($values as $value) {
        //    if (str_contains("\n", $value) || str_contains("\r", $value)
        //        || str_contains("\x00", $value) || str_contains("\x7F", $value)) {
        //        return false;
        //    }
        //}

        if (!array_key_exists($name, $this->knownHeaders)) {
            return true;
        }

        $spec = $this->knownHeaders[$name];

        $errors = [];
        $normalizedValues = $this->parseHeaderValue($values, $spec, $errors);

        if ($errors) {
            return false;
        }

        switch ($spec->format) {
            case HF::Cookie:
            case HF::Date:
            case HF::Token:
            case HF::Integer:
                foreach ($normalizedValues as $value) {
                    if (!preg_match($spec->validationRegexp, $value)) {
                        return false;
                    }
                }
                break;
            case HF::Generic:
                if ($spec->validationRegexp !== null) {
                    foreach ($normalizedValues as $value) {
                        if (!preg_match($spec->validationRegexp, $value)) {
                            return false;
                        }
                    }
                }
                break;
        }

        if ($spec->isSingleton && (count($values) > 1 || count($normalizedValues) > 1)) {
            return false;
        }

        return true;
    }

    /**
     * Normalizes the value of a header, as obtained by a psr-7 message, by splitting it into a list of strings and
     * removing quoted-encodings, based on its known syntax.
     *
     * @param string[] $values as obtained by a psr-7 message. Keys must be numerically indexed from 0
     * @return string[]
     * @throws InvalidHeaderValue only when $onErrors == HeaderParserOnError::Throw
     *
     * @todo is there a better name for this function?
     */
    protected function parseHeaderValue(array $values, HS|null $hs, array|null &$errorsFound = []): array
    {
        //$isToken = $hs !== null && $hs->format === HF::Token;
        $isCookie = $hs !== null && $hs->format === HF::Cookie;
        $isDate = $hs !== null && $hs->format === HF::Date;
        $isJson = $hs !== null && $hs->format === HF::Json;
        /// @todo move this methods to HP/HF?
        $isStructured = $hs !== null &&  ($hs->format === HF::SFDictionary || $hs->format === HF::SFList || $hs->format === HF::SFItem);

        //$isSingleton = $hs !== null && $hs->isSingleton;

        $allowsQuotedStrings = $hs !== null && $hs->quotedSpansFormat === HQSF::QuotedString;
        //$allowsComments = $options & HeaderSpec::ALLOWS_TRAILING_COMMENT;

        $splitValuesOnCommas = !($isCookie || $isDate || $isJson || $isStructured);
        //$joinMultipleValuesOnCommas = false;

        // Which approach is better for headers supposed to be singletons?
        // 1. split on commas and then check how many values we got, or
        // 2. do not split and keep a single-value? Or even
        // 3. combine multiple values with a ', '
        //
        // The constraints are:
        // - the http spec says that for _any header_, _the recipient_ can concatenate the values of multiple header
        //   occurrences without changing the semantics of the message (but we are an intermediary, not the recipient)
        // - the PSR APIs we use to retrieve the header values fed to this method gives us an array as value for each header...
        // - ...but in the end, for the most common scenario (PHP running as a FCGI app, or via a webserver), the
        //   values for http headers are gotten from $_SERVER['HTTP_***'], meaning that for each http header there
        //   will be _only 1 value_, not many. Which, in turn, means that the webserver is extremely likely to
        //   concatenate together multiple values using the comma rule.
        //
        // This means that, given header "X-Custom", defined as singleton, it is factually impossible for php to tell
        // apart these 2 cases:
        //     X-Custom: "hello
        //     X-Custom: world"
        // and
        //     X-custom: "hello, world"
        // which is of course not the best position to be in for a firewall... Fe. rfc9651 explicitly says that a parser
        // _might_ reject the first case...
        //
        // The best solution seems to be to start out with the a-priori knowledge of the headers that should not be split
        // on quotes, rather than the headers which are supposed to be singletons, and start from that...
        //
        // See: https://github.com/php/frankenphp/discussions/2575

        //if ($joinMultipleValuesOnCommas) {
        //    $values = [implode(', '), $values];
        //}

        $out = [];
        $errorsFound = [];
        foreach ($values as $value) {

            // @see https://www.rfc-editor.org/info/rfc9112/#section-5
            // @see https://www.rfc-editor.org/info/rfc9110/#section-5.5

            // some webservers (I am looking at you, Nginx...) do not _always apply this stripping
            $value = trim($value, " \t");

/// @todo... allow stripping of trailing comments

            if ($splitValuesOnCommas) {
                if ($allowsQuotedStrings) {
                    $pieces = $this->parseMaybeQuotedString($value, $errorsFound);
                } else {
                    // non-singleton, no quoted strings
                    $pieces = $this->parseGeneric($value, $errorsFound);
                }
                $out = array_merge($out, $pieces);
            } elseif ($isCookie) {
                $out[] = array_merge($out, $this->parseCookie($value, $errorsFound));
            //} elseif ($isDate) {
            //    $out[] = $this->parseDate($value, $errorsFound);
            } elseif ($isStructured) {
                $out = array_merge($out, $this->parseStructuredValue($value, $errorsFound));
            } else {
                $out[] = $value;
            }
        }

        return $out;
    }

    /**
     * This "normalizes" the Cookie header, by splitting it as it is effectively a list - but it does not use the
     * cookie name as key of the returned array.
     *
     * @return string[]
     */
    protected function parseCookie(string $value, array &$errorsFound): array
    {
/// @todo should we remove dquotes around the value? We do get them in $_COOKIE, but end users might not expect them
/// @todo... throw based on $onErrors - see the rationale below for parseDate
///          (should we just check for not having CTLs, whitespace, DQUOTE, comma, semicolon and backslash, or use a proper
///          regxep validation? Note the test results in duplicateCookieDataProvider: webservers are quite lenient in what
///          they pass on to php in $_COOKIE...)
        $pieces = preg_split("/;[ \\t]*/", $value, -1, PREG_SPLIT_NO_EMPTY);
        return $pieces;
    }

    /*protected function parseDate(string $value, array &$errorsFound): string
    {
/// @todo... on non-conforming date strings log a debug message / throw based on $onErrors. This is needed in order to
///          catch a client sending eg. 2 "Date" headers, which, thanks to cgi/fcgi collating them into a single string,
///          would possibly be matched by a fw rule set up by the user, which might result in request smuggling
///          (should we just check for 2 commas, or use a proper regxep validation?)
        return $value;
    }*/

    /**
     * @return string[]
     */
    protected function parseGeneric(string $value, array &$errorsFound): array
    {
        return preg_split("/[ \\t]*,[ \\t]*/", $value, -1, PREG_SPLIT_NO_EMPTY);
    }

    /**
     * @return string[]
     */
    protected function parseMaybeQuotedString(string $value, array &$errorsFound): array
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
                            //if ($onErrors === HeaderParserOnError::Throw) {
                            //    throw new InvalidHeaderValue("Quoted string has backslash before final quote");
                            //}
                            //$this->debug("Found invalid quoted string in http header: the final double quote is escaped by a backslash");
                            //switch($onErrors) {
                            //    case HeaderParserOnError::ReturnNull:
                            //        $pieces[] = '';
                            //        break;
                            //    case HeaderParserOnError::ReplaceWithSpace:
                            //        $pieces[] = $piece .' ';
                            //        break;
                            //    case HeaderParserOnError::Ignore:
                            //        $pieces[] = $piece;
                            //        break;
                            //}
                            $errorsFound[] = 'Invalid quoted-string: it has backslash before final quote';
/// @todo... should we add the final, spurious backslash to the returned value instead of dropping it?
                            $pieces[] = $piece;
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
            //if ($onErrors === HeaderParserOnError::Throw) {
            //    throw new InvalidHeaderValue("Quoted string has no final quote");
            //}
            //$this->debug("Found invalid quoted string in http header: no final quote");
            //switch($onErrors) {
            //    case HeaderParserOnError::ReturnNull:
            //        $pieces[] = '';
            //        break;
            /// @todo should we do some specific processing in case of ReplaceWithSpace ?
            //    case HeaderParserOnError::ReplaceWithSpace:
            //    case HeaderParserOnError::Ignore:
            //        $pieces[] = $piece;
            //        break;
            //}
            $errorsFound[] = "Invalid quoted string: missing closing quote";
/// @todo... should we use as returned value the original string, without backslash escaping?
            $pieces[] = $piece;
            $piece = '';
            $notStarted = true;
        }

        if (!$notStarted) {
            $pieces[] = rtrim($piece, " \t") . $trailingSpaces;
        }

        return $pieces;
    }

    protected function parseStructuredValue(): array
    {
        $pieces = [];

/// @todo...

        return $pieces;
    }

/*
    protected function validateSingletonHeader(array $values): array
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
     * /
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
*/
}
