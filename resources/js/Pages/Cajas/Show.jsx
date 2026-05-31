import { useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import {
    IconArrowLeft,
    IconCashRegister,
    IconDownload,
} from '@tabler/icons-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Badge from '@/Components/Badge';
import Can from '@/Components/Can';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import CerrarCajaModal from './Partials/CerrarCajaModal';
import { formatearMoneda } from '@/utils/format';

export default function Show({ caja }) {
    const [cerrarAbierto, setCerrarAbierto] = useState(false);

    const formatFecha = (fecha) =>
        fecha
            ? new Date(fecha).toLocaleDateString('es-PE', {
                  day: '2-digit',
                  month: '2-digit',
                  year: 'numeric',
                  hour: '2-digit',
                  minute: '2-digit',
              })
            : '—';

    const estaAbierta = caja.estado === 'ABIERTA';
    const diferencia  = caja.diferencia !== null ? Number(caja.diferencia) : null;

    const colorDiferencia =
        diferencia === null
            ? 'text-gray-700 dark:text-gray-300'
            : diferencia === 0
            ? 'text-gray-700 dark:text-gray-300'
            : diferencia > 0
            ? 'text-emerald-600'
            : 'text-red-600';

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold text-gray-800 dark:text-gray-200">
                        Caja #{caja.id}
                    </h2>
                    <Badge variant={estaAbierta ? 'warning' : 'success'}>{caja.estado}</Badge>
                </div>
            }
        >
            <Head title={`Caja #${caja.id}`} />

            <div className="mx-auto max-w-3xl space-y-6">
                {/* Acciones */}
                <div className="flex flex-wrap gap-3">
                    <Link href={route('cajas.index')}>
                        <SecondaryButton type="button">
                            <IconArrowLeft className="me-1 h-4 w-4" />
                            Volver
                        </SecondaryButton>
                    </Link>

                    {estaAbierta && (
                        <Can permission="cajas.close">
                            <PrimaryButton type="button" onClick={() => setCerrarAbierto(true)}>
                                <IconCashRegister className="me-1 h-4 w-4" />
                                Cerrar caja
                            </PrimaryButton>
                        </Can>
                    )}

                    {!estaAbierta && (
                        <a href={route('cajas.reporteZ', caja.id)}>
                            <PrimaryButton type="button">
                                <IconDownload className="me-1 h-4 w-4" />
                                Descargar Reporte Z
                            </PrimaryButton>
                        </a>
                    )}
                </div>

                {/* Datos generales */}
                <div className="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
                    <h3 className="mb-3 text-base font-semibold text-gray-800 dark:text-gray-100">
                        Apertura
                    </h3>
                    <dl className="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
                        <div>
                            <dt className="text-gray-500 dark:text-gray-400">Cajero</dt>
                            <dd className="font-medium text-gray-800 dark:text-gray-100">
                                {caja.cajero?.name ?? '—'}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-gray-500 dark:text-gray-400">Fecha y hora</dt>
                            <dd className="font-medium text-gray-800 dark:text-gray-100">
                                {formatFecha(caja.abierta_en)}
                            </dd>
                        </div>
                        <div className="sm:col-span-2">
                            <dt className="text-gray-500 dark:text-gray-400">Monto inicial</dt>
                            <dd className="text-xl font-bold text-brand-600 dark:text-brand-400">
                                {formatearMoneda(caja.monto_apertura)}
                            </dd>
                        </div>
                    </dl>
                </div>

                {/* Cierre */}
                <div className="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
                    <h3 className="mb-3 text-base font-semibold text-gray-800 dark:text-gray-100">
                        Cierre
                    </h3>

                    {estaAbierta ? (
                        <p className="text-sm text-gray-500 dark:text-gray-400">
                            La caja sigue abierta. Cuando termines el turno, click en
                            <strong> Cerrar caja</strong> para registrar el cuadre.
                        </p>
                    ) : (
                        <dl className="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
                            <div>
                                <dt className="text-gray-500 dark:text-gray-400">Fecha y hora</dt>
                                <dd className="font-medium text-gray-800 dark:text-gray-100">
                                    {formatFecha(caja.cerrada_en)}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-gray-500 dark:text-gray-400">Cerrada por</dt>
                                <dd className="font-medium text-gray-800 dark:text-gray-100">
                                    {caja.cerrada_por?.name ?? '—'}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-gray-500 dark:text-gray-400">Total ventas del turno</dt>
                                <dd className="font-medium text-gray-800 dark:text-gray-100">
                                    {formatearMoneda(caja.total_ventas)}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-gray-500 dark:text-gray-400">Total esperado</dt>
                                <dd className="font-medium text-gray-800 dark:text-gray-100">
                                    {formatearMoneda(caja.total_esperado)}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-gray-500 dark:text-gray-400">Monto contado</dt>
                                <dd className="font-medium text-gray-800 dark:text-gray-100">
                                    {formatearMoneda(caja.monto_cierre)}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-gray-500 dark:text-gray-400">Diferencia</dt>
                                <dd className={`text-xl font-bold ${colorDiferencia}`}>
                                    {formatearMoneda(caja.diferencia)}
                                    {diferencia !== null && diferencia !== 0 && (
                                        <span className="ms-2 text-xs font-normal text-gray-500">
                                            ({diferencia > 0 ? 'sobrante' : 'faltante'})
                                        </span>
                                    )}
                                </dd>
                            </div>
                        </dl>
                    )}

                    {caja.observaciones && (
                        <div className="mt-4 border-t border-gray-200 pt-3 dark:border-gray-700">
                            <p className="text-xs text-gray-500 dark:text-gray-400">Observaciones</p>
                            <p className="whitespace-pre-line text-sm text-gray-700 dark:text-gray-200">
                                {caja.observaciones}
                            </p>
                        </div>
                    )}
                </div>
            </div>

            {estaAbierta && (
                <CerrarCajaModal
                    show={cerrarAbierto}
                    onClose={() => setCerrarAbierto(false)}
                    caja={caja}
                />
            )}
        </AuthenticatedLayout>
    );
}
