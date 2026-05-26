import { IconSun, IconMoon } from '@tabler/icons-react';
import { useTheme } from '@/hooks/useTheme';

/**
 * Botón para alternar entre modo claro y oscuro.
 */
export default function ThemeToggle() {
    const { isDark, toggle } = useTheme();

    return (
        <button
            type="button"
            onClick={toggle}
            aria-label={isDark ? 'Activar modo claro' : 'Activar modo oscuro'}
            title={isDark ? 'Modo claro' : 'Modo oscuro'}
            className="rounded-md p-2 text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200"
        >
            {isDark ? <IconSun className="h-5 w-5" /> : <IconMoon className="h-5 w-5" />}
        </button>
    );
}
