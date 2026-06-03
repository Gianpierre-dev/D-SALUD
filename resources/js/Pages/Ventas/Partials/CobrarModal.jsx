import { useEffect, useMemo, useState } from 'react';
import { IconCash, IconDeviceMobile, IconCreditCard, IconBuildingBank } from '@tabler/icons-react';
import Modal from '@/Components/Modal';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import { soloDecimalPositivo } from '@/utils/inputs';
import { formatearMoneda } from '@/utils/format';

/**
 * Modal de cobro del POS.
 *
 * Dos modos:
 *  - Simple (default): un único medio cubre todo el total. Si es efectivo,
 *    el cajero ingresa "efectivo recibido" y se calcula el vuelto.
 *  - Mixto: el cajero distribuye montos por medio; la suma debe igualar el total.
 *
 * Al confirmar entrega { pagos: [{medio_pago, monto}], monto_recibido } al POS.
 */
const MEDIOS = [
    { value: 'EFECTIVO', label: 'Efectivo', icon: IconCash },
    { value: 'YAPE', label: 'Yape', icon: IconDeviceMobile },
    { value: 'PLIN', label: 'Plin', icon: IconDeviceMobile },
    { value: 'TARJETA', label: 'Tarjeta', icon: IconCreditCard },
    { value: 'TRANSFERENCIA', label: 'Transferencia', icon: IconBuildingBank },
];

