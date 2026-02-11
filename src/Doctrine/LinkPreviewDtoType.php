<?php

namespace App\Doctrine;

use App\DTO\LinkPreview;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

class LinkPreviewDtoType extends Type
{
    public const LINK_PREVIEW_DTO = 'link_preview_dto';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'JSON';
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?LinkPreview
    {
        if ($value === null || $value === '') {
            return null;
        }

        $data = is_string($value) ? json_decode($value, true) : $value;

        if (!is_array($data)) {
            return null;
        }

        $dto = new LinkPreview();
        $dto->setUrl($data['url'] ?? null);
        $dto->setTitle($data['title'] ?? null);
        $dto->setStatus($data['status'] ?? null);
        $dto->setProduct($data['product'] ?? null);
        $dto->setFinalUrl($data['finalUrl'] ?? null);
        $dto->setImageUrl($data['imageUrl'] ?? null);
        $dto->setSiteName($data['siteName'] ?? null);
        $dto->setFaviconUrl($data['faviconUrl'] ?? null);
        $dto->setDescription($data['description'] ?? null);

        return $dto;
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!$value instanceof LinkPreview) {
            throw new \InvalidArgumentException('Expected LinkPreview instance');
        }

        $data = [
            'url' => $value->getUrl(),
            'title' => $value->getTitle(),
            'status' => $value->getStatus(),
            'product' => $value->getProduct(),
            'finalUrl' => $value->getFinalUrl(),
            'imageUrl' => $value->getImageUrl(),
            'siteName' => $value->getSiteName(),
            'faviconUrl' => $value->getFaviconUrl(),
            'description' => $value->getDescription(),
        ];

        return json_encode($data);
    }

    public function getName(): string
    {
        return self::LINK_PREVIEW_DTO;
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
