import { Link } from '@inertiajs/react';

/**
 * Decodifica las entidades HTML que entrega el paginador de Laravel
 * y las muestra como texto plano (evita dangerouslySetInnerHTML / XSS).
 */
function decodeLabel(label) {
    return label
        .replaceAll('&laquo;', '«')
        .replaceAll('&raquo;', '»')
        .replaceAll('&hellip;', '…')
        .replace(/<[^>]*>/g, '')
        .trim();
}

/**
 * Paginación basada en los enlaces que entrega el paginador de Laravel.
 * Las URL ya vienen con los parámetros de query (búsqueda, filtros).
 */
export default function Pagination({ links = [] }) {
    if (links.length <= 3) {
        return null;
    }

    return (
        <nav className="flex flex-wrap items-center justify-center gap-1" aria-label="Paginación">
            {links.map((link, index) => {
                const baseClasses = 'min-w-9 rounded-md px-3 py-2 text-sm transition select-none';
                const label = decodeLabel(link.label);

                if (!link.url) {
                    return (
                        <span
                            key={index}
                            className={`${baseClasses} cursor-default text-gray-400 dark:text-gray-600`}
                        >
                            {label}
                        </span>
                    );
                }

                return (
                    <Link
                        key={index}
                        href={link.url}
                        preserveScroll
                        preserveState
                        aria-current={link.active ? 'page' : undefined}
                        className={`${baseClasses} ${
                            link.active
                                ? 'bg-brand-600 text-white'
                                : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700'
                        }`}
                    >
                        {label}
                    </Link>
                );
            })}
        </nav>
    );
}
