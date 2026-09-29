"""Rebuild public galleries from the curated source manifest. Requires Pillow."""
import json
from pathlib import Path
from PIL import Image, ImageOps
ROOT = Path(__file__).resolve().parents[1]
manifest = json.loads((ROOT / 'config/photo-manifest.json').read_text())
# Validate all sources before touching generated output.
for row in manifest:
    with Image.open(ROOT / row['source']) as image:
        image.verify()
for destination in sorted({row['destination'] for row in manifest}):
    directory = ROOT / 'public/assets/photos' / destination
    directory.mkdir(parents=True, exist_ok=True)
    (directory / 'responsive').mkdir(exist_ok=True)
    expected = set()
    for row in [r for r in manifest if r['destination'] == destination]:
        with Image.open(ROOT / row['source']) as original:
            image = ImageOps.exif_transpose(original).convert('RGB')
            for width in [1600, 360, 720, 1024]:
                output = directory / row['asset'] if width == 1600 else directory / 'responsive' / f"{Path(row['asset']).stem}-{width}.webp"
                resized = image.copy()
                resized.thumbnail((width, width * image.height // image.width), Image.Resampling.LANCZOS)
                resized.save(output, 'WEBP', quality=84, method=6)
                expected.add(output)
    # Remove only stale generated gallery assets, never original photos.
    for output in [*directory.glob('*.webp'), *(directory / 'responsive').glob('*.webp')]:
        if output not in expected:
            output.unlink()
    print(f'{destination}: {len(expected)//4} photos, with responsive variants')
