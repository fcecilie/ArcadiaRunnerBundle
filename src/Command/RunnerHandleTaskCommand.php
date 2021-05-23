<?php

namespace Arcadia\Bundle\RunnerBundle\Command;

use Arcadia\Bundle\RunnerBundle\Entity\TaskInterface;
use Arcadia\Bundle\RunnerBundle\Service\TaskHandlerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;

class RunnerHandleTaskCommand extends Command
{
    protected static $defaultName = 'arcadia:runner:handle-task';

    private array $config;

    private SymfonyStyle $io;
    private ManagerRegistry $registry;
    private ContainerInterface $container;
    private TaskHandlerInterface $taskHandler;
    private TaskInterface $task;

    public function __construct(ManagerRegistry $registry, ContainerInterface $container, array $config = [])
    {
        parent::__construct();
        $this->registry = $registry;
        $this->container = $container;
        $this->config = $config;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Handle a task.')
            ->addArgument('runnerName', InputArgument::REQUIRED, 'The runner name.')
            ->addArgument('taskId', InputArgument::REQUIRED, 'The id of the task to handle.')
        ;
    }

    protected function inputIsValid(InputInterface $input): bool
    {
        $runnerName = $input->getArgument('runnerName');
        $taskId = $input->getArgument('taskId');

        // Check runnerName
        $runner = $this->config[$runnerName] ?? null;
        if (!$runner) {
            $this->io->error("Runner '$runnerName' not found in the bundle configuration. Check your 'arcadia_runner.yaml' to add it.");
            return false;
        }

        // Check handler
        /** @var TaskHandlerInterface $taskHandler */
        $taskHandler = $this->container->get($runner['task']['handler']);
        if (!$taskHandler) {
            $this->io->error("Handler '{$runner['task']['handler']}' not found in container. Please, configure it as public.");
            return false;
        }

        $taskManager = $this->registry->getManager($runner['task']['doctrine_manager']);
        $taskRepository = $taskManager->getRepository($runner['task']['class']);

        // Check task
        /** @var TaskInterface $task */
        $task = $taskRepository->findOneBy(['id' => $taskId]);
        if ($task === null) {
            $this->io->error("Task of id $taskId not found.");
            return false;
        }

        $this->taskHandler = $taskHandler;
        $this->task = $task;
        return true;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        if (!$this->inputIsValid($input)) {
            return Command::FAILURE;
        }

        return $this->taskHandler->handle($this->task);
    }
}