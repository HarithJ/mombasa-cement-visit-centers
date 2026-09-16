# Issue tracker: Local Markdown

Issues and specs live as Markdown files in `.scratch/`.

## Conventions

- One feature per directory: `.scratch/<feature-slug>/`.
- Specs: `.scratch/<feature-slug>/spec.md`.
- Tickets: `.scratch/<feature-slug>/issues/<NN>-<slug>.md`,
  numbered from 01, one file per ticket.
- Record triage state as a `Status:` line near the top.
- Append discussion under a `## Comments` heading.

Publishing means creating the relevant local Markdown file.
Fetching means reading the referenced file.

## Wayfinding

- Map: `.scratch/<effort>/map.md`.
- Child tickets use the numbered ticket convention above.
- Record ticket type using `Type:`.
- Record dependencies using `Blocked by: NN, NN`.
- Select open, unblocked, unclaimed tickets in numerical order.
- Claim by saving `Status: claimed` before starting.
- Resolve by appending an `## Answer`, saving `Status: resolved`,
  and adding a summary and link to the map.
