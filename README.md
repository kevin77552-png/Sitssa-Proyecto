<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## Evaluación P3 - MVC y patrones de creación

### Evaluación 3: patrones de creación

#### Problema identificado

La exportación CSV/XLSX estaba acoplada al componente de Livewire, sin una fábrica reutilizable.

#### Solución aplicada

El módulo [app/Patterns/Inventory](app/Patterns/Inventory/README.md) mantiene la fábrica de exportadores y su administrador singleton. La importación de Excel usa lotes de staging con vista previa y confirmación; `inventory_items` no cambia durante el análisis.

#### Evidencia funcional

- Pruebas: `php artisan test --testsuite=Unit --filter=InventoryPatternsTest`.
- Resultado esperado: 2 pruebas sobre la fábrica de exportación.
- Pruebas fuente: [tests/Unit/InventoryPatternsTest.php](tests/Unit/InventoryPatternsTest.php).
- Documentación técnica: [app/Patterns/Inventory/README.md](app/Patterns/Inventory/README.md).
- Historial técnico: [CHANGELOG.md](CHANGELOG.md).

#### Evidencia de Git

- `feat: implementar patrones creacionales para inventario`
- `docs: actualizar README de evaluación 3`
- `docs: registrar evolución de patrones creacionales`

URL del repositorio público: https://github.com/kevin77552-png/proyecto-p3-mvc-seccion-1-grupo-e9

## Evaluación P3 - MVC


### Diagnóstico inicial
- El proyecto está construido sobre Laravel, lo que ya ofrece una base MVC.
- El módulo de autenticación de login estaba implementado con Livewire/Volt, mezclando lógica de negocio con la vista.
- Esto no cumple una separación stricte de MVC para el ejercicio, porque la lógica de autenticación se encontraba dentro de componentes de vista.

### Cambios realizados
- Creación de `app/Http/Controllers/Auth/LoginController.php`.
- Creación de `resources/views/auth/login.blade.php` para la vista de login.
- Actualización de `routes/auth.php` para que la ruta `login` use el controlador MVC.
- Mantenimiento de las demás rutas de autenticación existentes del proyecto.

### Evidencia en Git
- `feat: creación de controlador de login MVC`
- `feat: nueva vista login MVC`
- `docs: documentación de evaluación en README`
- `chore: importar proyecto actual al repositorio`
- `fix: comentar rutas de auth`
- `docs: añadir nota de commits en README`
- `refactor: comentario en controlador de login`
- `style: comentario en vista de login`

### Cómo ejecutar el proyecto
1. Clonar el repositorio.
2. Instalar dependencias con `composer install`.
3. Configurar `.env` con la base de datos y los valores de app.
4. Crear la base de datos y ejecutar migraciones con `php artisan migrate`.
5. Ejecutar el servidor con `php artisan serve`.
6. Abrir el login en `http://127.0.0.1:8000/login`.

### Respaldos programados de PostgreSQL

El comando `php artisan db:backup` genera y verifica un respaldo en formato custom con `pg_dump` y `pg_restore --list`.

Antes de aplicar migraciones en una base existente, crea y verifica un respaldo. Las migraciones crean las tablas de respaldos e importación y eliminan las antiguas tablas de staging que ya no se usan.

Configura `BACKUP_PG_DUMP_PATH` y `BACKUP_PG_RESTORE_PATH` con las rutas de los ejecutables si no están en `PATH`. `BACKUP_DIRECTORY` permite elegir una carpeta privada fuera de `public/`; `BACKUP_COPY_PATH` y `BACKUP_ALERT_EMAIL` son opcionales. En Windows, restringe con ACL el acceso a la carpeta y configura la contraseña de PostgreSQL mediante las variables de conexión existentes, nunca en los argumentos del comando.

Para automatizar el respaldo diario a las 02:00 y despachar el outbox de importaciones, el scheduler de Laravel debe ejecutarse cada minuto. En el Programador de tareas de Windows configura una tarea con el ejecutable `php` y el argumento `C:\ruta\proyecto\artisan schedule:run`, con el directorio inicial del proyecto. En Linux, configura el cron de Laravel: `* * * * * cd /ruta/proyecto && php artisan schedule:run >> /dev/null 2>&1`. La retención automática solo elimina respaldos `scheduled`; conserva los `manual`.

### Importación segura de inventario

Los administradores pueden cargar XLSX o CSV en `/admin/importaciones`. El análisis guarda las filas en `import_rows` para mostrar nuevos, existentes, repetidos e inválidos antes de aplicar. Cada SIGICOV válido representa una unidad; las columnas `Inventario` y `Total` del archivo se ignoran y cada nuevo artículo importado inicia con inventario `1`. Solo la confirmación **Aplicar** escribe filas nuevas; nunca actualiza un SIGICOV existente. Aplicar y revertir requieren respaldo previo; con PostgreSQL se exige el respaldo verificado `pre_import`.

