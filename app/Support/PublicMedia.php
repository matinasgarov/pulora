<?php // app/Support/PublicMedia.php

namespace App\Support;

use Illuminate\Support\Facades\File;

/**
 * Resolves a photograph dropped into `public/media` by name.
 *
 * Convention over configuration, the same bargain HeroMedia already struck:
 * there is one hero and one bespoke shot, and a settings table for two images
 * would be more moving parts than the thing it configures. Drop the file in
 * under the right name and the page picks it up.
 *
 * The variants are tried most-efficient first, so adding a `.webp` beside an
 * existing `.jpg` is all it takes to upgrade a section — nothing to register.
 * A name with no file behind it returns null rather than a broken `<img>`, which
 * is what lets each section fall back to the placeholder frame naming the shot
 * still owed.
 */
class PublicMedia
{
    /** Public sub-directory the files live in. */
    public const DIRECTORY = 'media';

    /** Most efficient format first — the first one present is the one used. */
    public const FORMATS = ['avif', 'webp', 'jpg', 'jpeg'];

    /** Overridable so tests do not have to write into the real public directory. */
    public function __construct(private readonly ?string $directory = null) {}

    /** The URL of `$name`'s best available variant, or null if there is none. */
    public function image(string $name): ?string
    {
        foreach (self::FORMATS as $format) {
            if ($this->exists($name.'.'.$format)) {
                return $this->url($name.'.'.$format);
            }
        }

        return null;
    }

    public function exists(string $file): bool
    {
        return File::exists($this->path().DIRECTORY_SEPARATOR.$file);
    }

    public function url(string $file): string
    {
        return asset(self::DIRECTORY.'/'.$file);
    }

    private function path(): string
    {
        return $this->directory ?? public_path(self::DIRECTORY);
    }
}
