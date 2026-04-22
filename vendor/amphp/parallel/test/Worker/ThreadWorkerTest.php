<?php declare(strict_types=1);

namespace Amp\Parallel\Test\Worker;

use Amp\Parallel\Context\ThreadContext;
use Amp\Parallel\Context\ThreadContextFactory;
use Amp\Parallel\Worker\ContextWorkerFactory;
use Amp\Parallel\Worker\Worker;

class ThreadWorkerTest extends AbstractWorkerTest
{
    protected function createWorker(?string $autoloadPath = null): Worker
    {
        if (!ThreadContext::isSupported()) {
            $this->markTestSkipped('ext-parallel required');
        }

        $factory = new ContextWorkerFactory(
            bootstrapPath: $autoloadPath,
            contextFactory: new ThreadContextFactory(),
        );

        return $factory->create();
    }
}
