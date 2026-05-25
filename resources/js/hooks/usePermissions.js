import { usePage } from '@inertiajs/react';

/**
 * Acceso a los permisos y roles del usuario autenticado, compartidos por Inertia.
 * Centraliza la lógica de autorización del lado del cliente.
 */
export function usePermissions() {
    const { auth } = usePage().props;
    const permissions = auth?.user?.permissions ?? [];
    const roles = auth?.user?.roles ?? [];

    const can = (permission) => permissions.includes(permission);
    const canAny = (list) => list.some((permission) => permissions.includes(permission));
    const hasRole = (role) => roles.includes(role);

    return { can, canAny, hasRole, permissions, roles };
}
