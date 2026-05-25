import { useEffect } from 'react';
import { useForm } from '@inertiajs/react';
import Modal from '@/Components/Modal';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import InputError from '@/Components/InputError';
import SelectInput from '@/Components/SelectInput';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';

/**
 * Modal de creación/edición de lote.
 * Si recibe `lote`, opera en modo edición; si no, en modo creación.
 */
export default function LoteFormModal({ show, onClose, lote = null, productos, proveedores }) {
    const esEdicion = Boolean(lote);

    const { data, setData, post, put, processing, errors, reset, clearErrors } = useForm({
        producto_id:       '',
        proveedor_id:      '',
        codigo_lote:       '',
        fecha_vencimiento: '',
        stock:             '',
        precio_compra:     '',
    });

    useEffect(() => {
        if (!show) {
            return;
        }
        clearErrors();
        if (lote) {
            setData({
                producto_id:       lote.producto_id ?? '',
                proveedor_id:      lote.proveedor_id ?? '',
                codigo_lote:       lote.codigo_lote ?? '',
                fecha_vencimiento: lote.fecha_vencimiento ?? '',
                stock:             lote.stock ?? '',
                precio_compra:     lote.precio_compra ?? '',
            });
        } else {
            reset();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [show, lote]);

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
            put(route('lotes.update', lote.id), opciones);
        } else {
            post(route('lotes.store'), opciones);
        }
    };

    return (
        <Modal show={show} onClose={cerrar} maxWidth="md">
            <form onSubmit={submit} className="p-6">
                <h2 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                    {esEdicion ? 'Editar lote' : 'Nuevo lote'}
                </h2>

                {/* Producto */}
                <div className="mt-4">
                    <InputLabel htmlFor="producto_id" value="Producto" />
                    <SelectInput
                        id="producto_id"
                        className="mt-1 block w-full"
                        value={data.producto_id}
                        onChange={(e) => setData('producto_id', e.target.value)}
                    >
                        <option value="">— Seleccione un producto —</option>
                        {productos.map((p) => (
                            <option key={p.id} value={p.id}>
                                {p.nombre}
                            </option>
                        ))}
                    </SelectInput>
                    <InputError message={errors.producto_id} className="mt-2" />
                </div>

                {/* Proveedor (opcional) */}
                <div className="mt-4">
                    <InputLabel htmlFor="proveedor_id" value="Proveedor (opcional)" />
                    <SelectInput
                        id="proveedor_id"
                        className="mt-1 block w-full"
                        value={data.proveedor_id}
                        onChange={(e) => setData('proveedor_id', e.target.value)}
                    >
                        <option value="">— Sin proveedor —</option>
                        {proveedores.map((p) => (
                            <option key={p.id} value={p.id}>
                                {p.razon_social}
                            </option>
                        ))}
                    </SelectInput>
                    <InputError message={errors.proveedor_id} className="mt-2" />
                </div>

                {/* Código de lote */}
                <div className="mt-4">
                    <InputLabel htmlFor="codigo_lote" value="Código de lote" />
                    <TextInput
                        id="codigo_lote"
                        className="mt-1 block w-full"
                        value={data.codigo_lote}
                        onChange={(e) => setData('codigo_lote', e.target.value)}
                        autoComplete="off"
                        maxLength={100}
                    />
                    <InputError message={errors.codigo_lote} className="mt-2" />
                </div>

                {/* Fecha de vencimiento */}
                <div className="mt-4">
                    <InputLabel htmlFor="fecha_vencimiento" value="Fecha de vencimiento" />
                    <TextInput
                        id="fecha_vencimiento"
                        type="date"
                        className="mt-1 block w-full"
                        value={data.fecha_vencimiento}
                        onChange={(e) => setData('fecha_vencimiento', e.target.value)}
                    />
                    <InputError message={errors.fecha_vencimiento} className="mt-2" />
                </div>

                {/* Stock y Precio de compra en fila */}
                <div className="mt-4 grid grid-cols-2 gap-4">
                    <div>
                        <InputLabel htmlFor="stock" value="Stock" />
                        <TextInput
                            id="stock"
                            type="number"
                            min="0"
                            step="1"
                            className="mt-1 block w-full"
                            value={data.stock}
                            onChange={(e) => setData('stock', e.target.value)}
                        />
                        <InputError message={errors.stock} className="mt-2" />
                    </div>
                    <div>
                        <InputLabel htmlFor="precio_compra" value="Precio de compra (S/)" />
                        <TextInput
                            id="precio_compra"
                            type="number"
                            min="0"
                            step="0.01"
                            className="mt-1 block w-full"
                            value={data.precio_compra}
                            onChange={(e) => setData('precio_compra', e.target.value)}
                        />
                        <InputError message={errors.precio_compra} className="mt-2" />
                    </div>
                </div>

                <div className="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" onClick={cerrar} disabled={processing}>
                        Cancelar
                    </SecondaryButton>
                    <PrimaryButton disabled={processing}>
                        {esEdicion ? 'Guardar cambios' : 'Crear lote'}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>
    );
}
