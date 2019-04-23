<?php
/**
 * This file is part of Phplrt package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare(strict_types=1);

namespace Phplrt\Exception;

use Phplrt\Io\Readable;
use Phplrt\Position\PositionInterface;

/**
 * Interface ExternalExceptionInterface
 */
interface ExternalExceptionInterface extends
    \Throwable,
    PositionInterface,
    MutableTraceInterface,
    MutableExceptionInterface
{
    /**
     * @param Readable $file
     * @param int $offsetOrLine
     * @param int|null $column
     * @return ExternalExceptionInterface
     */
    public function throwsIn(Readable $file, int $offsetOrLine = 0, int $column = null): self;

    /**
     * @param \Throwable $exception
     * @return ExternalExceptionInterface|$this
     */
    public function from(\Throwable $exception): self;
}
