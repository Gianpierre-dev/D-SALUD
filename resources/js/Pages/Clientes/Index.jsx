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
import ClienteFormModal from './Partials/ClienteFormModal';
import { useFormModal } from '@/hooks/useFormModal';
import { useDelete } from '@/hooks/useDelete';

export default function Index({ clientes, filtros }) {
    const modal = useFormModal();
    const borrado = useDelete('clientes.destroy');

    const buscar = (termino) =>
        router.get(
            route('clientes.index'),
            { buscar: termino },
            { preserveState: true, replace: true },
        );

    const columns = [
        {
            key: 'documento',
            label: 'Documento',
            render: (row) => (
                <span className="text-sm">
                    <span className="me-2 inline-flex items-center rounded bg-gray-100 px-1.5 py-0.5 text-xs font-semibold text-gray-700 dark:bg-gray-700 dark:text-gray-200">
                        {row.tipo_documento}
                    </span>
                    {row.numero_documento}
                </span>
            ),
        },
        { key: 'nombre', label: 'Nombre / Razón social' },
        {
            key: 'telefono',
            label: 'Teléfono',
            render: (row) => row.telefono || '—',
        },
        {
            key: 'email',
            label: 'Correo',
            render: (row) => row.email || '—',
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
                    <Can permission="clientes.update">
                        <IconButton
                            icon={IconPencil}
                            variant="primary"
                            title="Editar"
                            onClick={() => modal.abrirEditar(row)}
                        />
                    </Can>
                    <Can permission="clientes.delete">
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
                    Clientes
                </h2>
            }
        >
            <Head title="Clientes" />

            <div className="mx-auto max-w-6xl">
                <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <SearchInput
                        value={filtros.buscar ?? ''}
                        onSearch={buscar}
                        placeholder="Buscar por nombre o documento..."
                    />
                    <Can permission="clientes.create">
                        <PrimaryButton onClick={modal.abrirCrear}>
                            <IconPlus className="me-1 h-4 w-4" />
                            Nuevo cliente
                        </PrimaryButton>
                    </Can>
                </div>

                <DataTable
                    columns={columns}
                    rows={clientes.data}
                    emptyMessage="No hay clientes registrados."
                />

                <div className="mt-4">
                    <Pagination links={clientes.links} />
                </div>
            </div>

            <ClienteFormModal
                show={modal.abierto}
                onClose={modal.cerrar}
                cliente={modal.entidad}
            />

            <ConfirmDialog
                show={Boolean(borrado.pendiente)}
                title="Eliminar cliente"
                message={`¿Está seguro de eliminar al cliente "${borrado.pendiente?.nombre}"? Esta acción no se puede deshacer.`}
                confirmLabel="Eliminar"
                processing={borrado.procesando}
                onConfirm={borrado.confirmar}
                onClose={borrado.cancelar}
            />
        </AuthenticatedLayout>
    );
}
