"""Rebuild public galleries from the curated source manifest. Requires Pillow."""
import json
from pathlib import Path
from PIL import Image, ImageOps
ROOT = Path(__file__).resolve().parents[1]

def trim_letterbox(image):
    """Crop near-black bars (video letterboxing) from each edge; leave dark photographs alone."""
    gray = image.convert('L')
    rows = list(gray.resize((1, image.height), Image.Resampling.BOX).tobytes())
    columns = list(gray.resize((image.width, 1), Image.Resampling.BOX).tobytes())
    def bar(means, limit):
        size = 0
        while size < limit and means[size] < 20:
            size += 1
        return size if size >= len(means) * 0.01 else 0
    top, bottom = bar(rows, image.height // 4), bar(rows[::-1], image.height // 4)
    left, right = bar(columns, image.width // 4), bar(columns[::-1], image.width // 4)
    # A few rows inside each bar's edge are blended; trim slightly past it.
    inset = lambda size: size + 2 if size else 0
    box = (inset(left), inset(top), image.width - inset(right), image.height - inset(bottom))
    return image.crop(box) if box != (0, 0, image.width, image.height) else image

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
            image = trim_letterbox(ImageOps.exif_transpose(original).convert('RGB'))
            if image.size != original.size: print(f"  trimmed bars: {row['asset']} {original.size} -> {image.size}")
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
