import { useEffect } from 'react';
import { useForm } from '@inertiajs/react';
import Modal from '@/Components/Modal';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import { soloDecimalPositivo } from '@/utils/inputs';

export default function CerrarCajaModal({ show, onClose, caja }) {
    const { data, setData, put, processing, errors, reset, clearErrors } = useForm({
        monto_cierre:  '',
        observaciones: '',
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
        put(route('cajas.close', caja.id), {
            preserveScroll: true,
            onSuccess: cerrar,
        });
    };

    return (
        <Modal show={show} onClose={cerrar} maxWidth="md">
            <form onSubmit={submit} className="p-6">
                <h2 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                    Cerrar caja #{caja?.id}
                </h2>
                <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Cuenta físicamente el efectivo y declara el monto contado.
                    El sistema calculará la diferencia contra el total esperado
                    (apertura + ventas del turno).
                </p>

                <div className="mt-4 space-y-4">
                    <div>
                        <InputLabel htmlFor="monto_cierre" value="Monto contado (S/)" />
                        <TextInput
                            id="monto_cierre"
                            inputMode="decimal"
                            pattern="[0-9]*[.]?[0-9]*"
                            className="mt-1 block w-full"
                            value={data.monto_cierre}
                            onChange={(e) =>
                                setData('monto_cierre', soloDecimalPositivo(e.target.value, 8, 2))
                            }
                            placeholder="0.00"
                            isFocused
                        />
                        <InputError message={errors.monto_cierre} className="mt-2" />
                    </div>

                    <div>
                        <InputLabel htmlFor="observaciones" value="Observaciones (opcional)" />
                        <textarea
                            id="observaciones"
                            rows={2}
                            maxLength={500}
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                            value={data.observaciones}
                            onChange={(e) => setData('observaciones', e.target.value)}
                        />
                        <InputError message={errors.observaciones} className="mt-2" />
                    </div>
                </div>

                <div className="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" onClick={cerrar} disabled={processing}>
                        Cancelar
                    </SecondaryButton>
                    <PrimaryButton disabled={processing}>
                        Cerrar caja
                    </PrimaryButton>
                </div>
            </form>
        </Modal>
    );
}
