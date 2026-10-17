<?php

use LBHurtado\XStamp\Catalog;
use LBHurtado\XStamp\Renderer;

it('validates and renders every immutable design in the distributed collection', function () {
    $catalog = new Catalog;
    $catalog->load(dirname(__DIR__));
    expect(array_map(fn ($design) => $design->id(), $catalog->all()))->toBe(['steampunk', 'steampunk', 'cats', 'valentine']);
    foreach ($catalog->all() as $design) {
        expect($design->slots())->toContain('amount', 'recipient');
        $contents = (new Renderer)->png($design, ['amount' => '₱1,000.00']);
        expect(getimagesizefromstring($contents)[0])->toBe(1200);
    }
});

it('adds a faint centered watermark while preserving the historical Stamp and financial text', function () {
    $catalog = new Catalog;
    $catalog->load(dirname(__DIR__));
    $original = $catalog->resolve('steampunk', 1);
    $watermarked = $catalog->resolve('steampunk', 2);
    $renderer = new Renderer;
    $slots = ['amount' => '₱1,000.00', 'recipient' => 'Amelia', 'expiry' => '3 Days', 'instructions' => 'Full Name'];
    $text = fn ($design) => array_values(array_filter($renderer->layout($design, $slots), fn (array $layer): bool => $layer['type'] === 'text'));
    expect($text($watermarked))->toBe($text($original));
    expect(hash_file('sha256', dirname(__DIR__).'/designs/steampunk/1/stamp.json'))->toBe('80dfc21d7f61b7383f874bde209bdebec6e16f307bce93879b0d3e4556010654');

    $before = imagecreatefromstring($renderer->png($original));
    $after = imagecreatefromstring($renderer->png($watermarked));
    $changed = 0;
    for ($y = 60; $y < 570; $y++) {
        for ($x = 345; $x < 855; $x++) {
            if (imagecolorat($before, $x, $y) !== imagecolorat($after, $x, $y)) {
                $changed++;
            }
        }
    }
    expect($changed)->toBeGreaterThan(1000);
    expect(imagecolorat($after, 50, 50))->toBe(imagecolorat($before, 50, 50));
    expect(imagecolorat($after, 1000, 550))->toBe(imagecolorat($before, 1000, 550));
    imagedestroy($before);
    imagedestroy($after);

    $artwork = imagecreatefrompng($watermarked->asset('assets/artwork/g-clef-watermark.png'));
    $minimumAlpha = 127;
    for ($y = 0; $y < imagesy($artwork); $y++) {
        for ($x = 0; $x < imagesx($artwork); $x++) {
            $alpha = imagecolorsforindex($artwork, imagecolorat($artwork, $x, $y))['alpha'];
            $minimumAlpha = min($minimumAlpha, $alpha);
        }
    }
    expect($minimumAlpha)->toBeGreaterThanOrEqual(110)->toBeLessThan(127);
    imagedestroy($artwork);
});
