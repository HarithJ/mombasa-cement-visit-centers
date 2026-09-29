# Destination photographs

The original photographs live in `Sahajanand`, `galana`, and `Feeding Center`. Filenames describe the destination and subject. `config/photo-manifest.json` records previous filenames, current source filenames, and the ordered public assets.

Run `bin/build-photos.py` with a Python environment containing Pillow after updating the manifest. It validates sources, applies EXIF orientation, generates WebP assets up to 1600 pixels wide and 360/720/1024 responsive variants without upscaling, and removes stale generated WebP files. Original pixels and embedded screenshot borders are retained in the source files. Served copies omit source metadata.

The first image is the school entrance, farm crop rows, or prepared meals. These stable asset names are also used by admin booking details. Galleries discover the generated files in filename order.

Current collection: 10 school, 15 Galana, and 20 feeding-centre photographs. Deleted originals are absent from the regenerated public collection. Run `node tests/photos.test.mjs` with the project's Playwright/browser environment to decode every gallery image across available versions and check counts and wraparound. Static-preview checks verify exported assets as well.
