<?php // tests/Unit/Support/HeroMediaTest.php

use App\Support\HeroMedia;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->mediaDirectory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'hero-media-'.uniqid();
    File::ensureDirectoryExists($this->mediaDirectory);
});

afterEach(function () {
    File::deleteDirectory($this->mediaDirectory);
});

function dropHeroFile(string $directory, string $name): void
{
    File::put($directory.DIRECTORY_SEPARATOR.$name, 'x');
}

it('finds no poster when nothing has been dropped in', function () {
    expect((new HeroMedia($this->mediaDirectory))->poster())->toBeNull();
});

it('prefers the most efficient format present', function () {
    dropHeroFile($this->mediaDirectory, 'hero.jpg');
    expect((new HeroMedia($this->mediaDirectory))->poster())->toContain('hero.jpg');

    dropHeroFile($this->mediaDirectory, 'hero.webp');
    expect((new HeroMedia($this->mediaDirectory))->poster())->toContain('hero.webp');

    dropHeroFile($this->mediaDirectory, 'hero.avif');
    expect((new HeroMedia($this->mediaDirectory))->poster())->toContain('hero.avif');
});

it('offers video sources in the order a browser should try them', function () {
    dropHeroFile($this->mediaDirectory, 'hero.jpg');
    dropHeroFile($this->mediaDirectory, 'hero.mp4');
    dropHeroFile($this->mediaDirectory, 'hero.webm');

    expect(array_column((new HeroMedia($this->mediaDirectory))->videoSources(), 'type'))
        ->toBe(['video/webm', 'video/mp4']);
});

it('withholds the video when there is no photograph behind it', function () {
    // Nothing to show while it buffers and nothing to fall back to when it is
    // gated out, so a loop on its own is not offered at all.
    dropHeroFile($this->mediaDirectory, 'hero.mp4');

    expect((new HeroMedia($this->mediaDirectory))->videoSources())->toBe([]);
});
