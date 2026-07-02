<?php
declare(strict_types=1);

namespace YAWAF\Core\Firewall;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerAwareTrait;
use YAWAF\Core\Exception\RequestDenied;
use YAWAF\Core\Filter\Server\Request\RequestFilterInterface;
use YAWAF\Core\Filter\Server\Response\ResponseFilterInterface;
use YAWAF\Core\Logger\PrivateLoggerTrait;
use YAWAF\Core\Matcher\Logic\AlwaysMatcher;
use YAWAF\Core\Matcher\Request\RequestMatcherInterface;
use YAWAF\Core\Matcher\Response\ResponseMatcherInterface;
use YAWAF\Core\Stdlib;

class Rule implements RequestMatcherInterface, RequestFilterInterface, ResponseFilterInterface
{
    use LoggerAwareTrait;
    use PrivateLoggerTrait;

    protected RequestMatcherInterface $requestMatcher;
    /** @var RequestFilterInterface[] */
    protected array $requestFilters = [];
    protected RuleAction $requestAction = RuleAction::Allow;
    protected null|ResponseMatcherInterface $responseMatcher;
    /** @var ResponseFilterInterface[] */
    protected array $responseFilters = [];
    protected RuleAction $responseAction = RuleAction::Allow;

    /**
     * @param RequestMatcherInterface $requestMatcher
     * @param RequestFilterInterface[] $requestFilters
     * @param RuleAction $requestAction
     * @param ResponseMatcherInterface|null $responseMatcher
     * @param ResponseFilterInterface[] $responseFilters
     * @param RuleAction $responseAction
     * @throws \Exception
     */
    public function __construct(RequestMatcherInterface $requestMatcher, array $requestFilters = [], RuleAction $requestAction = RuleAction::Allow,
        ResponseMatcherInterface|null $responseMatcher = null, array $responseFilters = [], RuleAction $responseAction = RuleAction::Allow)
    {
        if (! Stdlib::array_of($requestFilters, RequestFilterInterface::class)) {
            throw new \InvalidArgumentException('requestFilters argument to Rule constructor must be an array of RequestFilterInterface');
        }
        if (! Stdlib::array_of($responseFilters, ResponseFilterInterface::class)) {
            throw new \InvalidArgumentException('responseFilters argument to Rule constructor must be an array of ResponseFilterInterface');
        }

/// @todo... review these checks - make sure they are in sync with what is built by the RuleFactory
        if ($requestAction === RuleAction::Deny) {
            if ($requestFilters || $responseFilters || $responseAction !== RuleAction::Allow) {
                throw new \Exception('A firewall rule with Deny request action can never fulfill request filters, response filters or response actions');
            }
            if ($requestMatcher instanceof AlwaysMatcher) {
                $this->warning('A firewall rule with Deny request action and matching all requests is a bad smell. The firewall default is to block all requests not matching any rule...');
            }
        }
        if ($responseAction === RuleAction::Deny) {
            if ($responseFilters) {
                throw new \Exception('A firewall rule with Deny response action can never fulfill response filters');
            }
            if ($responseMatcher instanceof AlwaysMatcher) {
                $this->warning('A firewall rule with Deny response action and matching all responses is a bad smell. Are you sure you did not mean to deny the request instead?');
            }
        }

        $this->requestMatcher = $requestMatcher;
        $this->requestFilters = $requestFilters;
        $this->requestAction = $requestAction;
        $this->responseMatcher = $responseMatcher;
        $this->responseFilters = $responseFilters;
        $this->responseAction = $responseAction;
    }

    public function matches(...$items): bool
    {
        if (count($items) !== 1 || ! $items[0] instanceof ServerRequestInterface) {
            throw new \InvalidArgumentException('Rule expected a ServerRequestInterface to match but got instead a ' . gettype($items[0]));
        }

        return $this->matchesRequest($items[0]);
    }

    public function matchesRequest(ServerRequestInterface $request): bool
    {
        return $this->requestMatcher->matchesRequest($request);
    }

    public function filterRequest(ServerRequestInterface $request): ServerRequestInterface|ResponseInterface
    {
        if ($this->requestAction === RuleAction::Deny) {
            throw new RequestDenied();
        }

        foreach ($this->requestFilters as $requestFilter) {
            $request = $requestFilter->filterRequest($request);
            if (/*$request === false ||*/ $request instanceof ResponseInterface) {
                return $request;
            }
        }
        return $request;
    }

    protected function matchesResponse(ResponseInterface $response): bool
    {
        return (bool)$this->responseMatcher?->matchesResponse($response);
    }

    public function filterResponse(ResponseInterface $response, ServerRequestInterface $request): ResponseInterface
    {
        if ($this->matchesResponse($response)) {
            if ($this->responseAction === RuleAction::Deny) {
                throw new RequestDenied();
            }
            foreach ($this->responseFilters as $responseFilter) {
                $response = $responseFilter->filterResponse($response, $request);
                //if ($response === false) {
                //    return false;
                //}
            }
        }
        return $response;
    }
}
