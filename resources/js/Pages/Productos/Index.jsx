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
import ProductoFormModal from './Partials/ProductoFormModal';
import { formatearMoneda } from '@/utils/format';
import { useFormModal } from '@/hooks/useFormModal';
import { useDelete } from '@/hooks/useDelete';

export default function Index({ productos, categorias, filtros }) {
    const modal = useFormModal();
    const borrado = useDelete('productos.destroy');

    const buscar = (termino) =>
        router.get(
            route('productos.index'),
            { buscar: termino },
            { preserveState: true, replace: true },
        );

    const columns = [
        { key: 'codigo', label: 'Código' },
        { key: 'nombre', label: 'Nombre' },
        {
            key: 'categoria',
            label: 'Categoría',
            render: (row) => row.categoria?.nombre ?? '—',
        },
        {
            key: 'precio_venta',
            label: 'Precio',
            render: (row) => formatearMoneda(row.precio_venta),
        },
        {
            key: 'stock_total',
            label: 'Stock',
            render: (row) => {
                const stock = row.stock_total ?? 0;
                const bajo = stock <= row.stock_minimo;
                return (
                    <span className="flex items-center gap-2">
                        {stock}
                        {bajo && (
                            <Badge variant="warning">Bajo</Badge>
                        )}
                    </span>
                );
            },
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
                    <Can permission="productos.update">
                        <IconButton
                            icon={IconPencil}
                            variant="primary"
                            title="Editar"
                            onClick={() => modal.abrirEditar(row)}
                        />
                    </Can>
                    <Can permission="productos.delete">
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
                    Productos
                </h2>
            }
        >
            <Head title="Productos" />

            <div className="mx-auto max-w-7xl">
                <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <SearchInput
                        value={filtros.buscar ?? ''}
                        onSearch={buscar}
                        placeholder="Buscar producto..."
                    />
                    <Can permission="productos.create">
                        <PrimaryButton onClick={modal.abrirCrear}>
                            <IconPlus className="me-1 h-4 w-4" />
                            Nuevo producto
                        </PrimaryButton>
                    </Can>
                </div>

                <DataTable
                    columns={columns}
                    rows={productos.data}
                    emptyMessage="No hay productos registrados."
                />

                <div className="mt-4">
                    <Pagination links={productos.links} />
                </div>
            </div>

            <ProductoFormModal
                show={modal.abierto}
                onClose={modal.cerrar}
                producto={modal.entidad}
                categorias={categorias}
            />

            <ConfirmDialog
                show={Boolean(borrado.pendiente)}
                title="Eliminar producto"
                message={`¿Está seguro de eliminar el producto "${borrado.pendiente?.nombre}"? Esta acción no se puede deshacer.`}
                confirmLabel="Eliminar"
                processing={borrado.procesando}
                onConfirm={borrado.confirmar}
                onClose={borrado.cancelar}
            />
        </AuthenticatedLayout>
    );
}
