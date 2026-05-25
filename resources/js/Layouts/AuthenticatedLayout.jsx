import { useState } from 'react';
import { usePage } from '@inertiajs/react';
import { IconMenu2, IconX } from '@tabler/icons-react';
import ApplicationLogo from '@/Components/ApplicationLogo';
import Dropdown from '@/Components/Dropdown';
import FlashMessages from '@/Components/FlashMessages';
import SidebarNavItem from '@/Components/SidebarNavItem';
import { navigation } from '@/navigation';
import { usePermissions } from '@/hooks/usePermissions';

export default function AuthenticatedLayout({ header, children }) {
    const { can } = usePermissions();
    const page = usePage();
    const user = page.props.auth.user;
    const appName = page.props.app?.name ?? "D'Salud";
    const [sidebarOpen, setSidebarOpen] = useState(false);

    // Solo secciones/ítems cuya ruta exista y el usuario tenga permiso.
    const secciones = navigation
        .map((seccion) => ({
            ...seccion,
            items: seccion.items.filter(
                (item) => route().has(item.routeName) && can(item.permission),
            ),
        }))
        .filter((seccion) => seccion.items.length > 0);

    const closeSidebar = () => setSidebarOpen(false);

    const menu = (
        <nav className="flex flex-col gap-6 overflow-y-auto p-4">
            {secciones.map((seccion) => (
                <div key={seccion.section}>
                    <h3 className="px-3 text-xs font-semibold uppercase tracking-wider text-gray-400">
                        {seccion.section}
                    </h3>
                    <div className="mt-2 flex flex-col gap-1">
                        {seccion.items.map((item) => (
                            <SidebarNavItem
                                key={item.routeName}
                                href={route(item.routeName)}
                                label={item.label}
                                icon={item.icon}
                                active={route().current(item.routeName)}
                                onNavigate={closeSidebar}
                            />
                        ))}
                    </div>
                </div>
            ))}
        </nav>
    );

    return (
        <div className="min-h-screen bg-gray-100 dark:bg-gray-900">
            {/* Sidebar fijo (desktop) */}
            <aside className="fixed inset-y-0 left-0 z-30 hidden w-64 flex-col border-r border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800 lg:flex">
                <div className="flex h-16 items-center gap-2 border-b border-gray-200 px-4 dark:border-gray-700">
                    <ApplicationLogo className="h-8 w-auto fill-current text-gray-800 dark:text-gray-200" />
                    <span className="font-semibold text-gray-800 dark:text-gray-200">{appName}</span>
                </div>
                {menu}
            </aside>

            {/* Drawer (mobile) */}
            {sidebarOpen && (
                <div className="fixed inset-0 z-40 lg:hidden">
                    <div
                        className="absolute inset-0 bg-black/50"
                        onClick={closeSidebar}
                        aria-hidden="true"
                    />
                    <aside className="absolute inset-y-0 left-0 flex w-64 flex-col bg-white dark:bg-gray-800">
                        <div className="flex h-16 items-center justify-between border-b border-gray-200 px-4 dark:border-gray-700">
                            <span className="font-semibold text-gray-800 dark:text-gray-200">
                                {appName}
                            </span>
                            <button
                                type="button"
                                onClick={closeSidebar}
                                className="text-gray-500 hover:text-gray-700 dark:hover:text-gray-300"
                                aria-label="Cerrar menú"
                            >
                                <IconX className="h-6 w-6" />
                            </button>
                        </div>
                        {menu}
                    </aside>
                </div>
            )}

            {/* Columna de contenido */}
            <div className="lg:pl-64">
                <header className="sticky top-0 z-20 flex h-16 items-center gap-4 border-b border-gray-200 bg-white px-4 dark:border-gray-700 dark:bg-gray-800 sm:px-6">
                    <button
                        type="button"
                        onClick={() => setSidebarOpen(true)}
                        className="text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 lg:hidden"
                        aria-label="Abrir menú"
                    >
                        <IconMenu2 className="h-6 w-6" />
                    </button>

                    <div className="hidden min-w-0 flex-1 truncate lg:block">{header}</div>
                    <div className="flex-1 lg:hidden" />

                    <Dropdown>
                        <Dropdown.Trigger>
                            <button
                                type="button"
                                className="inline-flex items-center rounded-md border border-transparent bg-white px-3 py-2 text-sm font-medium text-gray-600 transition hover:text-gray-800 focus:outline-none dark:bg-gray-800 dark:text-gray-300 dark:hover:text-gray-200"
                            >
                                {user.name}
                                <svg
                                    className="-me-0.5 ms-2 h-4 w-4"
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 20 20"
                                    fill="currentColor"
                                >
                                    <path
                                        fillRule="evenodd"
                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                        clipRule="evenodd"
                                    />
                                </svg>
                            </button>
                        </Dropdown.Trigger>
                        <Dropdown.Content>
                            <Dropdown.Link href={route('profile.edit')}>Perfil</Dropdown.Link>
                            <Dropdown.Link href={route('logout')} method="post" as="button">
                                Cerrar sesión
                            </Dropdown.Link>
                        </Dropdown.Content>
                    </Dropdown>
                </header>

                {header && (
                    <div className="border-b border-gray-200 bg-white px-4 py-4 dark:border-gray-700 dark:bg-gray-800 sm:px-6 lg:hidden">
                        {header}
                    </div>
                )}

                <main className="p-4 sm:p-6 lg:p-8">{children}</main>
            </div>

            <FlashMessages />
        </div>
    );
}
