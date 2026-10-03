<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Motoquero;
use App\Models\Configuracion;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([RoleSeeder::class]);

        /** =========================
         * ADMINISTRADOR
         * ========================= */
        $admin = User::updateOrCreate(
            ['email' => 'admin@adimmol.com'],
            [
                'name' => 'Admin Adimmol',
                'password' => Hash::make('533169021'),
            ]
        );

        $admin->assignRole('ADMINISTRADOR');

        /** =========================
         * CONFIGURACIÓN
         * ========================= */
        Configuracion::updateOrCreate(
            ['nombre' => 'Delivery Web'],
            [
                'descripcion' => 'Sistema de delivery',
                'direccion' => 'Zona Alto Lima 3ra Sección',
                'telefono' => '59175657007',
                'correo_electronico' => 'hilariweb@gmail.com',
                'web' => 'https://www.hilariweb.com',
                'divisa' => 'Bs',
                'logo' => 'img/logo.jpg',
            ]
        );

        /** =========================
         * MOTOQUEROS
         * ========================= */

        $this->crearMotoquero(
            'DIS 1',
            'dis1@gmail.com',
            '11111111',
            [
                'nombres' => 'DIS',
                'apellidos' => '1',
                'ci' => '11111111',
                'fecha_nacimiento' => '2021-06-07',
                'genero' => 'M',
                'celular' => '11111111',
                'direccion' => 'KM9',
                'placa' => '1111',
            ]
        );

        $this->crearMotoquero(
            'DIS 2',
            'dis2@gmail.com',
            '22222222',
            [
                'nombres' => 'DIS',
                'apellidos' => '2',
                'ci' => '22222222',
                'fecha_nacimiento' => '2021-06-07',
                'genero' => 'M',
                'celular' => '22222222',
                'direccion' => 'KM9',
                'placa' => '2222',
            ]
        );

        $this->crearMotoquero(
            'DIS 3',
            'dis3@gmail.com',
            '33333333',
            [
                'nombres' => 'DIS',
                'apellidos' => '3',
                'ci' => '33333333',
                'fecha_nacimiento' => '2021-06-07',
                'genero' => 'M',
                'celular' => '33333333',
                'direccion' => 'KM9',
                'placa' => '3333',
            ]
        );

        $this->crearMotoquero(
            'DIS 4',
            'dis4@gmail.com',
            '44444444',
            [
                'nombres' => 'DIS',
                'apellidos' => '4',
                'ci' => '44444444',
                'fecha_nacimiento' => '2021-06-07',
                'genero' => 'M',
                'celular' => '44444444',
                'direccion' => 'KM9',
                'placa' => '4444',
            ]
        );
    }

    private function crearMotoquero($name, $email, $password, array $datos)
    {
        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
            ]
        );

        $user->assignRole('MOTOQUERO');

        Motoquero::updateOrCreate(
            ['usuario_id' => $user->id],
            $datos
        );
    }
}