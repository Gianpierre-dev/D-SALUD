import {
    IconLayoutDashboard,
    IconShoppingCart,
    IconReceipt2,
    IconPill,
    IconCategory,
    IconPackages,
    IconTruck,
    IconFileSpreadsheet,
    IconUsers,
    IconShieldLock,
    IconHistory,
    IconSettings,
} from '@tabler/icons-react';

/**
 * Definición declarativa del menú lateral, agrupado por secciones.
 * Cada ítem indica el NOMBRE de ruta (Ziggy) y el permiso requerido.
 * El layout muestra un ítem solo si la ruta existe y el usuario tiene el permiso.
 */
export const navigation = [
    {
        section: 'Principal',
        items: [
            { label: 'Dashboard', routeName: 'dashboard', permission: 'dashboard.read', icon: IconLayoutDashboard },
            { label: 'Nueva Venta', routeName: 'ventas.create', permission: 'ventas.create', icon: IconShoppingCart },
            { label: 'Historial de Ventas', routeName: 'ventas.index', permission: 'ventas.read', icon: IconReceipt2 },
        ],
    },
    {
        section: 'Inventario',
        items: [
            { label: 'Productos', routeName: 'productos.index', permission: 'productos.read', icon: IconPill },
            { label: 'Categorías', routeName: 'categorias.index', permission: 'categorias.read', icon: IconCategory },
            { label: 'Lotes', routeName: 'lotes.index', permission: 'lotes.read', icon: IconPackages },
            { label: 'Proveedores', routeName: 'proveedores.index', permission: 'proveedores.read', icon: IconTruck },
        ],
    },
    {
        section: 'Reportes',
        items: [
            { label: 'Reportes', routeName: 'reportes.index', permission: 'reportes.read', icon: IconFileSpreadsheet },
        ],
    },
    {
        section: 'Administración',
        items: [
            { label: 'Usuarios', routeName: 'usuarios.index', permission: 'usuarios.read', icon: IconUsers },
            { label: 'Roles', routeName: 'roles.index', permission: 'roles.read', icon: IconShieldLock },
            { label: 'Auditoría', routeName: 'auditoria.index', permission: 'auditoria.read', icon: IconHistory },
            { label: 'Configuración', routeName: 'configuracion.edit', permission: 'empresa.update', icon: IconSettings },
        ],
    },
];
