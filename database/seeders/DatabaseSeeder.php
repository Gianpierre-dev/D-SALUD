<?php

namespace Database\Seeders;

use App\Models\Empresa;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Roles y permisos primero (los usuarios dependen de los roles).
        $this->call(RolePermissionSeeder::class);

        // Usuario administrador inicial.
        $admin = User::updateOrCreate(
            ['email' => 'admin@dsalud.com'],
            [
                'name' => 'Administrador',
                'password' => Hash::make('password'),
            ],
        );
        $admin->syncRoles('Administrador');

        // Usuario vendedor de ejemplo.
        $vendedor = User::updateOrCreate(
            ['email' => 'vendedor@dsalud.com'],
            [
                'name' => 'Vendedor',
                'password' => Hash::make('password'),
            ],
        );
        $vendedor->syncRoles('Vendedor');

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
    }
}
