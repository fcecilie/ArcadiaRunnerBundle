<?php

namespace Arcadia\Bundle\RunnerBundle\Service;

use Arcadia\Bundle\RunnerBundle\Entity\TaskInterface;

interface TaskHandlerInterface
{
    public function handle(TaskInterface $task): int;
}