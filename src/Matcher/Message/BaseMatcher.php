<?php
declare(strict_types=1);

namespace YAWAF\Core\Matcher\Message;

use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use YAWAF\Core\Matcher\Request\RequestMatcherInterface;
use YAWAF\Core\Matcher\Response\ResponseMatcherInterface;

abstract class BaseMatcher implements MessageMatcherInterface, RequestMatcherInterface, ResponseMatcherInterface
{
    /**
     * @param ...$items
     * @return bool
     * @throws \Exception
     */
    public function matches(...$items): bool
    {
        if (count($items) !== 1 || ! $items[0] instanceof MessageInterface) {
            throw new \Exception('Request Matcher expected a single MessageInterface element to match');
        }
        return $this->matchesMessage($items[0]);
    }

    public function matchesRequest(ServerRequestInterface $request): bool
    {
        return $this->matchesMessage($request);
    }

    function matchesResponse(ResponseInterface $response): bool
    {
        return $this->matchesMessage($response);
    }

    abstract function matchesMessage(MessageInterface $message): bool;
}
