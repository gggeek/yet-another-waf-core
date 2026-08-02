<?php

use YAWAF\Core\Http\HeaderFormat as HF;
use YAWAF\Core\Http\HeaderQuotedSpansFormat as QS;
use YAWAF\Core\Http\HeaderSpec as HS;

/**
 * @var HS|null[] keys should be lowercase strings
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
    'a-im'=> null, // token + ';' + '=' + qs (in a specific part) - https://www.rfc-editor.org/info/rfc3229/
    'accept'=> null, // token + '/' + '*' + ';' + '=' + qs (in a specific part) - https://www.rfc-editor.org/info/rfc9110/#section-12.5.1
/// @todo review - no qs?
    'accept-charset'=> null, // token + '*' + ';' + '=' - https://www.rfc-editor.org/info/rfc9110/#section-12.5.2
    'accept-datetime' => new HS(HF::Generic, null, QS::None, true), // 'a-zA-Z0-9' + ',' + ':' - https://www.rfc-editor.org/info/rfc7089/, https://www.rfc-editor.org/info/rfc1123/
/// @todo review - no qs?
    'accept-encoding'=> null, // token + '*' + ';' + '=' - https://www.rfc-editor.org/info/rfc9110/#field.accept-encoding
/// @todo review - no qs?
    'accept-features'=> null, // token + '!' + '=' + '{' + '}' + ';' - https://datatracker.ietf.org/doc/html/rfc2295#section-8.2
    'accept-language'=> null, // 'a-zA-Z0-9' + '*' + '-' + ';' + '=' + qs (in a specific part) - https://www.rfc-editor.org/info/rfc9110/#field.accept-language, https://www.rfc-editor.org/info/rfc4647/#section-2.1
    'access-control-request-headers' => new HS(HF::Token), // token - https://fetch.spec.whatwg.org/#http-new-header-syntax
    'access-control-request-method' => new HS(HF::Token, null, QS::None, true), // token - https://fetch.spec.whatwg.org/#http-new-header-syntax
    'alt-used' => new HS(HF::Token), // uri-host + ':' + '0-9' - https://datatracker.ietf.org/doc/html/rfc7838#section-5

    'authorization' => new HS(HF::Generic, null, QS::None, true), // singleton + ... - https://www.rfc-editor.org/info/rfc9110/#section-11.6.2
    'cache-control'=> null,
    'connection' => new HS(HF::Token),
    'content-digest'=> null,
    'content-encoding' => new HS(HF::Token),
    'content-length'=> null, // singleton
    //'content-md5'=> null, // singleton - obsoleted
    'content-type' => new HS(HF::Generic, null, QS::None, true), /// @todo token + '/' + ';' + '='
    'cookie' => new HS(HF::Cookie, null, QS::None, true), // not a csv list
    'date' => new HS(HF::Date, null, QS::None, true), // singleton
    'expect'=> null,
    'forwarded'=> null,
    'from'=> null, // singleton
    'host' => new HS(HF::Generic, null, QS::None, true),
    //'http2-settings'=> null, // singleton? - obsoleted
    'if-match'=> null,
    'if-modified-since'=> null, // singleton?
    'if-none-match'=> null,
    'if-range'=> null, // singleton?
    'if-unmodified-since'=> null, // singleton?
    'keep-alive'=> null,
    'max-forwards' => new HS(HF::Integer, null, QS::None, true), // should be restricted to 1 digit
    'origin'=> null, // singleton?
    'pragma'=> null, // singleton?
    'prefer'=> null, // not a csv list?
    'priority'=> null,
    'proxy-authorization'=> null, // singleton?
    'range'=> null,
    'referer'=> null, // singleton?
    'repr-digest'=> null,
    'sec-fetch-dest'=> null,
    'sec-fetch-mode'=> null,
    'sec-fetch-site'=> null,
    'sec-fetch-storage-access'=> null,
    'sec-fetch-user'=> null,
    'sec-gpc'=> null, // non-standard?
    'sec-purpose'=> null,
    'sec-websocket-extensions'=> null,
    'sec-websocket-key'=> null,
    'sec-websocket-protocol'=> null,
    'service-worker'=> null,
    'service-worker-navigation-preload'=> null,
    'te'=> null, /// @todo... this one allows QS in a specific part of the value...
    'trailer' => new HS(HF::Token),
    'transfer-encoding'=> null,
    'upgrade'=> null, /// @todo... token+'/'
    'upgrade-insecure-requests'=> null, // non-standard?
    'user-agent'=> null, // singleton?
    'via'=> null, /// @todo... token + '/' + ':' and trailing comment
    'want-content-digest'=> null,
    'want-repr-digest'=> null,
    'x-forwarded-for'=> null,
    'x-forwarded-host'=> null,
    'x-forwarded-proto'=> null,

    // req. non-standard
    'attribution-reporting-eligible'=> null,
    'attribution-reporting-register-source'=> null,
    'attribution-reporting-register-trigger'=> null,
    'available-dictionary'=> null,
    'correlation-id'=> null,
    'device-memory'=> null,
    'dictionary-id'=> null,
    'dnt'=> null,
    'downlink'=> null,
    'dpr'=> null,
    'early-data'=> null,
    'ect'=> null,
    'front-end-https'=> null,
    'idempotency-key'=> null,
    'proxy-connection'=> null,
    'rtt'=> null,
    'save-data'=> null,
    'sec-browsing-topics'=> null,
    'sec-ch-device-memory'=> null,
    'sec-ch-dpr'=> null,
    'sec-ch-prefers-color-scheme'=> null,
    'sec-ch-prefers-reduced-motion'=> null,
    'sec-ch-prefers-reduced-transparency'=> null,
    'sec-ch-ua'=> null,
    'sec-ch-ua-arch'=> null,
    'sec-ch-ua-bitness'=> null,
    'sec-ch-ua-form-factors'=> null,
    'sec-ch-ua-full-version'=> null,
    'sec-ch-ua-full-version-list'=> null,
    'sec-ch-ua-mobile'=> null,
    'sec-ch-ua-model'=> null,
    'sec-ch-ua-platform'=> null,
    'sec-ch-ua-platform-version'=> null,
    'sec-ch-ua-wow64'=> null,
    'sec-ch-viewport-height'=> null,
    'sec-ch-viewport-width'=> null,
    'sec-ch-width'=> null,
    'sec-private-state-token'=> null,
    'sec-private-state-token-crypto-version'=> null,
    'sec-private-state-token-lifetime'=> null,
    'sec-redemption-record'=> null,
    'sec-speculation-tags'=> null,
    //'warning'=> null, // obsoleted
    'viewport-width'=> null,
    'width'=> null,
    'x-att-deviceid'=> null,
    'x-correlation-id'=> null,
    'x-csrf-token'=> null,
    'x-http-method-override'=> null,
    'x-request-id'=> null,
    'x-requested-with'=> null,
    'x-uidh'=> null,
    'x-wap-profile'=> null,

    // resp
    'accept-ch'=> null, // token + '(' + ')' + '=' + ';' + '*' + almost-dquote + '/' + ':' + '/' - https://httpwg.org/specs/rfc8942.html#accept-ch
    'accept-patch'=> null,
    'accept-post'=> null,
    'accept-ranges'=> null,
    'access-control-allow-credentials'=> null,
    'access-control-allow-headers'=> null,
    'access-control-allow-methods'=> null,
    'access-control-allow-origin'=> null,
    'access-control-expose-headers'=> null,
    'access-control-max-age'=> null,
    'activate-storage-access'=> null,
    'age'=> null,
    'allow'=> null,
    'alt-svc'=> null,
    'clear-site-data'=> null,
    'content-disposition'=> null,
    'content-language'=> null,
    'content-location'=> null,
    'content-range'=> null,
    'content-security-policy'=> null, // non-standard?
    'content-security-policy-report-only'=> null,
    'cross-origin-embedder-policy'=> null,
    'cross-origin-embedder-policy-report-only'=> null,
    'cross-origin-opener-policy'=> null,
    'cross-origin-resource-policy'=> null,
    'delta-base'=> null,
    'etag'=> null,
    'expires'=> null,
    'im'=> null,
    'integrity-policy'=> null,
    'integrity-policy-report-only'=> null,
    'last-modified'=> null,
    'link'=> null,
    'location'=> null,
    'memento-datetime'=> null, // - https://www.rfc-editor.org/info/rfc7089/
    'origin-agent-cluster'=> null,
    //'p3p'=> null, // obsoleted
    'preference-applied'=> null,
    'proxy-authenticate'=> null,
    'public-key-pins'=> null,
    'referrer-policy'=> null,
    'refresh'=> null, // non-standard?
    'reporting-endpoints'=> null,
    'retry-after'=> null,
    'sec-websocket-accept'=> null,
    'sec-websocket-version'=> null,
    'server'=> null,
    'server-timing'=> null,
    'service-worker-allowed'=> null,
    'set-cookie'=> null, /// @todo should de-encode qs spans; test how we get multiple values in responses
    'set-login'=> null,
    'sourcemap'=> null,
    'speculation-rules'=> null,
    'strict-transport-security'=> null,
    'supports-loading-mode'=> null,
    'timing-allow-origin'=> null, // non-standard?
    'vary'=> null,
    'www-authenticate'=> null,
    'x-content-type-options'=> null, // non-standard?
    'x-frame-options'=> null,

    // Resp. non-standard
    'accept-additions'=> null, // token + '*' + ';' + '=' + qs - https://datatracker.ietf.org/doc/html/rfc2324
    'content-dpr'=> null,
    'critical-ch'=> null,
    'expect-ct'=> null,
    'nel'=> null,
    'no-vary-search'=> null,
    'observe-browsing-topics'=> null,
    'permissions-policy'=> null,
    'permissions-policy-report-only'=> null,
    'report-to'=> null,
    //'safe' => HP::IS_TOKEN, // singleton? - obsoleted - https://datatracker.ietf.org/doc/html/rfc2324
    'status'=> null,
    'tk'=> null,
    'use-as-dictionary'=> null,
    'x-content-duration'=> null,
    'x-content-security-policy'=> null,
    'x-dns-prefetch-control'=> null,
    'x-permitted-cross-domain-policies'=> null,
    'x-powered-by'=> null,
    'x-redirect-by'=> null,
    'x-robots-tag'=> null,
    'x-webkit-csp'=> null,
    'x-ua-compatible'=> null,
    'x-xss-protection'=> null,
];
