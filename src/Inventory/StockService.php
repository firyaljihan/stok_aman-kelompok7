<?php

declare(strict_types=1);

namespace StokAman\Inventory;

use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

class StockService
{
    public function __construct(private PDO $pdo)
    {
    }

    public function getCurrentStock(int $productId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COALESCE(SUM(remaining_qty), 0) AS total_stock
             FROM stock_batches
             WHERE product_id = :product_id'
        );
        $stmt->execute(['product_id' => $productId]);
        $row = $stmt->fetch();

        return (int) ($row['total_stock'] ?? 0);
    }

    public function recordStockIn(
        string $sku,
        int $qty,
        string $batchCode,
        float $unitCost = 0.0,
        ?string $note = null,
        ?string $receivedDate = null
    ): array {
        $sku = strtoupper(trim($sku));
        $batchCode = trim($batchCode);

        if ($sku === '') {
            throw new InvalidArgumentException('Kode SKU wajib diisi.');
        }
        if ($qty <= 0) {
            throw new InvalidArgumentException('Jumlah barang masuk harus lebih dari 0.');
        }
        if ($batchCode === '') {
            throw new InvalidArgumentException('Kode batch wajib diisi.');
        }
        if ($unitCost < 0) {
            throw new InvalidArgumentException('Harga satuan tidak boleh negatif.');
        }

        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare('SELECT id, sku, name, min_stock FROM products WHERE sku = :sku LIMIT 1');
            $stmt->execute(['sku' => $sku]);
            $product = $stmt->fetch();

            if (!$product) {
                throw new RuntimeException("Produk dengan SKU '{$sku}' tidak ditemukan.");
            }

            $productId = (int) $product['id'];
            $stockBefore = $this->getCurrentStock($productId);
            $stockAfter = $stockBefore + $qty;
            $dateValue = $receivedDate ?: date('Y-m-d H:i:s');

            $insertBatch = $this->pdo->prepare(
                'INSERT INTO stock_batches
                    (product_id, batch_code, received_date, initial_qty, remaining_qty, unit_cost)
                 VALUES
                    (:product_id, :batch_code, :received_date, :initial_qty, :remaining_qty, :unit_cost)'
            );
            $insertBatch->execute([
                'product_id' => $productId,
                'batch_code' => $batchCode,
                'received_date' => $dateValue,
                'initial_qty' => $qty,
                'remaining_qty' => $qty,
                'unit_cost' => $unitCost,
            ]);
            $batchId = (int) $this->pdo->lastInsertId();

            $insertMovement = $this->pdo->prepare(
                'INSERT INTO stock_movements
                    (product_id, batch_id, movement_type, qty, stock_before, stock_after, note)
                 VALUES
                    (:product_id, :batch_id, \'IN\', :qty, :stock_before, :stock_after, :note)'
            );
            $insertMovement->execute([
                'product_id' => $productId,
                'batch_id' => $batchId,
                'qty' => $qty,
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
                'note' => $note ?: "Barang masuk batch {$batchCode}",
            ]);

            $this->pdo->commit();

            return [
                'sku' => $product['sku'],
                'product_name' => $product['name'],
                'batch_id' => $batchId,
                'batch_code' => $batchCode,
                'qty_in' => $qty,
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
                'is_low_stock' => $stockAfter <= (int) $product['min_stock'],
            ];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function recordStockOut(string $sku, int $qty, ?string $note = null): array
    {
        $sku = strtoupper(trim($sku));

        if ($sku === '') {
            throw new InvalidArgumentException('Kode SKU (hasil input/scan) wajib diisi.');
        }
        if ($qty <= 0) {
            throw new InvalidArgumentException('Jumlah barang keluar harus lebih dari 0.');
        }

        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare('SELECT id, sku, name, min_stock FROM products WHERE sku = :sku LIMIT 1');
            $stmt->execute(['sku' => $sku]);
            $product = $stmt->fetch();

            if (!$product) {
                throw new RuntimeException("Produk dengan SKU '{$sku}' tidak ditemukan.");
            }

            $productId = (int) $product['id'];
            $stockBefore = $this->getCurrentStock($productId);

            if ($qty > $stockBefore) {
                throw new RuntimeException(
                    "Stok tidak mencukupi untuk SKU '{$sku}'. Tersedia: {$stockBefore}, diminta: {$qty}."
                );
            }

            // Ambil batch dari yang paling awal masuk (FIFO)
            $batchStmt = $this->pdo->prepare(
                'SELECT id, batch_code, remaining_qty
                 FROM stock_batches
                 WHERE product_id = :product_id AND remaining_qty > 0
                 ORDER BY received_date ASC, id ASC
                 FOR UPDATE'
            );
            $batchStmt->execute(['product_id' => $productId]);
            $batches = $batchStmt->fetchAll();

            $remainingToDeduct = $qty;
            $deductedBatches = [];

            $updateBatchStmt = $this->pdo->prepare(
                'UPDATE stock_batches SET remaining_qty = :remaining_qty WHERE id = :id'
            );
            $insertMovementStmt = $this->pdo->prepare(
                'INSERT INTO stock_movements
                    (product_id, batch_id, movement_type, qty, stock_before, stock_after, note)
                 VALUES
                    (:product_id, :batch_id, \'OUT\', :qty, :stock_before, :stock_after, :note)'
            );

            $runningStock = $stockBefore;

            foreach ($batches as $batch) {
                if ($remainingToDeduct <= 0) {
                    break;
                }

                $batchId = (int) $batch['id'];
                $availableInBatch = (int) $batch['remaining_qty'];
                $takeQty = min($availableInBatch, $remainingToDeduct);
                $newBatchRemaining = $availableInBatch - $takeQty;

                $updateBatchStmt->execute([
                    'remaining_qty' => $newBatchRemaining,
                    'id' => $batchId,
                ]);

                $nextStock = $runningStock - $takeQty;

                $insertMovementStmt->execute([
                    'product_id' => $productId,
                    'batch_id' => $batchId,
                    'qty' => $takeQty,
                    'stock_before' => $runningStock,
                    'stock_after' => $nextStock,
                    'note' => $note ?: "Barang keluar (FIFO) batch {$batch['batch_code']}",
                ]);

                $deductedBatches[] = [
                    'batch_id' => $batchId,
                    'batch_code' => $batch['batch_code'],
                    'qty_taken' => $takeQty,
                    'batch_remaining_after' => $newBatchRemaining,
                ];

                $runningStock = $nextStock;
                $remainingToDeduct -= $takeQty;
            }

            if ($remainingToDeduct > 0) {
                throw new RuntimeException('Gagal memenuhi kuantitas FIFO secara penuh.');
            }

            $stockAfter = $stockBefore - $qty;
            $this->pdo->commit();

            return [
                'sku' => $product['sku'],
                'product_name' => $product['name'],
                'qty_out' => $qty,
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
                'is_low_stock' => $stockAfter <= (int) $product['min_stock'],
                'fifo_batches' => $deductedBatches,
            ];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}