/**
 * Tabla de datos genérica, presentacional y responsive (mobile-first).
 *
 * - En pantallas md+ se muestra como tabla tradicional.
 * - En mobile se muestra como tarjetas apiladas (cada fila = una tarjeta
 *   con etiqueta y valor), evitando el scroll horizontal.
 *
 * @param {Array<{key: string, label: string, render?: Function, cellClassName?: string}>} columns
 * @param {Array<object>} rows
 * @param {string} rowKey  Clave única de cada fila (por defecto "id").
 * @param {string} emptyMessage  Mensaje cuando no hay datos.
 */
export default function DataTable({
    columns = [],
    rows = [],
    rowKey = 'id',
    emptyMessage = 'No hay registros para mostrar.',
}) {
    const valor = (column, row) => (column.render ? column.render(row) : row[column.key]);

    if (rows.length === 0) {
        return (
            <div className="rounded-lg border border-gray-200 bg-white px-4 py-8 text-center text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-400">
                {emptyMessage}
            </div>
        );
    }

    return (
        <>
            {/* Tabla (desktop / tablet) */}
            <div className="hidden overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700 md:block">
                <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead className="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            {columns.map((column) => (
                                <th
                                    key={column.key}
                                    scope="col"
                                    className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400"
                                >
                                    {column.label}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                        {rows.map((row) => (
                            <tr
                                key={row[rowKey]}
                                className="transition hover:bg-gray-50 dark:hover:bg-gray-800/50"
                            >
                                {columns.map((column) => (
                                    <td
                                        key={column.key}
                                        className={`whitespace-nowrap px-4 py-3 text-sm text-gray-700 dark:text-gray-300 ${column.cellClassName ?? ''}`}
                                    >
                                        {valor(column, row)}
                                    </td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {/* Tarjetas (mobile) */}
            <div className="space-y-3 md:hidden">
                {rows.map((row) => (
                    <div
                        key={row[rowKey]}
                        className="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900"
                    >
                        {columns.map((column) => (
                            <div
                                key={column.key}
                                className="flex items-start justify-between gap-3 border-b border-gray-100 py-2 last:border-0 dark:border-gray-800"
                            >
                                <span className="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    {column.label}
                                </span>
                                <span className="text-right text-sm text-gray-700 dark:text-gray-300">
                                    {valor(column, row)}
                                </span>
                            </div>
                        ))}
                    </div>
                ))}
            </div>
        </>
    );
}
