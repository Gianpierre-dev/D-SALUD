import { useState } from 'react';
import { Head } from '@inertiajs/react';
import { IconDownload } from '@tabler/icons-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import PrimaryButton from '@/Components/PrimaryButton';
import ReporteCard from './Partials/ReporteCard';

// ─── Helpers ─────────────────────────────────────────────────────────────────

/** Hoy en formato YYYY-MM-DD para el valor por defecto de los date inputs. */
function hoy() {
    return new Date().toISOString().slice(0, 10);
}

/** Primer día del mes actual en YYYY-MM-DD. */
function primerDiaMes() {
    const d = new Date();
    d.setDate(1);
    return d.toISOString().slice(0, 10);
}

/**
 * Construye la URL de descarga usando Ziggy's route() con los parámetros
 * como query-string. La descarga la maneja el navegador directamente
 * (no Inertia), por lo que usamos window.location.href.
 */
function descargar(nombreRuta, params = {}) {
    window.location.href = route(nombreRuta, params);
}

// ─── Sub-formularios ──────────────────────────────────────────────────────────

function FormRangoFechas({ nombreRuta, archivoLabel }) {
    const [desde, setDesde] = useState(primerDiaMes());
    const [hasta, setHasta] = useState(hoy());
    const [error, setError] = useState('');

    const handleDescargar = () => {
        if (!desde || !hasta) {
            setError('Selecciona ambas fechas.');
            return;
        }
        if (hasta < desde) {
            setError('La fecha "Hasta" debe ser igual o posterior a "Desde".');
            return;
        }
        setError('');
        descargar(nombreRuta, { fecha_inicio: desde, fecha_fin: hasta });
    };

    return (
        <div className="space-y-3">
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                    <InputLabel value="Desde" className="mb-1 text-xs" />
                    <TextInput
                        type="date"
                        value={desde}
                        onChange={(e) => setDesde(e.target.value)}
                        className="w-full text-sm"
                    />
                </div>
                <div>
                    <InputLabel value="Hasta" className="mb-1 text-xs" />
                    <TextInput
                        type="date"
                        value={hasta}
                        onChange={(e) => setHasta(e.target.value)}
                        className="w-full text-sm"
                    />
                </div>
            </div>

            {error && (
                <p className="text-xs text-red-600 dark:text-red-400">{error}</p>
            )}

            <PrimaryButton
                type="button"
                onClick={handleDescargar}
                className="w-full justify-center"
            >
                <IconDownload className="me-1.5 h-4 w-4" />
                Descargar Excel
            </PrimaryButton>
        </div>
    );
}

function FormRangoFechasOpcional({ nombreRuta }) {
    const [desde, setDesde] = useState('');
    const [hasta, setHasta] = useState('');
    const [error, setError] = useState('');

    const handleDescargar = () => {
        if (desde && hasta && hasta < desde) {
            setError('La fecha "Hasta" debe ser igual o posterior a "Desde".');
            return;
        }
        setError('');
        const params = {};
        if (desde) params.fecha_inicio = desde;
        if (hasta) params.fecha_fin = hasta;
        descargar(nombreRuta, params);
    };

    return (
        <div className="space-y-3">
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                    <InputLabel value="Desde (opcional)" className="mb-1 text-xs" />
                    <TextInput
                        type="date"
                        value={desde}
                        onChange={(e) => setDesde(e.target.value)}
                        className="w-full text-sm"
                    />
                </div>
                <div>
                    <InputLabel value="Hasta (opcional)" className="mb-1 text-xs" />
                    <TextInput
                        type="date"
                        value={hasta}
                        onChange={(e) => setHasta(e.target.value)}
                        className="w-full text-sm"
                    />
                </div>
            </div>

            {error && (
                <p className="text-xs text-red-600 dark:text-red-400">{error}</p>
            )}

            <PrimaryButton
                type="button"
                onClick={handleDescargar}
                className="w-full justify-center"
            >
                <IconDownload className="me-1.5 h-4 w-4" />
                Descargar Excel
            </PrimaryButton>
        </div>
    );
}

function BotonDescargaDirecta({ nombreRuta, label = 'Descargar Excel' }) {
    return (
        <PrimaryButton
            type="button"
            onClick={() => descargar(nombreRuta)}
            className="w-full justify-center"
        >
            <IconDownload className="me-1.5 h-4 w-4" />
            {label}
        </PrimaryButton>
    );
}

// ─── Página principal ─────────────────────────────────────────────────────────

export default function Index() {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold text-gray-800 dark:text-gray-200">
                    Reportes
                </h2>
            }
        >
            <Head title="Reportes" />

            <div className="mx-auto max-w-5xl">
                <p className="mb-6 text-sm text-gray-600 dark:text-gray-400">
                    Selecciona el reporte que deseas exportar. Los archivos se
                    descargan en formato Excel (.xlsx).
                </p>

                <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    {/* Ventas por período */}
                    <ReporteCard
                        titulo="Ventas por período"
                        descripcion="Lista de ventas completadas con boleta y vendedor en el rango indicado."
                    >
                        <FormRangoFechas nombreRuta="reportes.ventasPorPeriodo" />
                    </ReporteCard>

                    {/* Productos más vendidos */}
                    <ReporteCard
                        titulo="Productos más vendidos"
                        descripcion="Ranking de productos por cantidad vendida y total recaudado en el rango indicado."
                    >
                        <FormRangoFechas nombreRuta="reportes.productosMasVendidos" />
                    </ReporteCard>

                    {/* Productos por vencer */}
                    <ReporteCard
                        titulo="Productos próximos a vencer"
                        descripcion="Lotes con stock disponible cuya fecha de vencimiento está dentro del umbral configurado."
                    >
                        <BotonDescargaDirecta nombreRuta="reportes.productosPorVencer" />
                    </ReporteCard>

                    {/* Stock bajo */}
                    <ReporteCard
                        titulo="Productos con stock bajo"
                        descripcion="Productos activos cuyo stock total es igual o menor al mínimo configurado."
                    >
                        <BotonDescargaDirecta nombreRuta="reportes.lotesStockBajo" />
                    </ReporteCard>

                    {/* Auditoría */}
                    <ReporteCard
                        titulo="Registro de auditoría"
                        descripcion="Historial de acciones de los usuarios en el sistema. Filtro de fechas opcional."
                    >
                        <FormRangoFechasOpcional nombreRuta="reportes.auditoria" />
                    </ReporteCard>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
