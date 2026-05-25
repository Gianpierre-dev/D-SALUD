import { useState } from 'react';
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

export default function Index({ usuarios, roles, filtros }) {
    const [modalAbierto, setModalAbierto] = useState(false);
    const [usuarioEdit, setUsuarioEdit] = useState(null);
    const [usuarioEliminar, setUsuarioEliminar] = useState(null);
    const [eliminando, setEliminando] = useState(false);

    const buscar = (termino) =>
        router.get(
            route('usuarios.index'),
            { buscar: termino },
            { preserveState: true, replace: true },
        );

    const abrirCrear = () => {
        setUsuarioEdit(null);
        setModalAbierto(true);
    };

    const abrirEditar = (usuario) => {
        setUsuarioEdit(usuario);
        setModalAbierto(true);
    };

    const confirmarEliminar = () => {
        setEliminando(true);
        router.delete(route('usuarios.destroy', usuarioEliminar.id), {
            preserveScroll: true,
            onFinish: () => {
                setEliminando(false);
                setUsuarioEliminar(null);
            },
        });
    };

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
                            onClick={() => abrirEditar(row)}
                        />
                    </Can>
                    <Can permission="usuarios.delete">
                        <IconButton
                            icon={IconTrash}
                            variant="danger"
                            title="Eliminar"
                            onClick={() => setUsuarioEliminar(row)}
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
                        <PrimaryButton onClick={abrirCrear}>
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
                show={modalAbierto}
                onClose={() => setModalAbierto(false)}
                usuario={usuarioEdit}
                roles={roles}
            />

            <ConfirmDialog
                show={Boolean(usuarioEliminar)}
                title="Eliminar usuario"
                message={`¿Está seguro de eliminar al usuario "${usuarioEliminar?.name}"? Esta acción no se puede deshacer.`}
                confirmLabel="Eliminar"
                processing={eliminando}
                onConfirm={confirmarEliminar}
                onClose={() => setUsuarioEliminar(null)}
            />
        </AuthenticatedLayout>
    );
}
