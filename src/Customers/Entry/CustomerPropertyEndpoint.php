<?php

declare(strict_types=1);

namespace HelpScout\Api\Customers\Entry;

use HelpScout\Api\Endpoint;
use HelpScout\Api\Entity\Collection;

class CustomerPropertyEndpoint extends Endpoint
{
    public const CUSTOMER_PROPERTIES = '/v2/customers/%d/properties';

    public function updateProperties(int $customerId, Collection $properties): void
    {
        $this->restClient->patchResource(
            $properties, sprintf(self::CUSTOMER_PROPERTIES, $customerId)
        );
    }
}
