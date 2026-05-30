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
import ProveedorFormModal from './Partials/ProveedorFormModal';
import { useFormModal } from '@/hooks/useFormModal';
import { useDelete } from '@/hooks/useDelete';

export default function Index({ proveedores, filtros }) {
    const modal = useFormModal();
    const borrado = useDelete('proveedores.destroy');

    const buscar = (termino) =>
        router.get(
            route('proveedores.index'),
            { buscar: termino },
            { preserveState: true, replace: true },
        );

    const columns = [
        { key: 'ruc', label: 'RUC' },
        { key: 'razon_social', label: 'Razón Social' },
        {
            key: 'contacto',
            label: 'Contacto',
            render: (row) => row.contacto || '—',
        },
        {
            key: 'telefono',
            label: 'Teléfono',
            render: (row) => row.telefono || '—',
        },
        {
            key: 'activo',
            label: 'Estado',
            render: (row) => (
                <Badge variant={row.activo ? 'success' : 'neutral'}>
                    {row.activo ? 'Activo' : 'Inactivo'}
                </Badge>
            ),
        },
        {
            key: 'acciones',
            label: 'Acciones',
            render: (row) => (
                <div className="flex gap-1">
                    <Can permission="proveedores.update">
                        <IconButton
                            icon={IconPencil}
                            variant="primary"
                            title="Editar"
                            onClick={() => modal.abrirEditar(row)}
                        />
                    </Can>
                    <Can permission="proveedores.delete">
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
                    Proveedores
                </h2>
            }
        >
            <Head title="Proveedores" />

            <div className="mx-auto max-w-6xl">
                <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <SearchInput
                        value={filtros.buscar ?? ''}
                        onSearch={buscar}
                        placeholder="Buscar proveedor..."
                    />
                    <Can permission="proveedores.create">
                        <PrimaryButton onClick={modal.abrirCrear}>
                            <IconPlus className="me-1 h-4 w-4" />
                            Nuevo proveedor
                        </PrimaryButton>
                    </Can>
                </div>

                <DataTable
                    columns={columns}
                    rows={proveedores.data}
                    emptyMessage="No hay proveedores registrados."
                />

                <div className="mt-4">
                    <Pagination links={proveedores.links} />
                </div>
            </div>

            <ProveedorFormModal
                show={modal.abierto}
                onClose={modal.cerrar}
                proveedor={modal.entidad}
            />

            <ConfirmDialog
                show={Boolean(borrado.pendiente)}
                title="Eliminar proveedor"
                message={`¿Está seguro de eliminar el proveedor "${borrado.pendiente?.razon_social}"? Esta acción no se puede deshacer.`}
                confirmLabel="Eliminar"
                processing={borrado.procesando}
                onConfirm={borrado.confirmar}
                onClose={borrado.cancelar}
            />
        </AuthenticatedLayout>
    );
}
