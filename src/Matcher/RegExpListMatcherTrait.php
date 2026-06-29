<?php
declare(strict_types=1);

namespace YAWAF\Core\Matcher;

/**
 * Allows matching a string that must fall within an allowed list of regexes
 * @todo allow to set (globally?) the regexpDelimiter
 */
trait RegExpListMatcherTrait
{
    /** @var string[] $allowedValues */
    protected array $allowedValues;
    protected string $regexpDelimiter = ':';
    protected string $regexp;

    /**
     * @param string|string[] $values these can be either regexps, glob-expressions or plain strings, depending on the
     *                                conversion done by `normalizeMatchingRegexp`
     * @throws \Exception
     */
    protected function setMatchingValues(string|array $values): void
    {
        if (is_array($values)) {
            if (!$values) {
                throw new \Exception('At least one string is required as argument to the matcher');
            }
            $this->allowedValues = [];
            foreach ($values as $value) {
                if (!is_string($value)) {
                    throw new \Exception('Only arrays of strings are allowed as argument to the matcher');
                }
                $this->allowedValues[] = $this->normalizeMatchingRegexp($value);
            }
            $this->regexp = $this->regexpDelimiter . '(' . implode('|', $this->allowedValues) . ')' . $this->regexpDelimiter;
        } else {
            $this->regexp = $this->regexpDelimiter . $this->normalizeMatchingRegexp($values) . $this->regexpDelimiter;
        }
    }

    protected function matchesRegexp(string $value): bool
    {
        return (bool)preg_match($this->regexp, $value);
    }

    /**
     * To be reimplemented in subclasses
     * @param string $value
     * @return string
     */
    protected function normalizeMatchingRegexp(string $value): string
    {
        return preg_quote($value, $this->regexpDelimiter);
    }

    protected function wildcardToRegexp(string $value): string
    {
        return '^' . str_replace(['\\*'], ['.*'], preg_quote($value, $this->regexpDelimiter)) . '$';
    }

    public function getRegexpDelimiter(): string
    {
        return $this->regexpDelimiter;
    }
}
