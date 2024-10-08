<?php

declare(strict_types=1);

namespace Phplrt\Source;

class VirtualStreamingFile extends Stream implements VirtualFileInterface
{
    /**
     * @psalm-taint-sink file $filename
     * @param resource $stream
     * @param non-empty-string $algo
     * @param int<1, max> $chunkSize
     */
    public function __construct(
        /**
         * @var non-empty-string
         */
        private readonly string $filename,
        mixed $stream,
        string $algo = SourceFactory::DEFAULT_HASH_ALGO,
        int $chunkSize = SourceFactory::DEFAULT_CHUNK_SIZE
    ) {
        assert($filename !== '', 'Filename must not be empty');

        parent::__construct($stream, $algo, $chunkSize);
    }

    public function getPathname(): string
    {
        return $this->filename;
    }

    /**
     * @return array<non-empty-string, mixed>
     */
    public function __serialize(): array
    {
        return \array_merge(parent::__serialize(), [
            'file' => $this->filename,
        ]);
    }

    /**
     * @param array<non-empty-string, mixed> $data
     *
     * @throws \ErrorException
     */
    public function __unserialize(array $data): void
    {
        $this->filename = $data['file'] ?? 'php://memory';

        parent::__unserialize($data);
    }
}
