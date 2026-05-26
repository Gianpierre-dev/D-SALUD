import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { IconEye, IconBan } from '@tabler/icons-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import DataTable from '@/Components/DataTable';
import Pagination from '@/Components/Pagination';
import Badge from '@/Components/Badge';
import Can from '@/Components/Can';
import IconButton from '@/Components/IconButton';
import SelectInput from '@/Components/SelectInput';
import AnularVentaModal from './Partials/AnularVentaModal';
import { ESTADO_VENTA } from '@/constants';
import { formatearMoneda } from '@/utils/format';

/**
 * Historial de ventas con filtros y acciones de reimprimir / anular.
 */
export default function Index({ ventas, vendedores = [], filtros, esAdmin = false }) {
    const [ventaAnular, setVentaAnular] = useState(null);

    // ---------- Filtros ----------

    const aplicarFiltros = (cambios) => {
        router.get(
            route('ventas.index'),
            { ...filtros, ...cambios },
            { preserveState: true, replace: true }
        );
    };

    // ---------- Columnas de la tabla ----------

    const columns = [
        {
            key: 'boleta',
            label: 'Boleta',
            render: (row) =>
                row.boleta?.numero_formateado ?? (
                    <span className="text-gray-400">—</span>
                ),
        },
        {
            key: 'fecha',
            label: 'Fecha',
            render: (row) =>
                new Date(row.created_at).toLocaleDateString('es-PE', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                }),
        },
        {
            key: 'vendedor',
            label: 'Vendedor',
            render: (row) => row.vendedor?.name ?? <span className="text-gray-400">—</span>,
        },
        {
            key: 'total',
            label: 'Total',
            render: (row) => formatearMoneda(row.total),
        },
        {
            key: 'estado',
            label: 'Estado',
            render: (row) => (
                <Badge variant={row.estado === ESTADO_VENTA.COMPLETADA ? 'success' : 'danger'}>
                    {row.estado === ESTADO_VENTA.COMPLETADA ? 'Completada' : 'Anulada'}
                </Badge>
            ),
        },
        {
            key: 'acciones',
            label: 'Acciones',
            render: (row) => (
                <div className="flex gap-1">
                    <Can permission="ventas.read">
                        <Link href={route('ventas.boleta', row.id)}>
                            <IconButton icon={IconEye} title="Ver boleta" variant="primary" />
                        </Link>
                    </Can>
                    <Can permission="ventas.cancel">
                        {row.estado === ESTADO_VENTA.COMPLETADA && (
                            <IconButton
                                icon={IconBan}
                                title="Anular venta"
                                variant="danger"
                                onClick={() => setVentaAnular(row)}
                            />
                        )}
                    </Can>
                </div>
            ),
        },
    ];

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold text-gray-800 dark:text-gray-200">
                    Historial de ventas
                </h2>
            }
        >
            <Head title="Historial de ventas" />

            <div className="mx-auto max-w-7xl">
                {/* Filtros */}
                <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                    {/* Fecha */}
                    <input
                        type="date"
                        value={filtros.fecha ?? ''}
                        onChange={(e) =>
                            aplicarFiltros({ fecha: e.target.value || null })
                        }
                        className="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 sm:w-auto"
                    />

                    {/* Vendedor (solo administrador) */}
                    {esAdmin && (
                        <SelectInput
                            value={filtros.vendedor_id ?? ''}
                            onChange={(e) =>
                                aplicarFiltros({ vendedor_id: e.target.value || null })
                            }
                            className="w-full text-sm sm:w-auto"
                        >
                            <option value="">Todos los vendedores</option>
                            {vendedores.map((v) => (
                                <option key={v.id} value={v.id}>
                                    {v.name}
                                </option>
                            ))}
                        </SelectInput>
                    )}

                    {/* Estado */}
                    <SelectInput
                        value={filtros.estado ?? ''}
                        onChange={(e) =>
                            aplicarFiltros({ estado: e.target.value || null })
                        }
                        className="w-full text-sm sm:w-auto"
                    >
                        <option value="">Todos los estados</option>
                        <option value={ESTADO_VENTA.COMPLETADA}>Completada</option>
                        <option value={ESTADO_VENTA.ANULADA}>Anulada</option>
                    </SelectInput>
                </div>

                <DataTable
                    columns={columns}
                    rows={ventas.data}
                    emptyMessage="No hay ventas que coincidan con los filtros."
                />

                <div className="mt-4">
                    <Pagination links={ventas.links} />
                </div>
            </div>

            <AnularVentaModal
                show={Boolean(ventaAnular)}
                onClose={() => setVentaAnular(null)}
                venta={ventaAnular}
            />
        </AuthenticatedLayout>
    );
}
