<?php

declare(strict_types=1);

namespace Tests\Application\Actions\Chat;

use Tests\TestCase;

final class GroupsTest extends TestCase
{
    private function registerUser(string $name = 'Alice'): string
    {
        $res = $this->request('POST', '/auth/register', [], ['name' => $name]);
        $this->assertSame(201, $res->getStatusCode());

        $json = $this->json($res);
        $this->assertArrayHasKey('data', $json);
        $this->assertArrayHasKey('token', $json['data']);

        return (string)$json['data']['token'];
    }

    public function testCreateGroupRequiresAuth(): void
    {
        $res = $this->request('POST', '/groups', [], [
            'name' => 'Public Group 1',
            'description' => 'general',
        ]);

        $this->assertSame(401, $res->getStatusCode());
    }

    public function testCreateGroupReturnsGroupId(): void
    {
        $token = $this->registerUser('Alice');

        $res = $this->request(
            'POST',
            '/groups',
            ['Authorization' => 'Bearer ' . $token],
            ['name' => 'Public Group 1', 'description' => 'general']
        );

        $this->assertSame(201, $res->getStatusCode());

        $json = $this->json($res);
        $this->assertArrayHasKey('data', $json);
        $this->assertArrayHasKey('groupId', $json['data']);
        $this->assertIsInt($json['data']['groupId']);
    }

    public function testListGroupsReturnsArray(): void
    {
        $token = $this->registerUser('Alice');

        // Create one group first
        $create = $this->request(
            'POST',
            '/groups',
            ['Authorization' => 'Bearer ' . $token],
            ['name' => 'Public Group 1', 'description' => 'general']
        );
        $this->assertSame(201, $create->getStatusCode());

        // List groups
        $list = $this->request(
            'GET',
            '/groups?limit=20&offset=0',
            ['Authorization' => 'Bearer ' . $token]
        );

        $this->assertSame(200, $list->getStatusCode());

        $json = $this->json($list);
        $this->assertArrayHasKey('data', $json);
        $this->assertIsArray($json['data']);

        // Ensure at least one has the name we created
        $names = array_map(static fn ($g) => $g['name'] ?? null, $json['data']);
        $this->assertContains('Public Group 1', $names);
    }
}
