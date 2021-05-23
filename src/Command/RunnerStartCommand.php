<?php

namespace Arcadia\Bundle\RunnerBundle\Command;

use Arcadia\Bundle\RunnerBundle\Entity\RunnerInterface;
use Arcadia\Bundle\RunnerBundle\Entity\TaskInterface;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\SignalableCommandInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Process\Process;

class RunnerStartCommand extends Command implements SignalableCommandInterface
{
    protected static $defaultName = 'arcadia:runner:start';

    private array $config;
    private array $runnerConfig;
    private ?string $runnerName = null;
    private ?RunnerInterface $runner = null;

    private array $processes;
    private array $tasks;

    private int $errors;

    private SymfonyStyle $io;
    private ManagerRegistry $registry;
    private ObjectManager $taskManager;
    private ObjectManager $runnerManager;

    private string $projectDir;

    public function __construct(ManagerRegistry $registry, KernelInterface $kernel, array $config = [])
    {
        parent::__construct();
        $this->config = $config;
        $this->processes = [];
        $this->tasks = [];
        $this->errors = 0;
        $this->registry = $registry;
        $this->projectDir = $kernel->getProjectDir();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Start a runner.')
            ->addArgument('runnerName', InputArgument::REQUIRED, 'The runner name.')
        ;
    }

    protected function inputIsValid(InputInterface $input): bool
    {
        $this->runnerName = $input->getArgument('runnerName');
        if (isset($this->config[$this->runnerName])) {
            $this->runnerConfig = $this->config[$this->runnerName];
            $this->taskManager = $this->registry->getManager($this->runnerConfig['task']['doctrine_manager']);
            $this->runnerManager = $this->registry->getManager($this->runnerConfig['runner']['doctrine_manager']);
            return true;
        }

        $this->io->error("Runner '$this->runnerName' not found in the bundle configuration. Check your 'arcadia_runner.yaml' to add it.");
        return false;
    }

    protected function getRunner(): ?RunnerInterface
    {
        $runnerRepository = $this->runnerManager->getRepository($this->runnerConfig['runner']['class']);

        /** @var RunnerInterface|null $runner */
        $runner = $runnerRepository->findOneBy(['name' => $this->runnerName]);

        return $runner;
    }

    protected function createRunner(): RunnerInterface
    {
        /** @var RunnerInterface $runner */
        $runner = new $this->runnerConfig['runner']['class']($this->runnerName, $this->runnerConfig['runner']['ttl']);

        $this->runnerManager->persist($runner);
        $this->runnerManager->flush();

        return $runner;
    }

    protected function getNextTask(array $tasks): ?TaskInterface
    {
        $ids = array_column($this->tasks, 'id');

        /** @var QueryBuilder $taskQueryBuilder */
        $qb = $this->taskManager->createQueryBuilder();

        $qb = $qb
            ->select('task')
            ->from($this->runnerConfig['task']['class'], 'task')
            ->where('task.runnerName = :runnerName')
            ->setParameter('runnerName', $this->runnerName)
            ->andWhere('task.status = :status')
            ->setParameter('status', TaskInterface::WAITING)
            ->orderBy('task.creationDate', 'ASC')
            ->setMaxResults(1)
        ;

        if (!empty($ids)) {
            $qb
                ->andWhere('task.id NOT IN (:ids)')
                ->setParameter('ids', $ids)
            ;
        }

        $nextTask = $qb
            ->getQuery()
            ->getResult()
        ;

        if (isset($nextTask[0])) {
            foreach ($tasks as $task) {
                if ($task->getId() === $nextTask[0]->getId()) {
                    return null;
                }
            }

            return $nextTask[0];
        }

        return null;
    }

    protected function addTask(TaskInterface $task)
    {
        // Mark task as running in DB
        $task->setStatus(TaskInterface::RUNNING);
        $this->taskManager->flush();

        // Create process and start it
        $process = new Process(['php', "$this->projectDir/bin/console", 'arcadia:runner:handle-task', $this->runnerName, $task->getId()]);
        $process->start();

        // Store task and process
        $this->processes[$process->getPid()] = $process;
        $this->tasks[$process->getPid()] = $task;
    }

    protected function removeTask(int $pid)
    {
        $task = $this->tasks[$pid];
        $process = $this->processes[$pid];

        if (!$process->isSuccessful()) {
            $this->errors++;
            $task->setStatus(TaskInterface::ERROR);
        }

        unset($this->processes[$pid]);
        unset($this->tasks[$pid]);

        $this->taskManager->flush();
    }

    protected function handleRunner(RunnerInterface $runner): void
    {
        while ($runner->getStatus() !== RunnerInterface::STOPPED && $runner->getShutdownDate() > new \DateTime('now')) {

            // Refresh runner
            if ($this->runnerConfig['runner']['refresh']) {
                $this->runnerManager->refresh($runner);
            }

            // Remove finished processes
            /** @var Process $process */
            foreach ($this->processes as $pid => $process) {
                if (!$process->isRunning()) {
                    $this->removeTask($pid);
                }
            }

            // Add new processes
            while (count($this->processes) < $this->runnerConfig['tasks_in_parallel']) {
                if (($task = $this->getNextTask($this->tasks)) === null) {
                    break;
                }
                $this->addTask($task);
            }

            // Idle runner
            if ($this->runnerConfig['runner']['idle'] > 0) {
                sleep($this->runnerConfig['runner']['idle']);
            }

            // Stop if there to much errors
            if ($this->errors > 3) {
                $this->runner->setStatus(RunnerInterface::STOPPED);
                $this->runner->setShutdownDate(new \DateTime('now'));
                $this->runnerManager->flush();
            }

        }

        foreach ($this->processes as $pid => $process) {
            $process->wait();
            $this->removeTask($pid);
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        if (!$this->inputIsValid($input)) {
            return Command::FAILURE;
        }

        $this->runner = $this->getRunner();
        if ($this->runner instanceof RunnerInterface) {

            if ($this->runner->getStatus() === RunnerInterface::STOPPED) {
                $this->io->block("Runner '$this->runnerName' is stopped.", 'INFO', 'fg=blue');
                return Command::SUCCESS;
            }

            if ($this->runner->getShutdownDate() > new \DateTime('now')) {
                $this->io->block("Runner '$this->runnerName' is already running.", 'INFO', 'fg=blue');
                return Command::SUCCESS;
            }

            $this->runner->setShutdownDateFromTtl($this->runnerConfig['runner']['ttl']);
            $this->runnerManager->flush();

        } else {
            $this->runner = $this->createRunner();
        }

        $this->handleRunner($this->runner);
        return Command::SUCCESS;
    }

    public function getSubscribedSignals(): array
    {
        return [SIGQUIT, SIGINT, SIGTERM];
    }

    public function handleSignal(int $signal): void
    {
        if ($this->runner instanceof RunnerInterface) {
            $this->runner->setShutdownDate(new \DateTime('now'));
            $this->runnerManager->flush();
        }
    }
}