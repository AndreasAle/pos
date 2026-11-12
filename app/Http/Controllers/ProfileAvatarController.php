<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Foto profil. File disimpan privat per bisnis, dibaca ulang dari piksel (bukan file asli) sehingga
 * metadata/EXIF dan muatan tersembunyi terbuang, lalu dilayani hanya untuk pengguna dari bisnis yang sama.
 */
class ProfileAvatarController extends Controller
{
    private const SIZE = 480;

    public function store(Request $request): JsonResponse
    {
        $request->validate(['photo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:4096']], [
            'photo.required' => 'Pilih foto terlebih dahulu.',
            'photo.mimes' => 'Format foto harus JPG, PNG, atau WebP.',
            'photo.max' => 'Ukuran foto maksimal 4 MB.',
        ]);
        if (! function_exists('imagecreatefromstring')) {
            return response()->json(['message' => 'Server belum mendukung pemrosesan foto.'], 503);
        }

        $img = @imagecreatefromstring((string) file_get_contents($request->file('photo')->getRealPath()));
        if (! $img) {
            return response()->json(['message' => 'File bukan gambar yang valid.'], 422);
        }
        [$w, $h] = [imagesx($img), imagesy($img)];
        if (min($w, $h) < 96) {
            return response()->json(['message' => 'Foto terlalu kecil. Minimal 96 × 96 piksel.'], 422);
        }
        // potong persegi dari tengah lalu kecilkan
        $side = min($w, $h);
        $square = imagecreatetruecolor(self::SIZE, self::SIZE);
        imagefill($square, 0, 0, imagecolorallocate($square, 255, 255, 255));
        imagecopyresampled($square, $img, 0, 0, intdiv($w - $side, 2), intdiv($h - $side, 2), self::SIZE, self::SIZE, $side, $side);
        ob_start();
        imagejpeg($square, null, 86);
        $jpeg = (string) ob_get_clean();

        $user = $request->user();
        $old = $user->avatar;
        $path = 'avatars/'.($user->business_id ?: 0).'/'.Str::uuid().'.jpg';
        Storage::disk('local')->put($path, $jpeg);
        $user->forceFill(['avatar' => $path])->save();
        if ($old && Storage::disk('local')->exists($old)) {
            Storage::disk('local')->delete($old);
        }

        return response()->json(['message' => 'Foto profil berhasil disimpan.', 'url' => $user->fresh()->avatarUrl()]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->avatar && Storage::disk('local')->exists($user->avatar)) {
            Storage::disk('local')->delete($user->avatar);
        }
        $user->forceFill(['avatar' => null])->save();

        return response()->json(['message' => 'Foto profil dihapus.', 'url' => $user->fresh()->avatarUrl()]);
    }

    public function show(Request $request, User $user)
    {
        $viewer = $request->user();
        abort_unless($viewer->id === $user->id || ($viewer->business_id && $viewer->business_id === $user->business_id), 403);
        abort_unless($user->avatar && Storage::disk('local')->exists($user->avatar), 404);

        return response()->file(Storage::disk('local')->path($user->avatar), ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, max-age=86400']);
    }
}
