<?php

namespace App\Http\Controllers;

use App\Services\ServicioMediaProducto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaProxyController extends Controller
{
    public function __construct(
        private ServicioMediaProducto $mediaProducto,
    ) {}

    public function __invoke(Request $request): StreamedResponse|BinaryFileResponse|Response
    {
        $url = trim((string) $request->query('url'));
        if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
            abort(400, 'URL inválida');
        }

        if (! $this->hostPermitido($url)) {
            abort(403, 'URL de media no permitida');
        }

        $rutaLocal = $this->mediaProducto->rutaLocalDesdeUrlPublica($url);

        if ($rutaLocal !== null && Storage::disk('public')->exists($rutaLocal)) {
            $absolutePath = Storage::disk('public')->path($rutaLocal);
            $mime = mime_content_type($absolutePath) ?: 'application/octet-stream';

            return response()->file($absolutePath, [
                'Content-Type' => $mime,
                'Cache-Control' => 'private, max-age=86400',
            ]);
        }

        $response = Http::withHeaders([
            'ngrok-skip-browser-warning' => 'true',
            'User-Agent' => 'RomaAgent/1.0',
        ])->timeout(30)->get($url);

        if (! $response->successful()) {
            abort($response->status(), 'No se pudo obtener el archivo');
        }

        $contentType = $response->header('Content-Type') ?? 'application/octet-stream';

        return response($response->body(), 200, [
            'Content-Type' => $contentType,
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    private function hostPermitido(string $url): bool
    {
        $urlHost = parse_url($url, PHP_URL_HOST);

        if (! is_string($urlHost) || $urlHost === '') {
            return false;
        }

        $urlHost = strtolower($urlHost);

        foreach ($this->hostsPermitidos() as $host) {
            if ($host !== null && strtolower($host) === $urlHost) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string|null>
     */
    private function hostsPermitidos(): array
    {
        return array_values(array_unique(array_filter([
            parse_url((string) config('app.public_url', config('app.url')), PHP_URL_HOST),
            parse_url((string) config('app.url'), PHP_URL_HOST),
        ])));
    }
}
