<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Serializer\Filter\GroupFilter;
use App\ApiPlatform\Filter\MultiFieldSearchFilter;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => ['read']],
            security: "is_granted('DEVICE_TYPE_CATEGORY_LIST', object)"
        ),
        new Get(security: "is_granted('DEVICE_TYPE_CATEGORY_SHOW', object)")
    ],
    normalizationContext: ['groups' => ['read']],
)]
#[ORM\Entity]
#[ORM\Table(name: 'app.device_type_category')]
#[ApiFilter(SearchFilter::class, properties: [
    'code' => 'exact',
    'label' => 'partial',
])]
#[ApiFilter(OrderFilter::class, properties: [
    'code',
    'label',
])]
#[ApiFilter(MultiFieldSearchFilter::class, properties: [
    'code' => 'exact',
    'label' => 'partial',
])]
#[ApiFilter(GroupFilter::class, arguments: ['overrideDefaultGroups' => true])]
class DeviceTypeCategory
{
    #[ORM\Id]
    #[ORM\Column(name: 'code', type: 'string', length: 100, nullable: false)]
    #[Groups(['read'])]
    private string $code;

    #[ORM\Column(name: 'label', type: 'text', nullable: false)]
    #[Assert\NotBlank(groups: ['read'])]
    #[Groups(['read'])]
    private string $label;

    #[ORM\OneToMany(targetEntity: DeviceType::class, mappedBy: 'category', fetch: 'EXTRA_LAZY')]
    #[Groups(['device_type_category_device_types'])]
    private Collection|ArrayCollection $deviceTypes;

    public function __construct()
    {
        $this->deviceTypes = new ArrayCollection();
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): void
    {
        $this->code = $code;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): void
    {
        $this->label = $label;
    }

    public function getDeviceTypes(): Collection|ArrayCollection
    {
        return $this->deviceTypes;
    }

    public function setDeviceTypes(Collection|ArrayCollection $deviceTypes): void
    {
        $this->deviceTypes = $deviceTypes;
    }
}
