<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

final class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $bancoConectado = $this->verificarConexaoComBanco();

        return response()->json([
            'status' => $bancoConectado ? 'ok' : 'degraded',
            'database' => $bancoConectado ? 'up' : 'down',
            'timestamp' => now()->toIso8601String(),
        ], $bancoConectado ? 200 : 503);
    }

    private function verificarConexaoComBanco(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}