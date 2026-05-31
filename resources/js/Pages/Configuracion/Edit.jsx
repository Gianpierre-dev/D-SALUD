import { Head, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';

export default function Edit({ empresa }) {
    const { data, setData, put, processing, errors } = useForm({
        razon_social: empresa.razon_social ?? '',
        ruc:          empresa.ruc          ?? '',
        direccion:    empresa.direccion    ?? '',
        telefono:     empresa.telefono     ?? '',
    });

    const submit = (e) => {
        e.preventDefault();
        put(route('configuracion.update'));
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold text-gray-800 dark:text-gray-200">
                    Configuración de empresa
                </h2>
            }
        >
            <Head title="Configuración de empresa" />

            <div className="mx-auto max-w-2xl">
                <div className="bg-white shadow sm:rounded-lg dark:bg-gray-800">
                    <form onSubmit={submit} className="space-y-6 p-6 sm:p-8">
                        <h3 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                            Datos de la empresa
                        </h3>

                        {/* Razón social */}
                        <div>
                            <InputLabel htmlFor="razon_social" value="Razón social" />
                            <TextInput
                                id="razon_social"
                                className="mt-1 block w-full dark:bg-gray-900 dark:text-gray-300"
                                value={data.razon_social}
                                onChange={(e) => setData('razon_social', e.target.value)}
                                autoComplete="organization"
                                isFocused
                            />
                            <InputError message={errors.razon_social} className="mt-2" />
                        </div>

                        {/* RUC — solo dígitos: el onChange descarta cualquier
                            carácter no numérico al tipear o pegar. La validación
                            autoritativa vive en UpdateEmpresaRequest (size:11 + regex). */}
                        <div>
                            <InputLabel htmlFor="ruc" value="RUC" />
                            <TextInput
                                id="ruc"
                                className="mt-1 block w-full dark:bg-gray-900 dark:text-gray-300"
                                value={data.ruc}
                                onChange={(e) =>
                                    setData('ruc', e.target.value.replace(/\D/g, '').slice(0, 11))
                                }
                                maxLength={11}
                                inputMode="numeric"
                                pattern="[0-9]*"
                                autoComplete="off"
                                placeholder="11 dígitos"
                            />
                            <InputError message={errors.ruc} className="mt-2" />
                        </div>

                        {/* Dirección */}
                        <div>
                            <InputLabel htmlFor="direccion" value="Dirección (opcional)" />
                            <TextInput
                                id="direccion"
                                className="mt-1 block w-full dark:bg-gray-900 dark:text-gray-300"
                                value={data.direccion}
                                onChange={(e) => setData('direccion', e.target.value)}
                                autoComplete="street-address"
                            />
                            <InputError message={errors.direccion} className="mt-2" />
                        </div>

                        {/* Teléfono */}
                        <div>
                            <InputLabel htmlFor="telefono" value="Teléfono (opcional)" />
                            <TextInput
                                id="telefono"
                                className="mt-1 block w-full dark:bg-gray-900 dark:text-gray-300"
                                value={data.telefono}
                                onChange={(e) => setData('telefono', e.target.value)}
                                autoComplete="tel"
                                inputMode="tel"
                            />
                            <InputError message={errors.telefono} className="mt-2" />
                        </div>

                        <div className="flex justify-end">
                            <PrimaryButton disabled={processing}>
                                Guardar cambios
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
