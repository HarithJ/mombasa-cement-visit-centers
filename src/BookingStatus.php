<?php
declare(strict_types=1);
// A booking's status: pending until someone decides it, then confirmed or rejected (never pending again).
final class BookingStatus {
    // Every status with its admin-panel label.
    public const LABELS = ['pending' => 'Awaiting confirmation', 'confirmed' => 'Confirmed', 'rejected' => 'Rejected'];
    // The statuses a decision can set.
    public const DECISIONS = ['confirmed', 'rejected'];
    // Heading on a decision page whose link can no longer decide.
    public const LINK_HEADINGS = ['pending' => 'Still awaiting a decision.', 'confirmed' => 'Already confirmed.', 'rejected' => 'Already rejected.'];
}
