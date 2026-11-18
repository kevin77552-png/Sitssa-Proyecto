<?php

namespace App\Patterns\Inventory;

interface InventoryTransferFactory
{
    public function exporter(\Closure $handler): InventoryExporter;
}
