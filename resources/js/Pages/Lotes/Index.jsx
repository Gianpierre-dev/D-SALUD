import { Head, router } from '@inertiajs/react';
import { IconPencil, IconTrash, IconPlus } from '@tabler/icons-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import DataTable from '@/Components/DataTable';
import Pagination from '@/Components/Pagination';
import SearchInput from '@/Components/SearchInput';
import Badge from '@/Components/Badge';
import Can from '@/Components/Can';
import IconButton from '@/Components/IconButton';
import ConfirmDialog from '@/Components/ConfirmDialog';
import PrimaryButton from '@/Components/PrimaryButton';
import LoteFormModal from './Partials/LoteFormModal';
import { useFormModal } from '@/hooks/useFormModal';
import { useDelete } from '@/hooks/useDelete';

/**
 * Determina el estado de vencimiento del lote.
 * Retorna 'vencido', 'por_vencer' o null.
 */
function estadoVencimiento(fechaVencimiento, diasAlerta) {
    const hoy = new Date();
    hoy.setHours(0, 0, 0, 0);
    const [y, m, d] = fechaVencimiento.split('-').map(Number);
    const vence = new Date(y, m - 1, d);

    if (vence < hoy) {
        return 'vencido';
    }

    const umbral = new Date(hoy);
    umbral.setDate(umbral.getDate() + diasAlerta);

    if (vence <= umbral) {
        return 'por_vencer';
    }

    return null;
}

/**
 * Formatea una cadena de fecha (YYYY-MM-DD) a formato local legible.
 */
function formatearFecha(fecha) {
    if (!fecha) return '—';
    const [year, month, day] = fecha.split('-');
    return `${day}/${month}/${year}`;
}

export default function Index({ lotes, productos, proveedores, filtros, diasAlerta }) {
    const modal = useFormModal();
    const borrado = useDelete('lotes.destroy');

    const buscar = (termino) =>
        router.get(
            route('lotes.index'),
            { buscar: termino },
            { preserveState: true, replace: true },
        );

    const columns = [
        {
            key: 'producto',
            label: 'Producto',
            render: (row) => row.producto?.nombre ?? '—',
        },
        {
            key: 'codigo_lote',
            label: 'Código de lote',
        },
        {
            key: 'fecha_vencimiento',
            label: 'Vencimiento',
            render: (row) => {
                const estado = estadoVencimiento(row.fecha_vencimiento, diasAlerta);
                return (
                    <div className="flex flex-wrap items-center gap-2">
                        <span>{formatearFecha(row.fecha_vencimiento)}</span>
                        {estado === 'vencido' && (
                            <Badge variant="danger">Vencido</Badge>
                        )}
                        {estado === 'por_vencer' && (
                            <Badge variant="warning">Por vencer</Badge>
                        )}
                    </div>
                );
            },
        },
        {
            key: 'stock',
            label: 'Stock',
        },
        {
            key: 'proveedor',
            label: 'Proveedor',
            render: (row) => row.proveedor?.razon_social ?? '—',
        },
        {
            key: 'acciones',
            label: 'Acciones',
            render: (row) => (
                <div className="flex gap-1">
                    <Can permission="lotes.update">
                        <IconButton
                            icon={IconPencil}
                            variant="primary"
                            title="Editar"
                            onClick={() => modal.abrirEditar(row)}
                        />
                    </Can>
                    <Can permission="lotes.delete">
                        <IconButton
                            icon={IconTrash}
                            variant="danger"
                            title="Eliminar"
                            onClick={() => borrado.solicitar(row)}
                        />
                    </Can>
                </div>
            ),
        },
    ];

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold text-gray-800 dark:text-gray-200">
                    Lotes
                </h2>
            }
        >
            <Head title="Lotes" />

            <div className="mx-auto max-w-6xl">
                <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <SearchInput
                        value={filtros.buscar ?? ''}
                        onSearch={buscar}
                        placeholder="Buscar lote o producto..."
                    />
                    <Can permission="lotes.create">
                        <PrimaryButton onClick={modal.abrirCrear}>
                            <IconPlus className="me-1 h-4 w-4" />
                            Nuevo lote
                        </PrimaryButton>
                    </Can>
                </div>

                <DataTable
                    columns={columns}
                    rows={lotes.data}
                    emptyMessage="No hay lotes registrados."
                />

                <div className="mt-4">
                    <Pagination links={lotes.links} />
                </div>
            </div>

            <LoteFormModal
                show={modal.abierto}
                onClose={modal.cerrar}
                lote={modal.entidad}
                productos={productos}
                proveedores={proveedores}
            />

            <ConfirmDialog
                show={Boolean(borrado.pendiente)}
                title="Eliminar lote"
                message={`¿Está seguro de eliminar el lote "${borrado.pendiente?.codigo_lote}"? Esta acción no se puede deshacer.`}
                confirmLabel="Eliminar"
                processing={borrado.procesando}
                onConfirm={borrado.confirmar}
                onClose={borrado.cancelar}
            />
        </AuthenticatedLayout>
    );
}
