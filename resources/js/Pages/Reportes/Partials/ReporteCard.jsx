import { IconFileSpreadsheet } from '@tabler/icons-react';

/**
 * Tarjeta reutilizable para el listado de reportes.
 *
 * Props:
 *   titulo       — nombre del reporte
 *   descripcion  — texto informativo breve
 *   children     — campos de parámetros + botón de descarga
 */
export default function ReporteCard({ titulo, descripcion, children }) {
    return (
        <div className="flex flex-col rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            {/* Encabezado */}
            <div className="mb-3 flex items-center gap-3">
                <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-brand-50 dark:bg-brand-900/30">
                    <IconFileSpreadsheet className="h-5 w-5 text-brand-600 dark:text-brand-400" />
                </span>
                <div>
                    <h3 className="text-sm font-semibold text-gray-900 dark:text-gray-100">
                        {titulo}
                    </h3>
                    <p className="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                        {descripcion}
                    </p>
                </div>
            </div>

            {/* Formulario / acciones */}
            <div className="mt-auto">{children}</div>
        </div>
    );
}
