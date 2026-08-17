<?php // tests/Unit/Support/PublicMediaTest.php

use App\Support\PublicMedia;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->mediaDirectory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'public-media-'.uniqid();
    File::ensureDirectoryExists($this->mediaDirectory);
});

afterEach(function () {
    File::deleteDirectory($this->mediaDirectory);
});

function dropMediaFile(string $directory, string $name): void
{
    File::put($directory.DIRECTORY_SEPARATOR.$name, 'x');
}

it('finds nothing when the name has no file behind it', function () {
    // Null rather than a URL to a missing file: this is what lets a section fall
    // back to the placeholder frame naming the shot still owed, instead of
    // rendering a broken image.
    expect((new PublicMedia($this->mediaDirectory))->image('bespoke'))->toBeNull();
});

it('prefers the most efficient format present', function () {
    dropMediaFile($this->mediaDirectory, 'bespoke.jpg');
    expect((new PublicMedia($this->mediaDirectory))->image('bespoke'))->toContain('bespoke.jpg');

    dropMediaFile($this->mediaDirectory, 'bespoke.webp');
    expect((new PublicMedia($this->mediaDirectory))->image('bespoke'))->toContain('bespoke.webp');

    dropMediaFile($this->mediaDirectory, 'bespoke.avif');
    expect((new PublicMedia($this->mediaDirectory))->image('bespoke'))->toContain('bespoke.avif');
});

it('keeps one name from picking up another name\'s file', function () {
    // Both live in the same directory under the same extensions, so a prefix
    // match here would put the hero photograph in the bespoke section.
    dropMediaFile($this->mediaDirectory, 'hero.jpg');

    expect((new PublicMedia($this->mediaDirectory))->image('bespoke'))->toBeNull();
    expect((new PublicMedia($this->mediaDirectory))->image('hero'))->toContain('hero.jpg');
});

it('ships the bespoke photograph the homepage expects', function () {
    // The section falls back silently to the placeholder frame, so a lost or
    // renamed file would otherwise only be noticed by looking at the page.
    expect((new PublicMedia)->image('bespoke'))->not->toBeNull();
});
