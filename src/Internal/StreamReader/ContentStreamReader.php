<?php

/**
 * This file is part of phplrt package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Phplrt\Source\Internal\StreamReader;

use Phplrt\Source\Exception\NotAccessibleException;
use Phplrt\Source\Internal\StreamReaderInterface;

/**
 * @internal This is an internal library class, please do not use it in your code.
 * @psalm-internal Phplrt\Source
 */
final class ContentStreamReader implements StreamReaderInterface
{
    /**
     * @var string
     */
    private const MEMORY_FILENAME = 'php://memory';

    /**
     * @var string
     */
    private const MEMORY_MODE = 'rb+';

    /**
     * @var string
     */
    private const ERROR_MEMORY_WRITING = 'Can not write content data into ' . self::MEMORY_FILENAME;

    /**
     * @var string
     */
    private const ERROR_MEMORY_NON_REWINDABLE = self::MEMORY_FILENAME . ' is not rewindable';

    /**
     * ContentStreamReader constructor.
     *
     * @param string $content
     */
    public function __construct(
        private readonly string $content
    ) {
    }

    /**
     * @return resource
     */
    public function getStream(): mixed
    {
        /** @var resource $memory */
        $memory = \fopen(self::MEMORY_FILENAME, self::MEMORY_MODE);

        if (@\fwrite($memory, $this->content) === false) {
            throw new NotAccessibleException(self::ERROR_MEMORY_WRITING);
        }

        if (@\rewind($memory) === false) {
            throw new NotAccessibleException(self::ERROR_MEMORY_NON_REWINDABLE);
        }

        return $memory;
    }
}
