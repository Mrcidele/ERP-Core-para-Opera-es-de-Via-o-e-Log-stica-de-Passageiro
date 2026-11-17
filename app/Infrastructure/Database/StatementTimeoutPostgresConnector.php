<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use Illuminate\Database\Connectors\PostgresConnector;
use PDO;

/**
 * Aplica o teto de tempo por consulta (`statement_timeout` da conexão) assim que o PostgreSQL
 * aceita a conexão, junto com timezone e search_path. Roda só quando a conexão abre de fato:
 * requisições que não usam o banco não pagam a ida e volta, e uma falha de conexão aparece no
 * ponto em que o banco é usado (o health check responde 503, não 500).
 *
 * O valor é validado antes de entrar no SQL (ex.: 15s, 500ms, 2min).
 */
final class StatementTimeoutPostgresConnector extends PostgresConnector
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function connect(array $config): PDO
    {
        $connection = parent::connect($config);
        $timeout = $config['statement_timeout'] ?? null;

        if (is_string($timeout) && preg_match('/^\d{1,6}(ms|s|min)?$/', $timeout) === 1) {
            $connection->exec("SET statement_timeout TO '{$timeout}'");
        }

        return $connection;
    }
}
