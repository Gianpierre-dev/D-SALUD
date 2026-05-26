<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Categoria;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Lógica de negocio del catálogo de categorías.
 * Mantiene el controlador delgado y centraliza la auditoría.
 */
class CategoriaService
{
    public function __construct(private readonly AuditoriaService $auditoria)
    {
    }

    /**
     * Lista paginada de categorías con búsqueda opcional por nombre.
     */
    public function paginar(?string $buscar): LengthAwarePaginator
    {
        return Categoria::query()
            ->when($buscar, fn ($query, $termino) => $query->where('nombre', 'like', "%{$termino}%"))
            ->orderBy('nombre')
            ->paginate(config('dsalud.paginacion.por_pagina'))
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function crear(array $datos): Categoria
    {
        $categoria = Categoria::create($datos);
        $this->auditoria->registrar('categorias', 'crear', "Categoría #{$categoria->id}: {$categoria->nombre}");

        return $categoria;
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function actualizar(Categoria $categoria, array $datos): Categoria
    {
        $categoria->update($datos);
        $this->auditoria->registrar('categorias', 'actualizar', "Categoría #{$categoria->id}: {$categoria->nombre}");

        return $categoria;
    }

    public function eliminar(Categoria $categoria): void
    {
        $this->auditoria->registrar('categorias', 'eliminar', "Categoría #{$categoria->id}: {$categoria->nombre}");
        $categoria->delete();
    }
}
