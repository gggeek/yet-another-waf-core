<?php
declare(strict_types=1);

namespace YAWAF\Core\Matcher\Logic;

use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use YAWAF\Core\Matcher\MatcherFactoryAwareTrait;
use YAWAF\Core\Matcher\MatcherFactoryInterface;
use YAWAF\Core\Matcher\MatcherInterface;

class MatcherFactory implements MatcherFactoryInterface
{
    use LoggerAwareTrait;
    use MatcherFactoryAwareTrait;

    public function __construct(LoggerInterface|null $logger = null)
    {
        $this->logger = $logger;
    }

    public function supports(string $type): bool
    {
        $type = strtolower(preg_replace('/:[0-9]+$/', '', trim($type)));
        return in_array($type, ['always', 'and', 'never', 'not', 'or']);
    }

    /**
     * @param string $type
     * @param mixed $values
     * @return \YAWAF\Core\Matcher\MatcherInterface
     * @throws \Exception
     */
    public function fromConfiguration(string $type, mixed $values): MatcherInterface
    {
        // allow a numeric suffix to be used, so that many matches of the same type can be in an array where the type is key
        $target = strtolower(preg_replace('/:[0-9]+$/', '', trim($type)));
        switch ($target) {
            case 'always':
/// @todo log a warning if $values is falsey
                return new AlwaysMatcher();
            case 'and':
            case 'or':
                if (!is_array($values) || count($values) <= 1) {
                    throw new \Exception("Invalid logical matching configuration: '$type' should have as value an array with at least 2 elements");
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
                    throw new \Exception("Invalid logical matching configuration: '$type' should have as value an array with a single element");
                }
                return new NegativeMatcher($this->matcherFactory->fromConfiguration(array_key_first($values), reset($values)));
        }
        throw new \Exception("Invalid logical matching configuration: '$type' => " . var_export($values, true));
    }
}
