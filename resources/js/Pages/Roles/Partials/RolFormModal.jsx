import { useEffect } from 'react';
import { useForm } from '@inertiajs/react';
import Modal from '@/Components/Modal';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import InputError from '@/Components/InputError';
import Checkbox from '@/Components/Checkbox';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';

/**
 * Modal de creación/edición de rol.
 * Si recibe `rol`, opera en modo edición; si no, en modo creación.
 *
 * @param {object|null}  rol      - Rol a editar (incluye rol.permissions[]).
 * @param {object}       permisos - Permisos agrupados por módulo:
 *                                  { categorias: [{id, name}, ...], productos: [...], ... }
 */
export default function RolFormModal({ show, onClose, rol = null, permisos = {} }) {
    const esEdicion = Boolean(rol);

    const { data, setData, post, put, processing, errors, reset, clearErrors } = useForm({
        name: '',
        permissions: [],
    });

    useEffect(() => {
        if (!show) {
            return;
        }
        clearErrors();
        if (rol) {
            setData({
                name: rol.name,
                permissions: (rol.permissions ?? []).map((p) => p.name),
            });
        } else {
            reset();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [show, rol]);

    const cerrar = () => {
        reset();
        onClose();
    };

    const togglePermiso = (nombrePermiso) => {
        setData('permissions', (prev) =>
            prev.includes(nombrePermiso)
                ? prev.filter((p) => p !== nombrePermiso)
                : [...prev, nombrePermiso],
        );
    };

    const toggleModulo = (permsDelModulo) => {
        const nombres = permsDelModulo.map((p) => p.name);
        const todosActivos = nombres.every((n) => data.permissions.includes(n));

        if (todosActivos) {
            setData('permissions', data.permissions.filter((p) => !nombres.includes(p)));
        } else {
            setData('permissions', [...new Set([...data.permissions, ...nombres])]);
        }
    };

    const submit = (e) => {
        e.preventDefault();
        const opciones = {
            preserveScroll: true,
            onSuccess: cerrar,
        };

        if (esEdicion) {
            put(route('roles.update', rol.id), opciones);
        } else {
            post(route('roles.store'), opciones);
        }
    };

    const modulos = Object.entries(permisos);

    return (
        <Modal show={show} onClose={cerrar} maxWidth="lg">
            <form onSubmit={submit} className="p-6">
                <h2 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                    {esEdicion ? 'Editar rol' : 'Nuevo rol'}
                </h2>

                {/* Nombre del rol */}
                <div className="mt-4">
                    <InputLabel htmlFor="name" value="Nombre del rol" />
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

                {/* Permisos agrupados por módulo */}
                {modulos.length > 0 && (
                    <div className="mt-5">
                        <p className="text-sm font-medium text-gray-700 dark:text-gray-300">
                            Permisos
                        </p>
                        <InputError message={errors.permissions} className="mt-1" />

                        <div className="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
                            {modulos.map(([modulo, permsModulo]) => {
                                const nombresModulo = permsModulo.map((p) => p.name);
                                const todosActivos = nombresModulo.every((n) =>
                                    data.permissions.includes(n),
                                );

                                return (
                                    <div
                                        key={modulo}
                                        className="rounded-lg border border-gray-200 p-3 dark:border-gray-700"
                                    >
                                        {/* Cabecera del módulo con toggle-all */}
                                        <label className="flex cursor-pointer items-center gap-2">
                                            <Checkbox
                                                checked={todosActivos}
                                                onChange={() => toggleModulo(permsModulo)}
                                            />
                                            <span className="text-sm font-semibold capitalize text-gray-800 dark:text-gray-200">
                                                {modulo}
                                            </span>
                                        </label>

                                        {/* Permisos individuales del módulo */}
                                        <div className="ml-6 mt-2 space-y-1">
                                            {permsModulo.map((permiso) => (
                                                <label
                                                    key={permiso.id}
                                                    className="flex cursor-pointer items-center gap-2"
                                                >
                                                    <Checkbox
                                                        checked={data.permissions.includes(permiso.name)}
                                                        onChange={() => togglePermiso(permiso.name)}
                                                    />
                                                    <span className="text-xs text-gray-600 dark:text-gray-400">
                                                        {permiso.name.split('.')[1]}
                                                    </span>
                                                </label>
                                            ))}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                )}

                <div className="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" onClick={cerrar} disabled={processing}>
                        Cancelar
                    </SecondaryButton>
                    <PrimaryButton disabled={processing}>
                        {esEdicion ? 'Guardar cambios' : 'Crear rol'}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>
    );
}
