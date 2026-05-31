import { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import {
    IconArrowLeft,
    IconPencil,
    IconTruck,
    IconBan,
} from '@tabler/icons-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Badge from '@/Components/Badge';
import Can from '@/Components/Can';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import DangerButton from '@/Components/DangerButton';
import ConfirmDialog from '@/Components/ConfirmDialog';
import Modal from '@/Components/Modal';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';
import { formatearMoneda } from '@/utils/format';

/**
 * Vista de detalle de una compra.
 *
 * Acciones según estado:
 *   PENDIENTE → Editar | Recibir mercadería | Anular
 *   RECIBIDA  → solo lectura + nota de recepción
 *   ANULADA   → solo lectura + motivo de anulación
 */
export default function Show({ compra }) {
    const [recibirAbierto, setRecibirAbierto] = useState(false);
    const [anularAbierto, setAnularAbierto] = useState(false);

    const recibir = useForm({});
    const anular  = useForm({ motivo: '' });

    const formatFecha = (fecha, conHora = false) =>
        fecha
            ? new Date(fecha).toLocaleDateString('es-PE', {
                  day: '2-digit',
                  month: '2-digit',
                  year: 'numeric',
                  ...(conHora ? { hour: '2-digit', minute: '2-digit' } : {}),
              })
            : '—';

    const colorEstado = (estado) =>
        ({
            PENDIENTE: 'warning',
            RECIBIDA: 'success',
            ANULADA: 'danger',
        }[estado] ?? 'neutral');

    const confirmarRecibir = () => {
        recibir.put(route('compras.recibir', compra.id), {
            onSuccess: () => setRecibirAbierto(false),
        });
    };

    const confirmarAnular = (e) => {
        e.preventDefault();
        anular.delete(route('compras.destroy', compra.id), {
            preserveScroll: true,
            onSuccess: () => setAnularAbierto(false),
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold text-gray-800 dark:text-gray-200">
                        Compra {compra.numero_formateado}
                    </h2>
                    <Badge variant={colorEstado(compra.estado)}>{compra.estado}</Badge>
                </div>
            }
        >
            <Head title={`Compra ${compra.numero_formateado}`} />

            <div className="mx-auto max-w-5xl space-y-6">
                {/* Acciones */}
                <div className="flex flex-wrap gap-3">
                    <Link href={route('compras.index')}>
                        <SecondaryButton type="button">
                            <IconArrowLeft className="me-1 h-4 w-4" />
                            Volver
                        </SecondaryButton>
                    </Link>

                    {compra.estado === 'PENDIENTE' && (
                        <>
                            <Can permission="compras.update">
                                <Link href={route('compras.edit', compra.id)}>
                                    <SecondaryButton type="button">
                                        <IconPencil className="me-1 h-4 w-4" />
                                        Editar
                                    </SecondaryButton>
                                </Link>
                            </Can>
                            <Can permission="compras.recibir">
                                <PrimaryButton type="button" onClick={() => setRecibirAbierto(true)}>
                                    <IconTruck className="me-1 h-4 w-4" />
                                    Recibir mercadería
                                </PrimaryButton>
                            </Can>
                            <Can permission="compras.delete">
                                <DangerButton type="button" onClick={() => setAnularAbierto(true)}>
                                    <IconBan className="me-1 h-4 w-4" />
                                    Anular
                                </DangerButton>
                            </Can>
                        </>
                    )}
                </div>

                {/* Cabecera de datos */}
                <div className="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
                    <div className="grid grid-cols-1 gap-4 text-sm sm:grid-cols-3">
                        <div>
                            <span className="text-gray-500 dark:text-gray-400">Proveedor</span>
                            <p className="font-semibold text-gray-800 dark:text-gray-100">
                                {compra.proveedor?.razon_social ?? '—'}
                            </p>
                            <p className="text-xs text-gray-500 dark:text-gray-400">
                                RUC {compra.proveedor?.ruc ?? '—'}
                            </p>
                        </div>
                        <div>
                            <span className="text-gray-500 dark:text-gray-400">Fecha de compra</span>
                            <p className="font-semibold text-gray-800 dark:text-gray-100">
                                {formatFecha(compra.fecha_compra)}
                            </p>
                        </div>
                        <div>
                            <span className="text-gray-500 dark:text-gray-400">Registrada por</span>
                            <p className="font-semibold text-gray-800 dark:text-gray-100">
                                {compra.registrada_por?.name ?? '—'}
                            </p>
                            <p className="text-xs text-gray-500 dark:text-gray-400">
                                {formatFecha(compra.created_at, true)}
                            </p>
                        </div>

                        {compra.observaciones && (
                            <div className="sm:col-span-3">
                                <span className="text-gray-500 dark:text-gray-400">Observaciones</span>
                                <p className="text-gray-800 dark:text-gray-100">{compra.observaciones}</p>
                            </div>
                        )}

                        {compra.estado === 'RECIBIDA' && (
                            <div className="sm:col-span-3 rounded-md border border-emerald-200 bg-emerald-50 p-3 dark:border-emerald-800 dark:bg-emerald-900/20">
                                <p className="text-sm font-semibold text-emerald-700 dark:text-emerald-300">
                                    Mercadería recibida el {formatFecha(compra.recibida_en, true)}
                                    {compra.recibida_por ? ` por ${compra.recibida_por.name}` : ''}.
                                </p>
                                <p className="text-xs text-emerald-700 dark:text-emerald-400">
                                    Los lotes ya están disponibles para venta y los movimientos
                                    aparecen en el kardex con motivo COMPRA.
                                </p>
                            </div>
                        )}

                        {compra.estado === 'ANULADA' && (
                            <div className="sm:col-span-3 rounded-md border border-red-200 bg-red-50 p-3 dark:border-red-800 dark:bg-red-900/20">
                                <p className="text-sm font-semibold text-red-700 dark:text-red-300">
                                    Compra anulada el {formatFecha(compra.anulada_en, true)}
                                    {compra.anulada_por ? ` por ${compra.anulada_por.name}` : ''}.
                                </p>
                                {compra.motivo_anulacion && (
                                    <p className="text-xs text-red-700 dark:text-red-400">
                                        Motivo: {compra.motivo_anulacion}
                                    </p>
                                )}
                            </div>
                        )}
                    </div>
                </div>

                {/* Detalles */}
                <div className="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
                    <h3 className="mb-3 text-base font-semibold text-gray-800 dark:text-gray-100">
                        Productos
                    </h3>
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-gray-200 dark:border-gray-700">
                                    <th className="py-2 text-left">Producto</th>
                                    <th className="py-2 text-left">Cód. lote</th>
                                    <th className="py-2 text-left">Vence</th>
                                    <th className="py-2 text-right">Cantidad</th>
                                    <th className="py-2 text-right">P. Unit.</th>
                                    <th className="py-2 text-right">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                {compra.detalles.map((detalle) => (
                                    <tr key={detalle.id} className="border-b border-gray-100 dark:border-gray-700/50">
                                        <td className="py-2 text-gray-800 dark:text-gray-200">
                                            {detalle.producto?.nombre ?? '—'}
                                        </td>
                                        <td className="py-2 text-gray-700 dark:text-gray-300">
                                            {detalle.codigo_lote}
                                        </td>
                                        <td className="py-2 text-gray-700 dark:text-gray-300">
                                            {formatFecha(detalle.fecha_vencimiento)}
                                        </td>
                                        <td className="py-2 text-right">{detalle.cantidad}</td>
                                        <td className="py-2 text-right">
                                            {formatearMoneda(detalle.precio_unitario)}
                                        </td>
                                        <td className="py-2 text-right font-medium">
                                            {formatearMoneda(detalle.subtotal)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    <div className="mt-4 flex items-center justify-end border-t border-gray-200 pt-3 dark:border-gray-700">
                        <span className="me-3 text-sm font-medium text-gray-600 dark:text-gray-400">
                            Total:
                        </span>
                        <span className="text-xl font-bold text-brand-600 dark:text-brand-400">
                            {formatearMoneda(compra.total)}
                        </span>
                    </div>
                </div>
            </div>

            {/* Confirmación recepción */}
            <ConfirmDialog
                show={recibirAbierto}
                title="Recibir mercadería"
                message={`Vas a confirmar la recepción de la compra ${compra.numero_formateado}. Se crearán ${compra.detalles.length} lotes nuevos y se generarán los movimientos correspondientes en el kardex. Esta acción no se puede deshacer (para revertir, registrá DEVOLUCIÓN A PROVEEDOR en Movimientos).`}
                confirmLabel="Sí, recibir"
                processing={recibir.processing}
                onConfirm={confirmarRecibir}
                onClose={() => setRecibirAbierto(false)}
            />

            {/* Anulación */}
            <Modal show={anularAbierto} onClose={() => setAnularAbierto(false)} maxWidth="md">
                <form onSubmit={confirmarAnular} className="p-6">
                    <h2 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                        Anular compra {compra.numero_formateado}
                    </h2>
                    <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        La compra debe estar en estado PENDIENTE. No se afecta inventario.
                    </p>
                    <div className="mt-4">
                        <InputLabel htmlFor="motivo" value="Motivo de anulación" />
                        <textarea
                            id="motivo"
                            rows={3}
                            maxLength={255}
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                            value={anular.data.motivo}
                            onChange={(e) => anular.setData('motivo', e.target.value)}
                        />
                        <InputError message={anular.errors.motivo} className="mt-2" />
                    </div>
                    <div className="mt-6 flex justify-end gap-3">
                        <SecondaryButton type="button" onClick={() => setAnularAbierto(false)} disabled={anular.processing}>
                            Cancelar
                        </SecondaryButton>
                        <DangerButton disabled={anular.processing}>
                            Anular compra
                        </DangerButton>
                    </div>
                </form>
            </Modal>
        </AuthenticatedLayout>
    );
}
