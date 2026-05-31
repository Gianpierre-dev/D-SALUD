import { Head, Link } from '@inertiajs/react';
import { IconPrinter, IconArrowLeft, IconFileTypePdf } from '@tabler/icons-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { ESTADO_VENTA } from '@/constants';
import { formatearMoneda } from '@/utils/format';

/**
 * Vista de boleta de venta.
 *
 * - En pantalla muestra layout completo con header y botones de acción.
 * - Al imprimir (print:) oculta navegación y muestra solo el recibo.
 * - "Imprimir" abre el diálogo nativo del navegador, que permite seleccionar
 *   impresora física o "Guardar como PDF" sin instalar dependencias adicionales.
 *   Reglas @media print en app.css garantizan que el logo y los colores de
 *   marca se preserven en la impresión/exportación.
 */
export default function Boleta({ venta, empresa }) {
    const boleta = venta.boleta;
    const vendedor = venta.vendedor;

    // Si la empresa no cargó un logo propio, usamos el logo del sistema
    // que está versionado en public/logo.png. Garantiza que la boleta nunca
    // salga sin imagen institucional.
    const logoSrc = empresa?.logo ?? '/logo.png';

    const formatFecha = (fecha) =>
        new Date(fecha).toLocaleDateString('es-PE', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold text-gray-800 dark:text-gray-200">
                    Boleta {boleta?.numero_formateado}
                </h2>
            }
        >
            <Head title={`Boleta ${boleta?.numero_formateado ?? ''}`} />

            {/* Botones de acción (se ocultan al imprimir) */}
            <div className="mx-auto mb-4 flex max-w-2xl flex-wrap gap-3 print:hidden">
                <Link
                    href={route('ventas.index')}
                    className="inline-flex items-center gap-1 rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                >
                    <IconArrowLeft className="h-4 w-4" />
                    Volver al historial
                </Link>
                <a
                    href={route('ventas.boleta.pdf', venta.id)}
                    className="inline-flex items-center gap-1 rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500"
                >
                    <IconFileTypePdf className="h-4 w-4" />
                    Descargar PDF
                </a>
                <button
                    type="button"
                    onClick={() => window.print()}
                    className="inline-flex items-center gap-1 rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                >
                    <IconPrinter className="h-4 w-4" />
                    Imprimir
                </button>
            </div>

            {/* ===== Recibo ===== */}
            <div
                id="boleta-imprimible"
                className="mx-auto max-w-2xl rounded-lg border border-gray-200 bg-white p-8 shadow-sm dark:border-gray-700 dark:bg-gray-800"
            >
                {/* Encabezado empresa */}
                <div className="mb-6 flex flex-col items-center text-center">
                    <img
                        src={logoSrc}
                        alt={empresa?.razon_social ?? "D'Salud"}
                        className="mb-3 h-20 w-auto object-contain"
                    />
                    <h1 className="text-lg font-bold text-gray-900 dark:text-gray-100 print:text-black">
                        {empresa?.razon_social ?? "D'Salud S.A.C."}
                    </h1>
                    {empresa?.ruc && (
                        <p className="text-sm text-gray-600 dark:text-gray-400 print:text-black">
                            RUC: {empresa.ruc}
                        </p>
                    )}
                    {empresa?.direccion && (
                        <p className="text-sm text-gray-600 dark:text-gray-400 print:text-black">
                            {empresa.direccion}
                        </p>
                    )}
                    {empresa?.telefono && (
                        <p className="text-sm text-gray-600 dark:text-gray-400 print:text-black">
                            Tel: {empresa.telefono}
                        </p>
                    )}
                </div>

                {/* Línea separadora */}
                <hr className="mb-4 border-dashed border-gray-300 dark:border-gray-600" />

                {/* Datos de la boleta */}
                <div className="mb-4 text-center">
                    <h2 className="text-base font-bold uppercase tracking-wider text-gray-900 dark:text-gray-100 print:text-black">
                        Boleta de Venta
                    </h2>
                    <p className="text-lg font-semibold text-brand-600 dark:text-brand-400">
                        {boleta?.numero_formateado}
                    </p>
                </div>

                <div className="mb-4 grid grid-cols-2 gap-1 text-sm">
                    <span className="text-gray-500 dark:text-gray-400 print:text-black">Fecha:</span>
                    <span className="text-gray-800 dark:text-gray-200 print:text-black">
                        {boleta?.fecha_emision ? formatFecha(boleta.fecha_emision) : '—'}
                    </span>
                    <span className="text-gray-500 dark:text-gray-400 print:text-black">Vendedor:</span>
                    <span className="text-gray-800 dark:text-gray-200 print:text-black">
                        {vendedor?.name ?? '—'}
                    </span>
                    {venta.cliente && (
                        <>
                            <span className="text-gray-500 dark:text-gray-400 print:text-black">
                                Cliente:
                            </span>
                            <span className="text-gray-800 dark:text-gray-200 print:text-black">
                                {venta.cliente.nombre}
                            </span>
                            <span className="text-gray-500 dark:text-gray-400 print:text-black">
                                {venta.cliente.tipo_documento}:
                            </span>
                            <span className="text-gray-800 dark:text-gray-200 print:text-black">
                                {venta.cliente.numero_documento}
                            </span>
                        </>
                    )}
                </div>

                <hr className="mb-4 border-dashed border-gray-300 dark:border-gray-600" />

                {/* Tabla de detalles */}
                <div className="mb-4 overflow-x-auto">
                    <table className="w-full text-sm">
                    <thead>
                        <tr className="border-b border-gray-200 dark:border-gray-700">
                            <th className="py-1 text-left font-semibold text-gray-700 dark:text-gray-300 print:text-black">
                                Producto
                            </th>
                            <th className="py-1 text-right font-semibold text-gray-700 dark:text-gray-300 print:text-black">
                                Cant.
                            </th>
                            <th className="py-1 text-right font-semibold text-gray-700 dark:text-gray-300 print:text-black">
                                P. Unit.
                            </th>
                            <th className="py-1 text-right font-semibold text-gray-700 dark:text-gray-300 print:text-black">
                                Subtotal
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {venta.detalles.map((detalle, index) => (
                            <tr
                                key={index}
                                className="border-b border-gray-100 dark:border-gray-700/50"
                            >
                                <td className="py-1.5 text-gray-800 dark:text-gray-200 print:text-black">
                                    {detalle.producto?.nombre ?? '—'}
                                </td>
                                <td className="py-1.5 text-right text-gray-700 dark:text-gray-300 print:text-black">
                                    {detalle.cantidad}
                                </td>
                                <td className="py-1.5 text-right text-gray-700 dark:text-gray-300 print:text-black">
                                    {formatearMoneda(detalle.precio_unitario)}
                                </td>
                                <td className="py-1.5 text-right font-medium text-gray-800 dark:text-gray-200 print:text-black">
                                    {formatearMoneda(detalle.subtotal)}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                    </table>
                </div>

                <hr className="mb-3 border-gray-200 dark:border-gray-700" />

                {/* Total */}
                <div className="flex items-center justify-between">
                    <span className="text-base font-bold text-gray-800 dark:text-gray-100 print:text-black">
                        TOTAL
                    </span>
                    <span className="text-xl font-bold text-brand-600 dark:text-brand-400">
                        {formatearMoneda(venta.total)}
                    </span>
                </div>

                {/* Estado anulada */}
                {venta.estado === ESTADO_VENTA.ANULADA && (
                    <div className="mt-4 rounded-md border border-red-300 bg-red-50 px-4 py-2 text-center dark:border-red-800 dark:bg-red-900/20">
                        <p className="text-sm font-semibold text-red-700 dark:text-red-400">
                            BOLETA ANULADA
                        </p>
                        {venta.motivo_anulacion && (
                            <p className="text-xs text-red-600 dark:text-red-500">
                                Motivo: {venta.motivo_anulacion}
                            </p>
                        )}
                    </div>
                )}

                {/* Pie */}
                <p className="mt-6 text-center text-xs text-gray-400 dark:text-gray-500 print:text-black">
                    Gracias por su compra.
                </p>
            </div>
        </AuthenticatedLayout>
    );
}