export default function CobrarModal({ show, onClose, total, procesando, onConfirmar, clienteEsRuc = false }) {
    const [mixto, setMixto] = useState(false);
    const [medioSimple, setMedioSimple] = useState('EFECTIVO');
    const [recibido, setRecibido] = useState('');
    const [montosMixtos, setMontosMixtos] = useState({});
    const [tipoComprobante, setTipoComprobante] = useState('BOLETA');

    // Reset al abrir.
    useEffect(() => {
        if (!show) return;
        setMixto(false);
        setMedioSimple('EFECTIVO');
        setRecibido('');
        setMontosMixtos({});
        setTipoComprobante('BOLETA');
    }, [show]);

    const totalNum = Number(total) || 0;

    // ----- Modo simple -----
    const esEfectivoSimple = !mixto && medioSimple === 'EFECTIVO';
    const recibidoNum = Number(recibido) || 0;
    const vueltoSimple = esEfectivoSimple ? Math.max(0, recibidoNum - totalNum) : 0;

    // ----- Modo mixto -----
    const sumaMixta = useMemo(
        () => Object.values(montosMixtos).reduce((acc, v) => acc + (Number(v) || 0), 0),
        [montosMixtos],
    );
    const faltaMixto = Math.max(0, totalNum - sumaMixta);
    const efectivoMixto = Number(montosMixtos.EFECTIVO) || 0;

    // ----- Validación de confirmación -----
    const puedeConfirmar = useMemo(() => {
        if (procesando || totalNum <= 0) return false;
        if (mixto) {
            // La suma debe igualar el total (tolerancia centavo).
            return Math.abs(sumaMixta - totalNum) < 0.01;
        }
        if (esEfectivoSimple) {
            // El efectivo recibido debe cubrir el total.
            return recibidoNum >= totalNum;
        }
        // Medio digital simple: siempre cubre el total exacto.
        return true;
    }, [procesando, totalNum, mixto, sumaMixta, esEfectivoSimple, recibidoNum]);

    const confirmar = () => {
        if (!puedeConfirmar) return;

        let pagos;
        let montoRecibido = null;

        if (mixto) {
            pagos = Object.entries(montosMixtos)
                .filter(([, monto]) => Number(monto) > 0)
                .map(([medio_pago, monto]) => ({ medio_pago, monto: Number(monto) }));
            if (efectivoMixto > 0) {
                montoRecibido = efectivoMixto; // sin vuelto en mixto (montos exactos)
            }
        } else if (esEfectivoSimple) {
            pagos = [{ medio_pago: 'EFECTIVO', monto: totalNum }];
            montoRecibido = recibidoNum;
        } else {
            pagos = [{ medio_pago: medioSimple, monto: totalNum }];
        }

        onConfirmar(pagos, montoRecibido, tipoComprobante);
    };

    // Factura exige cliente con RUC: el backend lo valida, pero avisamos antes.
    const facturaSinRuc = tipoComprobante === 'FACTURA' && !clienteEsRuc;

    return (
        <Modal show={show} onClose={onClose} maxWidth="md">
            <div className="p-6">
                <h2 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                    Cobrar venta
                </h2>

                {/* Total */}
                <div className="mt-3 flex items-center justify-between rounded-lg bg-brand-50 px-4 py-3 dark:bg-brand-900/20">
                    <span className="text-sm font-medium text-brand-700 dark:text-brand-300">
                        Total a cobrar
                    </span>
                    <span className="text-2xl font-bold text-brand-700 dark:text-brand-300">
                        {formatearMoneda(totalNum)}
                    </span>
                </div>

                {/* Tipo de comprobante */}
                <div className="mt-4">
                    <InputLabel value="Comprobante" />
                    <div className="mt-2 grid grid-cols-2 gap-2">
                        {[
                            { value: 'BOLETA', label: 'Boleta' },
                            { value: 'FACTURA', label: 'Factura' },
                        ].map(({ value, label }) => (
                            <button
                                key={value}
                                type="button"
                                onClick={() => setTipoComprobante(value)}
                                className={`rounded-md border px-3 py-2 text-sm transition ${
                                    tipoComprobante === value
                                        ? 'border-brand-500 bg-brand-50 text-brand-700 dark:bg-brand-900/30 dark:text-brand-300'
                                        : 'border-gray-200 text-gray-600 hover:border-brand-300 dark:border-gray-700 dark:text-gray-400'
                                }`}
                            >
                                {label}
                            </button>
                        ))}
                    </div>
                    {facturaSinRuc && (
                        <p className="mt-1 text-xs text-red-600">
                            La factura requiere un cliente con RUC seleccionado en el carrito.
                        </p>
                    )}
                </div>

                {/* Toggle mixto */}
                <label className="mt-4 flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                    <input
                        type="checkbox"
                        checked={mixto}
                        onChange={(e) => setMixto(e.target.checked)}
                        className="rounded border-gray-300 text-brand-600 focus:ring-brand-500"
                    />
                    Pago mixto (varios medios)
                </label>

                {!mixto ? (
                    /* ---------- MODO SIMPLE ---------- */
                    <div className="mt-4">
                        <InputLabel value="Medio de pago" />
                        <div className="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3">
                            {MEDIOS.map(({ value, label, icon: Icon }) => (
                                <button
                                    key={value}
                                    type="button"
                                    onClick={() => setMedioSimple(value)}
                                    className={`flex items-center gap-2 rounded-md border px-3 py-2 text-sm transition ${
                                        medioSimple === value
                                            ? 'border-brand-500 bg-brand-50 text-brand-700 dark:bg-brand-900/30 dark:text-brand-300'
                                            : 'border-gray-200 text-gray-600 hover:border-brand-300 dark:border-gray-700 dark:text-gray-400'
                                    }`}
                                >
                                    <Icon className="h-4 w-4" />
                                    {label}
                                </button>
                            ))}
                        </div>

                        {esEfectivoSimple && (
                            <div className="mt-4">
                                <InputLabel htmlFor="recibido" value="Efectivo recibido" />
                                <TextInput
                                    id="recibido"
                                    inputMode="decimal"
                                    pattern="[0-9]*[.]?[0-9]*"
                                    className="mt-1 block w-full"
                                    value={recibido}
                                    onChange={(e) => setRecibido(soloDecimalPositivo(e.target.value, 8, 2))}
                                    placeholder={totalNum.toFixed(2)}
                                    isFocused
                                />
                                <div className="mt-2 flex items-center justify-between text-sm">
                                    <span className="text-gray-500 dark:text-gray-400">Vuelto</span>
                                    <span className="font-bold text-emerald-600">
                                        {formatearMoneda(vueltoSimple)}
                                    </span>
                                </div>
                                {recibidoNum > 0 && recibidoNum < totalNum && (
                                    <p className="mt-1 text-xs text-red-600">
                                        El efectivo recibido no cubre el total.
                                    </p>
                                )}
                            </div>
                        )}
                    </div>
                ) : (
                    /* ---------- MODO MIXTO ---------- */
                    <div className="mt-4 space-y-2">
                        {MEDIOS.map(({ value, label, icon: Icon }) => (
                            <div key={value} className="flex items-center gap-2">
                                <span className="flex w-32 items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                                    <Icon className="h-4 w-4" />
                                    {label}
                                </span>
                                <TextInput
                                    inputMode="decimal"
                                    pattern="[0-9]*[.]?[0-9]*"
                                    className="block w-full text-sm"
                                    value={montosMixtos[value] ?? ''}
                                    onChange={(e) =>
                                        setMontosMixtos((prev) => ({
                                            ...prev,
                                            [value]: soloDecimalPositivo(e.target.value, 8, 2),
                                        }))
                                    }
                                    placeholder="0.00"
                                />
                            </div>
                        ))}
                        <div className="mt-2 flex items-center justify-between border-t border-gray-200 pt-2 text-sm dark:border-gray-700">
                            <span className="text-gray-500 dark:text-gray-400">
                                {faltaMixto > 0.001 ? 'Falta' : 'Cubierto'}
                            </span>
                            <span className={`font-bold ${faltaMixto > 0.001 ? 'text-red-600' : 'text-emerald-600'}`}>
                                {formatearMoneda(faltaMixto)}
                            </span>
                        </div>
                    </div>
                )}

                <div className="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" onClick={onClose} disabled={procesando}>
                        Cancelar
                    </SecondaryButton>
                    <PrimaryButton type="button" onClick={confirmar} disabled={!puedeConfirmar || facturaSinRuc}>
                        {procesando ? 'Registrando...' : 'Confirmar cobro'}
                    </PrimaryButton>
                </div>
            </div>
        </Modal>
    );
}
