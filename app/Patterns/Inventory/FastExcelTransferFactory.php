<?php

namespace App\Patterns\Inventory;

final class FastExcelTransferFactory implements InventoryTransferFactory
{
    public function exporter(\Closure $handler): InventoryExporter
    {
        return new CallbackInventoryExporter('fast-excel', $handler);
    }
}
