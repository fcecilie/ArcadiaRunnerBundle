<?php

namespace Arcadia\Bundle\RunnerBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 * @ORM\Table(name="arcadia_task")
 */
class DefaultTask implements TaskInterface
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
     * @ORM\Column(type="string", length=255)
     */
    protected string $runnerName;

    /**
     * @ORM\Column(type="string", length=32)
     */
    protected string $status;

    /**
     * @ORM\Column(type="json", nullable=true)
     */
    protected ?array $data;

    public function __construct(string $runnerName)
    {
        $this->status = TaskInterface::WAITING;
        $this->creationDate = new \DateTime('now');
        $this->runnerName = $runnerName;
        $this->data = null;
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

    public function getRunnerName(): string
    {
        return $this->runnerName;
    }

    public function setRunnerName(string $runnerName): self
    {
        $this->runnerName = $runnerName;
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

    public function getData(): ?array
    {
        return $this->data;
    }

    public function setData(?array $data): self
    {
        $this->data = $data;
        return $this;
    }
}