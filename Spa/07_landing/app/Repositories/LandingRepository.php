<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;
use RuntimeException;
use Throwable;

final class LandingRepository
{
    public function __construct(
        private PDO $pdo,
        private string $branchId
    ) {
    }

    public function serviciosActivos(): array
    {
        return $this->read(function (PDO $pdo): array {
            $stmt = $pdo->query(
                "SELECT id, nombre, duracion_min, precio
                 FROM catalog.service
                 WHERE estado = 'activo'
                 ORDER BY nombre"
            );

            return $stmt->fetchAll();
        });
    }

    public function resumenCamas(): array
    {
        return $this->read(function (PDO $pdo): array {
            $stmt = $pdo->query(
                "SELECT estado, COUNT(*)::int AS total
                 FROM catalog.resource
                 WHERE tipo = 'cama'
                 GROUP BY estado
                 ORDER BY estado"
            );

            return $stmt->fetchAll();
        });
    }

    public function sede(): ?array
    {
        return $this->read(function (PDO $pdo): ?array {
            $stmt = $pdo->prepare(
                'SELECT id, nombre, direccion
                 FROM core.branch
                 WHERE id = :branch_id
                 LIMIT 1'
            );
            $stmt->execute(['branch_id' => $this->branchId]);
            $row = $stmt->fetch();

            return $row === false ? null : $row;
        });
    }

    public function configPublica(): array
    {
        return $this->read(function (PDO $pdo): array {
            $stmt = $pdo->query(
                "SELECT clave, valor
                 FROM core.app_config
                 WHERE clave IN ('moneda')
                 ORDER BY clave"
            );

            $result = [];
            foreach ($stmt->fetchAll() as $row) {
                $result[$row['clave']] = $row['valor'];
            }

            return $result;
        });
    }

    private function read(callable $query): mixed
    {
        if ($this->branchId === '') {
            throw new RuntimeException('LANDING_BRANCH_ID no configurado');
        }

        $this->pdo->beginTransaction();

        try {
            $this->pdo->exec('SET TRANSACTION READ ONLY');

            $stmt = $this->pdo->prepare("SELECT set_config('app.branch_id', :branch_id, true)");
            $stmt->execute(['branch_id' => $this->branchId]);

            $result = $query($this->pdo);
            $this->pdo->commit();

            return $result;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }
}
