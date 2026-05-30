<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Rol;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Estrategia de seguridad:
     *  - Roles, permisos y configuración de empresa: se siembran siempre.
     *  - Usuarios de ejemplo (admin/vendedor): solo en entornos local/testing.
     *  - En producción: se requiere DSALUD_ADMIN_PASSWORD del entorno, no hay
     *    contraseña por defecto, y no se crea el vendedor de ejemplo.
     */
    public function run(): void
    {
        // Roles y permisos primero (los usuarios dependen de los roles).
        $this->call(RolePermissionSeeder::class);

        // Configuración inicial de la empresa (singleton).
        Empresa::updateOrCreate(
            ['id' => 1],
            [
                'razon_social' => "Botica D'Salud S.A.C.",
                'ruc' => '20600000001',
                'direccion' => 'Av. Guillermo Billinghurst 1045, San Juan de Miraflores, Lima',
                'telefono' => '01-0000000',
            ],
        );

        $this->sembrarUsuarios();
    }

    private function sembrarUsuarios(): void
    {
        $esProduccion = app()->environment('production');

        // En producción, la contraseña inicial DEBE venir del entorno.
        if ($esProduccion) {
            $passwordAdmin = (string) env('DSALUD_ADMIN_PASSWORD', '');

            if ($passwordAdmin === '') {
                throw new RuntimeException(
                    'En producción se requiere la variable de entorno DSALUD_ADMIN_PASSWORD ' .
                    'para crear la contraseña inicial del administrador.'
                );
            }
        } else {
            // Entornos local/testing: contraseña por defecto para desarrollo.
            $passwordAdmin = 'password';
        }

        $emailAdmin = (string) env('DSALUD_ADMIN_EMAIL', 'admin@dsalud.com');

        $admin = User::updateOrCreate(
            ['email' => $emailAdmin],
            [
                'name' => 'Administrador',
                'password' => Hash::make($passwordAdmin),
            ],
        );
        $admin->syncRoles(Rol::ADMINISTRADOR->value);

        // El usuario vendedor de demostración NO se crea en producción.
        if (! $esProduccion) {
            $vendedor = User::updateOrCreate(
                ['email' => 'vendedor@dsalud.com'],
                [
                    'name' => 'Vendedor',
                    'password' => Hash::make('password'),
                ],
            );
            $vendedor->syncRoles(Rol::VENDEDOR->value);
        }
    }
}
