import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { IconPlus, IconArrowDown, IconArrowUp } from '@tabler/icons-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import DataTable from '@/Components/DataTable';
import Pagination from '@/Components/Pagination';
import Badge from '@/Components/Badge';
import Can from '@/Components/Can';
import SelectInput from '@/Components/SelectInput';
import PrimaryButton from '@/Components/PrimaryButton';
import MovimientoFormModal from './Partials/MovimientoFormModal';

/**
 * Listado del kardex con filtros (producto, lote, tipo, motivo, fechas).
 * El registro manual abre un modal restringido a motivos manuales.
 */
export default function Index({
    movimientos,
    filtros,
    productos = [],
    lotes = [],
    motivosManuales = [],
    tipos = [],
}) {
    const [modalAbierto, setModalAbierto] = useState(false);

    const aplicarFiltros = (cambios) => {
        router.get(
            route('inventario.movimientos.index'),
            { ...filtros, ...cambios },
            { preserveState: true, replace: true },
        );
    };

    const formatFecha = (fecha) =>
        new Date(fecha).toLocaleDateString('es-PE', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });

    const columns = [
        {
            key: 'created_at',
            label: 'Fecha',
            render: (row) => formatFecha(row.created_at),
        },
        {
            key: 'tipo',
            label: 'Tipo',
            render: (row) => (
                <Badge variant={row.tipo === 'ENTRADA' ? 'success' : 'danger'}>
                    {row.tipo === 'ENTRADA' ? (
                        <IconArrowDown className="me-0.5 inline h-3 w-3" />
                    ) : (
                        <IconArrowUp className="me-0.5 inline h-3 w-3" />
                    )}
                    {row.tipo}
                </Badge>
            ),
        },
        { key: 'motivo', label: 'Motivo' },
        {
            key: 'producto',
            label: 'Producto',
            render: (row) => row.producto?.nombre ?? '—',
        },
        {
            key: 'lote',
            label: 'Lote',
            render: (row) => row.lote?.codigo_lote ?? '—',
        },
        {
            key: 'cantidad',
            label: 'Cantidad',
            render: (row) => (
                <span className={row.tipo === 'ENTRADA' ? 'text-emerald-600' : 'text-red-600'}>
                    {row.tipo === 'ENTRADA' ? '+' : '−'}
                    {row.cantidad}
                </span>
            ),
        },
        {
            key: 'stock',
            label: 'Stock (ant. → post.)',
            render: (row) => (
                <span className="text-xs text-gray-600 dark:text-gray-400">
                    {row.stock_anterior} → <strong>{row.stock_posterior}</strong>
                </span>
            ),
        },
        {
            key: 'usuario',
            label: 'Usuario',
            render: (row) => row.usuario?.name ?? '—',
        },
        {
            key: 'observacion',
            label: 'Observación',
            render: (row) => (
                <span className="text-xs text-gray-600 dark:text-gray-400">
                    {row.observacion ?? '—'}
                </span>
            ),
        },
    ];

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold text-gray-800 dark:text-gray-200">
                    Movimientos de inventario
                </h2>
            }
        >
            <Head title="Movimientos de inventario" />

            <div className="mx-auto max-w-7xl">
                {/* Filtros */}
                <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                    <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                        <SelectInput
                            value={filtros.producto_id ?? ''}
                            onChange={(e) => aplicarFiltros({ producto_id: e.target.value || null })}
                            className="w-full text-sm sm:w-auto"
                        >
                            <option value="">Todos los productos</option>
                            {productos.map((p) => (
                                <option key={p.id} value={p.id}>
                                    {p.nombre}
                                </option>
                            ))}
                        </SelectInput>

                        <SelectInput
                            value={filtros.tipo ?? ''}
                            onChange={(e) => aplicarFiltros({ tipo: e.target.value || null })}
                            className="w-full text-sm sm:w-auto"
                        >
                            <option value="">Entradas y salidas</option>
                            {tipos.map((t) => (
                                <option key={t} value={t}>
                                    {t}
                                </option>
                            ))}
                        </SelectInput>

                        <SelectInput
                            value={filtros.motivo ?? ''}
                            onChange={(e) => aplicarFiltros({ motivo: e.target.value || null })}
                            className="w-full text-sm sm:w-auto"
                        >
                            <option value="">Todos los motivos</option>
                            <option value="VENTA">Venta</option>
                            <option value="ANULACION_VENTA">Anulación de venta</option>
                            {motivosManuales.map((m) => (
                                <option key={m.value} value={m.value}>
                                    {m.label}
                                </option>
                            ))}
                        </SelectInput>

                        <input
                            type="date"
                            value={filtros.desde ?? ''}
                            onChange={(e) => aplicarFiltros({ desde: e.target.value || null })}
                            className="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 sm:w-auto"
                            placeholder="Desde"
                        />
                        <input
                            type="date"
                            value={filtros.hasta ?? ''}
                            onChange={(e) => aplicarFiltros({ hasta: e.target.value || null })}
                            className="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 sm:w-auto"
                            placeholder="Hasta"
                        />
                    </div>

                    <Can permission="inventario.create">
                        <PrimaryButton onClick={() => setModalAbierto(true)}>
                            <IconPlus className="me-1 h-4 w-4" />
                            Nuevo movimiento
                        </PrimaryButton>
                    </Can>
                </div>

                <DataTable
                    columns={columns}
                    rows={movimientos.data}
                    emptyMessage="No hay movimientos con los filtros aplicados."
                />

                <div className="mt-4">
                    <Pagination links={movimientos.links} />
                </div>
            </div>

            <MovimientoFormModal
                show={modalAbierto}
                onClose={() => setModalAbierto(false)}
                lotes={lotes}
                motivosManuales={motivosManuales}
            />
        </AuthenticatedLayout>
    );
}
