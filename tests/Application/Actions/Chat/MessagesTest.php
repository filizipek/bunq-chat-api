<?php

declare(strict_types=1);

namespace Tests\Application\Actions\Chat;

use Tests\TestCase;

final class MessagesTest extends TestCase
{
    private function register(string $name): string
    {
        $res = $this->request('POST', '/auth/register', [], ['name' => $name]);
        $this->assertSame(201, $res->getStatusCode());

        return (string)$this->json($res)['data']['token'];
    }

    private function createGroup(string $token, string $name = 'Group A'): int
    {
        $res = $this->request(
            'POST',
            '/groups',
            ['Authorization' => 'Bearer ' . $token],
            ['name' => $name, 'description' => 'general']
        );
        $this->assertSame(201, $res->getStatusCode());

        return (int)$this->json($res)['data']['groupId'];
    }

    public function testPostingMessageWithoutMembershipIsForbidden(): void
    {
        $alice = $this->register('Alice');
        $bob   = $this->register('Bob');

        $groupId = $this->createGroup($alice, 'Public Group 1');

        // Bob did not join, should be forbidden
        $res = $this->request(
            'POST',
            "/groups/{$groupId}/messages",
            ['Authorization' => 'Bearer ' . $bob],
            ['content' => 'Hello!']
        );

        $this->assertSame(403, $res->getStatusCode());
    }

    public function testJoinThenPostThenListMessages(): void
    {
        $alice = $this->register('Alice');
        $bob   = $this->register('Bob');

        $groupId = $this->createGroup($alice, 'Public Group 1');

        // Bob joins
        $join = $this->request(
            'POST',
            "/groups/{$groupId}/join",
            ['Authorization' => 'Bearer ' . $bob]
        );
        $this->assertSame(200, $join->getStatusCode());

        // Bob posts
        $post = $this->request(
            'POST',
            "/groups/{$groupId}/messages",
            ['Authorization' => 'Bearer ' . $bob],
            ['content' => 'Hello from Bob']
        );
        $this->assertSame(201, $post->getStatusCode());

        $postJson = $this->json($post);
        $this->assertArrayHasKey('data', $postJson);
        $this->assertArrayHasKey('messageId', $postJson['data']);

        // Bob lists
        $list = $this->request(
            'GET',
            "/groups/{$groupId}/messages?limit=50&offset=0",
            ['Authorization' => 'Bearer ' . $bob]
        );
        $this->assertSame(200, $list->getStatusCode());

        $json = $this->json($list);
        $this->assertArrayHasKey('data', $json);
        $this->assertIsArray($json['data']);

        $contents = array_map(static fn ($m) => $m['content'] ?? null, $json['data']);
        $this->assertContains('Hello from Bob', $contents);
    }
}
