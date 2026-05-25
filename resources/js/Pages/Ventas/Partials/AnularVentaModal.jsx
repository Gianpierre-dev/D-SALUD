import { useEffect } from 'react';
import { useForm } from '@inertiajs/react';
import Modal from '@/Components/Modal';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';

/**
 * Modal para ingresar el motivo de anulación de una venta.
 * Recibe `venta` (objeto con id y boleta.numero_formateado) y callbacks.
 */
export default function AnularVentaModal({ show, onClose, venta = null }) {
    const { data, setData, put, processing, errors, reset, clearErrors } = useForm({
        motivo: '',
    });

    useEffect(() => {
        if (show) {
            clearErrors();
            reset();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [show]);

    const cerrar = () => {
        reset();
        onClose();
    };

    const submit = (e) => {
        e.preventDefault();
        put(route('ventas.anular', venta?.id), {
            preserveScroll: true,
            onSuccess: cerrar,
        });
    };

    return (
        <Modal show={show} onClose={cerrar} maxWidth="md">
            <form onSubmit={submit} className="p-6">
                <h2 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                    Anular venta
                </h2>

                {venta?.boleta && (
                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Boleta:{' '}
                        <span className="font-semibold">
                            {venta.boleta.numero_formateado}
                        </span>
                    </p>
                )}

                <p className="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    Esta acción anulará la venta y repondrá el stock de los lotes
                    correspondientes. No se puede deshacer.
                </p>

                <div className="mt-4">
                    <InputLabel htmlFor="motivo" value="Motivo de anulación" />
                    <TextInput
                        id="motivo"
                        className="mt-1 block w-full"
                        value={data.motivo}
                        onChange={(e) => setData('motivo', e.target.value)}
                        isFocused
                        autoComplete="off"
                        maxLength={255}
                    />
                    <InputError message={errors.motivo} className="mt-2" />
                </div>

                <div className="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" onClick={cerrar} disabled={processing}>
                        Cancelar
                    </SecondaryButton>
                    <PrimaryButton
                        disabled={processing}
                        className="bg-red-600 hover:bg-red-700 focus:ring-red-500"
                    >
                        Anular venta
                    </PrimaryButton>
                </div>
            </form>
        </Modal>
    );
}
