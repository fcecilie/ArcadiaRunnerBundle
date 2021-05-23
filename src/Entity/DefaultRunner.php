<?php

namespace Arcadia\Bundle\RunnerBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 * @ORM\Table(name="arcadia_runner")
 */
class DefaultRunner implements RunnerInterface
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    protected ?int $id;

    /**
     * @ORM\Column(type="datetime")
     */
    protected \DateTimeInterface $creationDate;

    /**
     * @ORM\Column(type="datetime")
     */
    protected \DateTimeInterface $shutdownDate;

    /**
     * @ORM\Column(type="string", length=255)
     */
    protected string $name;

    /**
     * @ORM\Column(type="string", length=10)
     */
    protected string $status;

    public function __construct(string $name, int $ttl)
    {
        $this->name = $name;
        $this->status = self::RUNNING;
        $this->creationDate = new \DateTimeImmutable('now');
        $this->setShutdownDateFromTtl($ttl);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCreationDate(): \DateTimeInterface
    {
        return $this->creationDate;
    }

    public function setCreationDate(\DateTimeInterface $creationDate): self
    {
        $this->creationDate = $creationDate;
        return $this;
    }

    public function getShutdownDate(): \DateTimeInterface
    {
        return $this->shutdownDate;
    }

    public function setShutdownDate(\DateTimeInterface $shutdownDate): self
    {
        $this->shutdownDate = $shutdownDate;
        return $this;
    }

    public function setShutdownDateFromTtl(int $ttl): self
    {
        try {
            $ttl -= 60; // To prevent minimal rest of cron
            $ttl = ($ttl < 0 ) ? 0 : $ttl;
            $dateInterval = new \DateInterval("PT{$ttl}S");
        } catch (\Throwable $throwable) {
            throw new \Exception("Invalid ttl for runner $this->name.", $throwable->getCode(), $throwable);
        }

        $this->shutdownDate = (new \DateTime('now'))->add($dateInterval);
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }
}