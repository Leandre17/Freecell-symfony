<?php

namespace App\Service;

use PDO;

class StatsStore
{
    private PDO $connection;

    public function __construct(string $databasePath)
    {
        $directory = dirname($databasePath);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $this->connection = new PDO('sqlite:'.$databasePath);
        $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->connection->exec(
            'CREATE TABLE IF NOT EXISTS freecell_stats (' .
            'id INTEGER PRIMARY KEY CHECK (id = 1), ' .
            'games_started INTEGER NOT NULL DEFAULT 0, ' .
            'games_won INTEGER NOT NULL DEFAULT 0, ' .
            'total_moves INTEGER NOT NULL DEFAULT 0, ' .
            'best_moves INTEGER NULL)'
        );
        $this->connection->exec(
            'INSERT OR IGNORE INTO freecell_stats (id) VALUES (1)'
        );
    }

    public function recordGameStarted(): void
    {
        $this->connection->exec('UPDATE freecell_stats SET games_started = games_started + 1 WHERE id = 1');
    }

    public function recordGameWon(int $moves): void
    {
        $statement = $this->connection->prepare(
            'UPDATE freecell_stats SET games_won = games_won + 1, total_moves = total_moves + :moves, ' .
            'best_moves = CASE WHEN best_moves IS NULL OR :moves < best_moves THEN :moves ELSE best_moves END WHERE id = 1'
        );
        $statement->execute(['moves' => $moves]);
    }

    public function getStats(): array
    {
        return $this->connection->query(
            'SELECT games_started, games_won, total_moves, best_moves FROM freecell_stats WHERE id = 1'
        )->fetch(PDO::FETCH_ASSOC) ?: [
            'games_started' => 0,
            'games_won' => 0,
            'total_moves' => 0,
            'best_moves' => null,
        ];
    }
}
