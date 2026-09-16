ALTER TABLE bookings ADD COLUMN overnight INTEGER NOT NULL DEFAULT 0 CHECK(overnight IN (0, 1));
ALTER TABLE bookings ADD COLUMN arrival_date TEXT;
ALTER TABLE bookings ADD COLUMN departure_date TEXT;
ALTER TABLE bookings ADD COLUMN overnight_guests INTEGER;
CREATE TRIGGER bookings_overnight_insert BEFORE INSERT ON bookings
WHEN (NEW.overnight = 0 AND (NEW.arrival_date IS NOT NULL OR NEW.departure_date IS NOT NULL OR NEW.overnight_guests IS NOT NULL))
OR (NEW.overnight = 1 AND (NEW.destination <> 'galana' OR NEW.arrival_date IS NULL OR NEW.arrival_date <> NEW.visit_date OR NEW.departure_date IS NULL OR NEW.departure_date <= NEW.arrival_date OR NEW.overnight_guests IS NULL OR NEW.overnight_guests < 1 OR NEW.overnight_guests > NEW.attendees))
BEGIN SELECT RAISE(ABORT, 'Invalid overnight details'); END;
CREATE TRIGGER bookings_overnight_update BEFORE UPDATE ON bookings
WHEN (NEW.overnight = 0 AND (NEW.arrival_date IS NOT NULL OR NEW.departure_date IS NOT NULL OR NEW.overnight_guests IS NOT NULL))
OR (NEW.overnight = 1 AND (NEW.destination <> 'galana' OR NEW.arrival_date IS NULL OR NEW.arrival_date <> NEW.visit_date OR NEW.departure_date IS NULL OR NEW.departure_date <= NEW.arrival_date OR NEW.overnight_guests IS NULL OR NEW.overnight_guests < 1 OR NEW.overnight_guests > NEW.attendees))
BEGIN SELECT RAISE(ABORT, 'Invalid overnight details'); END;
