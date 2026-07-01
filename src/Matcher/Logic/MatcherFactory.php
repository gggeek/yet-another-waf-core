<?php
declare(strict_types=1);

namespace YAWAF\Core\Matcher\Logic;

use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use YAWAF\Core\Exception\ConfigurationError;
use YAWAF\Core\Matcher\MatcherFactoryAwareTrait;
use YAWAF\Core\Matcher\MatcherFactoryInterface;
use YAWAF\Core\Matcher\MatcherInterface;
use YAWAF\Core\Matcher\SuffixedMatcherFactory;

class MatcherFactory extends SuffixedMatcherFactory implements MatcherFactoryInterface
{
    use LoggerAwareTrait;
    use MatcherFactoryAwareTrait;

    protected array $supportedMatcherTypes = ['always', 'and', 'or', 'ever', 'not'];

    public function __construct(LoggerInterface|null $logger = null)
    {
        $this->logger = $logger;
    }

    /**
     * @param string $type
     * @param mixed $values
     * @return \YAWAF\Core\Matcher\MatcherInterface
     * @throws \Exception
     */
    public function fromConfiguration(string $type, mixed $values): MatcherInterface
    {
        switch ($this->getMatcherType($type)) {
            case 'always':
                /// @todo log a warning if $values is falsey
                return new AlwaysMatcher();
            case 'and':
            case 'or':
                if (!is_array($values) || count($values) <= 1) {
                    throw new ConfigurationError("Invalid logical matching configuration: '$type' should have as value an array with at least 2 elements");
                }
                $matchers = [];
                foreach ($values as $type => $subValues) {
                    $matchers[] = $this->matcherFactory->fromConfiguration($type, $subValues);
                }
                return $target === 'and' ? new AndMatcher($matchers) : new OrMatcher($matchers);
            case 'never':
                /// @todo log a warning if $values is falsey
                return new NeverMatcher();
            case 'not':
                if (!is_array($values) || count($values) !== 1) {
                    throw new ConfigurationError("Invalid logical matching configuration: '$type' should have as value an array with a single element");
                }
                return new NegativeMatcher($this->matcherFactory->fromConfiguration(array_key_first($values), reset($values)));
        }
        throw new ConfigurationError("Invalid logical matching configuration: '$type' => " . var_export($values, true));
    }
}
