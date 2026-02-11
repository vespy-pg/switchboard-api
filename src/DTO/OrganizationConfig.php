<?php

namespace App\DTO;

use Symfony\Component\Serializer\Annotation\Groups;

class OrganizationConfig
{
    #[Groups(['read'])]
    private ?string $exposed = null;

    private ?string $confidential = null;

    public function getExposed(): ?string
    {
        return $this->exposed;
    }

    public function setExposed(?string $exposed): void
    {
        $this->exposed = $exposed;
    }

    public function getConfidential(): ?string
    {
        return $this->confidential;
    }

    public function setConfidential(?string $confidential): void
    {
        $this->confidential = $confidential;
    }
}
