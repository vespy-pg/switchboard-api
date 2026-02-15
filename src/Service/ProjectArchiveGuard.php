<?php

namespace App\Service;

use App\Entity\Project;
use App\Entity\Switchboard;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class ProjectArchiveGuard
{
    public function assertProjectWritable(Project $project): void
    {
        if ($project->getArchivedAt() !== null) {
            throw new BadRequestHttpException('Archived project cannot be modified.');
        }
    }

    public function assertSwitchboardWritable(Switchboard $switchboard): void
    {
        $project = $switchboard->getProject();
        if ($project === null) {
            return;
        }

        $this->assertProjectWritable($project);
    }
}
