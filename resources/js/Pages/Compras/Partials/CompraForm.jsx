import { useEffect } from 'react';
import { useForm } from '@inertiajs/react';
import { IconPlus, IconTrash } from '@tabler/icons-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import InputError from '@/Components/InputError';
import SelectInput from '@/Components/SelectInput';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import IconButton from '@/Components/IconButton';
import { soloDigitos, soloDecimalPositivo } from '@/utils/inputs';
import { formatearMoneda } from '@/utils/format';

/**
 * Formulario compartido por Create y Edit de Compras.
 *
 * El listado de líneas es dinámico: el admin agrega/quita filas con
 * producto, cantidad, precio unitario, código de lote y fecha de
 * vencimiento. El total se recalcula en cada cambio.
 */
function lineaVacia() {
    return {
        producto_id: '',
        cantidad: '',
        precio_unitario: '',
        codigo_lote: '',
        fecha_vencimiento: '',
    };
}

export default function CompraForm({ compra = null, proveedores, productos, accion }) {
    const esEdicion = Boolean(compra);

    const valoresIniciales = esEdicion
        ? {
              proveedor_id: compra.proveedor_id ?? '',
              fecha_compra: compra.fecha_compra?.slice(0, 10) ?? new Date().toISOString().slice(0, 10),
              observaciones: compra.observaciones ?? '',
              items: compra.detalles?.map((d) => ({
                  producto_id: d.producto_id,
                  cantidad: String(d.cantidad),
                  precio_unitario: String(d.precio_unitario),
                  codigo_lote: d.codigo_lote ?? '',
                  fecha_vencimiento: d.fecha_vencimiento?.slice(0, 10) ?? '',
              })) ?? [lineaVacia()],
          }
        : {
              proveedor_id: '',
              fecha_compra: new Date().toISOString().slice(0, 10),
              observaciones: '',
              items: [lineaVacia()],
          };

    const { data, setData, post, put, processing, errors } = useForm(valoresIniciales);

    // Cuando el admin agrega/quita líneas no queremos errores residuales
    // apuntando a índices que ya no existen.
    useEffect(() => {
        if (data.items.length === 0) {
            setData('items', [lineaVacia()]);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [data.items.length]);

    const actualizarLinea = (index, campo, valor) => {
        setData('items', data.items.map((linea, i) => (i === index ? { ...linea, [campo]: valor } : linea)));
    };

    const agregarLinea = () => setData('items', [...data.items, lineaVacia()]);
    const quitarLinea = (index) => setData('items', data.items.filter((_, i) => i !== index));

    const totalCompra = data.items.reduce(
        (acc, linea) => acc + (Number(linea.cantidad) || 0) * (Number(linea.precio_unitario) || 0),
        0,
    );

    const submit = (e) => {
        e.preventDefault();
        if (esEdicion) {
            put(route('compras.update', compra.id));
        } else {
            post(route('compras.store'));
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold text-gray-800 dark:text-gray-200">
                    {accion === 'editar' ? `Editar compra ${compra?.numero_formateado}` : 'Nueva orden de compra'}
                </h2>
            }
        >
            <form onSubmit={submit} className="mx-auto max-w-5xl space-y-6">
                {/* Cabecera */}
                <div className="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div className="sm:col-span-2">
                            <InputLabel htmlFor="proveedor_id" value="Proveedor" />
                            <SelectInput
                                id="proveedor_id"
                                className="mt-1 block w-full"
                                value={data.proveedor_id}
                                onChange={(e) => setData('proveedor_id', e.target.value)}
                            >
                                <option value="">— Seleccione un proveedor —</option>
                                {proveedores.map((p) => (
                                    <option key={p.id} value={p.id}>
                                        {p.ruc} — {p.razon_social}
                                    </option>
                                ))}
                            </SelectInput>
                            <InputError message={errors.proveedor_id} className="mt-2" />
                        </div>

                        <div>
                            <InputLabel htmlFor="fecha_compra" value="Fecha de compra" />
                            <TextInput
                                id="fecha_compra"
                                type="date"
                                className="mt-1 block w-full"
                                value={data.fecha_compra}
                                onChange={(e) => setData('fecha_compra', e.target.value)}
                            />
                            <InputError message={errors.fecha_compra} className="mt-2" />
                        </div>

                        <div className="sm:col-span-3">
                            <InputLabel htmlFor="observaciones" value="Observaciones (opcional)" />
                            <textarea
                                id="observaciones"
                                rows={2}
                                maxLength={500}
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                value={data.observaciones ?? ''}
                                onChange={(e) => setData('observaciones', e.target.value)}
                            />
                            <InputError message={errors.observaciones} className="mt-2" />
                        </div>
                    </div>
                </div>

                {/* Líneas */}
                <div className="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                    <div className="mb-3 flex items-center justify-between">
                        <h3 className="text-base font-semibold text-gray-800 dark:text-gray-100">
                            Productos a comprar
                        </h3>
                        <SecondaryButton type="button" onClick={agregarLinea}>
                            <IconPlus className="me-1 h-4 w-4" />
                            Agregar línea
                        </SecondaryButton>
                    </div>

                    <div className="space-y-3">
                        {data.items.map((linea, index) => (
                            <div
                                key={index}
                                className="grid grid-cols-1 gap-3 rounded-md border border-gray-200 p-3 sm:grid-cols-12 dark:border-gray-700"
                            >
                                <div className="sm:col-span-4">
                                    <InputLabel value="Producto" className="text-xs" />
                                    <SelectInput
                                        className="mt-1 block w-full text-sm"
                                        value={linea.producto_id}
                                        onChange={(e) => actualizarLinea(index, 'producto_id', e.target.value)}
                                    >
                                        <option value="">—</option>
                                        {productos.map((p) => (
                                            <option key={p.id} value={p.id}>
                                                {p.codigo} — {p.nombre}
                                            </option>
                                        ))}
                                    </SelectInput>
                                    <InputError message={errors[`items.${index}.producto_id`]} className="mt-1" />
                                </div>

                                <div className="sm:col-span-2">
                                    <InputLabel value="Cantidad" className="text-xs" />
                                    <TextInput
                                        inputMode="numeric"
                                        pattern="[0-9]*"
                                        className="mt-1 block w-full text-sm"
                                        value={linea.cantidad}
                                        onChange={(e) => actualizarLinea(index, 'cantidad', soloDigitos(e.target.value, 5))}
                                    />
                                    <InputError message={errors[`items.${index}.cantidad`]} className="mt-1" />
                                </div>

                                <div className="sm:col-span-2">
                                    <InputLabel value="P. Unit (S/)" className="text-xs" />
                                    <TextInput
                                        inputMode="decimal"
                                        pattern="[0-9]*[.]?[0-9]*"
                                        className="mt-1 block w-full text-sm"
                                        value={linea.precio_unitario}
                                        onChange={(e) =>
                                            actualizarLinea(index, 'precio_unitario', soloDecimalPositivo(e.target.value, 8, 2))
                                        }
                                    />
                                    <InputError message={errors[`items.${index}.precio_unitario`]} className="mt-1" />
                                </div>

                                <div className="sm:col-span-2">
                                    <InputLabel value="Cód. lote" className="text-xs" />
                                    <TextInput
                                        className="mt-1 block w-full text-sm"
                                        value={linea.codigo_lote}
                                        maxLength={100}
                                        onChange={(e) => actualizarLinea(index, 'codigo_lote', e.target.value)}
                                    />
                                    <InputError message={errors[`items.${index}.codigo_lote`]} className="mt-1" />
                                </div>

                                <div className="sm:col-span-2">
                                    <InputLabel value="Vence" className="text-xs" />
                                    <TextInput
                                        type="date"
                                        className="mt-1 block w-full text-sm"
                                        value={linea.fecha_vencimiento}
                                        onChange={(e) => actualizarLinea(index, 'fecha_vencimiento', e.target.value)}
                                    />
                                    <InputError message={errors[`items.${index}.fecha_vencimiento`]} className="mt-1" />
                                </div>

                                <div className="flex items-end justify-end sm:col-span-12">
                                    <span className="me-3 text-sm text-gray-600 dark:text-gray-400">
                                        Subtotal:{' '}
                                        <strong>
                                            {formatearMoneda(
                                                (Number(linea.cantidad) || 0) *
                                                    (Number(linea.precio_unitario) || 0),
                                            )}
                                        </strong>
                                    </span>
                                    <IconButton
                                        icon={IconTrash}
                                        title="Quitar línea"
                                        variant="danger"
                                        onClick={() => quitarLinea(index)}
                                    />
                                </div>
                            </div>
                        ))}
                    </div>

                    {/* Total */}
                    <div className="mt-4 flex items-center justify-end border-t border-gray-200 pt-3 dark:border-gray-700">
                        <span className="me-3 text-sm font-medium text-gray-600 dark:text-gray-400">
                            Total estimado:
                        </span>
                        <span className="text-xl font-bold text-brand-600 dark:text-brand-400">
                            {formatearMoneda(totalCompra)}
                        </span>
                    </div>
                </div>

                <div className="flex justify-end gap-3">
                    <SecondaryButton type="button" onClick={() => window.history.back()} disabled={processing}>
                        Cancelar
                    </SecondaryButton>
                    <PrimaryButton disabled={processing}>
                        {esEdicion ? 'Guardar cambios' : 'Crear orden'}
                    </PrimaryButton>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}
