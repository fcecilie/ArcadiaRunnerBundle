<?php

namespace Arcadia\Bundle\RunnerBundle\Entity;

interface TaskInterface
{
    public const ERROR = "error";
    public const WAITING = "waiting";
    public const RUNNING = "running";
    public const SUCCESS = "success";

    function __construct(string $runnerName);

    function getId(): ?int;

    function getCreationDate(): \DateTimeInterface;
    function setCreationDate(\DateTimeInterface $creationDate): self;

    function getRunnerName(): string;
    function setRunnerName(string $runnerName): self;

    function getStatus(): string;
    function setStatus(string $status): self;
}