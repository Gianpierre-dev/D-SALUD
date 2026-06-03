import {
    ResponsiveContainer,
    AreaChart,
    Area,
    BarChart,
    Bar,
    PieChart,
    Pie,
    Cell,
    XAxis,
    YAxis,
    CartesianGrid,
    Tooltip,
    Legend,
} from 'recharts';
import { useTheme } from '@/hooks/useTheme';
import { formatearMoneda } from '@/utils/format';

/**
 * Paleta de marca para los gráficos. El primer color es el azul brand;
 * el resto deriva para series múltiples (medios de pago).
 */
const COLORES = ['#2563EB', '#16A34A', '#F59E0B', '#DB2777', '#7C3AED'];

/**
 * Tarjeta contenedora de un gráfico, con título y altura fija.
 */
function ChartCard({ titulo, children, vacio, mensajeVacio }) {
    return (
        <section className="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <h3 className="mb-4 font-semibold text-gray-800 dark:text-gray-200">{titulo}</h3>
            {vacio ? (
                <p className="flex h-56 items-center justify-center text-sm text-gray-400">
                    {mensajeVacio}
                </p>
            ) : (
                <div className="h-56">{children}</div>
            )}
        </section>
    );
}

/**
 * Conjunto de gráficos del dashboard:
 *  - Ventas de los últimos 14 días (área)
 *  - Top productos del mes (barras horizontales)
 *  - Ventas por medio de pago del mes (dona)
 */
export default function DashboardCharts({ ventasPorDia = [], topProductos = [], ventasPorMedio = [] }) {
    const { isDark } = useTheme();

    const colorEje    = isDark ? '#9CA3AF' : '#6B7280';
    const colorGrid   = isDark ? '#374151' : '#E5E7EB';
    const fondoTooltip = isDark ? '#1F2937' : '#FFFFFF';
    const bordeTooltip = isDark ? '#374151' : '#E5E7EB';

    const estiloTooltip = {
        backgroundColor: fondoTooltip,
        border: `1px solid ${bordeTooltip}`,
        borderRadius: 8,
        fontSize: 12,
        color: isDark ? '#F3F4F6' : '#111827',
    };

    const huboVentas = ventasPorDia.some((d) => d.total > 0);

    return (
        <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
            {/* Ventas últimos 14 días — ocupa todo el ancho */}
            <div className="lg:col-span-2">
                <ChartCard
                    titulo="Ventas de los últimos 14 días"
                    vacio={!huboVentas}
                    mensajeVacio="Aún no hay ventas registradas en el período."
                >
                    <ResponsiveContainer width="100%" height="100%">
                        <AreaChart data={ventasPorDia} margin={{ top: 8, right: 8, left: 0, bottom: 0 }}>
                            <defs>
                                <linearGradient id="gradVentas" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="5%" stopColor="#2563EB" stopOpacity={0.4} />
                                    <stop offset="95%" stopColor="#2563EB" stopOpacity={0} />
                                </linearGradient>
                            </defs>
                            <CartesianGrid strokeDasharray="3 3" stroke={colorGrid} />
                            <XAxis dataKey="etiqueta" tick={{ fontSize: 11, fill: colorEje }} stroke={colorGrid} />
                            <YAxis tick={{ fontSize: 11, fill: colorEje }} stroke={colorGrid} width={48} />
                            <Tooltip
                                contentStyle={estiloTooltip}
                                formatter={(valor) => [formatearMoneda(valor), 'Ventas']}
                            />
                            <Area
                                type="monotone"
                                dataKey="total"
                                stroke="#2563EB"
                                strokeWidth={2}
                                fill="url(#gradVentas)"
                            />
                        </AreaChart>
                    </ResponsiveContainer>
                </ChartCard>
            </div>

            {/* Top productos del mes */}
            <ChartCard
                titulo="Top productos del mes"
                vacio={topProductos.length === 0}
                mensajeVacio="Sin ventas este mes."
            >
                <ResponsiveContainer width="100%" height="100%">
                    <BarChart
                        data={topProductos}
                        layout="vertical"
                        margin={{ top: 4, right: 16, left: 8, bottom: 4 }}
                    >
                        <CartesianGrid strokeDasharray="3 3" stroke={colorGrid} horizontal={false} />
                        <XAxis type="number" tick={{ fontSize: 11, fill: colorEje }} stroke={colorGrid} allowDecimals={false} />
                        <YAxis
                            type="category"
                            dataKey="nombre"
                            tick={{ fontSize: 11, fill: colorEje }}
                            stroke={colorGrid}
                            width={110}
                        />
                        <Tooltip
                            contentStyle={estiloTooltip}
                            formatter={(valor) => [valor, 'Unidades']}
                            cursor={{ fill: isDark ? '#37415133' : '#F3F4F6' }}
                        />
                        <Bar dataKey="cantidad" fill="#2563EB" radius={[0, 4, 4, 0]} />
                    </BarChart>
                </ResponsiveContainer>
            </ChartCard>

            {/* Ventas por medio de pago */}
            <ChartCard
                titulo="Ventas por medio de pago (mes)"
                vacio={ventasPorMedio.length === 0}
                mensajeVacio="Sin ventas este mes."
            >
                <ResponsiveContainer width="100%" height="100%">
                    <PieChart>
                        <Pie
                            data={ventasPorMedio}
                            dataKey="total"
                            nameKey="medio"
                            cx="50%"
                            cy="50%"
                            innerRadius={45}
                            outerRadius={75}
                            paddingAngle={2}
                        >
                            {ventasPorMedio.map((_, i) => (
                                <Cell key={i} fill={COLORES[i % COLORES.length]} />
                            ))}
                        </Pie>
                        <Tooltip
                            contentStyle={estiloTooltip}
                            formatter={(valor, nombre) => [formatearMoneda(valor), nombre]}
                        />
                        <Legend
                            wrapperStyle={{ fontSize: 12, color: colorEje }}
                            iconType="circle"
                        />
                    </PieChart>
                </ResponsiveContainer>
            </ChartCard>
        </div>
    );
}
