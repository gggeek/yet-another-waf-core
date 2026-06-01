<?php
declare(strict_types=1);

namespace YAWAF\Core\Filter\Bidirectional;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class FilterChain implements BidirectionalFilterInterface
{
    /** @var BidirectionalFilterInterface[] */
    protected array $filters = [];
    /** @var ServerRequestInterface */
    protected array $requestChain = [];

    public function __construct(array $filters)
    {
        foreach ($filters as $filter) {
            $this->addFilter($filter);
        }
    }

    public function addFilter(BidirectionalFilterInterface $filter)
    {
        $this->filters[] = $filter;
    }

    public function filterRequest(ServerRequestInterface $request): ServerRequestInterface|ResponseInterface|false
    {
        $this->requestChain = [];
        foreach ($this->filters as $filter) {
            $this->requestChain[] = $request;
            $request = $filter->filterRequest($request);
            if ($request === false || $request instanceof ResponseInterface) {
                $this->requestChain = [];
                return $request;
            }
        }
        return $request;
    }

    public function filterResponse(ResponseInterface $response, ServerRequestInterface $request): ResponseInterface|false
    {
        for ($i = count($this->filters) - 1; $i >= 0; $i--) {
            $response = $this->filters[$i]->filterResponse($response, $this->requestChain[$i]);
            if ($response === false) {
                $this->requestChain = [];
                return false;
            }
        }
        $this->requestChain = [];
        return $response;
    }
}
