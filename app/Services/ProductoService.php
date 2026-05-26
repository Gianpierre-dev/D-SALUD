<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Categoria;
use App\Models\Producto;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Lógica de negocio del catálogo de productos.
 * Mantiene el controlador delgado y centraliza la auditoría.
 */
class ProductoService
{
    public function __construct(private readonly AuditoriaService $auditoria)
    {
    }

    /**
     * Lista paginada de productos con búsqueda opcional por nombre o código.
     * Incluye la categoría (eager loading) y el stock total calculado.
     */
    public function paginar(?string $buscar): LengthAwarePaginator
    {
        return Producto::query()
            ->with('categoria')
            ->withSum('lotes as stock_total', 'stock')
            ->when(
                $buscar,
                fn ($query, $termino) => $query
                    ->where('nombre', 'like', "%{$termino}%")
                    ->orWhere('codigo', 'like', "%{$termino}%"),
            )
            ->orderBy('nombre')
            ->paginate(config('dsalud.paginacion.por_pagina'))
            ->withQueryString();
    }

    /**
     * Lista de categorías activas para poblar el formulario de producto.
     *
     * @return Collection<int, Categoria>
     */
    public function categoriasActivas(): Collection
    {
        return Categoria::where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre']);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function crear(array $datos): Producto
    {
        $producto = Producto::create($datos);
        $this->auditoria->registrar('productos', 'crear', "Producto #{$producto->id}: {$producto->nombre}");

        return $producto;
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function actualizar(Producto $producto, array $datos): Producto
    {
        $producto->update($datos);
        $this->auditoria->registrar('productos', 'actualizar', "Producto #{$producto->id}: {$producto->nombre}");

        return $producto;
    }

    public function eliminar(Producto $producto): void
    {
        $this->auditoria->registrar('productos', 'eliminar', "Producto #{$producto->id}: {$producto->nombre}");
        $producto->delete();
    }
}
