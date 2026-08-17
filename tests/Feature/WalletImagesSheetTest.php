<?php // tests/Feature/WalletImagesSheetTest.php

use Illuminate\Support\Facades\File;

beforeEach(function () {
    // The real folder is gitignored, so the sheet is exercised against a
    // temporary one seeded with a single known file.
    $this->sourceDirectory = base_path('walletImages');
    $this->createdDirectory = ! File::isDirectory($this->sourceDirectory);

    File::ensureDirectoryExists($this->sourceDirectory);

    $this->fixture = $this->sourceDirectory.DIRECTORY_SEPARATOR.'zz9.jpg';
    File::put($this->fixture, 'not-really-a-jpeg');
});

afterEach(function () {
    File::delete($this->fixture);

    if ($this->createdDirectory) {
        File::deleteDirectory($this->sourceDirectory);
    }
});

it('serves a source photograph by name', function () {
    // Regression: the closure took a lone $file argument while the URI declares
    // {locale} first, so it received "en" and every image 404'd.
    $this->get('/en/wallet-images/zz9.jpg')->assertOk();
});

it('refuses to read outside the photograph folder', function () {
    $this->get('/en/wallet-images/'.urlencode('../.env'))->assertNotFound();
    $this->get('/en/wallet-images/'.urlencode('../../.env'))->assertNotFound();
});

it('404s a name with no file behind it', function () {
    $this->get('/en/wallet-images/nothing-here.jpg')->assertNotFound();
});

it('lists the sheet', function () {
    $this->get('/en/wallet-images')->assertOk()->assertSee('zz9');
});
