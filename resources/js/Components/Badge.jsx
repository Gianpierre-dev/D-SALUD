const VARIANTES = {
    success: 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
    danger: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
    warning: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
    neutral: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
    info: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300',
};

/**
 * Etiqueta de estado compacta y reutilizable.
 */
export default function Badge({ variant = 'neutral', children }) {
    return (
        <span
            className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${VARIANTES[variant] ?? VARIANTES.neutral}`}
        >
            {children}
        </span>
    );
}
