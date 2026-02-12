<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Serializer\Filter\GroupFilter;
use ApiPlatform\State\CreateProvider;
use App\ApiPlatform\Filter\MultiFieldSearchFilter;
use App\State\Project\ProjectCreateProcessor;
use App\State\Project\ProjectDeleteProcessor;
use App\State\Project\ProjectUpdateProcessor;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => ['read']],
            security: "is_granted('PROJECT_LIST', object)",
        ),
        new Get(security: "is_granted('PROJECT_SHOW', object)"),
        new Post(
            normalizationContext: ['groups' => ['read']],
            denormalizationContext: ['groups' => ['create']],
            securityPostDenormalize: "is_granted('PROJECT_CREATE', object)",
            validationContext: ['groups' => ['create']],
            provider: CreateProvider::class,
            processor: ProjectCreateProcessor::class,
        ),
        new Patch(
            normalizationContext: ['groups' => ['read']],
            denormalizationContext: ['groups' => ['update']],
            security: "is_granted('PROJECT_UPDATE', object)",
            validationContext: ['groups' => ['update']],
            processor: ProjectUpdateProcessor::class,
        ),
        new Delete(
            security: "is_granted('PROJECT_UPDATE', object)",
            processor: ProjectDeleteProcessor::class,
        ),
    ],
    normalizationContext: ['groups' => ['read']],
)]
#[ORM\Entity]
#[ORM\Table(name: 'app.tbl_project')]
#[ApiFilter(SearchFilter::class, properties: [
    'id' => 'exact',
    'name' => 'partial',
    'createdAt' => 'exact',
    'updatedAt' => 'exact',
    'removedAt' => 'exact',
    'user' => 'exact',
])]
#[ApiFilter(OrderFilter::class, properties: [
    'id',
    'name',
    'createdAt',
    'updatedAt',
    'removedAt',
    'user',
])]
#[ApiFilter(MultiFieldSearchFilter::class, properties: [
    'id' => 'exact',
    'name' => 'partial',
    'createdAt' => 'exact',
    'updatedAt' => 'exact',
    'removedAt' => 'exact',
    'user' => 'exact',
])]
#[ApiFilter(GroupFilter::class, arguments: ['overrideDefaultGroups' => true])]
class Project
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    #[ORM\Column(name: 'project_id', type: 'uuid', nullable: false, options: ['default' => 'gen_random_uuid()'])]
    #[Groups(['read'])]
    private string $id;

    #[ORM\Column(name: 'name', type: 'string', length: 255, nullable: false)]
    #[Groups(['read', 'create', 'update'])]
    private string $name;

    #[ORM\Column(name: 'created_at', type: 'datetimetz_immutable', nullable: false, options: ['default' => 'CURRENT_TIMESTAMP'])]
    #[Groups(['read'])]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetimetz_immutable', nullable: true)]
    #[Groups(['read'])]
    private ?DateTimeImmutable $updatedAt = null;

    #[ORM\Column(name: 'removed_at', type: 'datetimetz_immutable', nullable: true)]
    #[Groups(['read'])]
    private ?DateTimeImmutable $removedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'user_id', nullable: false)]
    #[Groups(['project_user'])]
    private ?User $user = null;

    #[ORM\OneToMany(targetEntity: Switchboard::class, mappedBy: 'project')]
    #[Groups(['project_switchboards'])]
    private Collection | ArrayCollection $switchboards;

    public function __construct()
    {
        $this->switchboards = new ArrayCollection();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?DateTimeImmutable $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getRemovedAt(): ?DateTimeImmutable
    {
        return $this->removedAt;
    }

    public function setRemovedAt(?DateTimeImmutable $removedAt): void
    {
        $this->removedAt = $removedAt;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): void
    {
        $this->user = $user;
    }

    #[Groups(['read'])]
    public function getUserId(): ?string
    {
        return $this->getUser()?->getId();
    }

    public function getSwitchboards(): ArrayCollection|Collection
    {
        return $this->switchboards;
    }

    public function setSwitchboards(ArrayCollection|Collection $switchboards): void
    {
        $this->switchboards = $switchboards;
    }
}
