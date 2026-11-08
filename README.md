# Repartia

Panel de administración para gestionar repartidores, centros y pedidos.
Laravel 10 + Filament 3. La app de repartidores (Flutter) consumirá la API con Sanctum.

## Módulos

- **Pedidos**: número, fotos, destinatario, dirección, categoría, repartidor, entregado, comentarios y precio de entrega.
- **Categorías de pedido**: propias de cada centro; no se pueden borrar si están en uso.
- **Usuarios**: el super admin ve todos y es el único que crea y elimina; el resto solo ve los de su centro.
- **Centros**: solo para el super admin.
- **Mi centro**: cada usuario edita los datos de su centro.
- **Perfil**: datos del usuario logueado.

## Instalación

```bash
cp .env.example .env      # configurar la base de datos
composer install
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
npm install
npm run build
```

Super administrador creado por el seeder: `ing.newal.medina@gmail.com`
(contraseña en `database/seeders/SuperAdminSeeder.php`; cámbiala tras el primer acceso).

## Roles y permisos

Solo el **super administrador** ve "Roles y permisos" y asigna roles a los usuarios.
Un usuario puede tener varios roles; sus permisos se suman. Los permisos están en `app/Support/Permissions.php`.

Reglas de pedidos:
- Sin "Ver todos los pedidos del centro" solo se ven los pedidos asignados a uno mismo (también en el escritorio).
- Un pedido entregado o devuelto hace más de 24 h ya no se puede editar ni eliminar (solo ver).
- Los devueltos no generan comisión.

## Datos de prueba

```bash
php artisan migrate
php artisan db:seed --class=TestDataSeeder
```

Crea 3 centros (Barcelona = centro del super admin, Madrid, Valencia), categorías por centro,
usuarios con distintos roles y pedidos desde el día 1 del mes pasado hasta hoy.
Contraseña de todos: `Prueba123*`. Emails: `<usuario>.<centro>@repartia.test`, por ejemplo
`admin.madrid@repartia.test`, `rep1.barcelona@repartia.test`, `sinrol.barcelona@repartia.test`.
"# repartia" 
