<?php // app/Support/HeroMedia.php

namespace App\Support;

use Illuminate\Support\Facades\File;

/**
 * Finds the homepage hero's photograph and, optionally, its video loop.
 *
 * Convention over configuration: drop the files into `public/media` under the
 * names below and they are used. There is no admin field and no migration
 * because there is exactly one hero, and a settings table for a single image
 * would be more moving parts than the thing it configures.
 *
 *   public/media/hero.avif|webp|jpg   the photograph — required
 *   public/media/hero.webm            the loop — optional
 *   public/media/hero.mp4             the loop, for browsers without the above
 *
 * With no photograph present the homepage keeps rendering the placeholder frame
 * that names the shot still owed, so the gap stays visible rather than becoming
 * a blank box.
 */
class HeroMedia
{
    /** Public sub-directory the files live in. */
    public const DIRECTORY = 'media';

    /** Most efficient format first — the first one present is the one used. */
    private const POSTERS = ['hero.avif', 'hero.webp', 'hero.jpg', 'hero.jpeg'];

    /** In `<source>` order, so a browser takes the first it can play. */
    private const VIDEOS = ['hero.webm' => 'video/webm', 'hero.mp4' => 'video/mp4'];

    /** Overridable so tests do not have to write into the real public directory. */
    public function __construct(private readonly ?string $directory = null) {}

    public function poster(): ?string
    {
        foreach (self::POSTERS as $file) {
            if ($this->exists($file)) {
                return $this->url($file);
            }
        }

        return null;
    }

    /** @return list<array{src: string, type: string}> */
    public function videoSources(): array
    {
        // A loop with no photograph behind it has nothing to show while it
        // buffers and nothing to fall back to when it is gated out, so it is
        // only ever offered alongside a poster.
        if ($this->poster() === null) {
            return [];
        }

        $sources = [];

        foreach (self::VIDEOS as $file => $type) {
            if ($this->exists($file)) {
                $sources[] = ['src' => $this->url($file), 'type' => $type];
            }
        }

        return $sources;
    }

    private function exists(string $file): bool
    {
        return File::exists(($this->directory ?? public_path(self::DIRECTORY)).DIRECTORY_SEPARATOR.$file);
    }

    private function url(string $file): string
    {
        return asset(self::DIRECTORY.'/'.$file);
    }
}
