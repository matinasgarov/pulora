<?php // tests/Feature/Storefront/ScrollRevealTest.php

use App\Domain\Catalog\Models\Product;

it('marks the sections that reveal on scroll', function () {
    Product::factory()->create(['is_active' => true]);

    $this->get('/az')->assertSee('data-reveal', false);
});

it('never hides content from a visitor without a script', function () {
    // The hidden state must stay scoped under `.js`, which the layout only adds
    // when a script runs. Unscope it and the whole page renders blank for
    // non-JS visitors — the one failure mode of a reveal worth a test.
    $css = file_get_contents(resource_path('css/app.css'));

    expect($css)->toContain('.js [data-reveal] {');
    expect($css)->not->toMatch('/^\[data-reveal\] \{/m');

    expect(file_get_contents(resource_path('views/components/layouts/storefront.blade.php')))
        ->toContain("classList.add('js')");
});
