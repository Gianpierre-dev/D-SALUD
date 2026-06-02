import { useEffect } from 'react';
import { useForm } from '@inertiajs/react';
import Modal from '@/Components/Modal';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import InputError from '@/Components/InputError';
import SelectInput from '@/Components/SelectInput';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import { soloDecimalPositivo } from '@/utils/inputs';

/**
 * Registra un ingreso o egreso de efectivo en la caja abierta.
 * Estos movimientos ajustan el efectivo esperado al cerrar el turno.
 */
export default function MovimientoCajaModal({ show, onClose, caja, tipos = [] }) {
    const { data, setData, post, processing, errors, reset, clearErrors } = useForm({
        tipo:     'EGRESO',
        monto:    '',
        concepto: '',
    });

    useEffect(() => {
        if (!show) return;
        clearErrors();
        reset();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [show]);

    const cerrar = () => {
        reset();
        onClose();
    };

    const submit = (e) => {
        e.preventDefault();
        post(route('cajas.movimiento', caja.id), {
            preserveScroll: true,
            onSuccess: cerrar,
        });
    };

    return (
        <Modal show={show} onClose={cerrar} maxWidth="md">
            <form onSubmit={submit} className="p-6">
                <h2 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                    Movimiento de caja
                </h2>
                <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Registra entradas o salidas de efectivo que no son ventas
                    (retiro, pago a proveedor, gasto menor).
                </p>

                <div className="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel htmlFor="tipo" value="Tipo" />
                        <SelectInput
                            id="tipo"
                            className="mt-1 block w-full"
                            value={data.tipo}
                            onChange={(e) => setData('tipo', e.target.value)}
                        >
                            {tipos.map((t) => (
                                <option key={t.value} value={t.value}>
                                    {t.label}
                                </option>
                            ))}
                        </SelectInput>
                        <InputError message={errors.tipo} className="mt-2" />
                    </div>

                    <div>
                        <InputLabel htmlFor="monto" value="Monto (S/)" />
                        <TextInput
                            id="monto"
                            inputMode="decimal"
                            pattern="[0-9]*[.]?[0-9]*"
                            className="mt-1 block w-full"
                            value={data.monto}
                            onChange={(e) => setData('monto', soloDecimalPositivo(e.target.value, 8, 2))}
                            placeholder="0.00"
                        />
                        <InputError message={errors.monto} className="mt-2" />
                    </div>

                    <div className="sm:col-span-2">
                        <InputLabel htmlFor="concepto" value="Concepto" />
                        <TextInput
                            id="concepto"
                            className="mt-1 block w-full"
                            value={data.concepto}
                            onChange={(e) => setData('concepto', e.target.value)}
                            maxLength={255}
                            placeholder="Ej. Pago a droguería, retiro de efectivo..."
                        />
                        <InputError message={errors.concepto} className="mt-2" />
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
