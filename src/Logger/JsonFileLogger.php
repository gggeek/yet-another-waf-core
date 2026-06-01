<?php
declare(strict_types=1);

namespace YAWAF\Core\Logger;

class JsonFileLogger extends FileLogger
{
    protected function formatMessage($level, string|\Stringable $message, array $context = []): string
    {
        return json_encode([
            'level' => $level,
            'timestamp' => microtime(true),
            'message' => $message,
            'context' => $context,
        ]);
    }
}
