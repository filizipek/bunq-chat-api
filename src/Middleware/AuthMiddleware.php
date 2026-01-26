<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Database\Connection;
use PDO;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

final class AuthMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $auth = $request->getHeaderLine('Authorization'); // "Bearer <token>"

        if (!preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) {
            return $this->jsonError(401, 'UNAUTHORIZED', 'Missing or invalid Authorization header');
        }

        $token = trim($m[1]);
        if ($token === '') {
            return $this->jsonError(401, 'UNAUTHORIZED', 'Missing token');
        }

        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('SELECT id, name, token, created_at FROM users WHERE token = :token LIMIT 1');
        $stmt->execute([':token' => $token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return $this->jsonError(401, 'UNAUTHORIZED', 'Invalid token');
        }

        // Attach user to request
        $request = $request->withAttribute('user', $user);

        return $handler->handle($request);
    }

    private function jsonError(int $status, string $code, string $message): ResponseInterface
    {
        $res = new Response($status);
        $res->getBody()->write(json_encode([
            'error' => ['code' => $code, 'message' => $message]
        ], JSON_UNESCAPED_SLASHES));
        return $res->withHeader('Content-Type', 'application/json');
    }
}
