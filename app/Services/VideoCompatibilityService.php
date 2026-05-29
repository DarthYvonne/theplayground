<?php

namespace App\Services;

use FFMpeg\FFProbe;
use Illuminate\Support\Facades\Log;

class VideoCompatibilityService
{
    public function checkCompatibility(string $videoPath): array
    {
        try {
            $ffprobe = FFProbe::create();

            $format = $ffprobe->format($videoPath);
            $streams = $ffprobe->streams($videoPath);

            $videoStream = $streams->videos()->first();
            $audioStream = $streams->audios()->first();

            $details = [
                'container' => $format->get('format_name'),
                'duration' => $format->get('duration'),
                'size' => $format->get('size'),
                'video_codec' => $videoStream ? $videoStream->get('codec_name') : null,
                'video_profile' => $videoStream ? $videoStream->get('profile') : null,
                'pixel_format' => $videoStream ? $videoStream->get('pix_fmt') : null,
                'width' => $videoStream ? $videoStream->get('width') : null,
                'height' => $videoStream ? $videoStream->get('height') : null,
                'audio_codec' => $audioStream ? $audioStream->get('codec_name') : null,
                'audio_channels' => $audioStream ? $audioStream->get('channels') : null,
                'audio_sample_fmt' => $audioStream ? $audioStream->get('sample_fmt') : null,
            ];

            $compatible = true;
            $reasons = [];

            $containerFormats = explode(',', $details['container']);
            if (!in_array('mov', $containerFormats) && !in_array('mp4', $containerFormats) && !in_array('m4a', $containerFormats) && !in_array('3gp', $containerFormats) && !in_array('3g2', $containerFormats) && !in_array('mj2', $containerFormats)) {
                $compatible = false;
                $reasons[] = "Container format '{$details['container']}' is not MP4/MOV";
            }

            if ($videoStream && $details['video_codec'] !== 'h264') {
                $compatible = false;
                $reasons[] = "Video codec '{$details['video_codec']}' is not H.264";
            }

            if ($videoStream && $details['pixel_format'] !== 'yuv420p') {
                $compatible = false;
                $reasons[] = "Pixel format '{$details['pixel_format']}' is not yuv420p (required for iPhone hardware decoding)";
            }

            if ($audioStream && !in_array($details['audio_codec'], ['aac', 'mp3'])) {
                $compatible = false;
                $reasons[] = "Audio codec '{$details['audio_codec']}' is not AAC or MP3";
            }

            // NOTE: We intentionally do NOT check audio_sample_fmt. ffprobe reports
            // 'fltp' for virtually all AAC streams — that's the codec's internal
            // sample format, not a playback concern; AAC plays fine on iPhone. The
            // native ffmpeg AAC encoder only ever outputs fltp anyway, so flagging
            // it as incompatible triggered a transcode that could never satisfy the
            // check (and failed outright, since we also forced an unsupported s16).
            // Genuinely problematic uncompressed PCM is already caught above by the
            // audio-codec check.

            if (!$videoStream) {
                $compatible = false;
                $reasons[] = "No video stream found";
            }

            return [
                'compatible' => $compatible,
                'details' => $details,
                'reason' => $compatible ? null : implode('; ', $reasons),
            ];

        } catch (\Exception $e) {
            Log::error('Video compatibility check failed', [
                'video_path' => $videoPath,
                'error' => $e->getMessage(),
            ]);

            return [
                'compatible' => false,
                'details' => [],
                'reason' => 'Failed to analyze video: ' . $e->getMessage(),
            ];
        }
    }

    public function getRecommendedSettings(): array
    {
        return [
            'video_codec' => 'libx264',
            'audio_codec' => 'aac',
            'preset' => 'medium',
            // Tuned for smooth playback on weak connections: 720p, ~2 Mbps with a
            // hard 2.5 Mbps ceiling, and faststart so playback can begin before
            // the whole file has downloaded.
            'crf' => 26,
            'maxrate' => '2500k',
            'bufsize' => '5000k',
            'profile' => 'high',
            'level' => '4.1',
            'pix_fmt' => 'yuv420p',
            'audio_bitrate' => '128k',
            'audio_sample_rate' => 48000,
            'movflags' => '+faststart',
            'max_short_edge' => 720,
            'max_long_edge' => 1280,
        ];
    }
}
