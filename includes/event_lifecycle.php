<?php
/** Event lifecycle uses the event's local date/end time in India. No database writes. */
function evenza_event_now(): DateTimeImmutable {
    return new DateTimeImmutable('now', new DateTimeZone('Asia/Kolkata'));
}
function evenza_event_has_ended(array $event, ?DateTimeImmutable $now = null): bool {
    $date = (string)($event['event_date'] ?? '');
    $time = trim((string)($event['end_time'] ?? ''));
    // Missing end time means the event lasts until the end of its listed day.
    if ($time === '') $time = '23:59:59';
    if (strlen($time) === 5) $time .= ':00';
    $end = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', "$date $time", new DateTimeZone('Asia/Kolkata'));
    if (!$end || $end->format('Y-m-d H:i:s') !== "$date $time") return false;
    return $end <= ($now ?? evenza_event_now());
}
function evenza_event_status(array $event): string {
    $status = (string)($event['status'] ?? 'draft');
    return $status === 'published' && evenza_event_has_ended($event) ? 'completed' : $status;
}
function evenza_active_event_sql(string $alias = 'e'): string {
    if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D', $alias)) throw new InvalidArgumentException('Invalid event alias');
    $now = evenza_event_now()->format('Y-m-d H:i:s');
    return "($alias.status = 'published' AND TIMESTAMP($alias.event_date, COALESCE($alias.end_time, '23:59:59')) > '$now')";
}
