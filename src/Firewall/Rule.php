<?php
declare(strict_types=1);

namespace YAWAF\Core\Firewall;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerAwareTrait;
use YAWAF\Core\Filter\Request\RequestFilterInterface;
use YAWAF\Core\Filter\Response\ResponseFilterInterface;
use YAWAF\Core\Matcher\Request\RequestMatcherInterface;
use YAWAF\Core\Matcher\Response\ResponseMatcherInterface;

class Rule implements RequestMatcherInterface, RequestFilterInterface, ResponseFilterInterface
{
    const ACTION_ALLOW = 'allow';
    const ACTION_DENY = 'deny';
    /// @todo (feature creep...)
    //const ACTION_RERUN = 'rerun';

    use LoggerAwareTrait;

    protected RequestMatcherInterface $requestMatcher;
    /** @var RequestFilterInterface[] */
    protected array $requestFilters = [];
    protected string $requestAction = self::ACTION_ALLOW;
    protected null|ResponseMatcherInterface $responseMatcher;
    /** @var ResponseFilterInterface[] */
    protected array $responseFilters = [];
    protected string $responseAction = self::ACTION_ALLOW;

    /**
     * @param RequestMatcherInterface[] $requestMatch
     * @param RequestFilterInterface[] $requestFilters
     * @param string $requestAction
     * @param ResponseMatcherInterface[] $responseMatch
     * @param ResponseFilterInterface[] $responseFilters
     * @param string $responseAction
     */
    public function __construct(RequestMatcherInterface $requestMatcher, array $requestFilters = [], string $requestAction = self::ACTION_ALLOW,
        ResponseMatcherInterface|null $responseMatcher = null, array $responseFilters = [], string $responseAction = self::ACTION_ALLOW)
    {

/// @todo... validate types
/// @todo... only allow configs with no req_match to be accepted if
///          1. req_action is not deny and
///          2. either there are req_filters or resp_filters or resp_match+resp_action=deny

/// @todo... throw if there are req_filters, resp_match, resp_action or resp_filters when req_action is deny
/// @todo... throw if there are resp_filters when resp_action is deny

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
            throw new \Exception('Rule expected a ServerRequestInterface to match but got instead a ' . gettype($items[0]));
        }

        return $this->matchesRequest($items[0]);
    }

    public function matchesRequest(ServerRequestInterface $request): bool
    {
        return $this->requestMatcher->matchesRequest($request);
    }

    public function filterRequest(ServerRequestInterface $request): ServerRequestInterface|ResponseInterface|false
    {
        if ($this->requestAction === self::ACTION_DENY) {
            return false;
        }

        foreach ($this->requestFilters as $requestFilter) {
            $request = $requestFilter->filterRequest($request);
            if ($request === false || $request instanceof ResponseInterface) {
                return $request;
            }
        }
        return $request;
    }

    protected function matchesResponse(ResponseInterface $response): bool
    {
        return $this->responseMatcher->matchesResponse($response);
    }

    public function filterResponse(ResponseInterface $response, ServerRequestInterface $request): ResponseInterface|false
    {
        if ($this->matchesResponse($response)) {
            if ($this->responseAction === self::ACTION_DENY) {
                return false;
            }
            foreach ($this->responseFilters as $responseFilter) {
                $response = $responseFilter->filterResponse($response, $request);
                if ($response === false) {
                    return false;
                }
            }
        }
        return $response;
    }
}
