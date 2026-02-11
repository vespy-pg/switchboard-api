<?php

namespace App\Service;

use App\Entity\Language;
use Doctrine\ORM\EntityManagerInterface;

class LanguageService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function getLanguageByAnyCode(string $code): ?Language
    {
        $code = str_replace('_', '-', strtolower($code));
        return $this->entityManager->getRepository(Language::class)->findOneBy(['code' => $code]);
    }
}
