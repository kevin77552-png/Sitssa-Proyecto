# Historial del proyecto

## Estado actual de importación

- La importación está disponible en `/admin/importaciones` con lotes de staging, vista previa y confirmación explícita antes de escribir en `inventory_items`.
- La aplicación no sobrescribe SIGICOV existentes y exige respaldo verificado antes de modificar PostgreSQL.
- También está disponible `php artisan inventory:import-excel` para analizar (y opcionalmente aplicar) un archivo desde consola.
- La exportación CSV/XLSX sigue disponible en el módulo de migración.
- Una migración eliminó las antiguas tablas de staging retiradas; el flujo actual usa `import_batches` e `import_rows`.

### Historial

- En una etapa anterior se retiró la importación web; posteriormente se reimplementó como flujo separado de staging y aprobación.

## Evaluación 1 - Diagnóstico y arquitectura inicial

- Definición del proyecto de inventario.
- Identificación del problema de gestión de existencias y solicitudes.
- Base inicial construida con Laravel.

## Evaluación 2 - Arquitectura y MVC

- Separación del módulo de autenticación mediante controlador y vista MVC.
- Reorganización de rutas y responsabilidades del login.
- Documentación de la arquitectura aplicada.

## Evaluación 3 - Patrones de creación

- Identificación de la creación dispersa de exportadores de inventario.
- Implementación de Abstract Factory para crear exportadores.
- Implementación de Singleton para centralizar el acceso a las fábricas.
- Creación de pruebas unitarias para los patrones conservados.
- Actualización del README con problema, solución y evidencia funcional.
