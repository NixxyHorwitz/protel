<?php declare(strict_types=1);

namespace Amp\Parallel\Test\Worker;

use Amp\Parallel\Context\ProcessContextFactory;
use Amp\Parallel\Worker\ContextWorkerFactory;
use Amp\Parallel\Worker\Worker;

class ProcessWorkerTest extends AbstractWorkerTest
{
    protected function createWorker(?string $autoloadPath = null): Worker
    {
        $factory = new ContextWorkerFactory(
            bootstrapPath: $autoloadPath,
            contextFactory: new ProcessContextFactory(),
        );

        return $factory->create();
    }
}
