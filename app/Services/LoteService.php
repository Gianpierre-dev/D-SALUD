<?php

namespace App\Services;

use App\Models\Lote;
use App\Models\Producto;
use App\Models\Proveedor;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Lógica de negocio del módulo de lotes (inventario).
 * Mantiene el controlador delgado y centraliza la auditoría.
 */
class LoteService
{
    public function __construct(private readonly AuditoriaService $auditoria)
    {
    }

    /**
     * Lista paginada de lotes con relaciones, búsqueda por código de lote o nombre de producto.
     */
    public function paginar(?string $buscar): LengthAwarePaginator
    {
        return Lote::query()
            ->with(['producto', 'proveedor'])
            ->when(
                $buscar,
                fn ($query, $termino) => $query
                    ->where('codigo_lote', 'like', "%{$termino}%")
                    ->orWhereHas('producto', fn ($q) => $q->where('nombre', 'like', "%{$termino}%"))
            )
            ->orderBy('fecha_vencimiento')
            ->paginate(config('dsalud.paginacion.por_pagina'))
            ->withQueryString();
    }

    /**
     * Productos activos para el select del formulario.
     *
     * @return Collection<int, Producto>
     */
    public function productosActivos(): Collection
    {
        return Producto::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre']);
    }

    /**
     * Proveedores activos para el select del formulario.
     *
     * @return Collection<int, Proveedor>
     */
    public function proveedoresActivos(): Collection
    {
        return Proveedor::query()
            ->where('activo', true)
            ->orderBy('razon_social')
            ->get(['id', 'razon_social']);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function crear(array $datos): Lote
    {
        $lote = Lote::create($datos);
        $this->auditoria->registrar('lotes', 'crear', "Lote #{$lote->id}: {$lote->codigo_lote}");

        return $lote;
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function actualizar(Lote $lote, array $datos): Lote
    {
        $lote->update($datos);
        $this->auditoria->registrar('lotes', 'actualizar', "Lote #{$lote->id}: {$lote->codigo_lote}");

        return $lote;
    }

    public function eliminar(Lote $lote): void
    {
        $this->auditoria->registrar('lotes', 'eliminar', "Lote #{$lote->id}: {$lote->codigo_lote}");
        $lote->delete();
    }
}
