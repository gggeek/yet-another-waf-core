<?php

namespace YAWAF\Core\Http;

class HeaderSpec
{
    const IS_SINGLETON = 1; // headers that should not be present more than once per message

    /// @todo... allow other (common) rules than these
    const IS_TOKEN = 2;
    const IS_COOKIE = 4;
    const IS_DATE = 8;
    const IS_INTEGER = 16;
    const IS_JSON = 32;

    /// @todo... allow specification of a single type of value for Item, ie. IS_SF_INTEGER, IS_SF_DECIMAL, IS_SF_STRING, IS_SF_TOKEN, IS_SF_BYTESEQUENCE, IS_SF_BOOL, IS_SF_DATE, IS_SF_DISPLAYSTRING
    const IS_ITEM = 64;
    const IS_LIST = 128;
    const IS_DICTIONARY = 256;

    /// @todo is it useful to add a constant for the common 'parameter' abnf definition?

    /// @todo... allow specifying other types of double-quoted spans which impact split on commas but have different escaping rules
    const ALLOWS_QUOTED_STRINGS = 8192;
    // const ALLOWS_TRAILING_COMMENT = 16384;

    const REQUEST_ONLY_HEADER = 32768;
    const RESPONSE_ONLY_HEADER = 65536;

    protected static array $knownHeaders = [];

    public static function getHeadersDefinitions(): array
    {
        if (!self::$knownHeaders) {
            self::$knownHeaders = array_filter(require __DIR__ . '/KnownHttpHeaders.php');
        }

/// @todo... log at least a warning for incoherent definitions (either here or via a dedicated method)
        return self::$knownHeaders;
    }
}
