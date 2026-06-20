<?php
declare(strict_types=1);

namespace YAWAF\Core\Tests;

use YAWAF\Core\Stdlib;

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
                'headers' => function_exists('getallheaders') ? getallheaders() : Stdlib::getHeadersFromServer($_SERVER),
                /// @todo add php://input if $_POST is empty and/or the request is not GET / based on content-type req. header
            ])
        );
    }
}
