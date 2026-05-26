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
 * Modal de creación/edición de categoría.
 * Si recibe `categoria`, opera en modo edición; si no, en modo creación.
 */
export default function CategoriaFormModal({ show, onClose, categoria = null }) {
    const esEdicion = Boolean(categoria);

    const { data, setData, post, put, processing, errors, reset, clearErrors } = useForm({
        nombre: '',
        descripcion: '',
        activo: true,
    });

    useEffect(() => {
        if (!show) {
            return;
        }
        clearErrors();
        if (categoria) {
            setData({
                nombre: categoria.nombre,
                descripcion: categoria.descripcion ?? '',
                activo: Boolean(categoria.activo),
            });
        } else {
            reset();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [show, categoria]);

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
            put(route('categorias.update', categoria.id), opciones);
        } else {
            post(route('categorias.store'), opciones);
        }
    };

    return (
        <Modal show={show} onClose={cerrar} maxWidth="md">
            <form onSubmit={submit} className="p-6">
                <h2 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                    {esEdicion ? 'Editar categoría' : 'Nueva categoría'}
                </h2>

                <div className="mt-4">
                    <InputLabel htmlFor="nombre" value="Nombre" />
                    <TextInput
                        id="nombre"
                        className="mt-1 block w-full"
                        value={data.nombre}
                        onChange={(e) => setData('nombre', e.target.value)}
                        isFocused
                        autoComplete="off"
                    />
                    <InputError message={errors.nombre} className="mt-2" />
                </div>

                <div className="mt-4">
                    <InputLabel htmlFor="descripcion" value="Descripción (opcional)" />
                    <textarea
                        id="descripcion"
                        rows={3}
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                        value={data.descripcion}
                        onChange={(e) => setData('descripcion', e.target.value)}
                    />
                    <InputError message={errors.descripcion} className="mt-2" />
                </div>

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
                        {esEdicion ? 'Guardar cambios' : 'Crear categoría'}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>
    );
}
