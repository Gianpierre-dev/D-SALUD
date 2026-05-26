import { useCallback, useEffect, useState } from 'react';

/**
 * Maneja el tema claro/oscuro de la aplicación.
 * Persiste la preferencia en localStorage y la refleja en la clase `dark`
 * del elemento <html> (Tailwind darkMode: 'class').
 */
export function useTheme() {
    const [theme, setTheme] = useState(() => {
        if (typeof document === 'undefined') {
            return 'light';
        }
        return document.documentElement.classList.contains('dark') ? 'dark' : 'light';
    });

    useEffect(() => {
        const root = document.documentElement;
        if (theme === 'dark') {
            root.classList.add('dark');
        } else {
            root.classList.remove('dark');
        }
        localStorage.setItem('theme', theme);
    }, [theme]);

    const toggle = useCallback(() => {
        setTheme((actual) => (actual === 'dark' ? 'light' : 'dark'));
    }, []);

    return { theme, toggle, isDark: theme === 'dark' };
}
