import { Head } from '@inertiajs/react';
import {
    IconShoppingCart,
    IconCurrencyDollar,
    IconPackage,
    IconAlertTriangle,
    IconClockHour4,
} from '@tabler/icons-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Badge from '@/Components/Badge';
import StatCard from '@/Pages/Dashboard/Partials/StatCard';
import { formatearMoneda } from '@/utils/format';

/**
 * Determina la variante del badge según la proximidad del vencimiento.
 * Menos de 7 días → danger; de lo contrario → warning.
 *
 * @param {string} fechaVencimiento  — ISO date string (YYYY-MM-DD)
 * @returns {'danger'|'warning'}
 */
function variantePorVencer(fechaVencimiento) {
    const hoy = new Date();
    hoy.setHours(0, 0, 0, 0);
    const vence = new Date(fechaVencimiento);
    vence.setHours(0, 0, 0, 0);
    const diff = Math.round((vence - hoy) / (1000 * 60 * 60 * 24));
    return diff <= 7 ? 'danger' : 'warning';
}

/**
 * Formatea una fecha ISO (YYYY-MM-DD) a formato legible en español peruano.
 *
 * @param {string} fecha
 * @returns {string}
 */
function formatearFecha(fecha) {
    return new Date(fecha).toLocaleDateString('es-PE', {
        year:  'numeric',
        month: 'short',
        day:   'numeric',
    });
}

export default function Dashboard({ indicadores = {}, stockBajo = [], porVencer = [] }) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Dashboard
                </h2>
            }
        >
            <Head title="Dashboard" />

            {/* ─── Indicadores del día ─────────────────────────────────────── */}
            <section aria-labelledby="indicadores-titulo">
                <h3
                    id="indicadores-titulo"
                    className="mb-3 text-sm font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400"
                >
                    Resumen del día
                </h3>
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <StatCard
                        titulo="Ventas del día"
                        valor={indicadores.ventas}
                        icon={IconShoppingCart}
                    />
                    <StatCard
                        titulo="Total recaudado"
                        valor={formatearMoneda(indicadores.recaudado)}
                        icon={IconCurrencyDollar}
                    />
                    <StatCard
                        titulo="Productos vendidos"
                        valor={indicadores.productos_vendidos}
                        icon={IconPackage}
                    />
                </div>
            </section>

            {/* ─── Alertas de inventario ───────────────────────────────────── */}
            <div className="mt-8 grid grid-cols-1 gap-6 lg:grid-cols-2">

                {/* Stock bajo */}
                <section
                    aria-labelledby="stock-bajo-titulo"
                    className="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"
                >
                    <div className="flex items-center gap-2 border-b border-gray-200 px-5 py-4 dark:border-gray-700">
                        <IconAlertTriangle
                            className="h-5 w-5 text-amber-500"
                            aria-hidden="true"
                        />
                        <h3
                            id="stock-bajo-titulo"
                            className="font-semibold text-gray-800 dark:text-gray-200"
                        >
                            Productos con stock bajo
                        </h3>
                    </div>

                    {stockBajo.length === 0 ? (
                        <p className="px-5 py-6 text-sm text-gray-500 dark:text-gray-400">
                            Sin alertas de stock.
                        </p>
                    ) : (
                        <ul className="divide-y divide-gray-100 dark:divide-gray-700">
                            {stockBajo.map((producto) => (
                                <li
                                    key={producto.id}
                                    className="flex items-center justify-between gap-3 px-5 py-3"
                                >
                                    <span className="min-w-0 truncate text-sm font-medium text-gray-800 dark:text-gray-200">
                                        {producto.nombre}
                                    </span>
                                    <div className="flex flex-shrink-0 items-center gap-2">
                                        <span className="text-xs text-gray-500 dark:text-gray-400">
                                            {producto.stock_total ?? 0} / mín.&nbsp;{producto.stock_minimo}
                                        </span>
                                        <Badge variant="warning">Stock bajo</Badge>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                {/* Por vencer */}
                <section
                    aria-labelledby="por-vencer-titulo"
                    className="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"
                >
                    <div className="flex items-center gap-2 border-b border-gray-200 px-5 py-4 dark:border-gray-700">
                        <IconClockHour4
                            className="h-5 w-5 text-amber-500"
                            aria-hidden="true"
                        />
                        <h3
                            id="por-vencer-titulo"
                            className="font-semibold text-gray-800 dark:text-gray-200"
                        >
                            Productos por vencer
                        </h3>
                    </div>

                    {porVencer.length === 0 ? (
                        <p className="px-5 py-6 text-sm text-gray-500 dark:text-gray-400">
                            Sin alertas de vencimiento.
                        </p>
                    ) : (
                        <ul className="divide-y divide-gray-100 dark:divide-gray-700">
                            {porVencer.map((lote) => (
                                <li
                                    key={lote.id}
                                    className="flex items-center justify-between gap-3 px-5 py-3"
                                >
                                    <div className="min-w-0">
                                        <p className="truncate text-sm font-medium text-gray-800 dark:text-gray-200">
                                            {lote.producto?.nombre ?? '—'}
                                        </p>
                                        <p className="text-xs text-gray-500 dark:text-gray-400">
                                            Lote: {lote.codigo_lote}
                                        </p>
                                    </div>
                                    <div className="flex flex-shrink-0 items-center gap-2">
                                        <span className="text-xs text-gray-500 dark:text-gray-400">
                                            {formatearFecha(lote.fecha_vencimiento)}
                                        </span>
                                        <Badge variant={variantePorVencer(lote.fecha_vencimiento)}>
                                            Por vencer
                                        </Badge>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
