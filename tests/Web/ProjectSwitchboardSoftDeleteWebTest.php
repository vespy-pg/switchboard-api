<?php

namespace App\Tests\Web;

use App\DataFixtures\FixtureSetup;
use App\Entity\Project;
use App\Entity\Switchboard;
use App\Entity\User;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Response;

class ProjectSwitchboardSoftDeleteWebTest extends AbstractWebTestCase
{
    public function testCreateSwitchboardAcceptsProjectIdAliasInRequestBody(): void
    {
        $client = $this->createAuthenticatedClient([
            'ROLE_SWITCHBOARD_CREATE',
            'ROLE_SWITCHBOARD_SHOW',
        ], FixtureSetup::DEFAULT_VERIFIED_USER_ID);

        $project = $this->createProjectEntity('switchboard-create-project-alias');

        $client->request(
            'POST',
            '/api/switchboards',
            [],
            [],
            ['CONTENT_TYPE' => 'application/ld+json'],
            json_encode([
                'name' => 'switchboard-with-project-id-alias',
                'contentJson' => ['rows' => [], 'connections' => []],
                'projectId' => $project->getId(),
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $this->assertSame($project->getId(), $data['projectId'] ?? null);
    }

    public function testSwitchboardListAcceptsProjectIdAliasAsUrlQueryParam(): void
    {
        $client = $this->createAuthenticatedClient([
            'ROLE_SWITCHBOARD_LIST',
        ], FixtureSetup::DEFAULT_VERIFIED_USER_ID);

        $projectA = $this->createProjectEntity('switchboard-project-filter-a');
        $projectB = $this->createProjectEntity('switchboard-project-filter-b');

        $switchboardA = $this->createSwitchboardEntity($projectA, 'switchboard-filter-a');
        $switchboardB = $this->createSwitchboardEntity($projectB, 'switchboard-filter-b');

        $client->request('GET', '/api/switchboards', [
            'projectId' => $projectA->getId(),
        ]);

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $memberIds = array_map(static fn(array $item): string => $item['id'], $data['member'] ?? []);

        $this->assertContains($switchboardA->getId(), $memberIds);
        $this->assertNotContains($switchboardB->getId(), $memberIds);
    }

    public function testProjectSoftDeleteAndNoFurtherInteraction(): void
    {
        $client = $this->createAuthenticatedClient([
            'ROLE_PROJECT_LIST',
            'ROLE_PROJECT_SHOW',
            'ROLE_PROJECT_UPDATE',
        ], FixtureSetup::DEFAULT_VERIFIED_USER_ID);

        $project = $this->createProjectEntity('project-delete-test');
        $projectId = $project->getId();

        $client->request('DELETE', sprintf('/api/projects/%s', $projectId));
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->getEM()->refresh($project);
        $this->assertNotNull($project->getRemovedAt());

        $client->request('GET', sprintf('/api/projects/%s', $projectId));
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $client->request(
            'PATCH',
            sprintf('/api/projects/%s', $projectId),
            [],
            [],
            ['CONTENT_TYPE' => 'application/merge-patch+json'],
            json_encode(['name' => 'should-not-work'])
        );
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $client->request('DELETE', sprintf('/api/projects/%s', $projectId));
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testProjectListFiltersOutRemovedItems(): void
    {
        $client = $this->createAuthenticatedClient([
            'ROLE_PROJECT_LIST',
            'ROLE_PROJECT_UPDATE',
        ], FixtureSetup::DEFAULT_VERIFIED_USER_ID);

        $activeProject = $this->createProjectEntity('project-list-active');
        $removedProject = $this->createProjectEntity('project-list-removed');

        $client->request('DELETE', sprintf('/api/projects/%s', $removedProject->getId()));
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $client->request('GET', '/api/projects', [
            'id' => [$activeProject->getId(), $removedProject->getId()],
        ]);

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $memberIds = array_map(static fn(array $item): string => $item['id'], $data['member'] ?? []);

        $this->assertContains($activeProject->getId(), $memberIds);
        $this->assertNotContains($removedProject->getId(), $memberIds);
    }

    public function testSwitchboardSoftDeleteAndNoFurtherInteraction(): void
    {
        $client = $this->createAuthenticatedClient([
            'ROLE_SWITCHBOARD_LIST',
            'ROLE_SWITCHBOARD_SHOW',
            'ROLE_SWITCHBOARD_UPDATE',
        ], FixtureSetup::DEFAULT_VERIFIED_USER_ID);

        $project = $this->createProjectEntity('switchboard-parent-project');
        $switchboard = $this->createSwitchboardEntity($project, 'switchboard-delete-test');
        $switchboardId = $switchboard->getId();

        $client->request('DELETE', sprintf('/api/switchboards/%s', $switchboardId));
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->getEM()->refresh($switchboard);
        $this->assertNotNull($switchboard->getRemovedAt());

        $client->request('GET', sprintf('/api/switchboards/%s', $switchboardId));
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $client->request(
            'PATCH',
            sprintf('/api/switchboards/%s', $switchboardId),
            [],
            [],
            ['CONTENT_TYPE' => 'application/merge-patch+json'],
            json_encode(['name' => 'should-not-work'])
        );
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $client->request('DELETE', sprintf('/api/switchboards/%s', $switchboardId));
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testSwitchboardListFiltersOutRemovedItems(): void
    {
        $client = $this->createAuthenticatedClient([
            'ROLE_SWITCHBOARD_LIST',
            'ROLE_SWITCHBOARD_UPDATE',
        ], FixtureSetup::DEFAULT_VERIFIED_USER_ID);

        $project = $this->createProjectEntity('switchboard-list-project');
        $activeSwitchboard = $this->createSwitchboardEntity($project, 'switchboard-active');
        $removedSwitchboard = $this->createSwitchboardEntity($project, 'switchboard-removed');

        $client->request('DELETE', sprintf('/api/switchboards/%s', $removedSwitchboard->getId()));
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $client->request('GET', '/api/switchboards', [
            'id' => [$activeSwitchboard->getId(), $removedSwitchboard->getId()],
        ]);

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $memberIds = array_map(static fn(array $item): string => $item['id'], $data['member'] ?? []);

        $this->assertContains($activeSwitchboard->getId(), $memberIds);
        $this->assertNotContains($removedSwitchboard->getId(), $memberIds);
    }

    private function createProjectEntity(string $name): Project
    {
        $user = $this->getEM()->getRepository(User::class)->find(FixtureSetup::DEFAULT_VERIFIED_USER_ID);
        $this->assertNotNull($user);

        $project = new Project();
        $project->setName($name);
        $project->setUser($user);
        $project->setCreatedAt(new DateTimeImmutable());

        $this->getEM()->persist($project);
        $this->getEM()->flush();

        return $project;
    }

    private function createSwitchboardEntity(Project $project, string $name): Switchboard
    {
        $switchboard = new Switchboard();
        $switchboard->setName($name);
        $switchboard->setProject($project);
        $switchboard->setContentJson([]);
        $switchboard->setVersion(1);
        $switchboard->setCreatedAt(new DateTimeImmutable());

        $this->getEM()->persist($switchboard);
        $this->getEM()->flush();

        return $switchboard;
    }
}
