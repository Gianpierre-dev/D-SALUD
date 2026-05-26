import { useEffect, useState } from 'react';

/**
 * Input de búsqueda con debounce. Notifica el término al padre mediante onSearch.
 * No conoce la fuente de datos: solo emite el texto buscado (presentational).
 */
export default function SearchInput({
    value = '',
    onSearch,
    placeholder = 'Buscar...',
    delay = 350,
}) {
    const [term, setTerm] = useState(value);

    useEffect(() => {
        const handler = setTimeout(() => {
            if (term !== value) {
                onSearch(term);
            }
        }, delay);

        return () => clearTimeout(handler);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [term]);

    return (
        <input
            type="search"
            value={term}
            onChange={(e) => setTerm(e.target.value)}
            placeholder={placeholder}
            className="w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 sm:w-64"
        />
    );
}
