ALTER TABLE bookings ADD COLUMN transport_requested INTEGER NOT NULL DEFAULT 0 CHECK(transport_requested IN (0, 1));
