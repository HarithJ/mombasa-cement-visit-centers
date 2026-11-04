# Destination photographs

The original photographs live in `galana`. Filenames describe the subject. `config/photo-manifest.json` records previous filenames, current source filenames, and the ordered public assets.

Run `bin/build-photos.py` with a Python environment containing Pillow after updating the manifest. It validates sources, applies EXIF orientation, trims black letterbox bars left by video stills, generates WebP assets up to 1600 pixels wide and 360/720/1024 responsive variants without upscaling, and removes stale generated WebP files. Embedded screenshot borders are retained in the source files. Served copies omit source metadata.

Review any new photographs containing people before adding them, and obscure children's faces in the source files so every generated size inherits the edit.

The first image is the farm crop rows. This stable asset name is also used by admin booking details. The gallery discovers the generated files in filename order.

Current collection: 15 Galana Farm photographs. The Sahajanand Special School and Kibarani Feeding center collections were removed with those destinations (see [ADR 0001](adr/0001-galana-farm-only.md)); their originals remain in git history. Run `node tests/photos.test.mjs` with the project's Playwright/browser environment to decode every gallery image and check counts and wraparound.
