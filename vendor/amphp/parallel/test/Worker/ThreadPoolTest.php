<?php declare(strict_types=1);

namespace Amp\Parallel\Test\Worker;

use Amp\Parallel\Context\ThreadContext;
use Amp\Parallel\Context\ThreadContextFactory;
use Amp\Parallel\Worker\ContextWorkerFactory;
use Amp\Parallel\Worker\ContextWorkerPool;
use Amp\Parallel\Worker\WorkerPool;

class ThreadPoolTest extends AbstractPoolTest
{
    protected function createPool(
        int $max = WorkerPool::DEFAULT_WORKER_LIMIT,
        ?string $autoloadPath = null,
    ): WorkerPool {
        if (!ThreadContext::isSupported()) {
            $this->markTestSkipped('ext-parallel required');
        }

        $factory = new ContextWorkerFactory(
            bootstrapPath: $autoloadPath,
            contextFactory: new ThreadContextFactory(),
        );

        return new ContextWorkerPool($max, $factory);
    }
}
