import { useEffect, useMemo } from 'react';
import { useForm } from '@inertiajs/react';
import Modal from '@/Components/Modal';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import InputError from '@/Components/InputError';
import SelectInput from '@/Components/SelectInput';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import { soloDigitos } from '@/utils/inputs';

/**
 * Modal de registro de movimiento de inventario manual.
 *
 * Solo expone motivos MANUALES (mermas, ajustes, vencimientos, devoluciones).
 * Los motivos automáticos (VENTA, ANULACION_VENTA) son generados por
 * VentaService y no aparecen en esta UI.
 */
export default function MovimientoFormModal({ show, onClose, lotes, motivosManuales }) {
    const { data, setData, post, processing, errors, reset, clearErrors } = useForm({
        lote_id:     '',
        motivo:      motivosManuales[0]?.value ?? '',
        cantidad:    '',
        observacion: '',
    });

    useEffect(() => {
        if (!show) {
            return;
        }
        clearErrors();
        reset();
        setData('motivo', motivosManuales[0]?.value ?? '');
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [show]);

    const loteSeleccionado = useMemo(
        () => lotes.find((l) => String(l.id) === String(data.lote_id)),
        [data.lote_id, lotes],
    );

    const cerrar = () => {
        reset();
        onClose();
    };

    const submit = (e) => {
        e.preventDefault();
        post(route('inventario.movimientos.store'), {
            preserveScroll: true,
            onSuccess: cerrar,
        });
    };

    return (
        <Modal show={show} onClose={cerrar} maxWidth="lg">
            <form onSubmit={submit} className="p-6">
                <h2 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                    Registrar movimiento manual
                </h2>
                <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Los movimientos por venta/anulación se generan automáticamente
                    y aparecen en el listado.
                </p>

                <div className="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    {/* Lote */}
                    <div className="sm:col-span-2">
                        <InputLabel htmlFor="lote_id" value="Lote" />
                        <SelectInput
                            id="lote_id"
                            className="mt-1 block w-full"
                            value={data.lote_id}
                            onChange={(e) => setData('lote_id', e.target.value)}
                        >
                            <option value="">— Seleccione un lote —</option>
                            {lotes.map((l) => (
                                <option key={l.id} value={l.id}>
                                    {l.codigo_lote} — {l.producto?.nombre ?? 'Producto'} (stock: {l.stock})
                                </option>
                            ))}
                        </SelectInput>
                        <InputError message={errors.lote_id} className="mt-2" />
                        {loteSeleccionado && (
                            <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Stock actual: <strong>{loteSeleccionado.stock}</strong>
                            </p>
                        )}
                    </div>

                    {/* Motivo */}
                    <div>
                        <InputLabel htmlFor="motivo" value="Motivo" />
                        <SelectInput
                            id="motivo"
                            className="mt-1 block w-full"
                            value={data.motivo}
                            onChange={(e) => setData('motivo', e.target.value)}
                        >
                            {motivosManuales.map((m) => (
                                <option key={m.value} value={m.value}>
                                    {m.label}
                                </option>
                            ))}
                        </SelectInput>
                        <InputError message={errors.motivo} className="mt-2" />
                    </div>

                    {/* Cantidad */}
                    <div>
                        <InputLabel htmlFor="cantidad" value="Cantidad" />
                        <TextInput
                            id="cantidad"
                            inputMode="numeric"
                            pattern="[0-9]*"
                            className="mt-1 block w-full"
                            value={data.cantidad}
                            onChange={(e) => setData('cantidad', soloDigitos(e.target.value, 5))}
                            placeholder="0"
                        />
                        <InputError message={errors.cantidad} className="mt-2" />
                    </div>

                    {/* Observación */}
                    <div className="sm:col-span-2">
                        <InputLabel htmlFor="observacion" value="Observación (opcional)" />
                        <textarea
                            id="observacion"
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                            value={data.observacion}
                            onChange={(e) => setData('observacion', e.target.value)}
                            rows={2}
                            maxLength={255}
                        />
                        <InputError message={errors.observacion} className="mt-2" />
                    </div>
                </div>

                <div className="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" onClick={cerrar} disabled={processing}>
                        Cancelar
                    </SecondaryButton>
                    <PrimaryButton disabled={processing}>
                        Registrar
                    </PrimaryButton>
                </div>
            </form>
        </Modal>
    );
}
