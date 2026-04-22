<?php declare(strict_types=1);

namespace Amp\Parallel\Test\Worker;

use Amp\Parallel\Context\ProcessContextFactory;
use Amp\Parallel\Worker\ContextWorkerFactory;
use Amp\Parallel\Worker\ContextWorkerPool;
use Amp\Parallel\Worker\DelegatingWorkerPool;
use Amp\Parallel\Worker\WorkerPool;

class DelegatingWorkerPoolTest extends AbstractPoolTest
{
    protected function createPool(
        int $max = WorkerPool::DEFAULT_WORKER_LIMIT,
        ?string $autoloadPath = null,
    ): WorkerPool {
        $pool = new ContextWorkerPool(
            limit: $max * 2,
            factory: new ContextWorkerFactory($autoloadPath, contextFactory: new ProcessContextFactory()),
        );

        return new DelegatingWorkerPool($pool, $max);
    }
}
