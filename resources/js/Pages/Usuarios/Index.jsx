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
import UsuarioFormModal from './Partials/UsuarioFormModal';
import { useFormModal } from '@/hooks/useFormModal';
import { useDelete } from '@/hooks/useDelete';

export default function Index({ usuarios, roles, filtros }) {
    const modal = useFormModal();
    const borrado = useDelete('usuarios.destroy');

    const buscar = (termino) =>
        router.get(
            route('usuarios.index'),
            { buscar: termino },
            { preserveState: true, replace: true },
        );

    const columns = [
        { key: 'name', label: 'Nombre' },
        { key: 'email', label: 'Correo electrónico' },
        {
            key: 'roles',
            label: 'Rol(es)',
            render: (row) =>
                row.roles && row.roles.length > 0 ? (
                    <div className="flex flex-wrap gap-1">
                        {row.roles.map((r) => (
                            <Badge key={r.id} variant="info">
                                {r.name}
                            </Badge>
                        ))}
                    </div>
                ) : (
                    <span className="text-gray-400">Sin rol</span>
                ),
        },
        {
            key: 'acciones',
            label: 'Acciones',
            render: (row) => (
                <div className="flex gap-1">
                    <Can permission="usuarios.update">
                        <IconButton
                            icon={IconPencil}
                            variant="primary"
                            title="Editar"
                            onClick={() => modal.abrirEditar(row)}
                        />
                    </Can>
                    <Can permission="usuarios.delete">
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
                    Usuarios
                </h2>
            }
        >
            <Head title="Usuarios" />

            <div className="mx-auto max-w-5xl">
                <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <SearchInput
                        value={filtros.buscar ?? ''}
                        onSearch={buscar}
                        placeholder="Buscar por nombre o correo..."
                    />
                    <Can permission="usuarios.create">
                        <PrimaryButton onClick={modal.abrirCrear}>
                            <IconPlus className="me-1 h-4 w-4" />
                            Nuevo usuario
                        </PrimaryButton>
                    </Can>
                </div>

                <DataTable
                    columns={columns}
                    rows={usuarios.data}
                    emptyMessage="No hay usuarios registrados."
                />

                <div className="mt-4">
                    <Pagination links={usuarios.links} />
                </div>
            </div>

            <UsuarioFormModal
                show={modal.abierto}
                onClose={modal.cerrar}
                usuario={modal.entidad}
                roles={roles}
            />

            <ConfirmDialog
                show={Boolean(borrado.pendiente)}
                title="Eliminar usuario"
                message={`¿Está seguro de eliminar al usuario "${borrado.pendiente?.name}"? Esta acción no se puede deshacer.`}
                confirmLabel="Eliminar"
                processing={borrado.procesando}
                onConfirm={borrado.confirmar}
                onClose={borrado.cancelar}
            />
        </AuthenticatedLayout>
    );
}
