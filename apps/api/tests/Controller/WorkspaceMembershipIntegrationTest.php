<?php
declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\AppUser;
use App\Entity\Workspace;
use App\Entity\WorkspaceMember;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class WorkspaceMembershipIntegrationTest extends WebTestCase
{
    public function testOwnerEditorViewerAndLastOwnerGuards(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(6));
        $a = new Workspace('Test A', 'test-a-'.$suffix);
        $b = new Workspace('Test B', 'test-b-'.$suffix);
        $owner = new AppUser('owner-'.$suffix.'@example.test', 'unused');
        $viewer = new AppUser('viewer-'.$suffix.'@example.test', 'unused');
        $editor = new AppUser('editor-'.$suffix.'@example.test', 'unused');
        foreach ([$a, $b, $owner, $viewer, $editor] as $entity) $em->persist($entity);
        $em->persist(new WorkspaceMember($a, $owner, 'owner'));
        $em->persist(new WorkspaceMember($a, $viewer, 'viewer'));
        $em->persist(new WorkspaceMember($a, $editor, 'editor'));
        $em->persist(new WorkspaceMember($b, $viewer, 'owner'));
        $em->flush();

        try {
            $client->loginUser($viewer);
            $client->request('GET', '/api/workspaces/'.$a->id.'/members');
            self::assertResponseStatusCodeSame(403);
            $client->request('GET', '/api/workspaces/'.$a->id.'/members/csrf');
            self::assertResponseStatusCodeSame(404);

            $client->loginUser($editor);
            $client->request('GET', '/api/workspaces/'.$a->id.'/members');
            self::assertResponseStatusCodeSame(403);

            $client->loginUser($owner);
            $client->request('GET', '/api/workspaces/'.$a->id.'/members');
            self::assertResponseIsSuccessful();
            $client->request('GET', '/api/workspaces/'.$a->id.'/members/csrf');
            self::assertResponseIsSuccessful();
            $token = json_decode($client->getResponse()->getContent(), true)['token'];

            $client->request('PATCH', '/api/workspaces/'.$a->id.'/members/'.$owner->id, server: ['HTTP_X_CSRF_TOKEN' => $token, 'CONTENT_TYPE' => 'application/json'], content: '{"role":"viewer"}');
            self::assertResponseStatusCodeSame(409);
            $client->request('DELETE', '/api/workspaces/'.$a->id.'/members/'.$owner->id, server: ['HTTP_X_CSRF_TOKEN' => $token]);
            self::assertResponseStatusCodeSame(409);

            $client->request('PATCH', '/api/workspaces/'.$a->id.'/members/'.$viewer->id, server: ['CONTENT_TYPE' => 'application/json'], content: '{"role":"editor"}');
            self::assertResponseStatusCodeSame(403);
            $client->request('PATCH', '/api/workspaces/'.$a->id.'/members/'.$viewer->id, server: ['HTTP_X_CSRF_TOKEN' => $token, 'CONTENT_TYPE' => 'application/json'], content: '{"role":"editor"}');
            self::assertResponseIsSuccessful();

            $client->request('GET', '/api/workspaces/'.$b->id.'/members/csrf');
            self::assertResponseStatusCodeSame(404);
            $client->request('DELETE', '/api/workspaces/'.$b->id.'/members/'.$viewer->id, server: ['HTTP_X_CSRF_TOKEN' => $token]);
            self::assertResponseStatusCodeSame(403);
        } finally {
            foreach ($em->getRepository(WorkspaceMember::class)->findBy(['workspace' => $a]) as $member) $em->remove($member);
            foreach ($em->getRepository(WorkspaceMember::class)->findBy(['workspace' => $b]) as $member) $em->remove($member);
            $em->flush();
            foreach ([$a, $b, $owner, $viewer, $editor] as $entity) $em->remove($entity);
            $em->flush();
        }
    }
}
