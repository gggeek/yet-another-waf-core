<?php

namespace YAWAF\Core\Http;

class HeaderSpec
{
    /// @todo is it useful to add a constant for other common abnf definitions, such as 'parameter', OWS, .etc..?
    const TOKEN_REGEXP = '[0-9A-Za-z!#$%&\'*+\\-.^_`|~]+';
    const COOKIE_VALUE_REGEXP = '[0-9A-Za-z!#$%&\'*+\\-.^_`|~]+';

    /// @todo... allow specifying other types of double-quoted spans which impact split on commas but have different escaping rules
    //const DOUBLE_QUOTED_ESCAPED = 1;
    //const BACKLASH_AND_DQ_ESCAPED = 2;

    /// @todo... allow specifying the presence of trailing comments, possibly parameters?
    // const ALLOWS_TRAILING_COMMENT = 16384;

    public headerFormat $format;
    public string|null $validationRegexp;
    public bool $isSingleton;
    public HeaderQuotedSpansFormat $quotedSpansFormat;
    public bool $allowedInRequest;
    public bool $allowedInResponse;

    protected static array $knownHeaders = [];
/// @todo... should we relax the strict spacing requirements and replace them with OWS?
    protected static array $validationRegexps = [
        /// @see https://www.rfc-editor.org/info/rfc6265/#section-4.2
        // NB: this is stricter than what PHP accepts as valid when populating $_COOKIE (eg. it rejects multiple spaces to separate cookies)
        'Cookie' => '/^$' . self::TOKEN_REGEXP . '=(?:' . self::COOKIE_VALUE_REGEXP . '|"' . self::COOKIE_VALUE_REGEXP . '")(; ' . self::TOKEN_REGEXP. '=(?:' . self::COOKIE_VALUE_REGEXP . '|"' . self::COOKIE_VALUE_REGEXP . '"))*/',
        // @see https://httpwg.org/specs/rfc9110.html#http.date
        // NB: this does not guarantee valid days or times - day 32, hour 25 and minute 99 are all accepted
        'Date' => '^(:?' .
            '(:?' . '(?:Mon|Tue|Wed|Thu|Fri|Sat|Sun), \d{2} (?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec) \d{4} \d{2}:\d{2}:\d{2} GMT' . ')|' .
            '(:?' . '(?:Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday), \d{2}-(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)-\d{2} \d{2}:\d{2}:\d{2} GMT' . ')|' .
            '(:?' . '(?:Mon|Tue|Wed|Thu|Fri|Sat|Sun) (?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec) (?:\d{2}| \d) \d{2}:\d{2}:\d{2} \d{4}' . '))$',
        'Integer' => '^\d+$',
        'Token' => '^' . self::TOKEN_REGEXP . '$',
    ];

    public function __construct(headerFormat $format, string|null $validationRegexp = null, HeaderQuotedSpansFormat $quotedSpansFormat = HeaderQuotedSpansFormat::None, bool $isSingleton = false, bool $allowedInRequest = true, bool $allowedInResponse = true)
    {
        $this->format = $format;
        if ($validationRegexp === null && array_key_exists($format->name, self::$validationRegexps)) {
            $this->validationRegexp = self::$validationRegexps[$format->name];
        } else {
            $this->validationRegexp = $validationRegexp;
        }
        $this->quotedSpansFormat = $quotedSpansFormat;
        $this->isSingleton = $isSingleton;
        $this->allowedInRequest = $allowedInRequest;
        $this->allowedInResponse = $allowedInResponse;
    }

    /**
     * @todo... start out from a json file instead of a php one
     * @return self[]
     */
    public static function getHeadersSpecifications(): array
    {
        if (!self::$knownHeaders) {
            self::$knownHeaders = array_values(array_filter(require __DIR__ . '/KnownHttpHeaders.php', [static::class, 'isNotGeneric']));
        }

        return self::$knownHeaders;
    }

    /**
     * Generic headers are the ones which:
     * - have no known format (any sequence of 1 or more chars allowed in http fields are valid)
     * - have no provision for allowing inclusions of the comma character in their value - the comma is used to split them in a list of values
     * - have no provision for using specific escaping rules for spans of texts surrounded by double-quotes (such as the quoted-string rule of rfc9110)
     * - are not restricted to being present only once per message (singletons)
     * - are not restricted to be present only in requests or only in responses
     */
    public static function isNotGeneric(HeaderSpec|null $spec): bool
    {
        if ($spec === null) {
            return false;
        }
        return $spec->format !== HeaderFormat::Generic || $spec->validationRegexp !== null || $spec->quotedSpansFormat !== HeaderQuotedSpansFormat::None ||
            $spec->isSingleton || !$spec->allowedInRequest || !$spec->allowedInResponse;
    }
}
