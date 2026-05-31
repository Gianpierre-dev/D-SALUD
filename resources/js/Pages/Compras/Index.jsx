import { Head, Link, router } from '@inertiajs/react';
import { IconEye, IconPlus } from '@tabler/icons-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import DataTable from '@/Components/DataTable';
import Pagination from '@/Components/Pagination';
import Badge from '@/Components/Badge';
import Can from '@/Components/Can';
import IconButton from '@/Components/IconButton';
import SelectInput from '@/Components/SelectInput';
import PrimaryButton from '@/Components/PrimaryButton';
import { formatearMoneda } from '@/utils/format';

/**
 * Listado de órdenes de compra con filtros.
 * El detalle y la acción "Recibir" están en Compras/Show.
 */
export default function Index({ compras, filtros, proveedores = [], estados = [] }) {
    const aplicarFiltros = (cambios) => {
        router.get(
            route('compras.index'),
            { ...filtros, ...cambios },
            { preserveState: true, replace: true },
        );
    };

    const formatFecha = (fecha) =>
        fecha
            ? new Date(fecha).toLocaleDateString('es-PE', {
                  day: '2-digit',
                  month: '2-digit',
                  year: 'numeric',
              })
            : '—';

    const colorEstado = (estado) =>
        ({
            PENDIENTE: 'warning',
            RECIBIDA: 'success',
            ANULADA: 'danger',
        }[estado] ?? 'neutral');

    const columns = [
        {
            key: 'numero',
            label: 'N° OC',
            render: (row) => `${row.serie}-${String(row.numero).padStart(5, '0')}`,
        },
        {
            key: 'fecha_compra',
            label: 'Fecha',
            render: (row) => formatFecha(row.fecha_compra),
        },
        {
            key: 'proveedor',
            label: 'Proveedor',
            render: (row) => row.proveedor?.razon_social ?? '—',
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
                <Badge variant={colorEstado(row.estado)}>
                    {estados.find((e) => e.value === row.estado)?.label ?? row.estado}
                </Badge>
            ),
        },
        {
            key: 'acciones',
            label: 'Acciones',
            render: (row) => (
                <Can permission="compras.read">
                    <Link href={route('compras.show', row.id)}>
                        <IconButton icon={IconEye} title="Ver detalle" variant="primary" />
                    </Link>
                </Can>
            ),
        },
    ];

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold text-gray-800 dark:text-gray-200">
                    Órdenes de compra
                </h2>
            }
        >
            <Head title="Compras" />

            <div className="mx-auto max-w-7xl">
                <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                    <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                        <SelectInput
                            value={filtros.estado ?? ''}
                            onChange={(e) => aplicarFiltros({ estado: e.target.value || null })}
                            className="w-full text-sm sm:w-auto"
                        >
                            <option value="">Todos los estados</option>
                            {estados.map((e) => (
                                <option key={e.value} value={e.value}>
                                    {e.label}
                                </option>
                            ))}
                        </SelectInput>

                        <SelectInput
                            value={filtros.proveedor_id ?? ''}
                            onChange={(e) => aplicarFiltros({ proveedor_id: e.target.value || null })}
                            className="w-full text-sm sm:w-auto"
                        >
                            <option value="">Todos los proveedores</option>
                            {proveedores.map((p) => (
                                <option key={p.id} value={p.id}>
                                    {p.razon_social}
                                </option>
                            ))}
                        </SelectInput>

                        <input
                            type="date"
                            value={filtros.fecha ?? ''}
                            onChange={(e) => aplicarFiltros({ fecha: e.target.value || null })}
                            className="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 sm:w-auto"
                        />
                    </div>

                    <Can permission="compras.create">
                        <Link href={route('compras.create')}>
                            <PrimaryButton>
                                <IconPlus className="me-1 h-4 w-4" />
                                Nueva orden
                            </PrimaryButton>
                        </Link>
                    </Can>
                </div>

                <DataTable
                    columns={columns}
                    rows={compras.data}
                    emptyMessage="No hay órdenes de compra registradas."
                />

                <div className="mt-4">
                    <Pagination links={compras.links} />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
