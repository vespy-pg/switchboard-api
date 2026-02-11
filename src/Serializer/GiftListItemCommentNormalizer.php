<?php

namespace App\Serializer;

use App\Entity\GiftListItemComment;
use ArrayObject;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class GiftListItemCommentNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    private const CONTEXT_ALREADY_CALLED = 'app_gift_list_item_comment_normalizer_called';

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        if (!($data instanceof GiftListItemComment)) {
            return false;
        }

        return empty($context[self::CONTEXT_ALREADY_CALLED]);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            GiftListItemComment::class => false,
        ];
    }

    public function normalize(
        mixed $data,
        ?string $format = null,
        array $context = []
    ): array|ArrayObject|bool|float|int|string|null {
        $context[self::CONTEXT_ALREADY_CALLED] = true;

        // Call the next normalizer in the chain
        $normalizedData = $this->normalizer->normalize($data, $format, $context);

        if (!is_array($normalizedData)) {
            return $normalizedData;
        }

        // Only strip removed comment text if 'admin' group is NOT in the serialization groups
        // This allows admin endpoints to keep the full text by including 'admin' group
        $groups = $context['groups'] ?? [];
        $isAdminContext = in_array('admin', $groups, true);

        if (!$isAdminContext && $data->getRemovedAt() !== null && isset($normalizedData['text'])) {
            $normalizedData['text'] = null;
        }

        return $normalizedData;
    }
}
