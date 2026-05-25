import { IconMinus, IconPlus, IconTrash } from '@tabler/icons-react';
import IconButton from '@/Components/IconButton';

/**
 * Fila del carrito de ventas.
 * Muestra el producto, permite ajustar cantidad y quitar el ítem.
 */
export default function CarritoItem({ item, onCambiarCantidad, onQuitar }) {
    const subtotal = item.cantidad * Number(item.precio_venta);

    return (
        <div className="flex items-center gap-3 rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-800">
            {/* Nombre y precio unitario */}
            <div className="min-w-0 flex-1">
                <p className="truncate text-sm font-medium text-gray-800 dark:text-gray-100">
                    {item.nombre}
                </p>
                <p className="text-xs text-gray-500 dark:text-gray-400">
                    S/{' '}
                    {Number(item.precio_venta).toLocaleString('es-PE', {
                        minimumFractionDigits: 2,
                    })}{' '}
                    c/u
                </p>
            </div>

            {/* Control de cantidad */}
            <div className="flex items-center gap-1">
                <IconButton
                    icon={IconMinus}
                    title="Reducir cantidad"
                    onClick={() => onCambiarCantidad(item.producto_id, item.cantidad - 1)}
                    variant="default"
                />
                <span className="w-8 text-center text-sm font-semibold text-gray-800 dark:text-gray-100">
                    {item.cantidad}
                </span>
                <IconButton
                    icon={IconPlus}
                    title="Aumentar cantidad"
                    onClick={() => onCambiarCantidad(item.producto_id, item.cantidad + 1)}
                    variant="default"
                />
            </div>

            {/* Subtotal */}
            <div className="w-20 text-right text-sm font-semibold text-gray-800 dark:text-gray-100">
                S/{' '}
                {subtotal.toLocaleString('es-PE', { minimumFractionDigits: 2 })}
            </div>

            {/* Quitar */}
            <IconButton
                icon={IconTrash}
                title="Quitar del carrito"
                onClick={() => onQuitar(item.producto_id)}
                variant="danger"
            />
        </div>
    );
}
