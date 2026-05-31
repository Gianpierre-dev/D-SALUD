<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Cliente;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Lógica de negocio del catálogo de clientes.
 * Mantiene el controlador delgado y centraliza la auditoría.
 */
class ClienteService
{
    public function __construct(private readonly AuditoriaService $auditoria)
    {
    }

    /**
     * Lista paginada con búsqueda opcional por nombre o número de documento.
     */
    public function paginar(?string $buscar): LengthAwarePaginator
    {
        return Cliente::query()
            ->when(
                $buscar,
                fn ($query, $termino) => $query->where(function ($q) use ($termino): void {
                    $q->where('nombre', 'like', "%{$termino}%")
                        ->orWhere('numero_documento', 'like', "%{$termino}%");
                }),
            )
            ->orderBy('nombre')
            ->paginate(config('dsalud.paginacion.por_pagina'))
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function crear(array $datos): Cliente
    {
        return DB::transaction(function () use ($datos): Cliente {
            $cliente = Cliente::create($datos);
            $this->auditoria->registrar(
                'clientes',
                'crear',
                "Cliente #{$cliente->id}: {$cliente->nombre} ({$cliente->numero_documento})"
            );

            return $cliente;
        });
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function actualizar(Cliente $cliente, array $datos): Cliente
    {
        return DB::transaction(function () use ($cliente, $datos): Cliente {
            $cliente->update($datos);
            $this->auditoria->registrar(
                'clientes',
                'actualizar',
                "Cliente #{$cliente->id}: {$cliente->nombre} ({$cliente->numero_documento})"
            );

            return $cliente;
        });
    }

    public function eliminar(Cliente $cliente): void
    {
        // Wrap en transacción + auditoría DESPUÉS del delete: si el delete fallara
        // por una FK inesperada, la auditoría no quedaría mintiendo.
        DB::transaction(function () use ($cliente): void {
            $descripcion = "Cliente #{$cliente->id}: {$cliente->nombre} ({$cliente->numero_documento})";
            $cliente->delete();
            $this->auditoria->registrar('clientes', 'eliminar', $descripcion);
        });
    }

    /**
     * Lista compacta de clientes activos para selectors en otras pantallas (POS).
     *
     * @return Collection<int, Cliente>
     */
    public function activos(): Collection
    {
        return Cliente::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'tipo_documento', 'numero_documento', 'nombre']);
    }
}
