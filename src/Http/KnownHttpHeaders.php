<?php

use YAWAF\Core\Http\HeaderParser as HP;

/**
 * @var int[] keys should be lowercase, and values be a bitmask of the class constants
 *
 * Info taken from:
 * 1. https://www.iana.org/assignments/http-fields/http-fields.xhtml,
 * 2. https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers,
 * 3. https://en.wikipedia.org/wiki/List_of_HTTP_header_fields,
 *
 * NB: ** this file is auto_generated from /doc/http_headers_reference.md **
 */
return [

    // token = see https://www.rfc-editor.org/info/rfc9110/#appendix-A
    // qs = quoted-string, also from rfc9110
    // uri-host = 'a-zA-Z0-9' + '-' + '_' + '~' + '%' + '.' + ':' + [' + ']' + '!' + '$' + '&' + "'" + '(' + ')' + '*' + '+' + ',' + ';' + '=' - https://www.rfc-editor.org/info/rfc3986#section-3.2.2

    // req
    'a-im' => 0, // token + ';' + '=' + qs (in a specific part) - https://www.rfc-editor.org/info/rfc3229/
    'accept' => 0, // token + '/' + '*' + ';' + '=' + qs (in a specific part) - https://www.rfc-editor.org/info/rfc9110/#section-12.5.1
/// @todo review - no qs?
    'accept-charset' => 0, // token + '*' + ';' + '=' - https://www.rfc-editor.org/info/rfc9110/#section-12.5.2
    'accept-datetime' => HP::IS_SINGLETON, // 'a-zA-Z0-9' + ',' + ':' - https://www.rfc-editor.org/info/rfc7089/, https://www.rfc-editor.org/info/rfc1123/
/// @todo review - no qs?
    'accept-encoding' => 0, // token + '*' + ';' + '=' - https://www.rfc-editor.org/info/rfc9110/#field.accept-encoding
/// @todo review - no qs?
    'accept-features' => 0, // token + '!' + '=' + '{' + '}' + ';' - https://datatracker.ietf.org/doc/html/rfc2295#section-8.2
    'accept-language' => 0, // 'a-zA-Z0-9' + '*' + '-' + ';' + '=' + qs (in a specific part) - https://www.rfc-editor.org/info/rfc9110/#field.accept-language, https://www.rfc-editor.org/info/rfc4647/#section-2.1
    'access-control-request-headers' => HP::IS_TOKEN, // token - https://fetch.spec.whatwg.org/#http-new-header-syntax
    'access-control-request-method' => HP::IS_TOKEN | HP::IS_SINGLETON, // token - https://fetch.spec.whatwg.org/#http-new-header-syntax
    'alt-used' => HP::IS_TOKEN, // uri-host + ':' + '0-9' - https://datatracker.ietf.org/doc/html/rfc7838#section-5

    'authorization' => HP::IS_SINGLETON, // singleton + ... - https://www.rfc-editor.org/info/rfc9110/#section-11.6.2
    'cache-control' => 0,
    'connection' => HP::IS_TOKEN,
    'content-digest' => 0,
    'content-encoding' => HP::IS_TOKEN,
    'content-length' => 0, // singleton
    //'content-md5' => 0, // singleton - obsoleted
    'content-type' => HP::IS_SINGLETON, /// @todo token + '/' + ';' + '='
    'cookie' => HP::IS_COOKIE | HP::IS_SINGLETON, // not a csv list
    'date' => HP::IS_DATE | HP::IS_SINGLETON, // singleton
    'expect' => 0,
    'forwarded' => 0,
    'from' => 0, // singleton
    'host' => HP::IS_SINGLETON,
    //'http2-settings' => 0, // singleton? - obsoleted
    'if-match' => 0,
    'if-modified-since' => 0, // singleton?
    'if-none-match' => 0,
    'if-range' => 0, // singleton?
    'if-unmodified-since' => 0, // singleton?
    'keep-alive' => 0,
    'max-forwards' => HP::IS_INTEGER | HP::IS_SINGLETON, // should be restricted to 1 digit
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
    'te' => 0, /// @todo... this one allows QS in a specific part of the value...
    'trailer' => HP::IS_TOKEN,
    'transfer-encoding' => 0,
    'upgrade' => 0, /// @todo... token+'/'
    'upgrade-insecure-requests' => 0, // non-standard?
    'user-agent' => 0, // singleton?
    'via' => 0, /// @todo... token + '/' + ':' and trailing comment
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
    //'warning' => 0, // obsoleted
    'viewport-width' => 0,
    'width' => 0,
    'x-att-deviceid' => 0,
    'x-correlation-id' => 0,
    'x-csrf-token' => 0,
    'x-http-method-override' => 0,
    'x-request-id' => 0,
    'x-requested-with' => 0,
    'x-uidh' => 0,
    'x-wap-profile' => 0,

    // resp
    'accept-ch' => 0, // token + '(' + ')' + '=' + ';' + '*' + almost-dquote + '/' + ':' + '/' - https://httpwg.org/specs/rfc8942.html#accept-ch
    'accept-patch' => 0,
    'accept-post' => 0,
    'accept-ranges' => 0,
    'access-control-allow-credentials' => 0,
    'access-control-allow-headers' => 0,
    'access-control-allow-methods' => 0,
    'access-control-allow-origin' => 0,
    'access-control-expose-headers' => 0,
    'access-control-max-age' => 0,
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
    'memento-datetime' => 0, // - https://www.rfc-editor.org/info/rfc7089/
    'origin-agent-cluster' => 0,
    //'p3p' => 0, // obsoleted
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
    'set-cookie' => 0, /// @todo should de-encode qs spans; test how we get multiple values in responses
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
    'accept-additions' => 0, // token + '*' + ';' + '=' + qs - https://datatracker.ietf.org/doc/html/rfc2324
    'content-dpr' => 0,
    'critical-ch' => 0,
    'expect-ct' => 0,
    'nel' => 0,
    'no-vary-search' => 0,
    'observe-browsing-topics' => 0,
    'permissions-policy' => 0,
    'permissions-policy-report-only' => 0,
    'report-to' => 0,
    //'safe' => HP::IS_TOKEN, // singleton? - obsoleted - https://datatracker.ietf.org/doc/html/rfc2324
    'status' => 0,
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
