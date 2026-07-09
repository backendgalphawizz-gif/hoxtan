<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;

class DeployArtisanController extends Controller
{
    /**
     * @var array<string, string>
     */
    private const COMMANDS = [
        'migrate' => 'migrate',
        'optimize-clear' => 'optimize:clear',
        'storage-link' => 'storage:link',
    ];

    public function run(string $command): JsonResponse
    {
        if (! isset(self::COMMANDS[$command])) {
            abort(404);
        }

        $options = match ($command) {
            'migrate', 'storage-link' => ['--force' => true],
            default => [],
        };

        $exitCode = Artisan::call(self::COMMANDS[$command], $options);

        return response()->json([
            'success' => $exitCode === 0,
            'command' => self::COMMANDS[$command],
            'exit_code' => $exitCode,
            'output' => trim(Artisan::output()),
        ]);
    }
}
