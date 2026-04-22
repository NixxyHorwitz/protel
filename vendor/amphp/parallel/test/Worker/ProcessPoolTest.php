<?php declare(strict_types=1);

namespace Amp\Parallel\Test\Worker;

use Amp\Parallel\Context\ProcessContextFactory;
use Amp\Parallel\Worker\ContextWorkerFactory;
use Amp\Parallel\Worker\ContextWorkerPool;
use Amp\Parallel\Worker\WorkerPool;

class ProcessPoolTest extends AbstractPoolTest
{
    protected function createPool(
        int $max = WorkerPool::DEFAULT_WORKER_LIMIT,
        ?string $autoloadPath = null,
    ): WorkerPool {
        $factory = new ContextWorkerFactory(
            bootstrapPath: $autoloadPath,
            contextFactory: new ProcessContextFactory(),
        );

        return new ContextWorkerPool($max, $factory);
    }
}
