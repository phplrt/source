<?php

declare(strict_types=1);

namespace Phplrt\Source;

use Phplrt\Contracts\Source\FileInterface;
use Phplrt\Source\Exception\HashCalculationException;
use Phplrt\Source\Exception\NotReadableException;

class File extends Readable implements FileInterface
{
    /**
     * @psalm-taint-sink file $filename
     */
    public function __construct(
        /**
         * @var non-empty-string
         */
        private readonly string $filename,
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
        private readonly int $chunkSize = SourceFactory::DEFAULT_CHUNK_SIZE,
    ) {
        assert($filename !== '', 'Filename must not be empty');
        assert($algo !== '', 'Hashing algorithm name must not be empty');
        assert($chunkSize >= 1, 'Chunk size must be greater than 0');
    }

    public function getContents(): string
    {
        try {
            if (\Fiber::getCurrent() !== null) {
                return $this->asyncGetContents();
            }

            return $this->syncGetContents();
        } catch (\Throwable $e) {
            throw NotReadableException::fromInternalFileError($this->filename, $e);
        }
    }

    /**
     * @throws \Throwable
     */
    private function asyncGetContents(): string
    {
        $file = \fopen($this->filename, 'rb');
        \stream_set_blocking($file, false);
        \flock($file, \LOCK_SH);

        \Fiber::suspend();
        $buffer = '';

        while (!\feof($file)) {
            $buffer .= \fread($file, $this->chunkSize);

            \Fiber::suspend();
        }

        \flock($file, \LOCK_UN);
        \fclose($file);

        return $buffer;
    }

    /**
     * @throws \ErrorException
     */
    private function syncGetContents(): string
    {
        \error_clear_last();

        $result = @\file_get_contents($this->filename);

        if ($result === false) {
            throw NotReadableException::createFromLastInternalError();
        }

        return $result;
    }

    /**
     * @throws NotReadableException
     */
    public function getStream(): mixed
    {
        $stream = \fopen($this->filename, 'rb');

        if (!\is_resource($stream)) {
            throw NotReadableException::fromOpeningFile($this->filename);
        }

        return $stream;
    }

    /**
     * @throws HashCalculationException
     */
    public function getHash(): string
    {
        try {
            return \hash_file($this->algo, $this->filename);
        } catch (\ValueError $e) {
            throw HashCalculationException::fromInvalidHashAlgo($this->algo, $e);
        }
    }

    public function getPathname(): string
    {
        return $this->filename;
    }
}
