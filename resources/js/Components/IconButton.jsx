const VARIANTES = {
    default: 'text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-700',
    primary: 'text-indigo-600 hover:bg-indigo-50 dark:text-indigo-400 dark:hover:bg-indigo-900/30',
    danger: 'text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/30',
};

/**
 * Botón compacto de acción con icono, pensado para celdas de tabla.
 */
export default function IconButton({ icon: Icon, onClick, title, variant = 'default', type = 'button' }) {
    return (
        <button
            type={type}
            onClick={onClick}
            title={title}
            aria-label={title}
            className={`rounded-md p-1.5 transition ${VARIANTES[variant] ?? VARIANTES.default}`}
        >
            <Icon className="h-5 w-5" stroke={1.75} />
        </button>
    );
}
