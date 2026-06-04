<?php
declare(strict_types=1);

namespace YAWAF\Core\Tests;

class TestServer
{
    const DEFAULT_RESPONSE = ['result' => 'OK'];

    public function respond(): void
    {
        header('Content-type: application/json');
        echo json_encode(self::DEFAULT_RESPONSE);
    }
}
