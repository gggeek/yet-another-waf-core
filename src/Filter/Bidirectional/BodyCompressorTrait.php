<?php
declare(strict_types=1);

namespace YAWAF\Core\Filter\Bidirectional;

use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use YAWAF\Core\Exception\RequestBodyCantBeCompressed;
use YAWAF\Core\Exception\RequestBodyCantBeDecompressed;
use YAWAF\Core\Exception\ResponseBodyCantBeCompressed;
use YAWAF\Core\Exception\ResponseBodyCantBeDecompressed;

trait BodyCompressorTrait
{
    protected function messageBodyIsCompressed(MessageInterface $message): bool
    {
        if ($message->hasHeader('Content-Encoding')) {
            foreach ($message->getHeader('Content-Encoding') as $encoding) {
                if (strtolower($encoding) !== 'identity') {
                    return true;
                }
            }
        }
        return false;
    }

    protected function messageBodyIsChunked(MessageInterface $message): bool
    {
        return $message->hasHeader('Transfer-Encoding');
    }

    /**
     * @param string[] $contentEncodings
     * @throws RequestBodyCantBeCompressed
     * @throws ResponseBodyCantBeCompressed
     */
    protected function compressMessageBody(MessageInterface $message, array $contentEncodings, string &$actualEncoding): string
    {
/// @todo...
        throw new \Exception("compressMessageBody: not implemented yet!");

        foreach ($contentEncodings as $contentEncoding) {
            switch (strtolower($contentEncoding)) {
                /// @todo add support for brotli, zstd if those extensions exist (check for functions, not exts)
                //case 'br':
                //case 'dcb':
                //case 'dcz':
                //    $actualEncoding = $contentEncoding;
                //    return '...';
                case 'deflate':
                    $actualEncoding = $contentEncoding;
                    return '...';
                case 'gzip':
                    $actualEncoding = $contentEncoding;
                    return '...';
                case 'identity':
                    return '';
                //case 'zstd':
                //    $actualEncoding = $contentEncoding;
                //    return '...';
                default:
                    // do nothing
            }
        }
        if ($message instanceof RequestInterface) {
            throw new RequestBodyCantBeCompressed("Unsupported content-encoding(s): '" . implode("', '", $contentEncodings) . "'");
        } else {
            throw new ResponseBodyCantBeCompressed("Unsupported content-encoding(s): '" . implode("', '", $contentEncodings) . "'");
        }
    }

    /**
     * @param string[] $contentEncodings
     * @throws RequestBodyCantBeDecompressed
     * @throws ResponseBodyCantBeDecompressed
     * @todo allow streaming decompression
     */
    protected function decompressMessageBody(MessageInterface $message, null|array $contentEncodings = null): string
    {
        if ($contentEncodings === null) {
            $contentEncodings = $message->getHeader('Content-encoding');
        }

        /// @todo... verify - is this ever unnecessary?
        //$body = $this->dechunkMessageBody($message);

        $stream = $message->getBody();
        $stream->rewind();
        $body = $stream->getContents();

        foreach (array_reverse($contentEncodings) as $contentEncoding) {
            $contentEncoding = strtolower($contentEncoding);
            $errorMessage = null;
            switch ($contentEncoding) {
                /// @todo add support for dcb, dcz
                case 'br':
                //case 'dcb':
                //case 'dcz':
                    if (function_exists('brotli_uncompress')) {
                        $body = @brotli_uncompress($body);
                        if ($body === false) {
                            $errorMessage = "Failed decompressing " . $contentEncoding . " body";
                        }
                    } else {
                        $errorMessage = "Unsupported content-encoding: '$contentEncoding' (missing php function: brotli_uncompress)";
                    }
                    break;
                case 'deflate':
                    if (function_exists('gzuncompress')) {
                        $body = @gzuncompress($body);
                        if ($body === false) {
                            $errorMessage = "Failed decompressing " . $contentEncoding . " body";
                        }
                    } else {
                        $errorMessage = "Unsupported content-encoding: '$contentEncoding' (missing php function: gzuncompress)";
                    }
                    break;
                case 'gzip':
                    if (function_exists('gzinflate')) {
                        $body = @gzinflate(substr($body, 10, -8));
                        if ($body === false) {
                            $errorMessage = "Failed decompressing " . $contentEncoding . " body";
                        }
                    } else {
                        $errorMessage = "Unsupported content-encoding: '$contentEncoding' (missing php function: gzinflate)";
                    }
                    break;
                case 'identity':
                    break;
                case 'zstd':
                    if (function_exists('zstd_uncompress')) {
                        $body = @zstd_uncompress($body);
                        if ($body === false) {
                            $errorMessage = "Failed decompressing " . $contentEncoding . " body";
                        }
                    } else {
                        $errorMessage = "Unsupported content-encoding: '$contentEncoding' (missing php function: zstd_uncompress)";
                    }
                    break;
                default:
                    $errorMessage = "Unsupported content-encoding: '$contentEncoding'";
            }
            if ($errorMessage !== null) {
                if ($message instanceof RequestInterface) {
                    throw new RequestBodyCantBeDecompressed($errorMessage);
                } else {
                    throw new ResponseBodyCantBeDecompressed($errorMessage);
                }
            }
        }

        return $body;
    }

