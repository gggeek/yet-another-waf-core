<?php

namespace YAWAF\Core\Http;

use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use YAWAF\Core\Exception\InvalidHeaderValue;
use YAWAF\Core\Logger\PrivateLoggerTrait;

class HeaderParser
{
    use LoggerAwareTrait;
    use PrivateLoggerTrait;

/// @todo... allow other productions than token, quoted-string
    const SINGLETON = 1;
    const ALLOWS_QUOTED_STRINGS = 2;
    const ALLOWS_TRAILING_COMMENT = 4;

    //const IS_DATE = 8;

    /// @todo... complete list of known headers: singletons, non-csv-lists, double-quoted, dates, cookies, ...
    /** @var int[] */
    protected static $knownHeaders = [
        'cookie' => 0,
        'host' => self::SINGLETON,
    ];

    public function __construct(LoggerInterface|null $logger = null)
    {
        $this->logger = $logger;
    }

    /**
     * @param string $name lowercase
     * @param string[] $values as obtained by a psr-7 message
     * @return string[]
     * @throws InvalidHeaderValue only when $throwOnErrors is true
     */
    public function normalizeHeaderValue(string $name, array $values, bool $throwOnErrors = false): array
    {
        if (array_key_exists($name, self::$knownHeaders)) {
            try {
                return $this->normalizeCustomHeaderValue($values, self::$knownHeaders[$name], $throwOnErrors);
            } catch (InvalidHeaderValue $e) {
                throw new InvalidHeaderValue("Error parsing header '$name': " . $e->getMessage());
            }
        } else {
/// @todo... throw!
            //return $this->normalizeCustomHeaderValue($values, )
            throw new \Exception("ToBeDone");
        }
    }

    /**
     * @param string[] $values as obtained by a psr-7 message. Keys must be numerically indexed from 0
     * @param int $options
     * @return string[]
     * @throws InvalidHeaderValue only when $throwOnErrors is true
     */
    public function normalizeCustomHeaderValue(array $values, int $options, bool $throwOnErrors = false): array
    {
        /// @todo allow parsing of non-csv lists, eg. for Set-Cookie
        $allowsQuotedStrings = $options & self::ALLOWS_QUOTED_STRINGS;
        $singleton = $options & self::SINGLETON;
        $allowsComments = $options & self::ALLOWS_TRAILING_COMMENT;

/// @todo allow stripping of trailing comments

        $out = [];
        foreach ($values as $value) {
            // @see https://www.rfc-editor.org/info/rfc9110/#section-5.5

            $value = trim($value, " \t");

            /// @todo would a regexp be faster?
            if ($throwOnErrors && (str_contains("\n", $value) || str_contains("\r", $value) || str_contains("\x00", $value))) {
                throw new InvalidHeaderValue("Found invalid character: CR, LF or NUL");
            }

            /// @todo also throw if any CTRL chars are found

            // we do not reject CR, LF an NUL, but transform them to SP
            /// @todo log a message if we transform any chars
            $value = str_replace(["\n", "\r", "\x00"], ' ', $value);

            if ($singleton) {

                if ($allowsQuotedStrings && ($len = strlen($value)) >= 2 && $value[0] === '"' && $value[$len-1] === '"') {
                    $out[] = $this->parseQuotedStingContents($value, $len, $throwOnErrors);
                } else {
/// @todo... this is wrong: it assumes a 'token' production, which is not obligatory. Also, in token strings other chars are forbidden: (),/:;<=>?@[\]{}
                    if (str_contains($value, '"')) {
                        if ($throwOnErrors) {
                            throw new InvalidHeaderValue("Non-quoted string contains double-quote character");
                        } else {
                            $this->debug("Non-quoted string contains double-quote character: '$value'");
                            $out[] = '';
                        }
                    } else {
                        $out[] = $value;
                    }
                }

            } else {

                if ($allowsQuotedStrings) {
                    $len = strlen($value);
                    $piece = '';
                    $quoted = false;
                    $start = true;
                    for ($i = 0; $i < $len; $i++) {
                        if ($start) {
                            switch($value[$i]) {
                                case '"':
                                    $quoted = true;
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
                                        if ($throwOnErrors) {
                                            throw new InvalidHeaderValue("Quoted string has backslash before final quote");
                                        }
                                        $this->debug("Found invalid quoted string: the final double quote is escaped by a backslash");
                                        $quoted = false;
                                        $out[] = '';
                                        $piece = '';
                                    }
                                    break;
                                case '"':
                                    $quoted = false;
                                    $out[] = $piece;
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
                                    $out[] = rtrim($piece, " \t");
                                    $piece = '';
                                    // no need to advance, as we set $start
                                    //for ($j = $i+1; $j < $len; $j++) {
                                    //    if ($value[$j] === ',' || $value[$j] === ' ' || $value[$j] === "\t") {
                                    //        $i++;
                                    //    }
                                    //}
                                    $start = true;
                                    break;
                                default:
                                    $piece .= $value[$i];
                            }
                        }
                    }
                    if ($quoted) {
                        if ($throwOnErrors) {
                            throw new InvalidHeaderValue("Quoted string has no final quote");
                        }
                        $this->debug("Found invalid quoted string: the final double quote is escaped by a backslash");
                        $piece = '';

                    }
                    if ($piece !== '') {
                        $out[] = $piece;
                    }
                } else {
                    // non-singleton, no quoted strings
                    $pieces = preg_split("/[ \\t]*,[ \\t]*/", $value, -1, PREG_SPLIT_NO_EMPTY);
/// @todo... this is wrong: it assumes a 'token' production, which is not obligatory. Also, in token strings other chars are forbidden: (),/:;<=>?@[\]{}
                    foreach ($pieces as $i => $piece) {
                        if (str_contains($piece, '"')) {
                            if ($throwOnErrors) {
                                throw new InvalidHeaderValue("Non-quoted string contains double-quote character");
                            } else {
                                $this->debug("Non-quoted string contains double-quote character: '$piece'");
                                $pieces[$i] = '';
                            }
                        }
                    }

                    $out = array_merge($out, $pieces);
                }

            }
        }

        /// @todo log a message when not throwing
        if ($throwOnErrors && $singleton && count($out) > 1) {
            throw new InvalidHeaderValue(count($out) . " values received but only 1 allowed");
        }

        return $out;
    }

    /**
     * @param string $value the string, including the delimiting quotes. NB: this function does not check for those 2 chars!
     * @throws InvalidHeaderValue when $throwOnErrors is true
     */
    protected function parseQuotedStingContents(string $value, int $length, bool $throwOnErrors = false): string
    {
        // forget about the last char
        $length -= 1;

        $out = '';
        for ($i = 1; $i < $length; $i++) {
            switch($value[$i]) {
                case '\\':
                    if (($j = $i + 1) < ($length)) {
                        $out .= $value[$j];
                        $i++;
                    } else {
                        if ($throwOnErrors) {
                            throw new InvalidHeaderValue("Quoted string has backslash before final quote");
                        }
                        $this->debug("Found invalid quoted string: the final double quote is escaped by a backslash");
                        return '';
                    }
                    break;
                case '"':
                    if ($throwOnErrors) {
                        throw new InvalidHeaderValue("Quoted string has unescaped double quote before final one");
                    }
                    $this->debug("Quoted string has unescaped double quote before final one");
                    return '';
                default:
                    $out .= $value[$i];
            }
        }

        return $out;
    }

    public function registerCustomHeader(string $headerName, int $headerSpec): void
    {
        self::$knownHeaders[$headerName] = $headerName;
    }
}
