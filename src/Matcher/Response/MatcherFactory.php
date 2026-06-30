<?php
declare(strict_types=1);

namespace YAWAF\Core\Matcher\Response;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use YAWAF\Core\Matcher\MatcherFactoryInterface;
use YAWAF\Core\Matcher\MatcherInterface;
use YAWAF\Core\Matcher\Message\BodyMatcher;
use YAWAF\Core\Matcher\Message\ContentTypeMatcher;
use YAWAF\Core\Matcher\Message\HeaderMatcher;
use YAWAF\Core\Matcher\OptionAwareMatcherFactoryTrait;

class MatcherFactory implements MatcherFactoryInterface
{
    use LoggerAwareTrait;
    use OptionAwareMatcherFactoryTrait;

    public function __construct(LoggerInterface|null $logger = null)
    {
        $this->logger = $logger;
    }

    public function supports(string $type): bool
    {
        $type = strtolower(preg_replace('/:[0-9]+$/', '', trim($type)));
        return in_array($type, ['body', 'content_type', 'http_header', 'status_code']);
    }

    /**
     * @param string $type
     * @param mixed $values
     * @return \YAWAF\Core\Matcher\MatcherInterface
     * @throws \Exception
     */
    public function fromConfiguration(string $type, mixed $values): MatcherInterface
    {
        $target = $this->parseMatcherType($type);
        switch($target['type']) {
            case 'body':
                $matcher = new BodyMatcher($values, $target['caseInsensitive'], $target['expandWildcards']);
                break;
            case 'content_type':
                /// @todo throw if $target['caseInsensitive'] is used
                $matcher = new ContentTypeMatcher($values, $target['expandWildcards']);
                break;
            case 'http_header':
                if (!is_array($values) || count($values) !== 1) {
                    throw new \Exception("Invalid response matching configuration: '$type' should be followed with an object with 1 element only");
                }
                $hv = reset($values);
                $hn = array_key_first($values);
                if (!is_string($hn) || !(is_string($hv) || is_array($hv))) {
                    throw new \Exception("Invalid response matching configuration: '$type' should be followed with an object with 1 element: a string name, and a string or string[] for values");
                }
                $matcher = new HeaderMatcher($hn, $hv, $target['caseInsensitive'], $target['expandWildcards']);
                break;
            case 'status_code':
                /// @todo throw if $target['caseInsensitive'] is used
                $matcher = new StatusCodeMatcher($values, $target['expandWildcards']);
                break;
            default:
                throw new \Exception("Invalid response matching configuration: '$type' => " . var_export($values, true));
        }
        if ($this->logger && $matcher instanceof LoggerAwareInterface) {
            $matcher->setLogger($this->logger);
        }
        return $matcher;
    }
}
