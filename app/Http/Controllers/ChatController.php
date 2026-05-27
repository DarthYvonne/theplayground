<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessVideoJob;
use App\Models\AppNotification;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Message;
use App\Models\MessageRead;
use App\Models\Respekt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ChatController extends Controller
{
    public function platform(Request $request)
    {
        return view('chat.platform');
    }

    public function course(Request $request, Course $course)
    {
        $this->authorizeCourse($request, $course);
        return view('chat.course', compact('course'));
    }

    public function listPlatform(Request $request): JsonResponse
    {
        $messages = Message::with('user')->where('channel_type','platform')->orderByDesc('id')->limit(100)->get()->reverse()->values();
        $request->user()->forceFill(['last_seen_platform_chat_at' => now()])->save();
        return response()->json(['messages' => $this->serialize($messages, $request->user()->id)]);
    }

    public function listCourse(Request $request, Course $course): JsonResponse
    {
        $this->authorizeCourse($request, $course);
        $messages = Message::with('user')->where('channel_type','course')->where('course_id', $course->id)->orderByDesc('id')->limit(200)->get()->reverse()->values();
        MessageRead::updateOrCreate(
            ['user_id' => $request->user()->id, 'course_id' => $course->id],
            ['last_read_at' => now()]
        );
        return response()->json(['messages' => $this->serialize($messages, $request->user()->id)]);
    }

    public function sendPlatform(Request $request): JsonResponse
    {
        $data = $request->validate([
            'body' => ['nullable','string','max:2000'],
            'image_path' => ['nullable','string','max:255'],
            'video_path' => ['nullable','string','max:255'],
        ]);
        $body = trim($data['body'] ?? '');
        $imagePath = $this->resolveImagePath($data['image_path'] ?? null);
        $videoPath = $this->resolveVideoPath($data['video_path'] ?? null);
        if ($body === '' && !$imagePath && !$videoPath) {
            abort(422, 'Skriv noget eller vedhæft et billede eller en video.');
        }
        $m = Message::create([
            'channel_type' => 'platform',
            'user_id' => $request->user()->id,
            'body' => $body,
            'image_path' => $imagePath,
            'video_path' => $videoPath,
            'video_processing_status' => $videoPath ? 'pending' : null,
        ]);

        if ($videoPath) {
            ProcessVideoJob::dispatch($m->id, $videoPath);
        }

        return response()->json(['message' => $this->serializeOne($m->load('user'), $request->user()->id)]);
    }

    public function uploadImage(Request $request): JsonResponse
    {
        $file = $request->file('image');

        if ($file === null) {
            $postMax = $this->iniBytes(ini_get('post_max_size'));
            $contentLength = (int) $request->server('CONTENT_LENGTH');
            if ($postMax && $contentLength > $postMax) {
                $mb = round($postMax / (1024 * 1024));
                return response()->json(['message' => "Billedet er for stort. Maks {$mb} MB."], 413);
            }
            return response()->json(['message' => 'Intet billede modtaget.'], 422);
        }

        if (!$file->isValid()) {
            $err = $file->getError();
            $uploadMax = $this->iniBytes(ini_get('upload_max_filesize'));
            $mb = $uploadMax ? round($uploadMax / (1024 * 1024)) : null;
            $msg = match ($err) {
                UPLOAD_ERR_INI_SIZE => $mb ? "Billedet er for stort. Maks {$mb} MB." : 'Billedet er for stort.',
                UPLOAD_ERR_FORM_SIZE => 'Billedet er for stort.',
                UPLOAD_ERR_PARTIAL => 'Upload blev afbrudt. Prøv igen.',
                UPLOAD_ERR_NO_FILE => 'Intet billede valgt.',
                UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE => 'Serveren kunne ikke gemme billedet.',
                default => 'Billedet kunne ikke uploades.',
            };
            return response()->json(['message' => $msg], 422);
        }

        try {
            $request->validate([
                'image' => ['required','image','mimes:jpeg,jpg,png,gif,webp','max:8192'],
            ], [
                'image.required' => 'Vælg et billede.',
                'image.image' => 'Filen er ikke et billede.',
                'image.mimes' => 'Billedet skal være JPG, PNG, GIF eller WebP.',
                'image.max' => 'Billedet må højst være 8 MB.',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $first = collect($e->errors())->flatten()->first() ?? 'Billedet kunne ikke uploades.';
            return response()->json(['message' => $first], 422);
        }

        $name = Str::ulid() . '.' . strtolower($file->getClientOriginalExtension() ?: $file->extension());
        $path = $file->storeAs(now()->format('Y/m'), $name, 'feed_images');

        if (!$path) {
            return response()->json(['message' => 'Kunne ikke gemme billedet på serveren.'], 500);
        }

        return response()->json([
            'path' => $path,
            'url' => Storage::disk('feed_images')->url($path),
        ]);
    }

    private function iniBytes(?string $val): int
    {
        if (!$val) return 0;
        $val = trim($val);
        $unit = strtolower(substr($val, -1));
        $num = (int) $val;
        return match ($unit) {
            'g' => $num * 1024 * 1024 * 1024,
            'm' => $num * 1024 * 1024,
            'k' => $num * 1024,
            default => (int) $val,
        };
    }

    private function resolveImagePath(?string $path): ?string
    {
        if (!$path) return null;
        if (str_contains($path, '..')) return null;
        return Storage::disk('feed_images')->exists($path) ? $path : null;
    }

    private function resolveVideoPath(?string $path): ?string
    {
        if (!$path) return null;
        if (str_contains($path, '..')) return null;
        return Storage::disk('feed_videos')->exists($path) ? $path : null;
    }

    public function uploadVideo(Request $request): JsonResponse
    {
        $file = $request->file('video');

        if ($file === null) {
            $postMax = $this->iniBytes(ini_get('post_max_size'));
            $contentLength = (int) $request->server('CONTENT_LENGTH');
            if ($postMax && $contentLength > $postMax) {
                $mb = round($postMax / (1024 * 1024));
                return response()->json(['message' => "Videoen er for stor. Maks {$mb} MB."], 413);
            }
            return response()->json(['message' => 'Ingen video modtaget.'], 422);
        }

        if (!$file->isValid()) {
            $err = $file->getError();
            $uploadMax = $this->iniBytes(ini_get('upload_max_filesize'));
            $mb = $uploadMax ? round($uploadMax / (1024 * 1024)) : null;
            $msg = match ($err) {
                UPLOAD_ERR_INI_SIZE => $mb ? "Videoen er for stor. Maks {$mb} MB." : 'Videoen er for stor.',
                UPLOAD_ERR_FORM_SIZE => 'Videoen er for stor.',
                UPLOAD_ERR_PARTIAL => 'Upload blev afbrudt. Prøv igen.',
                UPLOAD_ERR_NO_FILE => 'Ingen video valgt.',
                UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE => 'Serveren kunne ikke gemme videoen.',
                default => 'Videoen kunne ikke uploades.',
            };
            return response()->json(['message' => $msg], 422);
        }

        try {
            $request->validate([
                'video' => ['required','file','mimes:mp4,mov,avi,webm,m4v,mkv','max:512000'],
            ], [
                'video.required' => 'Vælg en video.',
                'video.file' => 'Filen er ikke gyldig.',
                'video.mimes' => 'Videoen skal være MP4, MOV, AVI, WebM, M4V eller MKV.',
                'video.max' => 'Videoen må højst være 500 MB.',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $first = collect($e->errors())->flatten()->first() ?? 'Videoen kunne ikke uploades.';
            return response()->json(['message' => $first], 422);
        }

        $name = Str::ulid() . '.' . strtolower($file->getClientOriginalExtension() ?: $file->extension());
        $path = $file->storeAs(now()->format('Y/m'), $name, 'feed_videos');

        if (!$path) {
            return response()->json(['message' => 'Kunne ikke gemme videoen på serveren.'], 500);
        }

        return response()->json([
            'path' => $path,
            'url' => Storage::disk('feed_videos')->url($path),
        ]);
    }

    public function sendCourse(Request $request, Course $course): JsonResponse
    {
        $this->authorizeCourse($request, $course);
        $data = $request->validate(['body' => ['required','string','max:2000']]);
        $sender = $request->user();
        $m = Message::create([
            'channel_type' => 'course',
            'course_id' => $course->id,
            'user_id' => $sender->id,
            'body' => $data['body'],
        ]);
        $this->notifyHoldMembers($course, $sender, $m);
        return response()->json(['message' => $this->serializeOne($m->load('user'), $sender->id)]);
    }

    public function updateMessage(Request $request, Message $message): JsonResponse
    {
        abort_unless($message->user_id === $request->user()->id, 403);
        $data = $request->validate(['body' => ['required','string','max:2000']]);
        $message->update(['body' => $data['body']]);
        return response()->json([
            'ok' => true,
            'body' => $message->body,
            'time_human' => $message->created_at->diffForHumans(),
        ]);
    }

    public function destroyMessage(Request $request, Message $message): JsonResponse
    {
        abort_unless($message->user_id === $request->user()->id, 403);
        $targetType = $message->channel_type === 'course' ? 'course_message' : 'platform_message';
        Respekt::where('target_type', $targetType)->where('target_id', $message->id)->delete();
        if ($message->image_path) {
            Storage::disk('feed_images')->delete($message->image_path);
        }
        if ($message->video_path) {
            Storage::disk('feed_videos')->delete($message->video_path);
        }
        if ($message->original_video_path) {
            Storage::disk('feed_videos')->delete($message->original_video_path);
        }
        $message->delete();
        return response()->json(['ok' => true]);
    }

    private function notifyHoldMembers(Course $course, $sender, Message $message): void
    {
        $recipientIds = Enrollment::where('course_id', $course->id)
            ->where('status', 'active')
            ->pluck('user_id')
            ->merge($course->trainers()->pluck('users.id'))
            ->unique()
            ->reject(fn ($id) => $id === $sender->id)
            ->values();

        if ($recipientIds->isEmpty()) return;

        $title = $sender->name . ' skrev i ' . $course->title;
        $body = mb_substr($message->body, 0, 200);
        $link = route('chat.course', $course);
        $now = now();

        $rows = $recipientIds->map(fn ($uid) => [
            'user_id' => $uid,
            'type' => 'hold_message',
            'title' => $title,
            'body' => $body,
            'link' => $link,
            'course_id' => $course->id,
            'actor_id' => $sender->id,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        AppNotification::insert($rows);
    }

    private function authorizeCourse(Request $request, Course $course): void
    {
        $u = $request->user();
        $ok = $u->isOwner() || $course->hasTrainer($u) || $u->enrolledIn($course);
        abort_unless($ok, 403);
    }

    private function serialize($messages, int $viewerId): array
    {
        return $messages->map(fn ($m) => $this->serializeOne($m, $viewerId))->all();
    }

    private function serializeOne(Message $m, int $viewerId): array
    {
        return [
            'id' => $m->id,
            'body' => $m->body,
            'image_url' => $m->imageUrl(),
            'video_url' => $m->videoUrl(),
            'video_processing_status' => $m->video_processing_status,
            'created_at' => $m->created_at->toIso8601String(),
            'time_human' => $m->created_at->diffForHumans(),
            'mine' => $m->user_id === $viewerId,
            'user' => [
                'id' => $m->user->id,
                'name' => $m->user->name,
                'initials' => $m->user->initials(),
                'picture_url' => $m->user->pictureUrl(),
                'role' => $m->user->role,
            ],
        ];
    }
}
