import { Head, router } from '@inertiajs/react';
import { IconPencil, IconTrash, IconPlus } from '@tabler/icons-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import DataTable from '@/Components/DataTable';
import Pagination from '@/Components/Pagination';
import SearchInput from '@/Components/SearchInput';
import Can from '@/Components/Can';
import IconButton from '@/Components/IconButton';
import ConfirmDialog from '@/Components/ConfirmDialog';
import PrimaryButton from '@/Components/PrimaryButton';
import RolFormModal from './Partials/RolFormModal';
import { useFormModal } from '@/hooks/useFormModal';
import { useDelete } from '@/hooks/useDelete';

/** Roles del sistema que no se pueden eliminar. */
const ROLES_PROTEGIDOS = ['Administrador', 'Vendedor'];

export default function Index({ roles, permisos, filtros }) {
    const modal = useFormModal();
    const borrado = useDelete('roles.destroy');

    const buscar = (termino) =>
        router.get(
            route('roles.index'),
            { buscar: termino },
            { preserveState: true, replace: true },
        );

    const columns = [
        { key: 'name', label: 'Nombre' },
        {
            key: 'permissions_count',
            label: 'Permisos',
            render: (row) => (
                <span className="font-medium">{row.permissions_count}</span>
            ),
        },
        {
            key: 'acciones',
            label: 'Acciones',
            render: (row) => (
                <div className="flex gap-1">
                    <Can permission="roles.update">
                        <IconButton
                            icon={IconPencil}
                            variant="primary"
                            title="Editar"
                            onClick={() => modal.abrirEditar(row)}
                        />
                    </Can>
                    {!ROLES_PROTEGIDOS.includes(row.name) && (
                        <Can permission="roles.delete">
                            <IconButton
                                icon={IconTrash}
                                variant="danger"
                                title="Eliminar"
                                onClick={() => borrado.solicitar(row)}
                            />
                        </Can>
                    )}
                </div>
            ),
        },
    ];

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold text-gray-800 dark:text-gray-200">
                    Roles
                </h2>
            }
        >
            <Head title="Roles" />

            <div className="mx-auto max-w-4xl">
                <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <SearchInput
                        value={filtros.buscar ?? ''}
                        onSearch={buscar}
                        placeholder="Buscar rol..."
                    />
                    <Can permission="roles.create">
                        <PrimaryButton onClick={modal.abrirCrear}>
                            <IconPlus className="me-1 h-4 w-4" />
                            Nuevo rol
                        </PrimaryButton>
                    </Can>
                </div>

                <DataTable
                    columns={columns}
                    rows={roles.data}
                    emptyMessage="No hay roles registrados."
                />

                <div className="mt-4">
                    <Pagination links={roles.links} />
                </div>
            </div>

            <RolFormModal
                show={modal.abierto}
                onClose={modal.cerrar}
                rol={modal.entidad}
                permisos={permisos}
            />

            <ConfirmDialog
                show={Boolean(borrado.pendiente)}
                title="Eliminar rol"
                message={`¿Está seguro de eliminar el rol "${borrado.pendiente?.name}"? Esta acción no se puede deshacer.`}
                confirmLabel="Eliminar"
                processing={borrado.procesando}
                onConfirm={borrado.confirmar}
                onClose={borrado.cancelar}
            />
        </AuthenticatedLayout>
    );
}
