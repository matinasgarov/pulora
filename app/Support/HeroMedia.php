<?php // app/Support/HeroMedia.php

namespace App\Support;

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
 *
 * The still is resolved by PublicMedia, which the bespoke section uses too; only
 * the video loop is the hero's own, since nothing else on the site has one.
 */
class HeroMedia
{
    /** Public sub-directory the files live in. */
    public const DIRECTORY = PublicMedia::DIRECTORY;

    /** In `<source>` order, so a browser takes the first it can play. */
    private const VIDEOS = ['hero.webm' => 'video/webm', 'hero.mp4' => 'video/mp4'];

    private readonly PublicMedia $media;

    /** Overridable so tests do not have to write into the real public directory. */
    public function __construct(?string $directory = null)
    {
        $this->media = new PublicMedia($directory);
    }

    public function poster(): ?string
    {
        return $this->media->image('hero');
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
            if ($this->media->exists($file)) {
                $sources[] = ['src' => $this->media->url($file), 'type' => $type];
            }
        }

        return $sources;
    }
}
