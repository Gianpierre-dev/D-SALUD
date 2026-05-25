/**
 * Tabla de datos genérica y presentacional.
 * No conoce la fuente de datos ni la lógica: recibe columnas y filas.
 *
 * @param {Array<{key: string, label: string, render?: Function, cellClassName?: string}>} columns
 * @param {Array<object>} rows
 * @param {string} rowKey  Clave única de cada fila (por defecto "id").
 * @param {string} emptyMessage  Mensaje cuando no hay datos.
 */
export default function DataTable({
    columns,
    rows,
    rowKey = 'id',
    emptyMessage = 'No hay registros para mostrar.',
}) {
    return (
        <div className="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
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
                    {rows.length === 0 ? (
                        <tr>
                            <td
                                colSpan={columns.length}
                                className="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400"
                            >
                                {emptyMessage}
                            </td>
                        </tr>
                    ) : (
                        rows.map((row) => (
                            <tr
                                key={row[rowKey]}
                                className="transition hover:bg-gray-50 dark:hover:bg-gray-800/50"
                            >
                                {columns.map((column) => (
                                    <td
                                        key={column.key}
                                        className={`whitespace-nowrap px-4 py-3 text-sm text-gray-700 dark:text-gray-300 ${column.cellClassName ?? ''}`}
                                    >
                                        {column.render ? column.render(row) : row[column.key]}
                                    </td>
                                ))}
                            </tr>
                        ))
                    )}
                </tbody>
            </table>
        </div>
    );
}
