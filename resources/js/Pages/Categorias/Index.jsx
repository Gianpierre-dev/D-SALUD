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
import CategoriaFormModal from './Partials/CategoriaFormModal';

export default function Index({ categorias, filtros }) {
    const [modalAbierto, setModalAbierto] = useState(false);
    const [categoriaEdit, setCategoriaEdit] = useState(null);
    const [categoriaEliminar, setCategoriaEliminar] = useState(null);
    const [eliminando, setEliminando] = useState(false);

    const buscar = (termino) =>
        router.get(
            route('categorias.index'),
            { buscar: termino },
            { preserveState: true, replace: true },
        );

    const abrirCrear = () => {
        setCategoriaEdit(null);
        setModalAbierto(true);
    };

    const abrirEditar = (categoria) => {
        setCategoriaEdit(categoria);
        setModalAbierto(true);
    };

    const confirmarEliminar = () => {
        setEliminando(true);
        router.delete(route('categorias.destroy', categoriaEliminar.id), {
            preserveScroll: true,
            onFinish: () => {
                setEliminando(false);
                setCategoriaEliminar(null);
            },
        });
    };

    const columns = [
        { key: 'nombre', label: 'Nombre' },
        {
            key: 'descripcion',
            label: 'Descripción',
            render: (row) => row.descripcion || '—',
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
                    <Can permission="categorias.update">
                        <IconButton
                            icon={IconPencil}
                            variant="primary"
                            title="Editar"
                            onClick={() => abrirEditar(row)}
                        />
                    </Can>
                    <Can permission="categorias.delete">
                        <IconButton
                            icon={IconTrash}
                            variant="danger"
                            title="Eliminar"
                            onClick={() => setCategoriaEliminar(row)}
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
                    Categorías
                </h2>
            }
        >
            <Head title="Categorías" />

            <div className="mx-auto max-w-5xl">
                <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <SearchInput
                        value={filtros.buscar ?? ''}
                        onSearch={buscar}
                        placeholder="Buscar categoría..."
                    />
                    <Can permission="categorias.create">
                        <PrimaryButton onClick={abrirCrear}>
                            <IconPlus className="me-1 h-4 w-4" />
                            Nueva categoría
                        </PrimaryButton>
                    </Can>
                </div>

                <DataTable
                    columns={columns}
                    rows={categorias.data}
                    emptyMessage="No hay categorías registradas."
                />

                <div className="mt-4">
                    <Pagination links={categorias.links} />
                </div>
            </div>

            <CategoriaFormModal
                show={modalAbierto}
                onClose={() => setModalAbierto(false)}
                categoria={categoriaEdit}
            />

            <ConfirmDialog
                show={Boolean(categoriaEliminar)}
                title="Eliminar categoría"
                message={`¿Está seguro de eliminar la categoría "${categoriaEliminar?.nombre}"? Esta acción no se puede deshacer.`}
                confirmLabel="Eliminar"
                processing={eliminando}
                onConfirm={confirmarEliminar}
                onClose={() => setCategoriaEliminar(null)}
            />
        </AuthenticatedLayout>
    );
}
