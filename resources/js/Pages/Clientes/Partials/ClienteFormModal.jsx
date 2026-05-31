import { useEffect } from 'react';
import { useForm } from '@inertiajs/react';
import Modal from '@/Components/Modal';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import InputError from '@/Components/InputError';
import SelectInput from '@/Components/SelectInput';
import Checkbox from '@/Components/Checkbox';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import { soloDigitos, telefonoLimpio } from '@/utils/inputs';

/**
 * Modal de creación/edición de cliente.
 * Si recibe `cliente`, opera en modo edición; si no, en modo creación.
 *
 * El número de documento se limita al largo del tipo seleccionado (DNI=8,
 * RUC=11) y se filtra a solo dígitos en tiempo real. Cuando cambia el tipo
 * se reinicia el número para evitar arrastrar valores que ya no cumplen
 * el formato esperado.
 */
export default function ClienteFormModal({ show, onClose, cliente = null }) {
    const esEdicion = Boolean(cliente);

    const { data, setData, post, put, processing, errors, reset, clearErrors } = useForm({
        tipo_documento:   'DNI',
        numero_documento: '',
        nombre:           '',
        telefono:         '',
        email:            '',
        direccion:        '',
        activo:           true,
    });

    useEffect(() => {
        if (!show) {
            return;
        }
        clearErrors();
        if (cliente) {
            setData({
                tipo_documento:   cliente.tipo_documento ?? 'DNI',
                numero_documento: cliente.numero_documento ?? '',
                nombre:           cliente.nombre ?? '',
                telefono:         cliente.telefono ?? '',
                email:            cliente.email ?? '',
                direccion:        cliente.direccion ?? '',
                activo:           Boolean(cliente.activo),
            });
        } else {
            reset();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [show, cliente]);

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
            put(route('clientes.update', cliente.id), opciones);
        } else {
            post(route('clientes.store'), opciones);
        }
    };

    const longitudDoc = data.tipo_documento === 'RUC' ? 11 : 8;
    const placeholderDoc = data.tipo_documento === 'RUC' ? '20XXXXXXXXX' : '8 dígitos';

    return (
        <Modal show={show} onClose={cerrar} maxWidth="lg">
            <form onSubmit={submit} className="p-6">
                <h2 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                    {esEdicion ? 'Editar cliente' : 'Nuevo cliente'}
                </h2>

                <div className="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    {/* Tipo de documento */}
                    <div>
                        <InputLabel htmlFor="tipo_documento" value="Tipo de documento" />
                        <SelectInput
                            id="tipo_documento"
                            className="mt-1 block w-full"
                            value={data.tipo_documento}
                            onChange={(e) => {
                                setData((current) => ({
                                    ...current,
                                    tipo_documento: e.target.value,
                                    // Al cambiar el tipo, limpio el número para no arrastrar
                                    // un valor que ya no cumple el formato esperado.
                                    numero_documento: '',
                                }));
                            }}
                        >
                            <option value="DNI">DNI (Persona)</option>
                            <option value="RUC">RUC (Empresa)</option>
                        </SelectInput>
                        <InputError message={errors.tipo_documento} className="mt-2" />
                    </div>

                    {/* Número de documento */}
                    <div>
                        <InputLabel htmlFor="numero_documento" value="Número de documento" />
                        <TextInput
                            id="numero_documento"
                            className="mt-1 block w-full"
                            value={data.numero_documento}
                            onChange={(e) =>
                                setData('numero_documento', soloDigitos(e.target.value, longitudDoc))
                            }
                            maxLength={longitudDoc}
                            inputMode="numeric"
                            pattern="[0-9]*"
                            autoComplete="off"
                            placeholder={placeholderDoc}
                            isFocused
                        />
                        <InputError message={errors.numero_documento} className="mt-2" />
                    </div>

                    {/* Nombre / Razón social */}
                    <div className="sm:col-span-2">
                        <InputLabel
                            htmlFor="nombre"
                            value={data.tipo_documento === 'RUC' ? 'Razón social' : 'Nombres y apellidos'}
                        />
                        <TextInput
                            id="nombre"
                            className="mt-1 block w-full"
                            value={data.nombre}
                            onChange={(e) => setData('nombre', e.target.value)}
                            autoComplete="off"
                            maxLength={255}
                        />
                        <InputError message={errors.nombre} className="mt-2" />
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
                            placeholder="+51 9 99999999"
                        />
                        <InputError message={errors.telefono} className="mt-2" />
                    </div>

                    {/* Correo */}
                    <div>
                        <InputLabel htmlFor="email" value="Correo (opcional)" />
                        <TextInput
                            id="email"
                            type="email"
                            className="mt-1 block w-full"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            autoComplete="off"
                            maxLength={255}
                        />
                        <InputError message={errors.email} className="mt-2" />
                    </div>

                    {/* Dirección */}
                    <div className="sm:col-span-2">
                        <InputLabel htmlFor="direccion" value="Dirección (opcional)" />
                        <TextInput
                            id="direccion"
                            className="mt-1 block w-full"
                            value={data.direccion}
                            onChange={(e) => setData('direccion', e.target.value)}
                            autoComplete="off"
                            maxLength={255}
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
                        {esEdicion ? 'Guardar cambios' : 'Crear cliente'}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>
    );
}
