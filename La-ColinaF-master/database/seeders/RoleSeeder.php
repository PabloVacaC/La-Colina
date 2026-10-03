<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Roles
        $admin = Role::firstOrCreate(['name' => 'ADMINISTRADOR']);
        $motoquero = Role::firstOrCreate(['name' => 'MOTOQUERO']);

        // ---------------- CONFIGURACIÓN ----------------
        Permission::firstOrCreate(['name'=>'admin.configuracion.index'])->syncRoles([$admin]);
        Permission::firstOrCreate(['name'=>'admin.configuracion.store'])->syncRoles([$admin]);

        // ---------------- ROLES ----------------
        Permission::firstOrCreate(['name'=>'admin.roles.index'])->syncRoles([$admin]);
        Permission::firstOrCreate(['name'=>'admin.roles.create'])->syncRoles([$admin]);
        Permission::firstOrCreate(['name'=>'admin.roles.store'])->syncRoles([$admin]);
        Permission::firstOrCreate(['name'=>'admin.roles.permisos'])->syncRoles([$admin]);
        Permission::firstOrCreate(['name'=>'admin.roles.update_permisos'])->syncRoles([$admin]);
        Permission::firstOrCreate(['name'=>'admin.roles.edit'])->syncRoles([$admin]);
        Permission::firstOrCreate(['name'=>'admin.roles.update'])->syncRoles([$admin]);
        Permission::firstOrCreate(['name'=>'admin.roles.destroy'])->syncRoles([$admin]);

        // ---------------- USUARIOS ----------------
        Permission::firstOrCreate(['name'=>'admin.usuarios.index'])->syncRoles([$admin]);
        Permission::firstOrCreate(['name'=>'admin.usuarios.create'])->syncRoles([$admin]);
        Permission::firstOrCreate(['name'=>'admin.usuarios.store'])->syncRoles([$admin]);
        Permission::firstOrCreate(['name'=>'admin.usuarios.show'])->syncRoles([$admin]);
        Permission::firstOrCreate(['name'=>'admin.usuarios.edit'])->syncRoles([$admin]);
        Permission::firstOrCreate(['name'=>'admin.usuarios.update'])->syncRoles([$admin]);
        Permission::firstOrCreate(['name'=>'admin.usuarios.destroy'])->syncRoles([$admin]);

        // ---------------- MOTOQUEROS ----------------
        Permission::firstOrCreate(['name'=>'admin.motoqueros.index'])->syncRoles([$admin]);
        Permission::firstOrCreate(['name'=>'admin.motoqueros.create'])->syncRoles([$admin]);
        Permission::firstOrCreate(['name'=>'admin.motoqueros.store'])->syncRoles([$admin]);
        Permission::firstOrCreate(['name'=>'admin.motoqueros.show'])->syncRoles([$admin]);
        Permission::firstOrCreate(['name'=>'admin.motoqueros.edit'])->syncRoles([$admin]);
        Permission::firstOrCreate(['name'=>'admin.motoqueros.update'])->syncRoles([$admin]);
        Permission::firstOrCreate(['name'=>'admin.motoqueros.destroy'])->syncRoles([$admin]);

        // ---------------- TARIFAS ----------------
        Permission::firstOrCreate(['name'=>'admin.tarifas.index'])->syncRoles([$admin]);
        Permission::firstOrCreate(['name'=>'admin.tarifas.create'])->syncRoles([$admin]);
        Permission::firstOrCreate(['name'=>'admin.tarifas.store'])->syncRoles([$admin]);
        Permission::firstOrCreate(['name'=>'admin.tarifas.show'])->syncRoles([$admin]);
        Permission::firstOrCreate(['name'=>'admin.tarifas.edit'])->syncRoles([$admin]);
        Permission::firstOrCreate(['name'=>'admin.tarifas.update'])->syncRoles([$admin]);
        Permission::firstOrCreate(['name'=>'admin.tarifas.destroy'])->syncRoles([$admin]);

        // ---------------- CLIENTES ----------------
        Permission::firstOrCreate(['name'=>'admin.clientes.index'])->syncRoles([$admin]);
        Permission::firstOrCreate(['name'=>'admin.clientes.create'])->syncRoles([$admin]);
        Permission::firstOrCreate(['name'=>'admin.clientes.store'])->syncRoles([$admin]);
        Permission::firstOrCreate(['name'=>'admin.clientes.show'])->syncRoles([$admin]);
        Permission::firstOrCreate(['name'=>'admin.clientes.edit'])->syncRoles([$admin]);
        Permission::firstOrCreate(['name'=>'admin.clientes.update'])->syncRoles([$admin]);
        Permission::firstOrCreate(['name'=>'admin.clientes.destroy'])->syncRoles([$admin]);

        // ---------------- PEDIDOS ----------------
        Permission::firstOrCreate(['name'=>'admin.pedidos.index'])->syncRoles([$admin]);
        Permission::firstOrCreate(['name'=>'admin.pedidos.create'])->syncRoles([$admin]);
        Permission::firstOrCreate(['name'=>'admin.pedidos.store'])->syncRoles([$admin]);
        Permission::firstOrCreate(['name'=>'admin.pedidos.asignar_motoquero'])->syncRoles([$admin]);
        Permission::firstOrCreate(['name'=>'admin.pedidos.cambiar_motoquero'])->syncRoles([$admin]);
        Permission::firstOrCreate(['name'=>'admin.pedidos.finalizar_pedido'])->syncRoles([$admin, $motoquero]);
        Permission::firstOrCreate(['name'=>'admin.pedidos.destroy'])->syncRoles([$admin]);
        Permission::firstOrCreate(['name'=>'admin.pedidos.cancelar_pedido'])->syncRoles([$admin]);
        Permission::firstOrCreate(['name'=>'admin.pedidos.ver_pedidos_motoquero'])->syncRoles([$admin, $motoquero]);
        Permission::firstOrCreate(['name'=>'admin.pedidos.tomar_pedido'])->syncRoles([$admin, $motoquero]);
        Permission::firstOrCreate(['name'=>'admin.pedidos.rechazar_pedido'])->syncRoles([$admin, $motoquero]);

        // NUEVO: Permiso para reporte de ventas por motoquero
        Permission::firstOrCreate(['name'=>'admin.pedidos.reporte_motoquero'])->syncRoles([$admin]);

        // ---------------- PEDIDOS TEMPORALES ----------------
        Permission::firstOrCreate(['name'=>'admin.pedidos_tmp.store'])->syncRoles([$admin]);
        Permission::firstOrCreate(['name'=>'admin.pedidos_tmp.destroy'])->syncRoles([$admin]);
        Permission::firstOrCreate(['name'=>'admin.pedidos_tmp.update'])->syncRoles([$admin]);

        // ---------------- PRODUCTOS ----------------
        Permission::firstOrCreate(['name'=>'admin.productos.index'])->syncRoles([$admin]);
        Permission::firstOrCreate(['name'=>'admin.productos.create'])->syncRoles([$admin]);
        Permission::firstOrCreate(['name'=>'admin.productos.store'])->syncRoles([$admin]);
        Permission::firstOrCreate(['name'=>'admin.productos.edit'])->syncRoles([$admin]);
        Permission::firstOrCreate(['name'=>'admin.productos.update'])->syncRoles([$admin]);
        Permission::firstOrCreate(['name'=>'admin.productos.destroy'])->syncRoles([$admin]);


        // --- Permisos para Clientes Especiales ---
        Permission::firstOrCreate(['name' => 'admin.especiales.index'])->syncRoles([$admin]);
        Permission::firstOrCreate(['name' => 'admin.especiales.create'])->syncRoles([$admin]);
        Permission::firstOrCreate(['name' => 'admin.especiales.edit'])->syncRoles([$admin]);
        Permission::firstOrCreate(['name' => 'admin.especiales.destroy'])->syncRoles([$admin]);


        // --- Permisos para Promociones ---
        Permission::firstOrCreate(['name' => 'admin.promociones.index'])->syncRoles([$admin]);
        Permission::firstOrCreate(['name' => 'admin.promociones.create'])->syncRoles([$admin]);
        Permission::firstOrCreate(['name' => 'admin.promociones.edit'])->syncRoles([$admin]);
        Permission::firstOrCreate(['name' => 'admin.promociones.destroy'])->syncRoles([$admin]);
        Permission::firstOrCreate(['name' => 'admin.promociones.store'])->syncRoles([$admin]);
        Permission::firstOrCreate(['name' => 'admin.promociones.update'])->syncRoles([$admin]);
        Permission::firstOrCreate(['name' => 'admin.promociones.toggle'])->syncRoles([$admin]);

     
            // --- Permisos para Contabilidad ---
        Permission::firstOrCreate(['name' => 'admin.contabilidad.movimientos.index'])->syncRoles([$admin]);
        Permission::firstOrCreate(['name' => 'admin.contabilidad.movimientos.create'])->syncRoles([$admin]);
        Permission::firstOrCreate(['name' => 'admin.contabilidad.movimientos.store'])->syncRoles([$admin]);
        Permission::firstOrCreate(['name' => 'admin.contabilidad.movimientos.resumen'])->syncRoles([$admin]);

        Permission::firstOrCreate(['name' => 'admin.contabilidad.confirmar_venta.create'])->syncRoles([$admin]);
        Permission::firstOrCreate(['name' => 'admin.contabilidad.confirmar_venta.store'])->syncRoles([$admin]);
        Permission::firstOrCreate(['name' => 'admin.contabilidad.historial_cierres'])->syncRoles([$admin]);
        
        Permission::firstOrCreate(['name' => 'admin.contabilidad.gastos-fijos.store'])->syncRoles([$admin]);

        
    }
}