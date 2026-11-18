# Despliegue de las correcciones de seguridad

Antes de actualizar un entorno con datos existentes:

1. Haz una copia de seguridad de la base de datos y de `storage/app/public`.
2. Configura `APP_DEBUG=false` y define credenciales únicas y robustas en las variables `SEED_*` solo si vas a ejecutar un seeder privilegiado.
3. Ejecuta `php artisan migrate --force` para añadir la asignación de proveedor a las órdenes.
4. Ejecuta `php artisan invoices:secure-storage`. El comando copia las facturas de `storage/app/public/invoices` a almacenamiento privado y elimina las copias públicas después de verificar la copia. Si detecta un conflicto, se detiene sin borrar ese archivo; resuelve el conflicto y vuelve a ejecutarlo.
5. En el panel de consulta de órdenes, asigna un proveedor activo a las órdenes antiguas que quedaron sin asignar.

Las facturas nuevas se guardan en almacenamiento privado y se sirven únicamente a usuarios autenticados autorizados. No vuelvas a publicar archivos de factura mediante `storage:link` ni enlaces públicos.
