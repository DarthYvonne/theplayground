<?php

namespace App\Jobs;

use App\Models\Message;
use App\Services\VideoCompatibilityService;
use FFMpeg\Coordinate\Dimension;
use FFMpeg\Format\Video\X264;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
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

    protected int $messageId;
    protected string $videoPath;
    protected string $disk;

    public function __construct(int $messageId, string $videoPath, string $disk = 'feed_videos')
    {
        $this->messageId = $messageId;
        $this->videoPath = $videoPath;
        $this->disk = $disk;
    }

    public function handle(VideoCompatibilityService $compatibilityService): void
    {
        $message = Message::find($this->messageId);

        if (!$message) {
            Log::error('ProcessVideoJob: Message not found', ['id' => $this->messageId]);
            return;
        }

        $message->update(['video_processing_status' => 'processing']);

        try {
            $fullPath = Storage::disk($this->disk)->path($this->videoPath);

            Log::info('ProcessVideoJob: Starting video processing', [
                'message_id' => $this->messageId,
                'video_path' => $this->videoPath,
                'full_path' => $fullPath,
            ]);

            $compatibilityCheck = $compatibilityService->checkCompatibility($fullPath);

            Log::info('ProcessVideoJob: Compatibility check completed', [
                'compatible' => $compatibilityCheck['compatible'],
                'reason' => $compatibilityCheck['reason'],
            ]);

            if ($compatibilityCheck['compatible']) {
                $message->update(['video_processing_status' => 'skipped']);
                return;
            }

            $this->transcodeVideo($message, $fullPath, $compatibilityService);

        } catch (\Exception $e) {
            Log::error('ProcessVideoJob: Failed to process video', [
                'message_id' => $this->messageId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $message->update(['video_processing_status' => 'failed']);
            throw $e;
        }
    }

    protected function transcodeVideo(Message $message, string $inputPath, VideoCompatibilityService $compatibilityService): void
    {
        $settings = $compatibilityService->getRecommendedSettings();

        $pathInfo = pathinfo($this->videoPath);
        $outputFilename = $pathInfo['filename'] . '_processed.mp4';
        $outputPath = $pathInfo['dirname'] && $pathInfo['dirname'] !== '.' ? $pathInfo['dirname'] . '/' . $outputFilename : $outputFilename;

        Log::info('ProcessVideoJob: Starting transcoding', [
            'input' => $inputPath,
            'output' => $outputPath,
        ]);

        $format = new X264();
        $format->setKiloBitrate(1000)
               ->setAudioCodec($settings['audio_codec'])
               ->setAudioKiloBitrate(128);

        $media = FFMpeg::fromDisk($this->disk)->open($this->videoPath);

        $videoStream = $media->getStreams()->videos()->first();
        if ($videoStream) {
            $width = $videoStream->getDimensions()->getWidth();
            $height = $videoStream->getDimensions()->getHeight();

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

                Log::info('ProcessVideoJob: Resizing video', [
                    'original' => "{$width}x{$height}",
                    'new' => "{$newWidth}x{$newHeight}",
                ]);

                $media = $media->resize(new Dimension($newWidth, $newHeight));
            }
        }

        $media->addFilter([
            '-c:v', 'libx264',
            '-preset', $settings['preset'],
            '-crf', (string) $settings['crf'],
            '-profile:v', $settings['profile'],
            '-level', $settings['level'],
            '-pix_fmt', $settings['pix_fmt'],
            '-c:a', $settings['audio_codec'],
            '-b:a', $settings['audio_bitrate'],
            '-ar', (string) $settings['audio_sample_rate'],
            '-sample_fmt', 's16',
            '-movflags', $settings['movflags'],
        ]);

        $media->export()
            ->toDisk($this->disk)
            ->inFormat($format)
            ->save($outputPath);

        if (!Storage::disk($this->disk)->exists($outputPath)) {
            throw new \Exception('Processed video file was not created');
        }

        $message->update([
            'original_video_path' => $this->videoPath,
            'video_path' => $outputPath,
            'video_processing_status' => 'completed',
        ]);

        if ($this->videoPath !== $outputPath) {
            Storage::disk($this->disk)->delete($this->videoPath);
            Log::info('ProcessVideoJob: Deleted original video file', ['path' => $this->videoPath]);
        }

        Log::info('ProcessVideoJob: Video processing completed successfully');
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessVideoJob: Job failed permanently', [
            'message_id' => $this->messageId,
            'error' => $exception->getMessage(),
        ]);

        $message = Message::find($this->messageId);
        if ($message) {
            $message->update(['video_processing_status' => 'failed']);
        }
    }
}
