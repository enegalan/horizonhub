<?php

namespace App\Support;

final readonly class StreamOperation
{
    /**
     * The mode of the stream operation.
     */
    public string $mode;

    /**
     * The parts of the stream operation.
     *
     * @var array<int, mixed>
     */
    public array $parts;

    /**
     * The constructor.
     *
     * @param string $mode The mode of the stream operation.
     * @param array<int, mixed> $parts The parts of the stream operation.
     */
    public function __construct(string $mode, array $parts)
    {
        $this->mode = $mode;
        $this->parts = $parts;
    }

    /**
     * Create a new stream operation from an array.
     *
     * @param array<int, mixed> $operation
     */
    public static function fromArray(array $operation): self
    {
        $mode = (string) ($operation[0] ?? '');

        if ($mode === '') {
            throw new \InvalidArgumentException('Invalid mode: ');
        }

        return new self($mode, $operation);
    }
}
