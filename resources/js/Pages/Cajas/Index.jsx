import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { IconEye, IconCashRegister, IconPlus } from '@tabler/icons-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import DataTable from '@/Components/DataTable';
import Pagination from '@/Components/Pagination';
import Badge from '@/Components/Badge';
import Can from '@/Components/Can';
import IconButton from '@/Components/IconButton';
import SelectInput from '@/Components/SelectInput';
import PrimaryButton from '@/Components/PrimaryButton';
import AbrirCajaModal from './Partials/AbrirCajaModal';
import { formatearMoneda } from '@/utils/format';

/**
 * Listado de cajas (turnos) con filtros y CTA "Abrir caja" si el usuario
 * no tiene una caja activa.
 */
export default function Index({ cajas, filtros, miCajaAbierta = null, esAdmin = false }) {
    const [abrirModal, setAbrirModal] = useState(false);

    const aplicarFiltros = (cambios) => {
        router.get(
            route('cajas.index'),
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
                  hour: '2-digit',
                  minute: '2-digit',
              })
            : '—';

    const columns = [
        { key: 'id', label: 'N°' },
        {
            key: 'cajero',
            label: 'Cajero',
            render: (row) => row.cajero?.name ?? '—',
        },
        {
            key: 'abierta_en',
            label: 'Apertura',
            render: (row) => formatFecha(row.abierta_en),
        },
        {
            key: 'monto_apertura',
            label: 'Monto apertura',
            render: (row) => formatearMoneda(row.monto_apertura),
        },
        {
            key: 'cerrada_en',
            label: 'Cierre',
            render: (row) => formatFecha(row.cerrada_en),
        },
        {
            key: 'diferencia',
            label: 'Diferencia',
            render: (row) => {
                if (row.diferencia === null || row.diferencia === undefined) {
                    return <span className="text-gray-400">—</span>;
                }
                const num = Number(row.diferencia);
                const color =
                    num === 0
                        ? 'text-gray-700 dark:text-gray-300'
                        : num > 0
                        ? 'text-emerald-600'
                        : 'text-red-600';
                return <span className={color}>{formatearMoneda(num)}</span>;
            },
        },
        {
            key: 'estado',
            label: 'Estado',
            render: (row) => (
                <Badge variant={row.estado === 'ABIERTA' ? 'warning' : 'success'}>
                    {row.estado}
                </Badge>
            ),
        },
        {
            key: 'acciones',
            label: 'Acciones',
            render: (row) => (
                <Link href={route('cajas.show', row.id)}>
                    <IconButton icon={IconEye} title="Ver detalle" variant="primary" />
                </Link>
            ),
        },
    ];

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold text-gray-800 dark:text-gray-200">
                    Cajas
                </h2>
            }
        >
            <Head title="Cajas" />

            <div className="mx-auto max-w-7xl">
                {/* Banner caja activa */}
                {miCajaAbierta ? (
                    <div className="mb-4 rounded-md border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-800 dark:bg-emerald-900/20">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p className="text-sm font-semibold text-emerald-700 dark:text-emerald-300">
                                    Tienes una caja abierta (#{miCajaAbierta.id})
                                </p>
                                <p className="text-xs text-emerald-700 dark:text-emerald-400">
                                    Apertura: {formatFecha(miCajaAbierta.abierta_en)} ·
                                    Monto inicial: {formatearMoneda(miCajaAbierta.monto_apertura)}
                                </p>
                            </div>
                            <Link href={route('cajas.show', miCajaAbierta.id)}>
                                <PrimaryButton>
                                    <IconCashRegister className="me-1 h-4 w-4" />
                                    Ir a mi caja
                                </PrimaryButton>
                            </Link>
                        </div>
                    </div>
                ) : (
                    <div className="mb-4 rounded-md border border-amber-200 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-900/20">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p className="text-sm font-semibold text-amber-700 dark:text-amber-300">
                                    No tienes ninguna caja abierta.
                                </p>
                                <p className="text-xs text-amber-700 dark:text-amber-400">
                                    Debes abrir caja antes de registrar ventas en el POS.
                                </p>
                            </div>
                            <Can permission="cajas.create">
                                <PrimaryButton onClick={() => setAbrirModal(true)}>
                                    <IconPlus className="me-1 h-4 w-4" />
                                    Abrir caja
                                </PrimaryButton>
                            </Can>
                        </div>
                    </div>
                )}

                {/* Filtros */}
                <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                    <SelectInput
                        value={filtros.estado ?? ''}
                        onChange={(e) => aplicarFiltros({ estado: e.target.value || null })}
                        className="w-full text-sm sm:w-auto"
                    >
                        <option value="">Todos los estados</option>
                        <option value="ABIERTA">Abiertas</option>
                        <option value="CERRADA">Cerradas</option>
                    </SelectInput>
                </div>

                <DataTable
                    columns={columns}
                    rows={cajas.data}
                    emptyMessage="No hay cajas registradas."
                />

                <div className="mt-4">
                    <Pagination links={cajas.links} />
                </div>
            </div>

            <AbrirCajaModal show={abrirModal} onClose={() => setAbrirModal(false)} />
        </AuthenticatedLayout>
    );
}
