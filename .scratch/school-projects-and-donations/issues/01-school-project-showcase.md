# 01 — Show completed school projects

**What to build:** Visitors can discover the classrooms and school walls Nyumba has completed through a responsive, accessible project showcase that fits the v3 visit website.

**Blocked by:** None — can start immediately.

**Status:** claimed

**Type:** feature

**Base branch:** v3

- [ ] Add the school-project section after the destination stories and before planning, with responsive navigation. Keep all three destination booking controls accessible; construction projects are not a fourth destination.
- [ ] Render a maintainable structured collection with stable identifiers, school names, locations, classroom/wall types, descriptions, photographs and alternative text. Show completion dates and quantities only when verified.
- [ ] Publish only approved completed-project facts and photographs. Preserve originals and existing photo privacy treatment, and optimize public copies. Do not repurpose existing school photographs as evidence of another construction project.
- [ ] Render an honest empty state when approved content is absent. Fictional test fixtures stay out of production content; missing assets remain an explicit launch dependency.
- [ ] Match v3 typography, color, spacing, and image treatment. Verify desktop and mobile layouts, keyboard access, descriptive image alternatives, reduced-motion behavior where relevant, and useful content without JavaScript.
- [ ] Keep showcase composition independent of the request implementation so this ticket can land alone. Once ticket 02 exists, its form remains discoverable from this section without duplicating the school navigation entry.
- [ ] Test both construction types, optional metadata, empty content, escaped text, and preserved destination navigation through the existing browser setup. Review the resulting desktop/mobile presentation without brittle pixel assertions.
- [ ] Document how maintainers supply approved content. Do not introduce an admin CMS, invent impact totals, or modify other version branches.
