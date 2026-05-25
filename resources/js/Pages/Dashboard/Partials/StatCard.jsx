/**
 * Tarjeta de indicador para el dashboard.
 *
 * @param {{ titulo: string, valor: string|number, icon: React.ComponentType<{ className?: string }> }} props
 */
export default function StatCard({ titulo, valor, icon: Icon }) {
    return (
        <div className="flex items-center gap-4 rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div className="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-indigo-100 text-indigo-600 dark:bg-indigo-900/40 dark:text-indigo-400">
                <Icon className="h-6 w-6" aria-hidden="true" />
            </div>
            <div className="min-w-0">
                <p className="truncate text-sm font-medium text-gray-500 dark:text-gray-400">
                    {titulo}
                </p>
                <p className="mt-0.5 text-2xl font-bold tracking-tight text-gray-900 dark:text-gray-100">
                    {valor}
                </p>
            </div>
        </div>
    );
}
