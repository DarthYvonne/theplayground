<?php

namespace App\Jobs;

use App\Services\VideoCompatibilityService;
use FFMpeg\Coordinate\Dimension;
use FFMpeg\Coordinate\TimeCode;
use FFMpeg\Format\Video\X264;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;

class ProcessVideoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600;
    public $tries = 1;

    /** @param class-string<Model> $modelClass */
    public function __construct(
        protected string $modelClass,
        protected int $modelId,
        protected string $videoPath,
        protected string $disk = 'feed_videos',
        protected bool $generateThumbnail = false,
    ) {}

    public function handle(VideoCompatibilityService $compatibilityService): void
    {
        $model = $this->resolveModel();

        if (!$model) {
            Log::error('ProcessVideoJob: Model not found', [
                'class' => $this->modelClass,
                'id' => $this->modelId,
            ]);
            return;
        }

        $model->update(['video_processing_status' => 'processing']);

        try {
            $fullPath = Storage::disk($this->disk)->path($this->videoPath);

            Log::info('ProcessVideoJob: Starting', [
                'class' => $this->modelClass,
                'id' => $this->modelId,
                'path' => $this->videoPath,
            ]);

            $compatibilityCheck = $compatibilityService->checkCompatibility($fullPath);

            if ($compatibilityCheck['compatible']) {
                $model->update(['video_processing_status' => 'skipped']);
                $finalPath = $this->videoPath;
            } else {
                Log::info('ProcessVideoJob: transcoding', ['reason' => $compatibilityCheck['reason']]);
                $finalPath = $this->transcodeVideo($model, $compatibilityService);
            }

            if ($this->generateThumbnail) {
                $this->generateThumbnail($model, $finalPath);
            }

        } catch (\Exception $e) {
            Log::error('ProcessVideoJob: Failed', [
                'class' => $this->modelClass,
                'id' => $this->modelId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $model->update(['video_processing_status' => 'failed']);
            throw $e;
        }
    }

    protected function transcodeVideo(Model $model, VideoCompatibilityService $compatibilityService): string
    {
        $settings = $compatibilityService->getRecommendedSettings();

        $pathInfo = pathinfo($this->videoPath);
        $outputFilename = $pathInfo['filename'] . '_processed.mp4';
        $outputPath = $pathInfo['dirname'] && $pathInfo['dirname'] !== '.'
            ? $pathInfo['dirname'] . '/' . $outputFilename
            : $outputFilename;

        $format = new X264();
        $format->setAudioCodec($settings['audio_codec'])
               ->setAudioKiloBitrate(128)
               ->setAdditionalParameters([
                   '-preset', $settings['preset'],
                   '-crf', (string) $settings['crf'],
                   '-profile:v', $settings['profile'],
                   '-level', $settings['level'],
                   '-pix_fmt', $settings['pix_fmt'],
                   '-ar', (string) $settings['audio_sample_rate'],
                   '-sample_fmt', 's16',
                   '-movflags', $settings['movflags'],
               ]);

        $media = FFMpeg::fromDisk($this->disk)->open($this->videoPath);

        $videoStream = $media->getVideoStream();
        if ($videoStream) {
            $dimensions = $videoStream->getDimensions();
            $width = $dimensions->getWidth();
            $height = $dimensions->getHeight();

            if ($width > $settings['max_width'] || $height > $settings['max_height']) {
                $aspectRatio = $width / $height;
                if ($width > $height) {
                    $newWidth = min($width, $settings['max_width']);
                    $newHeight = (int) round($newWidth / $aspectRatio);
                } else {
                    $newHeight = min($height, $settings['max_height']);
                    $newWidth = (int) round($newHeight * $aspectRatio);
                }
                $newWidth = $newWidth % 2 === 0 ? $newWidth : $newWidth - 1;
                $newHeight = $newHeight % 2 === 0 ? $newHeight : $newHeight - 1;
                $media = $media->resize(new Dimension($newWidth, $newHeight));
            }
        }

        $media->export()
            ->toDisk($this->disk)
            ->inFormat($format)
            ->save($outputPath);

        if (!Storage::disk($this->disk)->exists($outputPath)) {
            throw new \Exception('Processed video file was not created');
        }

        $model->update([
            'original_video_path' => $this->videoPath,
            'video_path' => $outputPath,
            'video_processing_status' => 'completed',
        ]);

        if ($this->videoPath !== $outputPath) {
            Storage::disk($this->disk)->delete($this->videoPath);
        }

        return $outputPath;
    }

    /**
     * Extract a single frame (around the 1s mark) as a JPEG thumbnail.
     * Saved to the same disk so we don't need a second symlink/volume mount.
     */
    protected function generateThumbnail(Model $model, string $videoPath): void
    {
        if (!in_array('video_thumbnail_path', $model->getFillable(), true)) {
            return; // model doesn't support thumbnails
        }

        try {
            $pathInfo = pathinfo($videoPath);
            $thumbName = $pathInfo['filename'] . '_thumb.jpg';
            $thumbPath = $pathInfo['dirname'] && $pathInfo['dirname'] !== '.'
                ? $pathInfo['dirname'] . '/' . $thumbName
                : $thumbName;

            // Probe duration so we don't seek past the end on very short clips.
            $fullPath = Storage::disk($this->disk)->path($videoPath);
            $duration = 0.0;
            try {
                $probe = \FFMpeg\FFProbe::create()->format($fullPath);
                $duration = (float) ($probe->get('duration') ?? 0);
            } catch (\Throwable) {}

            $seekSeconds = $duration > 0 ? min(1.0, max(0.0, $duration * 0.1)) : 0.0;

            FFMpeg::fromDisk($this->disk)
                ->open($videoPath)
                ->getFrameFromSeconds($seekSeconds)
                ->export()
                ->toDisk($this->disk)
                ->save($thumbPath);

            if (Storage::disk($this->disk)->exists($thumbPath)) {
                $model->update(['video_thumbnail_path' => $thumbPath]);
                Log::info('ProcessVideoJob: thumbnail saved', ['path' => $thumbPath]);
            }
        } catch (\Throwable $e) {
            // Thumbnail failures shouldn't fail the whole job — the video itself is fine.
            Log::warning('ProcessVideoJob: thumbnail generation failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function resolveModel(): ?Model
    {
        if (!class_exists($this->modelClass)) return null;
        $instance = app($this->modelClass);
        return $instance instanceof Model ? $instance->find($this->modelId) : null;
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessVideoJob: failed permanently', [
            'class' => $this->modelClass,
            'id' => $this->modelId,
            'error' => $exception->getMessage(),
        ]);

        $model = $this->resolveModel();
        if ($model) {
            $model->update(['video_processing_status' => 'failed']);
        }
    }
}
