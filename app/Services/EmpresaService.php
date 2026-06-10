<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Empresa;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Lógica de negocio de la configuración de empresa (singleton, id=1).
 * El controlador se mantiene delgado; la auditoría se centraliza aquí.
 */
class EmpresaService
{
    private const CACHE_KEY = 'empresa.config';
    private const DISCO_LOGO = 'public';
    private const DIR_LOGO = 'empresa';

    public function __construct(private readonly AuditoriaService $auditoria)
    {
    }

    /**
     * Obtiene la única fila de empresa, cacheada hasta que se actualice.
     * Si no existe lanza ModelNotFoundException (falla visible en log).
     */
    public function obtener(): Empresa
    {
        return Cache::rememberForever(
            self::CACHE_KEY,
            fn (): Empresa => Empresa::firstOrFail(),
        );
    }

    /**
     * Actualiza los datos de la empresa, invalida la cache y registra auditoría.
     * Si se recibe un logo, lo guarda en el disco público y reemplaza al anterior.
     *
     * @param  array<string, mixed>  $datos
     */
    public function actualizar(array $datos, ?UploadedFile $logo = null): Empresa
    {
        $empresa = Empresa::firstOrFail();

        if ($logo !== null) {
            // Borrar el logo anterior si existía, para no acumular archivos.
            if ($empresa->logo && Storage::disk(self::DISCO_LOGO)->exists($empresa->logo)) {
                Storage::disk(self::DISCO_LOGO)->delete($empresa->logo);
            }

            $datos['logo'] = $logo->store(self::DIR_LOGO, self::DISCO_LOGO);
        }

        $empresa->update($datos);

        Cache::forget(self::CACHE_KEY);

        $this->auditoria->registrar('empresa', 'actualizar', "Empresa #{$empresa->id}: {$empresa->razon_social}");

        return $empresa;
    }

    /**
     * Ruta de filesystem del logo para embeberlo en PDFs/Excel.
     * Usa el logo subido por la empresa si existe; si no, el logo por defecto.
     */
    public function rutaLogo(): string
    {
        $empresa = $this->obtener();

        if ($empresa->logo && Storage::disk(self::DISCO_LOGO)->exists($empresa->logo)) {
            return Storage::disk(self::DISCO_LOGO)->path($empresa->logo);
        }

        return public_path('logo.png');
    }

    /**
     * URL pública del logo para previsualizarlo en la UI.
     */
    public function urlLogo(): string
    {
        $empresa = $this->obtener();

        if ($empresa->logo && Storage::disk(self::DISCO_LOGO)->exists($empresa->logo)) {
            return Storage::disk(self::DISCO_LOGO)->url($empresa->logo);
        }

        return asset('logo.png');
    }
}
