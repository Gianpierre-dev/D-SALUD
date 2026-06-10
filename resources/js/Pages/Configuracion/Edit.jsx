import { Head, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import { soloDigitos, telefonoLimpio } from '@/utils/inputs';

export default function Edit({ empresa, logoUrl }) {
    const { data, setData, post, processing, errors } = useForm({
        _method:      'put',
        razon_social: empresa.razon_social ?? '',
        ruc:          empresa.ruc          ?? '',
        direccion:    empresa.direccion    ?? '',
        telefono:     empresa.telefono     ?? '',
        logo:         null,
    });

    // Previsualiza el archivo recién elegido; si no hay, muestra el logo actual.
    const previewLogo = data.logo ? URL.createObjectURL(data.logo) : logoUrl;

    const submit = (e) => {
        e.preventDefault();
        // forceFormData + _method=put: el PUT con archivo requiere multipart
        // y spoofing de método (PHP no procesa multipart en PUT nativo).
        post(route('configuracion.update'), { forceFormData: true });
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
                                onChange={(e) => setData('ruc', soloDigitos(e.target.value, 11))}
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
                                onChange={(e) => setData('telefono', telefonoLimpio(e.target.value, 20))}
                                autoComplete="tel"
                                inputMode="tel"
                                placeholder="+51 1 1234567"
                            />
                            <InputError message={errors.telefono} className="mt-2" />
                        </div>

                        {/* Logo — aparece en boletas, reporte Z y Excel. */}
                        <div>
                            <InputLabel htmlFor="logo" value="Logo (opcional)" />
                            <div className="mt-2 flex items-center gap-4">
                                <img
                                    src={previewLogo}
                                    alt="Logo de la empresa"
                                    className="h-16 w-16 rounded border border-gray-200 object-contain p-1 dark:border-gray-700"
                                />
                                <input
                                    id="logo"
                                    type="file"
                                    accept="image/png,image/jpeg,image/webp"
                                    onChange={(e) => setData('logo', e.target.files[0] ?? null)}
                                    className="block text-sm text-gray-600 file:mr-4 file:rounded-md file:border-0 file:bg-brand-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-brand-700 hover:file:bg-brand-100 dark:text-gray-400"
                                />
                            </div>
                            <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                PNG, JPG o WEBP. Máximo 2 MB. Se usa en boletas y reportes.
                            </p>
                            <InputError message={errors.logo} className="mt-2" />
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