    /**
     * @param string[] $acceptedEncodings
     * @param string[]|null $contentEncodings
     * @throws RequestBodyCantBeDecompressed
     * @throws ResponseBodyCantBeDecompressed
     */
    protected function transcodeResponseBody(ResponseInterface $response, array $acceptedEncodings, null|array $contentEncodings = null): ResponseInterface
    {
        $noIdentityEncoding = null;
        $acceptedEncodings = $this->normalizeAcceptEncodings($acceptedEncodings, $noIdentityEncoding);

        if ($contentEncodings === null) {
            $contentEncodings = $response->getHeader('Content-Encoding');
        }

        $mustInflate = false;
        if (!in_array('*', $acceptedEncodings)) {
            foreach ($contentEncodings as $contentEncoding) {
                if (!in_array(strtolower($contentEncoding), $acceptedEncodings)) {
                    $mustInflate = true;
                    break;
                }
            }
        }

/// @todo... review this logic - it smells simplistic!
        $shouldDeflate = false;
        if ($mustInflate && array_diff($acceptedEncodings, ['identity', '*'])) {
            /// @todo should we be more careful in explicitly excluding accepted encodings specified with a q=0?
            $shouldDeflate = true;
        }
        if ($mustInflate) {
            $body = $this->decompressMessageBody($response, $contentEncodings);
        }

        if ($shouldDeflate) {
/// @todo... catch+rethrow in a way that allows us to return a 415 response
        } else {

        }

        return $response;
    }

/// @todo... add protected function transcodeRequestBody(RequestInterface $request, ...): RequestInterface

    /**
     * @param string[] $acceptedEncodings
     * @return string[]
     */
    protected function normalizeAcceptEncodings(array $acceptedEncodings, null|bool &$noIdentityEncoding): array
    {
        $noIdentityEncoding = false;
        $out = [];
        foreach ($acceptedEncodings as $acceptedEncoding) {
            $parts = explode(';', $acceptedEncoding, 2);
            $encoding = strtolower($parts[0]);
            if ($encoding === 'x-gzip' || $encoding === 'x-compress') {
                $encoding = substr($encoding, 2);
            }
            // NB: if the same encoding is listed twice, we use the last weight found. That includes a last weight of 0
            if (isset($parts[1]) && preg_match('/^q=(1(?:\\.0{0,3})?|0(?:\\.[0-9]{0,3})?)$/', $parts[1], $matches)) {
                /// @todo would using a regexp instead of a cast be faster?
                if (($weight = (float)$matches[1]) === 0.0) {
                    if ($encoding === 'identity' || $encoding === '*') {
                        /// @see https://www.rfc-editor.org/info/rfc9110/#section-12.5.3: ...without a more specific entry for "identity"
                        if (array_key_exists('identity', $out)) {
                            continue;
                        }
                        $noIdentityEncoding = true;
                    }
                    if (array_key_exists($encoding, $out)) {
                        unset($out[$encoding]);
                    }
                    continue;
                }
                $out[$encoding] = $weight;
            } else {
                $out[$encoding] = 1;
            }

            if (($encoding === 'identity' || $encoding === '*') && $noIdentityEncoding) {
                $noIdentityEncoding = false;
            }
        }

        if (count($out) > 1) {
            arsort($out, SORT_NUMERIC);
        }
        return array_keys($out);
    }

/*
    protected function dechunkMessageBody(MessageInterface $message, null|array $transferEncodings = null): string
    {
        if ($transferEncodings === null) {
            $transferEncodings = $message->getHeader('Transfer-Encoding');
        }

        $stream = $message->getBody();
        $stream->rewind();
        $body = $stream->getContents();
        foreach (array_reverse($transferEncodings) as $transferEncoding) {
            $transferEncoding = strtolower($transferEncoding);
            $errorMessage = null;
            switch ($transferEncoding) {
                /// @todo add support for compress, deflate, gzip (even though those seem exceedingly rare...)
                case 'chunked':
                    $body = $this->decodeChunked($body);
                    break;
                default:
                    $errorMessage = "Unsupported transfer-encoding: '$transferEncoding'";
            }
            if ($errorMessage !== null) {
                if ($message instanceof RequestInterface) {
                    throw new RequestBodyCantBeDecompressed($errorMessage);
                } else {
                    throw new ResponseBodyCantBeDecompressed($errorMessage);
                }
            }
        }

        return $body;
    }

    /// @see HttpClientTrait::dechunk for a better implementation
    protected static function decodeChunked(string $buffer): string
    {
        // length := 0
        //$length = 0;
        $new = '';

        // read chunk-size, chunk-extension (if any) and crlf
        // get the position of the linebreak
        $chunkEnd = strpos($buffer, "\r\n") + 2;
        $temp = substr($buffer, 0, $chunkEnd);
        $chunkSize = hexdec(trim($temp));
        $chunkStart = $chunkEnd;
        while ($chunkSize > 0) {
            $chunkEnd = strpos($buffer, "\r\n", $chunkStart + $chunkSize);

            // just in case we got a broken connection
            if ($chunkEnd == false) {
                $chunk = substr($buffer, $chunkStart);
                // append chunk-data to entity-body
                $new .= $chunk;
                //$length += strlen($chunk);
                break;
            }

            // read chunk-data and crlf
            $chunk = substr($buffer, $chunkStart, $chunkEnd - $chunkStart);
            // append chunk-data to entity-body
            $new .= $chunk;
            // length := length + chunk-size
            //$length += strlen($chunk);
            // read chunk-size and crlf
            $chunkStart = $chunkEnd + 2;

            $chunkEnd = strpos($buffer, "\r\n", $chunkStart) + 2;
            if ($chunkEnd == false) {
                break; // just in case we got a broken connection
            }
            $temp = substr($buffer, $chunkStart, $chunkEnd - $chunkStart);
            $chunkSize = hexdec(trim($temp));
            $chunkStart = $chunkEnd;
        }

        return $new;
    }
*/

}
