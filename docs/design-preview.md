# Static design previews

The `design-preview` branch builds a GitHub Pages site containing all three designs. The PHP source is retained for build-time rendering; GitHub Pages receives only the generated `_site/` directory.

## Build and test

```sh
npm ci
npm run build:preview
npx playwright install chromium
npm run test:preview
python3 -m http.server 8081 --directory _site
```

Open `http://localhost:8081/` for the version chooser, or `/v1/`, `/v2/`, `/v3/` directly. PHP 8.3+ is used only while exporting templates; the preview server needs no PHP or database. The build does not read the live SQLite database, start sessions or load the booking controller. `_site/` is generated and ignored by Git.

## Publish on GitHub Pages

1. Push `design-preview` to `origin`.
2. In the repository's **Settings → Pages → Build and deployment**, select **GitHub Actions** as the source.
3. Open **Actions → Deploy design previews** and rerun the latest run if its deployment failed before Pages was enabled.
4. If GitHub reports an environment branch restriction, allow `design-preview` in **Settings → Environments → github-pages → Deployment branches and tags**.

The workflow is restricted to `design-preview`. It builds and tests the artifact before deployment. The other version branches and `main` do not deploy this preview workflow.

Expected default URL after successful deployment:

- `https://harithj.github.io/mombasa-cement-visit-centers/`
- `https://harithj.github.io/mombasa-cement-visit-centers/v1/`
- `https://harithj.github.io/mombasa-cement-visit-centers/v2/`
- `https://harithj.github.io/mombasa-cement-visit-centers/v3/`

All asset and internal navigation URLs are relative, so the same artifact also works at a custom-domain root. Never select the repository root as a static publication directory: the workflow uploads only `_site/`.

## Preview behavior

- A preview notice labels each version as a design preview.
- The form validates sample input and displays a local preview summary, with no booking reference or registration claim.
- Submission is intercepted in the browser. No POST, booking API, cookies or browser storage are used to save form entries. Reloading clears the sample details.
- Gallery controls and on-demand Google Maps remain interactive. Opening a map contacts Google, but form details are not passed to it.
- Without JavaScript, the pages and version links work, the form submit button remains disabled, and a notice explains how to enable interactive previews.
- PHP, SQLite, private configuration, real booking data and session files are excluded from the deployment artifact.

The source templates still describe the real application; `bin/build-preview.mjs` converts their output to preview-only wording and behavior. When carrying future design changes into this branch, run `npm run test:preview` before pushing. Keep this preview-only conversion off the live version branches.
