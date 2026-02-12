<?php

namespace App\Security\Voter;

use App\Entity\Project;

class ProjectVoter extends Voter
{
    public const LIST   = 'PROJECT_LIST';
    public const CREATE = 'PROJECT_CREATE';
    public const SHOW   = 'PROJECT_SHOW';
    public const UPDATE = 'PROJECT_UPDATE';
    protected array $supportedAttributes = [self::SHOW, self::LIST, self::CREATE, self::UPDATE];

    protected function canList(Project $subject): void
    {
    }

    protected function canCreate(Project $subject): void
    {
    }

    protected function canShow(Project $subject): void
    {
    }

    protected function canUpdate(Project $subject): void
    {
    }
}
