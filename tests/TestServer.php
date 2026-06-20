<?php
declare(strict_types=1);

namespace YAWAF\Core\Tests;

class TestServer
{
    const DEFAULT_RESPONSE = ['result' => 'OK', '_GET' => [], '_POST' => [], '_COOKIE' => [], 'headers' => []];

    /**
     * Echoes a json payload with as much info as possible about the request received, to help testing
     */
    public function respond(): void
    {
        header('Content-type: application/json');
        echo json_encode(array_merge(
            self::DEFAULT_RESPONSE,
            [
                '_GET' => $_GET,
                '_POST' => $_POST,
                '_COOKIE' => $_COOKIE,
                'headers' => function_exists('getallheaders') ? getallheaders() : $this->getHeadersFromServer($_SERVER),
                /// @todo add php://input if $_POST is empty and/or the request is not GET / based on content-type req. header
            ])
        );
    }

    /**
     * Implementation from Nyholm\Psr7Server\ServerRequestCreator::getHeadersFromServer(), originally from Laminas\Diactoros\marshalHeadersFromSapi().
     */
    protected function getHeadersFromServer(array $server): array
    {
        $headers = [];
        foreach ($server as $key => $value) {
            // Apache prefixes environment variables with REDIRECT_
            // if they are added by rewrite rules
            if (0 === \strpos($key, 'REDIRECT_')) {
                $key = \substr($key, 9);

                // We will not overwrite existing variables with the
                // prefixed versions, though
                if (\array_key_exists($key, $server)) {
                    continue;
                }
            }

            if ($value && 0 === \strpos($key, 'HTTP_')) {
                $name = \strtr(\strtolower(\substr($key, 5)), '_', '-');
                $headers[$name] = $value;

                continue;
            }

            if ($value && 0 === \strpos($key, 'CONTENT_')) {
                $name = 'content-'.\strtolower(\substr($key, 8));
                $headers[$name] = $value;

                continue;
            }
        }

        return $headers;
    }
}
