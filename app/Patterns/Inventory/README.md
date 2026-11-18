# Patrón creacional de exportación de inventario

Este módulo mantiene una estrategia de exportación desacoplada mediante callbacks.

## Patrones incluidos

- **Abstract Factory:** `InventoryTransferFactory` crea exportadores para el driver seleccionado.
- **Singleton:** `InventoryTransferManager::instance()` mantiene un único punto de acceso a las fábricas disponibles.

## Uso

```php
$manager = InventoryTransferManager::instance();
$factory = $manager->factory('fast-excel');

$exporter = $factory->exporter(
    fn (iterable $items, array $options) => response()->streamDownload(/* contenido de exportación */)
);

$items = App\Models\InventoryItem::query()->cursor();
$result = $exporter->export($items);
```
