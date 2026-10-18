<?php

use LBHurtado\XStamp\Appearance;
use LBHurtado\XStamp\Catalog;
use LBHurtado\XStamp\Renderer;

it('validates and renders every immutable design in the distributed collection', function () {
    $catalog = new Catalog;
    $catalog->load(dirname(__DIR__));
    expect(array_count_values(array_map(fn ($design) => $design->id(), $catalog->all())))->toBe(['steampunk' => 8, 'cats' => 7, 'valentine' => 1, 'constellation' => 1]);
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

it('renders an illustrated pipe background with a solid logo while preserving previous revisions and financial text', function () {
    $catalog = new Catalog;
    $catalog->load(dirname(__DIR__));
    $renderer = new Renderer;
    $slots = ['amount' => '₱1,000.00', 'recipient' => 'Amelia', 'expiry' => '3 Days', 'instructions' => 'Full Name'];
    $text = fn ($design) => array_column(array_filter($renderer->layout($design, $slots), fn (array $layer): bool => $layer['type'] === 'text'), 'text');
    $design = $catalog->resolve('steampunk', 3);
    expect($text($design))->toBe($text($catalog->resolve('steampunk', 1)));
    expect(hash_file('sha256', dirname(__DIR__).'/designs/steampunk/2/stamp.json'))->toBe('7ca207fc46550cea24960599ea2ab1cdf2a3a66cf00ef1b7abac752f2555d838');
    $image = imagecreatefromstring($renderer->png($design, $slots));
    expect(imagesx($image))->toBe(1200);
    expect(imagesy($image))->toBe(630);
    $plaque = imagecolorsforindex($image, imagecolorat($image, 970, 70));
    expect([$plaque['red'], $plaque['green'], $plaque['blue']])->toBe([16, 23, 25]);
    $opaqueLogoPixels = 0;
    for ($y = 60; $y < 310; $y++) {
        for ($x = 1000; $x < 1100; $x++) {
            $color = imagecolorsforindex($image, imagecolorat($image, $x, $y));
            if ($color['red'] > 220 && $color['green'] > 180 && $color['blue'] > 110) {
                $opaqueLogoPixels++;
            }
        }
    }
    expect($opaqueLogoPixels)->toBeGreaterThan(500);
    imagedestroy($image);
});

it('keeps the G-clef logo over the pipe artwork without its previous plaque', function () {
    $catalog = new Catalog;
    $catalog->load(dirname(__DIR__));
    $renderer = new Renderer;
    $before = imagecreatefromstring($renderer->png($catalog->resolve('steampunk', 3)));
    $after = imagecreatefromstring($renderer->png($catalog->resolve('steampunk', 4)));
    expect(imagecolorat($after, 970, 70))->not->toBe(imagecolorat($before, 970, 70));
    expect(imagecolorat($after, 500, 300))->toBe(imagecolorat($before, 500, 300));
    $logoPixels = 0;
    for ($y = 60; $y < 310; $y++) {
        for ($x = 1000; $x < 1100; $x++) {
            $color = imagecolorsforindex($after, imagecolorat($after, $x, $y));
            if ($color['red'] === 230 && $color['green'] === 190 && $color['blue'] === 128) {
                $logoPixels++;
            }
        }
    }
    expect($logoPixels)->toBeGreaterThan(500);
    imagedestroy($before);
    imagedestroy($after);
});

it('uses the supplied Cats photograph with the Steampunk text and palette while preserving Cats revision 1', function () {
    $catalog = new Catalog;
    $catalog->load(dirname(__DIR__));
    $design = $catalog->resolve('cats', 2);
    $steampunk = $catalog->resolve('steampunk', 4);
    expect(hash_file('sha256', $design->asset('assets/artwork/cats-starbucks-v2.jpg')))->toBe('fe10c834e4b2851f4ebe1e9c6cdb0970e9a2a159231ab076ee3c13e58309dbfb');
    expect(hash_file('sha256', dirname(__DIR__).'/designs/cats/1/stamp.json'))->toBe('7ec6d4b59a45716c43b82e28d0814cf9640468f6f2f5b55dd12341508058b53f');
    expect($design->document['background'])->toBe($steampunk->document['background']);
    expect($design->document['foreground'])->toBe($steampunk->document['foreground']);
    $renderer = new Renderer;
    $slots = ['amount' => '₱1,000.00', 'purpose' => "Money should adapt to people.\nNot the other way around."];
    $text = fn ($stamp) => array_values(array_filter($renderer->layout($stamp, $slots), fn (array $layer): bool => $layer['type'] === 'text'));
    expect($text($design))->toBe($text($steampunk));
    $image = imagecreatefromstring($renderer->png($design, $slots));
    expect(imagesx($image))->toBe(1200);
    expect(imagesy($image))->toBe(630);
    imagedestroy($image);
});

it('keeps every Cats revision 3 foreground element identical to Steampunk revision 4', function () {
    $catalog = new Catalog;
    $catalog->load(dirname(__DIR__));
    $renderer = new Renderer;
    $slots = ['brand' => 'Pay Code', 'amount' => '₱1,000.00', 'recipient' => 'For Amelia', 'purpose' => "Money should adapt to people.\nNot the other way around.", 'expiry' => '3 Days', 'instructions' => 'Receive Funds · Full Name'];
    $cats = $renderer->layout($catalog->resolve('cats', 3), $slots);
    $steampunk = $renderer->layout($catalog->resolve('steampunk', 4), $slots);
    expect(array_slice($cats, 1))->toBe(array_slice($steampunk, 1));
    expect($cats[0]['asset'])->toBe('assets/artwork/cats-starbucks-v2.jpg');
    expect($cats[0]['fit'])->toBe('cover');
});

it('removes only the decorative G-clef from the latest Cats and Steampunk revisions', function (string $id, int $oldRevision, int $newRevision) {
    $catalog = new Catalog;
    $catalog->load(dirname(__DIR__));
    $renderer = new Renderer;
    $slots = ['brand' => 'Pay Code', 'amount' => '₱1,000.00', 'purpose' => "Money should adapt to people.\nNot the other way around."];
    $before = $renderer->layout($catalog->resolve($id, $oldRevision), $slots);
    $expected = array_values(array_filter($before, fn (array $layer): bool => ! str_contains($layer['asset'] ?? '', 'g-clef')));
    expect($renderer->layout($catalog->resolve($id, $newRevision), $slots))->toBe($expected);
})->with([['steampunk', 4, 5], ['cats', 3, 4]]);

it('omits decorative G-clef branding from all default designs except explicitly retained historical revisions', function () {
    $catalog = new Catalog;
    $catalog->load(dirname(__DIR__));
    $historical = ['steampunk@2', 'steampunk@3', 'steampunk@4', 'cats@2', 'cats@3'];
    foreach ($catalog->all() as $design) {
        if (in_array($design->id().'@'.$design->revision(), $historical, true)) {
            continue;
        }
        $layers = (new Renderer)->layout($design);
        $marks = array_filter($layers, fn (array $layer): bool => str_contains($layer['asset'] ?? '', 'g-clef'));
        expect($marks)->toBeEmpty();
    }
});

it('reserves portable provider identity without bundling provider branding or moving financial content', function (string $id, int $previous, int $revision) {
    $catalog = new Catalog;
    $catalog->load(dirname(__DIR__));
    $design = $catalog->resolve($id, $revision);
    expect($design->slots())->toContain('provider_logo', 'provider_name');
    $financial = fn ($stamp) => array_values(array_filter($stamp->document['layers'], fn ($layer) => in_array($layer['slot'] ?? '', ['amount', 'recipient', 'purpose', 'expiry', 'instructions', 'tagline'], true)));
    expect($financial($design))->toBe($financial($catalog->resolve($id, $previous)));
    $renderer = new Renderer;
    expect($renderer->svg($design, ['provider_name' => 'Example Bank']))->toContain('Example Bank')->not->toContain('NetBank');
    $logo = array_values(array_filter($design->document['layers'], fn ($layer) => ($layer['slot'] ?? '') === 'provider_logo'))[0];
    expect($logo['fit'])->toBe('contain');
    expect($logo)->not->toHaveKey('asset');
})->with([['steampunk', 5, 6], ['cats', 4, 5]]);

it('requires optional provider identity slots in subsequent system designs', function () {
    $catalog = new Catalog;
    $catalog->load(dirname(__DIR__));
    $historical = ['steampunk' => 5, 'cats' => 4, 'valentine' => 1];
    foreach ($catalog->all() as $design) {
        if ($design->revision() <= ($historical[$design->id()] ?? 0)) {
            continue;
        }
        expect($design->slots())->toContain('provider_logo', 'provider_name');
    }
});

it('places either provider identity before Pay Code while keeping other content unchanged', function (string $id, int $previous, int $revision) {
    $catalog = new Catalog;
    $catalog->load(dirname(__DIR__));
    $design = $catalog->resolve($id, $revision);
    $slots = array_column(array_filter($design->document['layers'], fn ($layer) => isset($layer['slot'])), null, 'slot');
    expect($slots['provider_logo']['x'])->toBeLessThan($slots['brand']['x']);
    expect($slots['provider_name']['x'] + $slots['provider_name']['width'])->toBeLessThan($slots['brand']['x']);
    $content = fn ($stamp) => array_values(array_filter($stamp->document['layers'], fn ($layer) => ! in_array($layer['slot'] ?? '', ['brand', 'provider_logo', 'provider_name'], true)));
    expect($content($design))->toBe($content($catalog->resolve($id, $previous)));
})->with([['steampunk', 6, 7], ['cats', 5, 6]]);

it('exposes supported customization on new revisions without changing historical documents', function () {
    $catalog = new Catalog;
    $catalog->load(dirname(__DIR__));
    expect($catalog->resolve('cats', 7)->document['customization']['controls'])->toContain('crop', 'qr', 'darkening', 'tagline');
    expect($catalog->resolve('steampunk', 8)->document['customization']['controls'])->not->toContain('crop');
    expect($catalog->resolve('cats', 6)->document)->not->toHaveKey('customization');
});

it('discovers Constellation with the current shared layout and an adjustable portrait crop', function () {
    $catalog = new Catalog;
    $catalog->load(dirname(__DIR__));
    $design = $catalog->resolve('constellation', 1);
    $cats = $catalog->resolve('cats', 7);
    expect($design->document['name'])->toBe('Constellation');
    expect(array_slice($design->document['layers'], 1))->toBe(array_slice($cats->document['layers'], 1));
    expect($design->document['layers'][0]['fit'])->toBe('cover');
    expect($design->document['customization']['defaults']['crop'])->toBe('top');
    $appearance = new Appearance;
    $top = $appearance->apply($design, $appearance->resolve($design));
    $bottom = $appearance->apply($design, $appearance->resolve($design, ['crop' => 'bottom']));
    $renderer = new Renderer;
    expect($renderer->png($top))->not->toBe($renderer->png($bottom));
    expect($renderer->svg($top))->toContain('xMidYMin slice');
    $manifest = json_decode(file_get_contents(dirname(__DIR__).'/collection.json'), true);
    expect($manifest['thumbnails']['constellation@1'])->toBe('designs/constellation/1/preview.png');
    expect(getimagesize(dirname(__DIR__).'/'.$manifest['thumbnails']['constellation@1']))->toMatchArray([0 => 1200, 1 => 630]);
});
