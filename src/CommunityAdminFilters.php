<?php
declare(strict_types=1);
final class CommunityAdminFilters {
    /** Parse public list filters and calculate inclusive Kenya-local date bounds. */
    public static function parse(array $query, array $states): array {
        $filters = [];
        foreach (['q', 'from', 'to', 'state'] as $key) {
            if (isset($query[$key]) && !is_string($query[$key])) throw new InvalidArgumentException('Invalid filters');
            $filters[$key] = trim($query[$key] ?? '');
        }
        if (strlen($filters['q']) > 200 || ($filters['state'] !== '' && !isset($states[$filters['state']]))) throw new InvalidArgumentException('Invalid filters');
        $bounds = [];
        foreach (['from', 'to'] as $key) {
            if ($filters[$key] === '') continue;
            if (!preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $filters[$key])) throw new InvalidArgumentException('Invalid dates');
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $filters[$key], new DateTimeZone('Africa/Nairobi'));
            if (!$date || $date->format('Y-m-d') !== $filters[$key]) throw new InvalidArgumentException('Invalid dates');
            if ($key === 'to') $date = $date->modify('+1 day');
            $bounds[$key] = $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
        }
        if ($filters['from'] && $filters['to'] && $filters['from'] > $filters['to']) throw new InvalidArgumentException('Invalid date range');
        $page = filter_var($query['page'] ?? 1, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1, 'max_range'=>1000000]]);
        if ($page === false) throw new InvalidArgumentException('Invalid page');
        return [$filters, $page, $bounds];
    }
}