Las cargas grandes usan los jobs `AnalyzeImportBatch`, `ApplyImportBatch` y `RevertImportBatch`, con timeout de 3600 segundos. Sus transiciones y mensajes se escriben en el mismo outbox transaccional; el scheduler despacha mensajes pendientes cada minuto y reintenta los que quedaron reclamados más de cinco minutos. Si un lote queda en análisis o aplicación por más de dos minutos, las pantallas avisan que verifiques el scheduler y el worker: ejecuta `php artisan queue:work --timeout=3600 --tries=1` si `QUEUE_CONNECTION` no es `sync`. La ventana de reentrega debe ser mayor que el timeout: la configuración mantiene un mínimo de 3660 segundos para database, Redis y Beanstalkd; si usas SQS, configura `VisibilityTimeout` de la cola en al menos 3660 segundos. Al cambiar variables de entorno, reconstruye la caché con `php artisan config:cache`. Un lote fallido durante la aplicación puede volver a `ready` con **Reintentar**; los errores del análisis requieren subir de nuevo el archivo. La hoja preferida inicial es `JUNIO 2025 (2)` y se puede elegir otra en el formulario. La reversión usa soft-delete y se bloquea si existen solicitudes relacionadas.

También se puede analizar desde consola con `php artisan inventory:import-excel C:\ruta\inventario.xlsx --user=ID --hoja="JUNIO 2025 (2)" --corte=2025-06-30`. Sin `--apply` solo genera la vista previa; agrega `--apply` para solicitar la aplicación (y el respaldo PostgreSQL verificado).

Para archivos grandes, configura en `php.ini` límites compatibles (por ejemplo, `upload_max_filesize=100M`, `post_max_size=110M` y `memory_limit=512M`) y reinicia el servidor PHP y el worker de cola.

Valida primero las migraciones y pruebas en una base aislada de pruebas. No ejecutes migraciones contra producción sin respaldo comprobado. El archivo histórico `INVENTARIOS_.xlsx` no estaba disponible para confirmar los conteos de filas indicados en la guía.

### Catálogo de tipos de inventario

Administradores y superadministradores mantienen el catálogo desde **Configuración → Tipos de inventario**. Cada tipo puede incluir palabras clave para sugerencias; las sugerencias no asignan ni importan un tipo por sí solas. En las importaciones, el usuario confirma cada asignación en la vista previa; las filas que se dejan sin tipo se importan sin clasificar. Ingresos manuales también usan el catálogo, y la consulta administrativa permite clasificar gradualmente artículos históricos. Consulta y egreso filtran tanto por un tipo como por artículos sin tipo; las asignaciones históricas se conservan al desactivar un tipo.

### Movimientos de inventario

El registro estructurado de movimientos se activa con `INVENTORY_MOVEMENTS_ENABLED=true` (valor predeterminado). Egresos y eliminaciones aprobadas guardan su movimiento dentro de la misma transacción que cambia el stock; las altas de repuestos registran una entrada sin impedir el guardado si falla el registro del movimiento. La aplicación de una importación intenta crear movimientos `inventario_inicial` para los repuestos importados.

**Confirmar recibido** de una solicitud cambia solamente el estado; no altera stock automáticamente. El formulario de ingreso registra artículos nuevos y ya no incluye una acción separada para sumar unidades a un SIGICOV existente. El dashboard muestra gráficos semanales de entradas/salidas para almacén, gerente y administración, y gráficos mensuales de solicitudes/órdenes para gerente y administración.

En una copia aislada de la base, aplica primero las migraciones y revisa el relleno antes de escribir:

```powershell
php artisan migrate
php artisan inventory:backfill-movements --dry-run
php artisan inventory:backfill-movements
php artisan inventory:backfill-movements --verify
```

La verificación debe responder `Consistente`. No ejecutes esta secuencia contra producción sin un respaldo verificado. El relleno es idempotente, omite artículos sin stock y no modifica `inventory_items.inventario`.

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## Evaluación P3 - MVC

Este repositorio contiene el código del proyecto original con una refactorización del módulo de login hacia una arquitectura MVC más clara.

Este proyecto se ha organizado en pasos simples para generar commits claros y comprensibles.

### Diagnóstico inicial
- El proyecto está construido sobre Laravel, lo que ya ofrece una base MVC.
- El módulo de autenticación de login estaba implementado con Livewire/Volt, mezclando lógica de negocio con la vista.
- Esto no cumple una separación stricte de MVC para el ejercicio, porque la lógica de autenticación se encontraba dentro de componentes de vista.

### Cambios realizados
- Creación de `app/Http/Controllers/Auth/LoginController.php`.
- Creación de `resources/views/auth/login.blade.php` para la vista de login.
- Actualización de `routes/auth.php` para que la ruta `login` use el controlador MVC.
- Mantenimiento de las demás rutas de autenticación existentes del proyecto.

### Evidencia en Git
- `feat: creación de controlador de login MVC`
- `refactor: separación MVC en módulo de autenticación`
- `docs: documentación de evaluación en README`

### Cómo ejecutar el proyecto
1. Clonar el repositorio.
2. Instalar dependencias con `composer install`.
3. Configurar `.env` con la base de datos y los valores de app.
4. Crear la base de datos y ejecutar migraciones con `php artisan migrate`.
5. Ejecutar el servidor con `php artisan serve`.
6. Abrir el login en `http://127.0.0.1:8000/login`.

### Descripción del Proyecto

El Proyecto se centra en el desarrollo de un sistema de gestión de inventario de repuestos para el Sistema Integral de Transporte Superficial S.A. (SITSSA), con el objetivo de facilitar y en algunos aspectos automatizar el manejo de los datos de inventario, tanto registro, ingreso, egreso, devolución, y descarte de los materiales de repuesto que hayan en disposición de los almacenes del SITSSA.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
=======
# proyecto-p3-mvc-seccion-1proyecto-p3-mvc-seccion-1
Proyecto Unetrans Programacion
>>>>>>> d5372b525666cefd4b44acf7e79c9a69f639a349
