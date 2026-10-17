<?php

use LBHurtado\XStamp\Catalog;
use LBHurtado\XStamp\Renderer;

it('validates and renders every immutable design in the distributed collection', function () {
    $catalog = new Catalog;
    $catalog->load(dirname(__DIR__));
    expect(array_map(fn ($design) => $design->id(), $catalog->all()))->toBe(['steampunk', 'cats', 'valentine']);
    foreach ($catalog->all() as $design) {
        expect($design->slots())->toContain('amount', 'recipient');
        $contents = (new Renderer)->png($design, ['amount' => '₱1,000.00']);
        expect(getimagesizefromstring($contents)[0])->toBe(1200);
    }
});
