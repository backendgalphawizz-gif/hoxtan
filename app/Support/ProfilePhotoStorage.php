<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProfilePhotoStorage
{
    /** @var list<string> */
    public const FILE_FIELDS = [
        'image',
        'profile_photo',
        'profile_image',
        'avatar',
        'photo',
    ];

    /** @var list<string> */
    public const BASE64_FIELDS = [
        'image',
        'image_base64',
        'profile_photo',
        'profile_photo_base64',
        'profile_image_base64',
        'avatar_base64',
        'photo_base64',
    ];

    public static function storeForUser(User $user, Request $request): ?string
    {
        $uploaded = static::resolveUploadedFile($request);

        if ($uploaded !== null) {
            validator(
                ['file' => $uploaded],
                ['file' => ['required', 'image', 'max:2048']],
                [
                    'file.image' => 'Profile photo must be a valid image file.',
                    'file.max' => 'Profile photo must not be larger than 2MB.',
                ]
            )->validate();

            return static::storeUploadedFile($user, $uploaded);
        }

        $base64 = static::resolveBase64Payload($request);

        if ($base64 !== null) {
            return static::storeBase64Image($user, $base64);
        }

        return null;
    }

    public static function resolveUploadedFile(Request $request): ?UploadedFile
    {
        foreach (static::FILE_FIELDS as $field) {
            if ($request->hasFile($field)) {
                return $request->file($field);
            }
        }

        if ($request->isMethod('PUT') || $request->isMethod('PATCH')) {
            foreach (static::FILE_FIELDS as $field) {
                $file = static::fileFromPutMultipart($request, $field);

                if ($file !== null) {
                    return $file;
                }
            }
        }

        return null;
    }

    public static function resolveBase64Payload(Request $request): ?string
    {
        foreach (static::BASE64_FIELDS as $field) {
            $value = $request->input($field);

            if (is_string($value) && filled(trim($value))) {
                return trim($value);
            }
        }

        foreach (static::FILE_FIELDS as $field) {
            if ($request->hasFile($field)) {
                continue;
            }

            $value = $request->input($field);

            if (is_string($value) && static::looksLikeBase64Image($value)) {
                return trim($value);
            }
        }

        return null;
    }

    public static function storeUploadedFile(User $user, UploadedFile $photo): string
    {
        static::deleteExistingPhoto($user);

        return $photo->store('profile-photos', 'public');
    }

    public static function storeBase64Image(User $user, string $payload): string
    {
        [$binary, $extension] = static::decodeBase64Image($payload);

        static::deleteExistingPhoto($user);

        $path = 'profile-photos/'.Str::uuid().'.'.$extension;
        Storage::disk('public')->put($path, $binary);

        return $path;
    }

    /**
     * PHP does not populate uploaded files for PUT/PATCH requests.
     */
    public static function fileFromPutMultipart(Request $request, string $fieldName): ?UploadedFile
    {
        $contentType = (string) $request->header('Content-Type', '');

        if (! str_contains(strtolower($contentType), 'multipart/form-data')) {
            return null;
        }

        if (! preg_match('/boundary=(?:"([^"]+)"|([^;]+))/i', $contentType, $matches)) {
            return null;
        }

        $boundary = $matches[1] !== '' ? $matches[1] : $matches[2];
        $body = $request->getContent();

        if ($body === '') {
            return null;
        }

        foreach (explode('--'.$boundary, $body) as $part) {
            $part = ltrim($part, "\r\n");

            if ($part === '' || $part === '--') {
                continue;
            }

            if (! preg_match('/name="'.preg_quote($fieldName, '/').'"/', $part)) {
                continue;
            }

            if (! preg_match('/filename="([^"]*)"/', $part, $filenameMatch)) {
                continue;
            }

            $segments = preg_split("/\r\n\r\n|\n\n/", $part, 2);

            if (! is_array($segments) || count($segments) < 2) {
                continue;
            }

            $fileContents = rtrim($segments[1], "\r\n--");

            if ($fileContents === '') {
                continue;
            }

            $tmpPath = tempnam(sys_get_temp_dir(), 'profile_photo_');

            if ($tmpPath === false) {
                return null;
            }

            file_put_contents($tmpPath, $fileContents);

            $mimeType = null;

            if (preg_match('/Content-Type:\s*([^\r\n]+)/i', $part, $mimeMatch)) {
                $mimeType = trim($mimeMatch[1]);
            }

            return new UploadedFile(
                $tmpPath,
                $filenameMatch[1] !== '' ? $filenameMatch[1] : $fieldName.'.jpg',
                $mimeType,
                UPLOAD_ERR_OK,
                true,
            );
        }

        return null;
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected static function decodeBase64Image(string $payload): array
    {
        $extension = 'jpg';

        if (preg_match('/^data:image\/(\w+);base64,/i', $payload, $matches)) {
            $extension = static::normalizeImageExtension($matches[1]);
            $payload = substr($payload, strpos($payload, ',') + 1);
        }

        $binary = base64_decode(str_replace(["\r", "\n", ' '], '', $payload), true);

        if ($binary === false || $binary === '') {
            throw ValidationException::withMessages([
                'image' => ['Invalid profile photo data. Upload an image file or send a valid base64 image.'],
            ]);
        }

        if (@getimagesizefromstring($binary) === false) {
            throw ValidationException::withMessages([
                'image' => ['Profile photo must be a valid image file.'],
            ]);
        }

        return [$binary, $extension];
    }

    protected static function looksLikeBase64Image(string $value): bool
    {
        if (str_starts_with($value, 'data:image/')) {
            return true;
        }

        if (strlen($value) < 100) {
            return false;
        }

        return preg_match('/^[A-Za-z0-9+\/=\r\n]+$/', $value) === 1;
    }

    protected static function normalizeImageExtension(string $extension): string
    {
        $extension = strtolower($extension);

        return match ($extension) {
            'jpeg' => 'jpg',
            'svg+xml' => 'svg',
            default => $extension,
        };
    }

    protected static function deleteExistingPhoto(User $user): void
    {
        if (blank($user->profile_photo)) {
            return;
        }

        Storage::disk('public')->delete($user->profile_photo);
    }
}
