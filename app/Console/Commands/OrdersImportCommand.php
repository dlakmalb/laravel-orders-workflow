<?php

namespace App\Console\Commands;

use App\Jobs\ProcessOrderJob;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\LazyCollection;

class OrdersImportCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:import {path : Path to CSV file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import orders CSV and enqueue processing jobs';

    private array $ordersResetThisRun = [];

    private array $ordersToEnqueue = [];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $path = $this->argument('path');

        if (! is_readable($path)) {
            $this->error("File not readable: {$path}");

            return self::FAILURE;
        }

        $stream = fopen($path, 'rb');

        $rows = LazyCollection::make(function () use ($stream) {
            $header = null;
            $lineNo = 0;

            while (($data = fgetcsv($stream)) !== false) {
                $lineNo++;

                if ($lineNo === 1) {
                    $header = $this->validateAndNormalizeHeader($data);

                    if (! $header) {
                        break;
                    }

                    continue;
                }

                yield $this->mapRow($header, $data, $lineNo);
            }

            fclose($stream);
        });

        $importedCount = 0;
        $skippedCount = 0;

        foreach ($rows as $row) {
            if ($row === null) {
                $skippedCount++;

                continue;
            }

            try {
                $this->importRow($row);
                $importedCount++;
            } catch (\Throwable $e) {
                Log::error('Import error at row', ['row' => $row, 'exception' => $e]);
                $this->warn("Row import failed (line {$row['_line']}). See logs.");
                $skippedCount++;
            }
        }

        // 3) Finalize: recompute totals and enqueue jobs for each order touched
        $this->finalizeOrders();

        $this->info("Import complete. Imported {$importedCount} rows, skipped {$skippedCount}.");
        $this->info('Queued processing for '.count($this->ordersToEnqueue).' orders.');

        return self::SUCCESS;
    }

    private function validateAndNormalizeHeader(array $header): ?array
    {
        $header = array_map(fn ($h) => trim(mb_strtolower($h)), $header);

        $required = [
            'external_order_id',
            'order_placed_at',
            'currency',
            'customer_id',
            'customer_email',
            'customer_name',
            'product_sku',
            'product_name',
            'unit_price_cents',
            'qty',
        ];

        $missing = array_values(array_diff($required, $header));

        if ($missing) {
            $this->error('Missing required columns: '.implode(', ', $missing));

            return null;
        }

        return $header;
    }

    private function mapRow(array $header, array $data, int $lineNo): ?array
    {
        // If the row has fewer cells than headers, fill with nulls; extra cells ignored.
        if (count($data) < count($header)) {
            $data = array_pad($data, count($header), null);
        }
        $assoc = array_combine($header, $data);
        $assoc['_line'] = $lineNo;

        // Basic presence checks; deeper validation happens later
        foreach (['external_order_id', 'customer_id', 'customer_email', 'customer_name', 'product_sku', 'product_name', 'unit_price_cents', 'qty', 'order_placed_at', 'currency'] as $k) {
            if (! isset($assoc[$k]) || $assoc[$k] === '') {
                $this->warn("Line {$lineNo}: missing value for '{$k}', skipping.");

                return null;
            }
        }

        return $assoc;
    }

    private function importRow(array $row): void
    {
        $externalOrderId = trim((string) $row['external_order_id']);
        $currency = trim((string) $row['currency']);
        $customerExternalId = trim((string) $row['customer_id']);
        $customerEmail = trim((string) $row['customer_email']);
        $customerName = trim((string) $row['customer_name']);
        $sku = trim((string) $row['product_sku']);
        $productName = trim((string) $row['product_name']);

        $unitPriceCents = (int) $row['unit_price_cents'];
        $qty = (int) $row['qty'];

        if ($unitPriceCents < 0 || $qty < 1) {
            $this->warn("Line {$row['_line']}: invalid money/qty; skipping.");

            return;
        }

        try {
            $placedAt = CarbonImmutable::parse($row['order_placed_at']);
        } catch (\Throwable $e) {
            $this->warn("Line {$row['_line']}: invalid order_placed_at; skipping.");

            return;
        }

        DB::transaction(function () use (
            $externalOrderId, $currency, $customerExternalId, $customerEmail, $customerName,
            $sku, $productName, $unitPriceCents, $qty, $placedAt,
        ) {
            // 2) Upsert Customer by external_id (idempotent)
            $customer = Customer::query()
                ->updateOrCreate(
                    ['external_id' => $customerExternalId],
                    ['email' => $customerEmail, 'name' => $customerName]
                );

            // 3) Upsert Product by sku (idempotent)
            $product = Product::query()
                ->updateOrCreate(
                    ['sku' => $sku],
                    [
                        'name' => $productName,
                        'price_cents' => $unitPriceCents,
                        'stock_qty' => 10,
                    ]
                );

            // 4) Upsert Order by external_order_id (idempotent)
            $order = Order::query()
                ->updateOrCreate(
                    ['external_order_id' => $externalOrderId],
                    [
                        'customer_id' => $customer->id,
                        'currency' => $currency,
                        'placed_at' => $placedAt,
                    ]
                );

            // 5) For this import run, delete existing items ONCE per order (so re-imports rebuild cleanly)
            if (! isset($this->ordersResetThisRun[$order->id])) {
                OrderItem::where('order_id', $order->id)->delete();
                $this->ordersResetThisRun[$order->id] = true;
                $this->ordersToEnqueue[$order->id] = true; // mark for later enqueue
            }

            // 6) Insert this item (subtotal is a stored generated column in your schema)
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'unit_price_cents' => $unitPriceCents,
                'qty' => $qty,
            ]);
        });
    }

    /**
     * Recompute totals and enqueue processing per order touched in this run.
     */
    private function finalizeOrders(): void
    {
        $orderIds = array_keys($this->ordersToEnqueue);

        if (empty($orderIds)) {
            Log::info('No orders to finalize.');

            return;
        }

        // We recompute totals in chunks to avoid giant IN() lists
        collect($orderIds)->chunk(500)->each(function (Collection $chunk) {
            $ids = $chunk->all();

            // 1) Recompute totals per order (sum of generated subtotal_cents)
            $sums = OrderItem::query()
                ->selectRaw('order_id, SUM(subtotal_cents) AS total')
                ->whereIn('order_id', $ids)
                ->groupBy('order_id')
                ->pluck('total', 'order_id'); // [order_id => total]

            foreach ($ids as $orderId) {
                $total = (int) ($sums[$orderId] ?? 0);
                Order::whereKey($orderId)->update(['total_cents' => $total]);

                // Enqueue processing job once per order
                ProcessOrderJob::dispatch($orderId);
            }
        });
    }
}
