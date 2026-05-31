import { useRef, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { IconShoppingCart, IconSearch, IconUserHeart } from '@tabler/icons-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PrimaryButton from '@/Components/PrimaryButton';
import SelectInput from '@/Components/SelectInput';
import InputLabel from '@/Components/InputLabel';
import CarritoItem from './Partials/CarritoItem';
import { formatearMoneda } from '@/utils/format';

/**
 * Punto de venta (POS).
 *
 * Layout:
 *   - Mobile: buscador + grilla de productos, carrito colapsado abajo.
 *   - Desktop (lg): 2 columnas — productos (izq, 2/3) + carrito (der, 1/3).
 *
 * Idempotencia: cada vez que el carrito se inicializa (o tras un éxito) se
 * genera un UUID que viaja como header Idempotency-Key. El backend devuelve
 * la misma boleta si el cliente reintenta el POST con la misma key dentro
 * de 60 s — bloquea dobles ventas por doble click o reintento de red.
 */
function generarIdempotencyKey() {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }
    // Fallback simple para navegadores sin crypto.randomUUID.
    return 'k-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 10);
}

export default function Create({ productos, clientes = [] }) {
    const [busqueda, setBusqueda] = useState('');
    const [carrito, setCarrito] = useState([]);
    const [clienteId, setClienteId] = useState('');
    const [procesando, setProcesando] = useState(false);
    const idempotencyKeyRef = useRef(generarIdempotencyKey());

    // ---------- Helpers ----------

    /** Filtra productos por nombre o código según búsqueda. */
    const productosFiltrados = productos.filter((p) => {
        if (!busqueda.trim()) return true;
        const q = busqueda.toLowerCase();
        return (
            p.nombre.toLowerCase().includes(q) ||
            (p.codigo && p.codigo.toLowerCase().includes(q))
        );
    });

    /** Stock disponible neto: stock del servidor menos lo ya en carrito. */
    const stockDisponible = (producto) => {
        const enCarrito =
            carrito.find((i) => i.producto_id === producto.id)?.cantidad ?? 0;
        return Number(producto.stock_total) - enCarrito;
    };

    const totalCarrito = carrito.reduce(
        (acc, item) => acc + item.cantidad * Number(item.precio_venta),
        0
    );

    // ---------- Acciones del carrito ----------

    const agregarAlCarrito = (producto) => {
        if (stockDisponible(producto) <= 0) return;

        setCarrito((prev) => {
            const existe = prev.find((i) => i.producto_id === producto.id);
            if (existe) {
                return prev.map((i) =>
                    i.producto_id === producto.id
                        ? { ...i, cantidad: i.cantidad + 1 }
                        : i
                );
            }
            return [
                ...prev,
                {
                    producto_id: producto.id,
                    nombre: producto.nombre,
                    precio_venta: producto.precio_venta,
                    stock_total: Number(producto.stock_total),
                    cantidad: 1,
                },
            ];
        });
    };

    const cambiarCantidad = (productoId, nuevaCantidad) => {
        if (nuevaCantidad <= 0) {
            quitarDelCarrito(productoId);
            return;
        }

        // Updater funcional: evita stale closures con clicks rápidos (POS).
        setCarrito((prev) =>
            prev.map((i) => {
                if (i.producto_id !== productoId) {
                    return i;
                }
                // No superar el stock total del servidor.
                const cantidad = Math.min(nuevaCantidad, Number(i.stock_total));
                return { ...i, cantidad };
            })
        );
    };

    const quitarDelCarrito = (productoId) => {
        setCarrito((prev) => prev.filter((i) => i.producto_id !== productoId));
    };

    // ---------- Envío ----------

    const registrarVenta = () => {
        if (carrito.length === 0 || procesando) return;
        setProcesando(true);
        router.post(
            route('ventas.store'),
            {
                cliente_id: clienteId ? Number(clienteId) : null,
                items: carrito.map(({ producto_id, cantidad }) => ({ producto_id, cantidad })),
            },
            {
                headers: { 'Idempotency-Key': idempotencyKeyRef.current },
                onSuccess: () => {
                    setCarrito([]);
                    setClienteId('');
                    // Tras un éxito real, rotamos la key para la próxima venta.
                    idempotencyKeyRef.current = generarIdempotencyKey();
                },
                onError: () => {
                    // Refresca stock_total tras un error de negocio (stock
                    // insuficiente, producto inactivo, etc.) para no insistir
                    // sobre datos viejos del carrito.
                    router.reload({ only: ['productos'] });
                },
                onFinish: () => setProcesando(false),
            }
        );
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold text-gray-800 dark:text-gray-200">
                    Nueva venta
                </h2>
            }
        >
            <Head title="Nueva venta" />

            <div className="mx-auto max-w-7xl">
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    {/* ===== COLUMNA IZQUIERDA: Catálogo ===== */}
                    <div className="lg:col-span-2">
                        {/* Buscador */}
                        <div className="relative mb-4">
                            <IconSearch className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                            <input
                                type="text"
                                placeholder="Buscar por nombre o código..."
                                value={busqueda}
                                onChange={(e) => setBusqueda(e.target.value)}
                                className="w-full rounded-md border border-gray-300 py-2 pl-9 pr-4 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                            />
                        </div>

                        {/* Grilla de productos */}
                        {productosFiltrados.length === 0 ? (
                            <p className="py-10 text-center text-sm text-gray-500">
                                No hay productos disponibles con stock.
                            </p>
                        ) : (
                            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
                                {productosFiltrados.map((producto) => {
                                    const disponible = stockDisponible(producto);
                                    const agotado = disponible <= 0;

                                    return (
                                        <button
                                            key={producto.id}
                                            type="button"
                                            disabled={agotado}
                                            onClick={() => agregarAlCarrito(producto)}
                                            className={`flex flex-col rounded-lg border p-3 text-left transition focus:outline-none focus:ring-2 focus:ring-brand-500 ${
                                                agotado
                                                    ? 'cursor-not-allowed border-gray-200 bg-gray-50 opacity-50 dark:border-gray-700 dark:bg-gray-800'
                                                    : 'cursor-pointer border-gray-200 bg-white hover:border-brand-400 hover:shadow-md dark:border-gray-700 dark:bg-gray-800 dark:hover:border-brand-500'
                                            }`}
                                        >
                                            <span className="mb-1 line-clamp-2 text-sm font-medium text-gray-800 dark:text-gray-100">
                                                {producto.nombre}
                                            </span>
                                            <span className="text-base font-bold text-brand-600 dark:text-brand-400">
                                                {formatearMoneda(producto.precio_venta)}
                                            </span>
                                            <span className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                Stock: {disponible}
                                            </span>
                                        </button>
                                    );
                                })}
                            </div>
                        )}
                    </div>

                    {/* ===== COLUMNA DERECHA: Carrito ===== */}
                    <div className="flex flex-col gap-4 lg:col-span-1 lg:sticky lg:top-4 lg:self-start">
                        <div className="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                            <h3 className="mb-3 flex items-center gap-2 text-base font-semibold text-gray-800 dark:text-gray-100">
                                <IconShoppingCart className="h-5 w-5 text-brand-500" />
                                Carrito
                                {carrito.length > 0 && (
                                    <span className="ml-auto rounded-full bg-brand-100 px-2 py-0.5 text-xs font-medium text-brand-700 dark:bg-brand-900/40 dark:text-brand-300">
                                        {carrito.length}{' '}
                                        {carrito.length === 1 ? 'ítem' : 'ítems'}
                                    </span>
                                )}
                            </h3>

                            {carrito.length === 0 ? (
                                <p className="py-6 text-center text-sm text-gray-400">
                                    Selecciona productos del catálogo.
                                </p>
                            ) : (
                                <div className="flex max-h-64 flex-col gap-2 overflow-y-auto lg:max-h-96">
                                    {carrito.map((item) => (
                                        <CarritoItem
                                            key={item.producto_id}
                                            item={item}
                                            onCambiarCantidad={cambiarCantidad}
                                            onQuitar={quitarDelCarrito}
                                        />
                                    ))}
                                </div>
                            )}

                            {/* Selector de cliente (opcional). Si se omite, queda
                                como venta a consumidor final. */}
                            <div className="mt-4 border-t border-gray-200 pt-4 dark:border-gray-700">
                                <InputLabel
                                    htmlFor="cliente_id"
                                    value={(
                                        <span className="flex items-center gap-1">
                                            <IconUserHeart className="h-4 w-4 text-brand-500" />
                                            Cliente (opcional)
                                        </span>
                                    )}
                                />
                                <SelectInput
                                    id="cliente_id"
                                    className="mt-1 block w-full text-sm"
                                    value={clienteId}
                                    onChange={(e) => setClienteId(e.target.value)}
                                >
                                    <option value="">Consumidor final</option>
                                    {clientes.map((c) => (
                                        <option key={c.id} value={c.id}>
                                            {c.tipo_documento} {c.numero_documento} — {c.nombre}
                                        </option>
                                    ))}
                                </SelectInput>
                            </div>

                            {/* Total y botón de registro */}
                            <div className="mt-4 border-t border-gray-200 pt-4 dark:border-gray-700">
                                <div className="mb-4 flex items-center justify-between">
                                    <span className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                        Total
                                    </span>
                                    <span className="text-lg font-bold text-gray-800 dark:text-gray-100">
                                        {formatearMoneda(totalCarrito)}
                                    </span>
                                </div>
                                <PrimaryButton
                                    className="w-full justify-center"
                                    disabled={carrito.length === 0 || procesando}
                                    onClick={registrarVenta}
                                >
                                    {procesando ? 'Registrando...' : 'Registrar venta'}
                                </PrimaryButton>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
