<?php
declare(strict_types=1);

namespace YAWAF\Core\Matcher;

interface MatcherFactoryInterface
{
    public function supports(string $type): bool;

    /**
     * @param string $type
     * @param mixed $values
     * @return MatcherInterface
     * @throws \Exception
     */
    public function fromConfiguration(string $type, mixed $values): MatcherInterface;
}
