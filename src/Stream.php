<?php

declare(strict_types=1);

namespace Phplrt\Source;

use Phplrt\Contracts\Source\SourceExceptionInterface;
use Phplrt\Source\Exception\HashCalculationException;
use Phplrt\Source\Exception\NotReadableException;

class Stream extends Readable
{
    /**
     * Initial offset of the stream to return to the specified offset after
     * reading the data.
     *
     * @var int<0, max>
     */
    private readonly int $offset;

    public function __construct(
        /**
         * @var resource
         */
        private readonly mixed $stream,
        /**
         * Hashing algorithm for the source.
         *
         * @var non-empty-string
         */
        private readonly string $algo = SourceFactory::DEFAULT_HASH_ALGO,
        /**
         * The chunk size used while non-blocking reading the file inside
         * the {@see \Fiber}.
         *
         * @var int<1, max>
         */
        private readonly int $chunkSize = SourceFactory::DEFAULT_CHUNK_SIZE
    ) {
        assert(\is_resource($stream), 'Stream argument must be a valid resource stream');
        assert($algo !== '', 'Hashing algorithm name must not be empty');
        assert($chunkSize >= 1, 'Chunk size must be greater than 0');

        $this->offset = (int) \ftell($stream);
    }

    public function getContents(): string
    {
        try {
            if (\Fiber::getCurrent() !== null) {
                return $this->asyncGetContents();
            }

            return $this->syncGetContents();
        } catch (\Throwable $e) {
            throw NotReadableException::fromInternalStreamError($e);
        }
    }

    /**
     * @throws \Throwable
     */
    private function asyncGetContents(): string
    {
        \stream_set_blocking($this->stream, false);
        \flock($this->stream, \LOCK_SH);

        \Fiber::suspend();
        $buffer = '';

        while (!\feof($this->stream)) {
            $buffer .= \fread($this->stream, $this->chunkSize);

            \Fiber::suspend();
        }

        \flock($this->stream, \LOCK_UN);
        \fseek($this->stream, $this->offset);

        return $buffer;
    }

    /**
     * @throws \ErrorException
     */
    private function syncGetContents(): string
    {
        \error_clear_last();

        $result = @\stream_get_contents($this->stream);

        if ($result === false) {
            throw NotReadableException::createFromLastInternalError();
        }

        return $result;
    }

    public function getStream(): mixed
    {
        return $this->stream;
    }

    /**
     * @throws HashCalculationException
     * @throws SourceExceptionInterface
     */
    public function getHash(): string
    {
        try {
            $metadata = \stream_get_meta_data($this->stream);

            // In the case that the stream is a link to a local file, we can
            // speed up hash generation using the low-level hashing API.
            if (\stream_is_local($metadata['uri'])) {
                return \hash_file($this->algo, $metadata['uri']);
            }

            return \hash($this->algo, $this->getContents());
        } catch (\ValueError $e) {
            throw HashCalculationException::fromInvalidHashAlgo($this->algo, $e);
        }
    }

    /**
     * @return array<non-empty-string, mixed>
     */
    public function __serialize(): array
    {
        \error_clear_last();

        $meta = @\stream_get_meta_data($this->stream);

        if (\error_get_last() !== null) {
            return [];
        }

        return [
            'uri' => $meta['uri'],
            'mode' => $meta['mode'],
            'seek' => $this->offset,
            'algo' => $this->algo,
            'chunk' => $this->chunkSize,
        ];
    }

    /**
     * @param array<non-empty-string, mixed> $data
     *
     * @throws \ErrorException
     */
    public function __unserialize(array $data): void
    {
        $this->algo = (string) ($data['algo'] ?? SourceFactory::DEFAULT_HASH_ALGO);
        $this->chunkSize = \max(1, (int) ($data['chunk'] ?? SourceFactory::DEFAULT_CHUNK_SIZE));
        $this->offset = \max(0, (int) ($data['seek'] ?? 0));

        \error_clear_last();

        $stream = @\fopen(
            $data['uri'] ?? 'php://memory',
            $data['mode'] ?? 'rb',
        );

        if ($stream === false) {
            throw NotReadableException::createFromLastInternalError();
        }

        $this->stream = $stream;
        \fseek($this->stream, $this->offset);
    }
}
