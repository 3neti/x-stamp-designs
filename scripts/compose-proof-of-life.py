#!/usr/bin/env python3
"""Compose the supplied, independently editable PROOF OF LIFE layers.

Requires ImageMagick with HEIC support and Pillow. The source photograph and
full-canvas overlays remain untouched; output is sized for the stamp renderer.
"""
import json
import subprocess
import tempfile
from pathlib import Path

from PIL import Image

root = Path(__file__).resolve().parent.parent
source = root / 'assets/artwork/proof-of-life'
config = json.loads((source / 'composition.json').read_text())
size = (config['canvas']['width'], config['canvas']['height'])
with tempfile.TemporaryDirectory() as tmp:
    background = Path(tmp) / 'background.png'
    subprocess.run(['magick', str(source / config['layers'][0]['file']), '-auto-orient', str(background)], check=True)
    photo = Image.open(background).convert('RGBA')
    if photo.size != size:
        raise ValueError('The photograph does not match the composition canvas.')
    for layer in config['layers'][1:]:
        overlay = Image.open(source / layer['file']).convert('RGBA')
        if overlay.size != size:
            raise ValueError('Overlay does not match the composition canvas.')
        opacity = layer['opacity']
        if opacity != 1:
            overlay.putalpha(overlay.getchannel('A').point(lambda alpha: round(alpha * opacity)))
        photo.alpha_composite(overlay)
    # The stamp renderer limits each image to 16 million pixels. Downsample
    # proportionally only after compositing at the photograph's resolution.
    photo.convert('RGB').resize((3000, 4000), Image.Resampling.LANCZOS).save(root / 'assets/artwork/proof-of-life-v1.png', optimize=True)
