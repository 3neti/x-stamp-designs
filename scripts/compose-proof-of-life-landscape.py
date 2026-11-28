#!/usr/bin/env python3
"""Recompose the supplied portrait and independent overlays as a wide stamp."""
import json
import subprocess
import sys
import tempfile
from pathlib import Path
from PIL import Image

root = Path(__file__).resolve().parent.parent
source = root / 'assets/artwork/proof-of-life'
config_name = sys.argv[1] if len(sys.argv) > 1 else 'landscape-composition.json'
config = json.loads((source / config_name).read_text())
with tempfile.TemporaryDirectory() as tmp:
    converted = Path(tmp) / 'background.png'
    subprocess.run(['magick', str(source / config['layers'][0]['file']), '-auto-orient', str(converted)], check=True)
    photo = Image.open(converted).convert('RGBA')
    canvas = config['source_canvas']
    if photo.size != (canvas['width'], canvas['height']):
        raise ValueError('The photograph does not match the source canvas.')
    crop = config['crop']
    photo = photo.crop((crop['x'], crop['y'], crop['x'] + crop['width'], crop['y'] + crop['height']))
    for layer in config['layers'][1:]:
        overlay = Image.open(source / layer['file']).convert('RGBA').crop(tuple(layer['bounds']))
        overlay = overlay.resize((layer['width'], layer['height']), Image.Resampling.LANCZOS)
        if layer['opacity'] != 1:
            overlay.putalpha(overlay.getchannel('A').point(lambda a: round(a * layer['opacity'])))
        photo.alpha_composite(overlay, (layer['x'], layer['y']))
    output = config['output']
    output_file = output.get('file', 'proof-of-life-v2.png')
    photo.convert('RGB').resize((output['width'], output['height']), Image.Resampling.LANCZOS).save(root / 'assets/artwork' / output_file, optimize=True)
