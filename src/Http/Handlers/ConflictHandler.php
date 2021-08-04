<?php

declare(strict_types=1);

namespace HelpScout\Api\Http\Handlers;

use HelpScout\Api\Exception\ConflictException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class ConflictHandler
{
    public function __invoke(callable $handler)
    {
        return function (RequestInterface $request, array $options = []) use ($handler) {
            return $handler($request, $options)->then(
                function (ResponseInterface $response) use ($request) {
                    if ($response->getStatusCode() === 409) {
                        throw new ConflictException('Conflict - entity cannot be created', $request, $response);
                    }

                    return $response;
                }
            );
        };
    }
}
