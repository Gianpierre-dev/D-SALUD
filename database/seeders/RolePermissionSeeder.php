<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Rol;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Permisos granulares del sistema, agrupados por módulo (modulo.accion).
     * Alineados con la Tabla 49 (rutas y permisos) del TAP D'Salud.
     *
     * @var list<string>
     */
    private array $permisos = [
        // Dashboard
        'dashboard.read',
        // Usuarios
        'usuarios.read', 'usuarios.create', 'usuarios.update', 'usuarios.delete',
        // Roles y permisos
        'roles.read', 'roles.create', 'roles.update', 'roles.delete',
        // Categorías
        'categorias.read', 'categorias.create', 'categorias.update', 'categorias.delete',
        // Productos
        'productos.read', 'productos.create', 'productos.update', 'productos.delete',
        // Lotes / Inventario
        'lotes.read', 'lotes.create', 'lotes.update', 'lotes.delete',
        // Proveedores
        'proveedores.read', 'proveedores.create', 'proveedores.update', 'proveedores.delete',
        // Clientes
        'clientes.read', 'clientes.create', 'clientes.update', 'clientes.delete',
        // Inventario (kardex de movimientos manuales)
        'inventario.read', 'inventario.create',
        // Compras (órdenes a proveedor + recepción)
        'compras.read', 'compras.create', 'compras.update', 'compras.delete', 'compras.recibir',
        // Ventas
        'ventas.read', 'ventas.create', 'ventas.cancel',
        // Reportes
        'reportes.read',
        // Auditoría
        'auditoria.read',
        // Empresa
        'empresa.update',
    ];

    /**
     * Permisos del rol Vendedor (operación de mostrador).
     * El resto de permisos quedan reservados al Administrador.
     *
     * @var list<string>
     */
    private array $permisosVendedor = [
        'dashboard.read',
        'categorias.read',
        'productos.read',
        'lotes.read',
        // El Vendedor puede consultar y crear clientes desde el POS,
        // pero no editarlos ni eliminarlos (eso queda al Administrador).
        'clientes.read',
        'clientes.create',
        'ventas.read',
        'ventas.create',
    ];

    public function run(): void
    {
        // Limpiar la caché de permisos de spatie antes de sembrar.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->permisos as $permiso) {
            Permission::firstOrCreate([
                'name' => $permiso,
                'guard_name' => 'web',
            ]);
        }

        // Administrador: acceso total.
        $administrador = Role::firstOrCreate(['name' => Rol::ADMINISTRADOR->value, 'guard_name' => 'web']);
        $administrador->syncPermissions(Permission::all());

        // Vendedor: solo operación de ventas y consulta de catálogo.
        $vendedor = Role::firstOrCreate(['name' => Rol::VENDEDOR->value, 'guard_name' => 'web']);
        $vendedor->syncPermissions($this->permisosVendedor);
    }
}
