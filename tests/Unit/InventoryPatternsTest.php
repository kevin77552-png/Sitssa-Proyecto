<?php

namespace Tests\Unit;

use App\Patterns\Inventory\InventoryTransferManager;
use PHPUnit\Framework\TestCase;

class InventoryPatternsTest extends TestCase
{
    public function test_abstract_factory_creates_exporter(): void
    {
        $factory = InventoryTransferManager::instance()->factory();
        $exporter = $factory->exporter(static fn (iterable $items, array $options): int => count($items) + count($options));

        $this->assertSame('fast-excel', $exporter->driver());
        $this->assertSame(2, $exporter->export(['item'], ['format' => 'csv']));
    }

    public function test_manager_is_a_singleton(): void
    {
        $this->assertSame(InventoryTransferManager::instance(), InventoryTransferManager::instance());
    }
}
