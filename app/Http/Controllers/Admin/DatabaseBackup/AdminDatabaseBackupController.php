<?php

namespace App\Http\Controllers\Admin\DatabaseBackup;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class AdminDatabaseBackupController extends Controller
{
    private const DIRECTORY = 'backups';

    private const FILENAME_PATTERN = '/^backup_\d{8}_\d{6}\.sql\.gz$/';

    public function index(): InertiaResponse
    {
        $disk = Storage::disk('local');

        $backups = collect($disk->files(self::DIRECTORY))
            ->filter(fn (string $path) => preg_match(self::FILENAME_PATTERN, basename($path)))
            ->map(fn (string $path) => [
                'name' => basename($path),
                'size' => $disk->size($path),
                'created_at' => $disk->lastModified($path),
            ])
            ->sortByDesc('created_at')
            ->values();

        return Inertia::render('Admin/DatabaseBackup/Index', [
            'backups' => $backups,
        ]);
    }

    public function download(string $filename)
    {
        abort_unless(preg_match(self::FILENAME_PATTERN, $filename), 404);

        $disk = Storage::disk('local');
        $path = self::DIRECTORY.'/'.$filename;

        abort_unless($disk->exists($path), 404);

        return $disk->download($path);
    }
}
