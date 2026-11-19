<?php

namespace App\Console\Commands;

use App\Services\Images\ImageShrinker;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

/**
 * Brings images uploaded BEFORE {@see \App\Http\Middleware\ShrinkUploadedImages} existed under the same ~100 KB limit, through the
 * same {@see ImageShrinker} (no crop). Nothing is lost: every original is first copied to
 * storage/app/private/media-originals/<media id>/, and when the type changes (PNG → WebP) the old file is also LEFT at its old
 * path so any link already handed out keeps working. Re-runnable: a file already under the limit is skipped.
 */
class ShrinkStoredMedia extends Command
{
    protected $signature = 'media:shrink-stored
        {--dry : Only list what would change}
        {--model= : Only this model, e.g. Product or App\\Models\\Facility}
        {--collection= : Only this media collection, e.g. large_image}
        {--limit=0 : Stop after this many files (0 = all)}';

    protected $description = 'Shrink stored images over ~100 KB (originals backed up first)';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry');
        $limit = (int) $this->option('limit');

        $query = Media::query()->whereIn('mime_type', ['image/jpeg', 'image/png', 'image/webp'])->orderBy('id');

        if ($model = $this->option('model')) {
            $query->where('model_type', str_contains($model, '\\') ? $model : 'App\\Models\\'.$model);
        }

        if ($collection = $this->option('collection')) {
            $query->where('collection_name', $collection);
        }

        $done = $skipped = $failed = 0;
        $before = $after = 0;

        foreach ($query->lazyById(100) as $media) {
            if ($limit > 0 && $done >= $limit) {
                break;
            }

            $path = $media->getPath();

            if (! is_file($path) || filesize($path) <= ImageShrinker::LIMIT_BYTES) {
                $skipped++;

                continue;
            }

            $oldSize = filesize($path);

            if ($dry) {
                $this->line(sprintf('#%d %s %s %s — %d KB', $media->id, class_basename($media->model_type), $media->collection_name, $media->file_name, round($oldSize / 1024)));
                $done++;
                $before += $oldSize;

                continue;
            }

            try {
                $shrunk = ImageShrinker::shrink(new UploadedFile($path, $media->file_name, $media->mime_type, null, true));
                $newSize = (int) $shrunk->getSize();

                if ($shrunk->getRealPath() === realpath($path) || $newSize <= 0 || $newSize >= $oldSize) {
                    $skipped++;

                    continue;
                }

                $backupDir = storage_path('app/private/media-originals/'.$media->id);
                if (! is_dir($backupDir)) {
                    mkdir($backupDir, 0755, true);
                }
                if (! is_file($backupDir.'/'.$media->file_name) && ! copy($path, $backupDir.'/'.$media->file_name)) {
                    throw new \RuntimeException('Could not back up the original');
                }

                $newName = pathinfo($media->file_name, PATHINFO_FILENAME).'.'.$shrunk->getClientOriginalExtension();
                $target = dirname($path).'/'.$newName;

                if (! copy($shrunk->getRealPath(), $target)) {
                    throw new \RuntimeException('Could not write the shrunk file');
                }

                $media->setCustomProperty('shrunk_from', ['file_name' => $media->file_name, 'size' => $oldSize, 'at' => now()->toDateTimeString()]);
                $media->file_name = $newName;
                $media->mime_type = $shrunk->getMimeType();
                $media->size = $newSize;
                // Quietly: on a changed file_name the media library's own observer would MOVE the old file onto the new name,
                // overwriting the shrunk one we just wrote. The old file is meant to stay where it is (old links keep working).
                $media->saveQuietly();

                $done++;
                $before += $oldSize;
                $after += $newSize;
            } catch (Throwable $e) {
                $failed++;
                Log::error('media:shrink-stored failed', ['media_id' => $media->id, 'file' => $media->file_name, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
                $this->error("#{$media->id} {$media->file_name}: {$e->getMessage()}");
            }
        }

        $this->info(sprintf(
            '%s %d file(s), %d MB%s; skipped %d, failed %d.',
            $dry ? 'Would shrink' : 'Shrank',
            $done,
            round($before / 1048576),
            $dry ? '' : ' → '.round($after / 1048576, 1).' MB',
            $skipped,
            $failed,
        ));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
