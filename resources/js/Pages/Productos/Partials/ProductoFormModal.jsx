import { useEffect } from 'react';
import { useForm } from '@inertiajs/react';
import Modal from '@/Components/Modal';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import InputError from '@/Components/InputError';
import Checkbox from '@/Components/Checkbox';
import SelectInput from '@/Components/SelectInput';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';

/**
 * Modal de creación/edición de producto.
 * Si recibe `producto`, opera en modo edición; si no, en modo creación.
 * Recibe `categorias` para poblar el select de categoría.
 */
export default function ProductoFormModal({
    show,
    onClose,
    producto = null,
    categorias = [],
}) {
    const esEdicion = Boolean(producto);

    const { data, setData, post, put, processing, errors, reset, clearErrors } = useForm({
        codigo: '',
        nombre: '',
        categoria_id: '',
        laboratorio: '',
        unidad_medida: '',
        precio_venta: '',
        stock_minimo: 0,
        activo: true,
    });

    useEffect(() => {
        if (!show) {
            return;
        }
        clearErrors();
        if (producto) {
            setData({
                codigo: producto.codigo,
                nombre: producto.nombre,
                categoria_id: producto.categoria_id ?? '',
                laboratorio: producto.laboratorio ?? '',
                unidad_medida: producto.unidad_medida,
                precio_venta: producto.precio_venta,
                stock_minimo: producto.stock_minimo,
                activo: Boolean(producto.activo),
            });
        } else {
            reset();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [show, producto]);

    const cerrar = () => {
        reset();
        onClose();
    };

    const submit = (e) => {
        e.preventDefault();
        const opciones = {
            preserveScroll: true,
            onSuccess: cerrar,
        };

        if (esEdicion) {
            put(route('productos.update', producto.id), opciones);
        } else {
            post(route('productos.store'), opciones);
        }
    };

    return (
        <Modal show={show} onClose={cerrar} maxWidth="lg">
            <form onSubmit={submit} className="p-6">
                <h2 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                    {esEdicion ? 'Editar producto' : 'Nuevo producto'}
                </h2>

                {/* Fila: Código + Nombre */}
                <div className="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel htmlFor="codigo" value="Código" />
                        <TextInput
                            id="codigo"
                            className="mt-1 block w-full"
                            value={data.codigo}
                            onChange={(e) => setData('codigo', e.target.value)}
                            isFocused
                            autoComplete="off"
                        />
                        <InputError message={errors.codigo} className="mt-2" />
                    </div>

                    <div>
                        <InputLabel htmlFor="nombre" value="Nombre" />
                        <TextInput
                            id="nombre"
                            className="mt-1 block w-full"
                            value={data.nombre}
                            onChange={(e) => setData('nombre', e.target.value)}
                            autoComplete="off"
                        />
                        <InputError message={errors.nombre} className="mt-2" />
                    </div>
                </div>

                {/* Categoría */}
                <div className="mt-4">
                    <InputLabel htmlFor="categoria_id" value="Categoría" />
                    <SelectInput
                        id="categoria_id"
                        className="mt-1 block w-full"
                        value={data.categoria_id}
                        onChange={(e) => setData('categoria_id', e.target.value)}
                    >
                        <option value="">— Seleccionar categoría —</option>
                        {categorias.map((cat) => (
                            <option key={cat.id} value={cat.id}>
                                {cat.nombre}
                            </option>
                        ))}
                    </SelectInput>
                    <InputError message={errors.categoria_id} className="mt-2" />
                </div>

                {/* Laboratorio */}
                <div className="mt-4">
                    <InputLabel htmlFor="laboratorio" value="Laboratorio (opcional)" />
                    <TextInput
                        id="laboratorio"
                        className="mt-1 block w-full"
                        value={data.laboratorio}
                        onChange={(e) => setData('laboratorio', e.target.value)}
                        autoComplete="off"
                    />
                    <InputError message={errors.laboratorio} className="mt-2" />
                </div>

                {/* Fila: Unidad de medida + Precio de venta */}
                <div className="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel htmlFor="unidad_medida" value="Unidad de medida" />
                        <TextInput
                            id="unidad_medida"
                            className="mt-1 block w-full"
                            value={data.unidad_medida}
                            onChange={(e) => setData('unidad_medida', e.target.value)}
                            autoComplete="off"
                        />
                        <InputError message={errors.unidad_medida} className="mt-2" />
                    </div>

                    <div>
                        <InputLabel htmlFor="precio_venta" value="Precio de venta" />
                        <TextInput
                            id="precio_venta"
                            type="number"
                            step="0.01"
                            min="0"
                            className="mt-1 block w-full"
                            value={data.precio_venta}
                            onChange={(e) => setData('precio_venta', e.target.value)}
                        />
                        <InputError message={errors.precio_venta} className="mt-2" />
                    </div>
                </div>

                {/* Stock mínimo */}
                <div className="mt-4">
                    <InputLabel htmlFor="stock_minimo" value="Stock mínimo" />
                    <TextInput
                        id="stock_minimo"
                        type="number"
                        min="0"
                        step="1"
                        className="mt-1 block w-full"
                        value={data.stock_minimo}
                        onChange={(e) => setData('stock_minimo', e.target.value)}
                    />
                    <InputError message={errors.stock_minimo} className="mt-2" />
                </div>

                {/* Activo */}
                <div className="mt-4">
                    <label className="flex items-center">
                        <Checkbox
                            checked={data.activo}
                            onChange={(e) => setData('activo', e.target.checked)}
                        />
                        <span className="ms-2 text-sm text-gray-600 dark:text-gray-400">
                            Activo
                        </span>
                    </label>
                </div>

                <div className="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" onClick={cerrar} disabled={processing}>
                        Cancelar
                    </SecondaryButton>
                    <PrimaryButton disabled={processing}>
                        {esEdicion ? 'Guardar cambios' : 'Crear producto'}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>
    );
}
