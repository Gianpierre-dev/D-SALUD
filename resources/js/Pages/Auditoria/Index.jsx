import { Head, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import DataTable from '@/Components/DataTable';
import Pagination from '@/Components/Pagination';
import SearchInput from '@/Components/SearchInput';
import Badge from '@/Components/Badge';
import SelectInput from '@/Components/SelectInput';

/**
 * Asigna una variante de Badge según la acción registrada.
 */
function variantePorAccion(accion) {
    const mapa = {
        crear: 'success',
        actualizar: 'info',
        eliminar: 'danger',
        anular: 'warning',
    };
    return mapa[accion?.toLowerCase()] ?? 'neutral';
}

export default function Index({ registros, modulos, filtros }) {
    const aplicarFiltros = (nuevos) => {
        router.get(
            route('auditoria.index'),
            { ...filtros, ...nuevos },
            { preserveState: true, replace: true },
        );
    };

    const columns = [
        {
            key: 'created_at',
            label: 'Fecha',
            render: (row) =>
                new Date(row.created_at).toLocaleString('es-PE', {
                    dateStyle: 'short',
                    timeStyle: 'short',
                }),
        },
        {
            key: 'usuario',
            label: 'Usuario',
            render: (row) => row.user?.name ?? 'Sistema',
        },
        {
            key: 'modulo',
            label: 'Módulo',
            render: (row) => (
                <Badge variant="info">
                    {row.modulo}
                </Badge>
            ),
        },
        {
            key: 'accion',
            label: 'Acción',
            render: (row) => (
                <Badge variant={variantePorAccion(row.accion)}>
                    {row.accion}
                </Badge>
            ),
        },
        {
            key: 'ip',
            label: 'IP',
            render: (row) => row.ip ?? '—',
        },
        {
            key: 'detalle',
            label: 'Detalle',
            cellClassName: 'max-w-xs truncate',
            render: (row) => row.detalle ?? '—',
        },
    ];

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold text-gray-800 dark:text-gray-200">
                    Auditoría
                </h2>
            }
        >
            <Head title="Auditoría" />

            <div className="mx-auto max-w-7xl">
                {/* Barra de filtros */}
                <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                    <SearchInput
                        value={filtros.buscar ?? ''}
                        onSearch={(termino) => aplicarFiltros({ buscar: termino, page: 1 })}
                        placeholder="Buscar por módulo o acción..."
                    />

                    <SelectInput
                        value={filtros.modulo ?? ''}
                        onChange={(e) => aplicarFiltros({ modulo: e.target.value, page: 1 })}
                        className="w-full sm:w-48"
                        aria-label="Filtrar por módulo"
                    >
                        <option value="">Todos los módulos</option>
                        {modulos.map((m) => (
                            <option key={m} value={m}>
                                {m}
                            </option>
                        ))}
                    </SelectInput>

                    <input
                        type="date"
                        value={filtros.fecha ?? ''}
                        onChange={(e) => aplicarFiltros({ fecha: e.target.value, page: 1 })}
                        className="w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 sm:w-44"
                        aria-label="Filtrar por fecha"
                    />
                </div>

                <DataTable
                    columns={columns}
                    rows={registros.data}
                    emptyMessage="No hay registros de auditoría para los filtros aplicados."
                />

                <div className="mt-4">
                    <Pagination links={registros.links} />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
