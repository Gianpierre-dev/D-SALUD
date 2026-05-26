import { Link } from '@inertiajs/react';

/**
 * Ítem individual del menú lateral. Presentacional: recibe la ruta ya resuelta
 * y si está activo. No decide visibilidad ni permisos.
 */
export default function SidebarNavItem({ href, label, icon: Icon, active, onNavigate }) {
    return (
        <Link
            href={href}
            onClick={onNavigate}
            aria-current={active ? 'page' : undefined}
            className={`flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition ${
                active
                    ? 'bg-gradient-to-r from-brand-600 to-salud-500 text-white shadow-sm'
                    : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800'
            }`}
        >
            {Icon && <Icon className="h-5 w-5 shrink-0" stroke={1.75} />}
            <span>{label}</span>
        </Link>
    );
}
