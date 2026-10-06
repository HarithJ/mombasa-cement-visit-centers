ALTER TABLE school_requests ADD COLUMN classroom_length REAL CHECK (classroom_length IS NULL OR (classroom_length > 0 AND classroom_length <= 1000000));
ALTER TABLE school_requests ADD COLUMN classroom_width REAL CHECK (classroom_width IS NULL OR (classroom_width > 0 AND classroom_width <= 1000000));
ALTER TABLE school_requests ADD COLUMN classroom_height REAL CHECK (classroom_height IS NULL OR (classroom_height > 0 AND classroom_height <= 1000000));
ALTER TABLE school_requests ADD COLUMN classroom_perimeter REAL CHECK (classroom_perimeter IS NULL OR (classroom_perimeter > 0 AND classroom_perimeter <= 1000000));
ALTER TABLE school_requests ADD COLUMN wall_length REAL CHECK (wall_length IS NULL OR (wall_length > 0 AND wall_length <= 1000000));
ALTER TABLE school_requests ADD COLUMN wall_height REAL CHECK (wall_height IS NULL OR (wall_height > 0 AND wall_height <= 1000000));
