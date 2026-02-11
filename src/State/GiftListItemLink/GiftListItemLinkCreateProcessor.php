<?php

namespace App\State\GiftListItemLink;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\GiftListItemLink;
use App\Entity\LinkHost;
use App\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\SecurityBundle\Security;

class GiftListItemLinkCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof GiftListItemLink) {
            return $data;
        }

        $user = $this->security->getUser();

        if (!$user instanceof User) {
            throw new RuntimeException('User must be authenticated to create a link');
        }
        $host = $this->extractHost($data->getUrl());
        if (!$host) {
            throw new RuntimeException('Host is not valid');
        }
        $linkHost = $this->entityManager->getRepository(LinkHost::class)->findOneBy(['host' => $host]);
        if (!$linkHost) {
            $linkHost = new LinkHost();
            $linkHost->setHost($host);
            $linkHost->setFaviconUrl($this->extractDomain($data->getUrl(), true) . '/favicon.ico');
            $linkHost->setIsPreviewEnabled(true);
            $linkHost->setCreatedAt(new DateTimeImmutable());
            $this->entityManager->persist($linkHost);
        }

        // Set createdByUser and createdAt to current user
        $data->setCreatedByUser($user);
        $data->setCreatedAt(new DateTimeImmutable());
        $data->setLinkType(GiftListItemLink::LINK_TYPE_WEB);
        $data->setDomain($this->extractDomain($data->getUrl()));
        $data->setLinkHost($linkHost);

        $this->entityManager->persist($data);
        $this->entityManager->flush();

        return $data;
    }

    private function extractDomain(?string $url, bool $includeScheme = false): ?string
    {
        $url = trim($url ?: '');
        if (!$url) {
            return null;
        }

        $host = parse_url($url, PHP_URL_HOST);
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (!$host || !$scheme) {
            return null;
        }

        return strtolower(($includeScheme ? $scheme . '://' : '') . $host);
    }

    private function extractHost(?string $url): ?string
    {
        return preg_replace('/^www\./', '', $this->extractDomain($url));
    }
}
