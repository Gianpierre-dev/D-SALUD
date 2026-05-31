import { useEffect } from 'react';
import { useForm } from '@inertiajs/react';
import Modal from '@/Components/Modal';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import InputError from '@/Components/InputError';
import Checkbox from '@/Components/Checkbox';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import { soloDigitos, telefonoLimpio } from '@/utils/inputs';

/**
 * Modal de creación/edición de proveedor.
 * Si recibe `proveedor`, opera en modo edición; si no, en modo creación.
 */
export default function ProveedorFormModal({ show, onClose, proveedor = null }) {
    const esEdicion = Boolean(proveedor);

    const { data, setData, post, put, processing, errors, reset, clearErrors } = useForm({
        ruc: '',
        razon_social: '',
        contacto: '',
        telefono: '',
        email: '',
        direccion: '',
        activo: true,
    });

    useEffect(() => {
        if (!show) {
            return;
        }
        clearErrors();
        if (proveedor) {
            setData({
                ruc: proveedor.ruc,
                razon_social: proveedor.razon_social,
                contacto: proveedor.contacto ?? '',
                telefono: proveedor.telefono ?? '',
                email: proveedor.email ?? '',
                direccion: proveedor.direccion ?? '',
                activo: Boolean(proveedor.activo),
            });
        } else {
            reset();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [show, proveedor]);

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
            put(route('proveedores.update', proveedor.id), opciones);
        } else {
            post(route('proveedores.store'), opciones);
        }
    };

    return (
        <Modal show={show} onClose={cerrar} maxWidth="lg">
            <form onSubmit={submit} className="p-6">
                <h2 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                    {esEdicion ? 'Editar proveedor' : 'Nuevo proveedor'}
                </h2>

                <div className="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    {/* RUC — solo dígitos: el onChange descarta cualquier
                        carácter no numérico al tipear o pegar. La validación
                        autoritativa vive en StoreProveedorRequest (size:11 + regex). */}
                    <div>
                        <InputLabel htmlFor="ruc" value="RUC" />
                        <TextInput
                            id="ruc"
                            className="mt-1 block w-full"
                            value={data.ruc}
                            onChange={(e) => setData('ruc', soloDigitos(e.target.value, 11))}
                            maxLength={11}
                            inputMode="numeric"
                            pattern="[0-9]*"
                            isFocused
                            autoComplete="off"
                            placeholder="11 dígitos"
                        />
                        <InputError message={errors.ruc} className="mt-2" />
                    </div>

                    {/* Razón social */}
                    <div>
                        <InputLabel htmlFor="razon_social" value="Razón social" />
                        <TextInput
                            id="razon_social"
                            className="mt-1 block w-full"
                            value={data.razon_social}
                            onChange={(e) => setData('razon_social', e.target.value)}
                            autoComplete="off"
                        />
                        <InputError message={errors.razon_social} className="mt-2" />
                    </div>

                    {/* Contacto */}
                    <div>
                        <InputLabel htmlFor="contacto" value="Contacto (opcional)" />
                        <TextInput
                            id="contacto"
                            className="mt-1 block w-full"
                            value={data.contacto}
                            onChange={(e) => setData('contacto', e.target.value)}
                            autoComplete="off"
                        />
                        <InputError message={errors.contacto} className="mt-2" />
                    </div>

                    {/* Teléfono */}
                    <div>
                        <InputLabel htmlFor="telefono" value="Teléfono (opcional)" />
                        <TextInput
                            id="telefono"
                            className="mt-1 block w-full"
                            value={data.telefono}
                            onChange={(e) => setData('telefono', telefonoLimpio(e.target.value, 20))}
                            inputMode="tel"
                            autoComplete="tel"
                            placeholder="+51 1 1234567"
                        />
                        <InputError message={errors.telefono} className="mt-2" />
                    </div>

                    {/* Correo */}
                    <div>
                        <InputLabel htmlFor="email" value="Correo electrónico (opcional)" />
                        <TextInput
                            id="email"
                            type="email"
                            className="mt-1 block w-full"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            autoComplete="off"
                        />
                        <InputError message={errors.email} className="mt-2" />
                    </div>

                    {/* Dirección */}
                    <div>
                        <InputLabel htmlFor="direccion" value="Dirección (opcional)" />
                        <TextInput
                            id="direccion"
                            className="mt-1 block w-full"
                            value={data.direccion}
                            onChange={(e) => setData('direccion', e.target.value)}
                            autoComplete="off"
                        />
                        <InputError message={errors.direccion} className="mt-2" />
                    </div>
                </div>

                {/* Estado activo */}
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
                        {esEdicion ? 'Guardar cambios' : 'Crear proveedor'}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>
    );
}
