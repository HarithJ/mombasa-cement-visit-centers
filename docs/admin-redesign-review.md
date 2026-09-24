# Admin redesign review

Reviewed from `0f8f942` through `1a5475d`, followed by the fixes recorded below. The owner confirmed this scope: the entire admin redesign plus existing authentication and security flows. Requirements came from the admin spec and the subsequently approved login/workspace design changes.

## Standards

The independent standards review found no documented repository-standard violations or security regressions. It reviewed output escaping, contact links, the password visibility control, login-only script policy, date shortcuts, and feedback presentation.

One wording finding was resolved: the generic “Awaiting feedback” badge could imply that an invitation had been sent to an email-less booking or while sending was disabled. It now says “No feedback received.” No remaining findings on this axis.

## Spec

The independent requirements review found no confirmed spec violations. The approved house image, stationary form, retained diagonal arrow, password visibility control, mobile cards, date shortcuts, contact links, and self-reported feedback remain present. An optional clarification was adopted: the list column/mobile label now says “Visitor feedback.” No remaining findings on this axis.

## Visual review and fixes

Browser checks found the house roof clipped at tablet widths because the image scaled beyond the compact banner height. Its maximum width is now bounded within the banner, and its full image bounds are checked at 320, 390, 768, 850, 851, 1024, and 1440 pixels. Small-phone title sizing was also tightened to give the roofline more space. A 1920-pixel desktop and a short 1024-by-600 laptop viewport were inspected separately.

Desktop/tablet bookings lists and desktop/mobile details were inspected. List, attended feedback, non-attendance, and empty results were exercised across five workspace widths. Browser assertions check horizontal overflow and successful image decoding. Records remain stationary; reduced-motion disables decorative login animation. Keyboard tab order through username, password, and the visibility toggle is checked.

## Verification and limits

The full repository regression suite passed: admin, email queue, feedback worker/migrations, bookings, overnight visits, contacts, versions, visitor feedback, and static previews. The expanded admin suite also covers CSRF, expired sessions, credential rotation, persistent failed-login throttling, authorization, no-JavaScript operation, hostile stored content, and preservation of domain records.

These are local PHP and Chromium checks using fictional data and isolated storage. They are not live-production verification, an exhaustive security audit, or a Safari/Firefox and assistive-technology certification. Static previews remain separate from the PHP-only admin area. No live credentials or real email sends were used.

Summary: standards — one wording finding resolved, zero remaining; spec — zero confirmed violations, one optional clarity improvement adopted; visual review — tablet clipping fixed and small-phone spacing refined.
