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
 * Modal de creación/edición de usuario.
 * Si recibe `usuario`, opera en modo edición; si no, en modo creación.
 * El campo password es opcional en edición (dejar en blanco = no cambiar).
 */
export default function UsuarioFormModal({ show, onClose, usuario = null, roles = [] }) {
    const esEdicion = Boolean(usuario);

    const { data, setData, post, put, processing, errors, reset, clearErrors } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        rol: '',
    });

    useEffect(() => {
        if (!show) {
            return;
        }
        clearErrors();
        if (usuario) {
            setData({
                name: usuario.name,
                email: usuario.email,
                password: '',
                password_confirmation: '',
                rol: usuario.roles?.[0]?.name ?? '',
            });
        } else {
            reset();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [show, usuario]);

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
            put(route('usuarios.update', usuario.id), opciones);
        } else {
            post(route('usuarios.store'), opciones);
        }
    };

    return (
        <Modal show={show} onClose={cerrar} maxWidth="md">
            <form onSubmit={submit} className="p-6">
                <h2 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                    {esEdicion ? 'Editar usuario' : 'Nuevo usuario'}
                </h2>

                {/* Nombre */}
                <div className="mt-4">
                    <InputLabel htmlFor="name" value="Nombre" />
                    <TextInput
                        id="name"
                        className="mt-1 block w-full"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        isFocused
                        autoComplete="off"
                    />
                    <InputError message={errors.name} className="mt-2" />
                </div>

                {/* Correo electrónico */}
                <div className="mt-4">
                    <InputLabel htmlFor="email" value="Correo electrónico" />
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

                {/* Contraseña */}
                <div className="mt-4">
                    <InputLabel
                        htmlFor="password"
                        value={esEdicion ? 'Nueva contraseña (opcional)' : 'Contraseña'}
                    />
                    <TextInput
                        id="password"
                        type="password"
                        className="mt-1 block w-full"
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                        placeholder={esEdicion ? 'Dejar en blanco para no cambiar' : ''}
                        autoComplete="new-password"
                    />
                    <InputError message={errors.password} className="mt-2" />
                </div>

                {/* Confirmar contraseña */}
                <div className="mt-4">
                    <InputLabel
                        htmlFor="password_confirmation"
                        value="Confirmar contraseña"
                    />
                    <TextInput
                        id="password_confirmation"
                        type="password"
                        className="mt-1 block w-full"
                        value={data.password_confirmation}
                        onChange={(e) => setData('password_confirmation', e.target.value)}
                        placeholder={esEdicion ? 'Dejar en blanco para no cambiar' : ''}
                        autoComplete="new-password"
                    />
                    <InputError message={errors.password_confirmation} className="mt-2" />
                </div>

                {/* Rol */}
                <div className="mt-4">
                    <InputLabel htmlFor="rol" value="Rol" />
                    <SelectInput
                        id="rol"
                        className="mt-1 block w-full"
                        value={data.rol}
                        onChange={(e) => setData('rol', e.target.value)}
                    >
                        <option value="">-- Seleccionar rol --</option>
                        {roles.map((r) => (
                            <option key={r.id} value={r.name}>
                                {r.name}
                            </option>
                        ))}
                    </SelectInput>
                    <InputError message={errors.rol} className="mt-2" />
                </div>

                <div className="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" onClick={cerrar} disabled={processing}>
                        Cancelar
                    </SecondaryButton>
                    <PrimaryButton disabled={processing}>
                        {esEdicion ? 'Guardar cambios' : 'Crear usuario'}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>
    );
}
