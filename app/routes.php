<?php

declare(strict_types=1);

use App\Database\Connection;
use App\Middleware\AuthMiddleware;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return function (App $app) {

    // Preflight handler for CORS
    $app->options('/{routes:.+}', function (Request $request, Response $response) {
        return $response;
    });

    // --- Public ---
    $app->get('/health', function (Request $request, Response $response) {
        $response->getBody()->write(json_encode(['status' => 'ok']));
        return $response->withHeader('Content-Type', 'application/json');
    });

    $app->post('/auth/register', function (Request $request, Response $response) {
        $body = (array)$request->getParsedBody();
        $name = trim((string)($body['name'] ?? ''));

        if ($name === '') {
            $response->getBody()->write(json_encode([
                'error' => ['code' => 'VALIDATION_ERROR', 'message' => 'name is required']
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $token = bin2hex(random_bytes(24));
        $now = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DATE_ATOM);

        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('INSERT INTO users (name, token, created_at) VALUES (:name, :token, :created_at)');
        $stmt->execute([
            ':name' => $name,
            ':token' => $token,
            ':created_at' => $now,
        ]);

        $userId = (int)$pdo->lastInsertId();

        $response->getBody()->write(json_encode([
            'data' => ['userId' => $userId, 'token' => $token]
        ]));
        return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
    });

    // --- Protected routes group ---
    $app->group('', function (RouteCollectorProxy $group) {

        // Create group
        $group->post('/groups', function (Request $request, Response $response) {
            $user = $request->getAttribute('user'); // from middleware
            $body = (array)$request->getParsedBody();

            $name = trim((string)($body['name'] ?? ''));
            $description = trim((string)($body['description'] ?? ''));

            if ($name === '') {
                $response->getBody()->write(json_encode([
                    'error' => ['code' => 'VALIDATION_ERROR', 'message' => 'name is required']
                ]));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            $now = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DATE_ATOM);
            $pdo = Connection::pdo();

            $stmt = $pdo->prepare(
                'INSERT INTO groups (name, description, creator_user_id, created_at)
                 VALUES (:name, :description, :creator_user_id, :created_at)'
            );
            $stmt->execute([
                ':name' => $name,
                ':description' => ($description === '' ? null : $description),
                ':creator_user_id' => (int)$user['id'],
                ':created_at' => $now,
            ]);

            $groupId = (int)$pdo->lastInsertId();

            // Auto-join creator
            $stmt2 = $pdo->prepare(
                'INSERT OR IGNORE INTO group_members (group_id, user_id, joined_at)
                 VALUES (:group_id, :user_id, :joined_at)'
            );
            $stmt2->execute([
                ':group_id' => $groupId,
                ':user_id' => (int)$user['id'],
                ':joined_at' => $now,
            ]);

            $response->getBody()->write(json_encode([
                'data' => ['groupId' => $groupId]
            ]));
            return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
        });

        // List groups (public groups)
        $group->get('/groups', function (Request $request, Response $response) {
            $params = $request->getQueryParams();
            $limit = max(1, min(50, (int)($params['limit'] ?? 20)));
            $offset = max(0, (int)($params['offset'] ?? 0));

            $pdo = Connection::pdo();
            $stmt = $pdo->prepare(
                'SELECT id, name, description, creator_user_id, created_at
                 FROM groups
                 ORDER BY id DESC
                 LIMIT :limit OFFSET :offset'
            );
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $response->getBody()->write(json_encode([
                'data' => $groups,
                'meta' => ['limit' => $limit, 'offset' => $offset]
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        });

        // Join group
        $group->post('/groups/{groupId}/join', function (Request $request, Response $response, array $args) {
            $user = $request->getAttribute('user');
            $groupId = (int)$args['groupId'];

            $pdo = Connection::pdo();

            // ensure group exists
            $stmt = $pdo->prepare('SELECT id FROM groups WHERE id = :id');
            $stmt->execute([':id' => $groupId]);
            if (!$stmt->fetch()) {
                $response->getBody()->write(json_encode([
                    'error' => ['code' => 'NOT_FOUND', 'message' => 'Group not found']
                ]));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            $now = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DATE_ATOM);

            $stmt2 = $pdo->prepare(
                'INSERT OR IGNORE INTO group_members (group_id, user_id, joined_at)
                 VALUES (:group_id, :user_id, :joined_at)'
            );
            $stmt2->execute([
                ':group_id' => $groupId,
                ':user_id' => (int)$user['id'],
                ':joined_at' => $now,
            ]);

            $response->getBody()->write(json_encode(['data' => ['joined' => true]]));
            return $response->withHeader('Content-Type', 'application/json');
        });
        

        // Post message
        $group->post('/groups/{groupId}/messages', function (Request $request, Response $response, array $args) {
            $user = $request->getAttribute('user');
            $groupId = (int)$args['groupId'];
            $body = (array)$request->getParsedBody();
            $content = trim((string)($body['content'] ?? ''));

            if ($content === '') {
                $response->getBody()->write(json_encode([
                    'error' => ['code' => 'VALIDATION_ERROR', 'message' => 'content is required']
                ]));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            $pdo = Connection::pdo();

            // must be member
            $stmt = $pdo->prepare('SELECT 1 FROM group_members WHERE group_id = :g AND user_id = :u');
            $stmt->execute([':g' => $groupId, ':u' => (int)$user['id']]);
            if (!$stmt->fetchColumn()) {
                $response->getBody()->write(json_encode([
                    'error' => ['code' => 'FORBIDDEN', 'message' => 'You are not a member of this group']
                ]));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            }

            $now = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DATE_ATOM);

            $stmt2 = $pdo->prepare(
                'INSERT INTO messages (group_id, user_id, content, created_at)
                 VALUES (:group_id, :user_id, :content, :created_at)'
            );
            $stmt2->execute([
                ':group_id' => $groupId,
                ':user_id' => (int)$user['id'],
                ':content' => $content,
                ':created_at' => $now,
            ]);

            $messageId = (int)$pdo->lastInsertId();

            $response->getBody()->write(json_encode(['data' => ['messageId' => $messageId]]));
            return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
        });

        // List messages (basic pagination with limit/offset)
        $group->get('/groups/{groupId}/messages', function (Request $request, Response $response, array $args) {
            $user = $request->getAttribute('user');
            $groupId = (int)$args['groupId'];

            $params = $request->getQueryParams();
            $limit = max(1, min(100, (int)($params['limit'] ?? 50)));
            $offset = max(0, (int)($params['offset'] ?? 0));

            $pdo = Connection::pdo();

            // must be member
            $stmt = $pdo->prepare('SELECT 1 FROM group_members WHERE group_id = :g AND user_id = :u');
            $stmt->execute([':g' => $groupId, ':u' => (int)$user['id']]);
            if (!$stmt->fetchColumn()) {
                $response->getBody()->write(json_encode([
                    'error' => ['code' => 'FORBIDDEN', 'message' => 'You are not a member of this group']
                ]));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            }

            $stmt2 = $pdo->prepare(
                'SELECT m.id, m.group_id, m.user_id, m.content, m.created_at, u.name AS user_name
                 FROM messages m
                 JOIN users u ON u.id = m.user_id
                 WHERE m.group_id = :group_id
                 ORDER BY m.id DESC
                 LIMIT :limit OFFSET :offset'
            );
            $stmt2->bindValue(':group_id', $groupId, PDO::PARAM_INT);
            $stmt2->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt2->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt2->execute();

            $messages = $stmt2->fetchAll(PDO::FETCH_ASSOC);

            $response->getBody()->write(json_encode([
                'data' => $messages,
                'meta' => ['limit' => $limit, 'offset' => $offset]
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        });

    })->add(new AuthMiddleware());
};
