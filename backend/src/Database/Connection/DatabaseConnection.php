<?php

declare(strict_types=1);

namespace App\Database\Connection;

use App\Database\Event\DatabaseQueryEvent;
use App\Exception\Database\DatabaseException;
use PDO;
use PDOException;
use SensitiveParameter;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class DatabaseConnection
{
    private ?PDO $pdo = null;

    public function __construct(#[SensitiveParameter] private string $dsn, #[SensitiveParameter] private string $username, #[SensitiveParameter] private string $password, private EventDispatcherInterface $dispatcher)
    {
    }

    public function getConnection(): PDO
    {
        if (null !== $this->pdo) {
            return $this->pdo;
        }

        $start = hrtime(true);
        $exception = null;

        try {
            return $this->pdo = new PDO($this->dsn, $this->username, $this->password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_STRINGIFY_FETCHES => true]);
        } catch (PDOException $exception) {
            $exception = $exception;

            throw new DatabaseException('Banco externo indisponível. Verifique a conexão configurada.', previous: $exception);
        } finally {
            $this->dispatcher->dispatch(new DatabaseQueryEvent('CONNECT', [], (hrtime(true) - $start) / 1e9, $this->getName(), 'connect', $exception));
        }
    }

    public function getName(): string
    {
        return match (strstr($this->dsn, ':', true)) {
            'oci' => 'oracle', 'pgsql' => 'postgresql', default => 'external',
        };
    }
}
