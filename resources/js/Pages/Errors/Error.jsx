import { Head, Link } from '@inertiajs/react';
import ApplicationLogo from '@/Components/ApplicationLogo';

const MENSAJES = {
    403: {
        titulo: 'Acceso denegado',
        descripcion: 'No tienes permiso para acceder a este recurso.',
    },
    404: {
        titulo: 'Página no encontrada',
        descripcion: 'La página que buscas no existe o fue movida.',
    },
    419: {
        titulo: 'Sesión expirada',
        descripcion: 'Tu sesión expiró por inactividad. Vuelve a iniciar sesión.',
    },
    500: {
        titulo: 'Error interno del servidor',
        descripcion: 'Ocurrió un error inesperado. El equipo técnico fue notificado.',
    },
    503: {
        titulo: 'Servicio en mantenimiento',
        descripcion: 'El sistema está temporalmente fuera de servicio. Vuelve a intentarlo en unos minutos.',
    },
};

export default function Error({ status = 500 }) {
    const info = MENSAJES[status] ?? MENSAJES[500];

    return (
        <div className="flex min-h-screen flex-col items-center justify-center bg-gray-100 px-6 py-12 dark:bg-gray-900">
            <Head title={`${status} — ${info.titulo}`} />

            <div className="w-full max-w-md text-center">
                <ApplicationLogo className="mx-auto h-16 w-auto" />

                <p className="mt-8 text-6xl font-extrabold text-brand-600 dark:text-brand-400">
                    {status}
                </p>

                <h1 className="mt-4 text-2xl font-semibold text-gray-800 dark:text-gray-200">
                    {info.titulo}
                </h1>

                <p className="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    {info.descripcion}
                </p>

                <div className="mt-8">
                    <Link
                        href="/"
                        className="inline-flex items-center rounded-md border border-transparent bg-gradient-to-r from-brand-600 to-salud-500 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white shadow-sm transition hover:from-brand-700 hover:to-salud-600 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900"
                    >
                        Volver al inicio
                    </Link>
                </div>
            </div>
        </div>
    );
}
