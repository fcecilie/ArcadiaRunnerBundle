<?php

namespace Arcadia\Bundle\RunnerBundle\Entity;

interface RunnerInterface
{
    public const RUNNING = 'running';
    public const STOPPED = 'stopped';

    function __construct(string $name, int $ttl);

    function getCreationDate(): \DateTimeInterface;
    function setCreationDate(\DateTimeInterface $creationDate): self;

    function getShutdownDate(): \DateTimeInterface;
    function setShutdownDate(\DateTimeInterface $shutdownDate): self;

    function setShutdownDateFromTtl(int $ttl): self;

    function getName(): string;
    function setName(string $name): self;

    function getStatus(): string;
    function setStatus(string $status): self;
}