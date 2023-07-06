<?php

declare(strict_types=1);

namespace HelpScout\Api\Http\Hal;

use HelpScout\Api\Exception\RuntimeException;
use Rize\UriTemplate;

class HalLink
{
    /**
     * The first page.
     */
    const REL_FIRST = 'first';

    /**
     * The last page.
     */
    const REL_LAST = 'last';

    /**
     * The next page.
     */
    const REL_NEXT = 'next';

    /**
     * The previous page.
     */
    const REL_PREVIOUS = 'previous';

    /**
     * The self relation.
     */
    const REL_SELF = 'self';

    /**
     * @var string
     */
    private $rel;

    /**
     * @var string
     */
    private $href;

    /**
     * @var bool
     */
    private $templated;

    public function __construct(string $rel, string $href, bool $templated)
    {
        $this->rel = $rel;
        $this->href = $href;
        $this->templated = $templated;
    }

    public function getRel(): string
    {
        return $this->rel;
    }

    public function getHref(): string
    {
        return $this->href;
    }

    public function isTemplated(): bool
    {
        return $this->templated;
    }

    public function expand(array $params): string
    {
        if (!$this->isTemplated()) {
            throw new RuntimeException(sprintf('The link "%s" is not templated', $this->getRel()));
        }

        return (new UriTemplate)->expand($this->getHref(), $params);
    }
}
