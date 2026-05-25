import { usePermissions } from '@/hooks/usePermissions';

/**
 * Renderiza su contenido solo si el usuario tiene el permiso indicado.
 */
export default function Can({ permission, children, fallback = null }) {
    const { can } = usePermissions();

    return can(permission) ? children : fallback;
}
