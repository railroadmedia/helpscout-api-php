<?php

declare(strict_types=1);

namespace HelpScout\Api\Customers\Entry;

use HelpScout\Api\Entity\Extractable;

class PropertyOperation implements Extractable
{
    public const OPERATION_REMOVE = 'remove';
    public const OPERATION_REPLACE = 'replace';

    /**
     * @var string
     */
    private $operation;

    /**
     * @var string
     */
    private $path;

    /**
     * @var string
     */
    private $value;

    public function __construct($operation = null, $path = null, $value = null)
    {
        $this->operation = $operation;
        $this->path = '/' . $path;
        $this->value = $value;
    }

    /**
     * {@inheritdoc}
     */
    public function extract(): array
    {
        $result = [
            'op' => $this->operation,
            'path' => $this->path,
        ];

        if ($this->operation == self::OPERATION_REPLACE) {
            $result['value'] = $this->value;
        }

        return $result;
    }

    public function getOperation()
    {
        return $this->operation;
    }

    /**
     * @param string $operation
     */
    public function setOperation(string $operation): PropertyOperation
    {
        $this->operation = $operation;

        return $this;
    }

    public function getPath()
    {
        return $this->path;
    }

    /**
     * @param string $path
     */
    public function setPath(string $path): PropertyOperation
    {
        $this->path = '/' . $path;

        return $this;
    }

    public function getValue()
    {
        return $this->value;
    }

    /**
     * @param string $value
     */
    public function setValue(string $value): PropertyOperation
    {
        $this->value = $value;

        return $this;
    }
}
