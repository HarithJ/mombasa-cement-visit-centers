CREATE TABLE IF NOT EXISTS bookings (
    id INTEGER PRIMARY KEY,
    reference TEXT NOT NULL UNIQUE,
    submission_token TEXT NOT NULL UNIQUE,
    full_name TEXT NOT NULL,
    phone TEXT NOT NULL,
    destination TEXT NOT NULL,
    visit_date TEXT NOT NULL,
    booked_time TEXT NOT NULL,
    attendees INTEGER NOT NULL CHECK(attendees > 0),
    status TEXT NOT NULL CHECK(status = 'automatically_confirmed'),
    created_at TEXT NOT NULL
);
