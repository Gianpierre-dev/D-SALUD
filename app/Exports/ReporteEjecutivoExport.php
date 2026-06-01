<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Empresa;
use App\Services\EmpresaService;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithDrawings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Events\BeforeSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

/**
 * Layout base de los reportes ejecutivos del sistema.
 *
 * Reserva las filas 1-8 para cabecera institucional y a partir de la 9
 * coloca los datos. Las hojas hijas solo definen su query, headings y map.
 *
 *   1-3  Logo embebido (public/logo.png) + razón social, RUC y dirección
 *   5    Título del reporte
 *   6    Subtítulo opcional (ej. rango de fechas)
 *   7    Fecha y hora de emisión
 *   9    Encabezados (estilo brand)
 *   10+  Datos
 *
 * Centralizar este layout evita que cada export reescriba estilos.
 */
abstract class ReporteEjecutivoExport implements
    FromQuery,
    ShouldAutoSize,
    WithChunkReading,
    WithCustomStartCell,
    WithDrawings,
    WithEvents,
    WithHeadings,
    WithMapping,
    WithTitle
{
    /** Color brand (azul) en hexadecimal, sin '#'. */
    private const COLOR_BRAND = '2563EB';

    /** Color de borde para data rows. */
    private const COLOR_BORDE = 'E5E7EB';

    /** Color para el zebra striping. */
    private const COLOR_ZEBRA = 'F9FAFB';

    /** Fila donde empiezan los encabezados de la tabla. */
    protected const FILA_HEADINGS = 9;

    /** Título del reporte mostrado en la fila 5 y en la pestaña. */
    abstract protected function tituloReporte(): string;

    /**
     * Subtítulo opcional (ej. "Periodo: 01/05/2026 — 31/05/2026").
     * Devuelve null para omitir la fila.
     */
    protected function subtituloReporte(): ?string
    {
        return null;
    }

    /** Tamaño de chunk para WithChunkReading: 1000 mantiene memoria constante. */
    public function chunkSize(): int
    {
        return 1000;
    }

    public function startCell(): string
    {
        return 'A' . self::FILA_HEADINGS;
    }

    public function title(): string
    {
        // Excel limita los nombres de pestaña a 31 caracteres.
        return mb_substr($this->tituloReporte(), 0, 31);
    }

    /**
     * Inserta el logo (si existe) en la esquina superior izquierda.
     *
     * @return array<int, Drawing>
     */
    public function drawings(): array
    {
        $logoPath = public_path('logo.png');
        if (! file_exists($logoPath)) {
            return [];
        }

        $logo = new Drawing();
        $logo->setName('Logo D\'Salud');
        $logo->setDescription($this->empresa()->razon_social ?? "D'Salud");
        $logo->setPath($logoPath);
        $logo->setHeight(70);
        $logo->setCoordinates('A1');
        $logo->setOffsetX(4);
        $logo->setOffsetY(4);

        return [$logo];
    }

    /**
     * Cabecera institucional + encabezado del reporte (antes de la tabla)
     * y estilos de la tabla (después de escribir los datos).
     *
     * @return array<string, callable>
     */
    public function registerEvents(): array
    {
        return [
            BeforeSheet::class => function (BeforeSheet $event): void {
                $this->escribirCabecera($event->sheet->getDelegate());
            },
            AfterSheet::class => function (AfterSheet $event): void {
                $this->aplicarEstilosTabla($event->sheet->getDelegate());
            },
        ];
    }

    private function escribirCabecera(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): void
    {
        $empresa = $this->empresa();

        // Bloque empresa a la derecha del logo (D2-D4).
        $sheet->setCellValue('D2', $empresa->razon_social ?? "D'Salud S.A.C.");
        $sheet->getStyle('D2')->getFont()->setBold(true)->setSize(14);

        if ($empresa->ruc ?? null) {
            $sheet->setCellValue('D3', 'RUC: ' . $empresa->ruc);
            $sheet->getStyle('D3')->getFont()->setSize(10);
        }

        if ($empresa->direccion ?? null) {
            $sheet->setCellValue('D4', $empresa->direccion);
            $sheet->getStyle('D4')->getFont()->setSize(10);
        }

        // Altura mínima para que el logo se vea completo.
        $sheet->getRowDimension(1)->setRowHeight(20);
        $sheet->getRowDimension(2)->setRowHeight(20);
        $sheet->getRowDimension(3)->setRowHeight(20);

        // Título del reporte: A5 (luego mergeamos en AfterSheet).
        $sheet->setCellValue('A5', $this->tituloReporte());
        $sheet->getStyle('A5')
            ->getFont()
            ->setBold(true)
            ->setSize(16)
            ->getColor()->setRGB(self::COLOR_BRAND);

        if ($this->subtituloReporte() !== null) {
            $sheet->setCellValue('A6', $this->subtituloReporte());
            $sheet->getStyle('A6')->getFont()->setItalic(true)->setSize(10);
            $sheet->getStyle('A6')->getFont()->getColor()->setRGB('6B7280');
        }

        // Fecha de emisión.
        $sheet->setCellValue('A7', 'Generado el ' . now()->format('d/m/Y H:i:s'));
        $sheet->getStyle('A7')->getFont()->setItalic(true)->setSize(9);
        $sheet->getStyle('A7')->getFont()->getColor()->setRGB('9CA3AF');
    }

    private function aplicarEstilosTabla(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): void
    {
        $ultimaFila    = $sheet->getHighestRow();
        $ultimaColumna = $sheet->getHighestColumn();
        $filaHead      = self::FILA_HEADINGS;

        // Merge título y subtítulo a todo el ancho de la tabla.
        $sheet->mergeCells("A5:{$ultimaColumna}5");
        if ($this->subtituloReporte() !== null) {
            $sheet->mergeCells("A6:{$ultimaColumna}6");
        }
        $sheet->mergeCells("A7:{$ultimaColumna}7");
        $sheet->getStyle("A7")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        // Encabezados de tabla con fondo brand + texto blanco.
        $rangoHead = "A{$filaHead}:{$ultimaColumna}{$filaHead}";
        $sheet->getStyle($rangoHead)->applyFromArray([
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['rgb' => self::COLOR_BRAND],
            ],
            'font' => [
                'bold'  => true,
                'size'  => 11,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color'       => ['rgb' => '1E40AF'],
                ],
            ],
        ]);
        $sheet->getRowDimension($filaHead)->setRowHeight(24);

        // Filas de datos: bordes finos + zebra striping.
        $primerDato = $filaHead + 1;
        if ($ultimaFila >= $primerDato) {
            $rangoDatos = "A{$primerDato}:{$ultimaColumna}{$ultimaFila}";

            $sheet->getStyle($rangoDatos)->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color'       => ['rgb' => self::COLOR_BORDE],
                    ],
                ],
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ]);

            for ($fila = $primerDato; $fila <= $ultimaFila; $fila++) {
                if (($fila - $primerDato) % 2 === 1) {
                    $sheet->getStyle("A{$fila}:{$ultimaColumna}{$fila}")
                        ->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB(self::COLOR_ZEBRA);
                }
            }
        }
    }

    /**
     * Devuelve la configuración singleton de empresa.
     * Service locator acotado a la generación del reporte.
     */
    protected function empresa(): Empresa
    {
        return app(EmpresaService::class)->obtener();
    }
}
