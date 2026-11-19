<?php
// Read-only scheduling: open pages refresh at the next event/pass/deadline boundary.
if (isset($conn) && $conn instanceof mysqli) {
    require_once __DIR__ . '/event_lifecycle.php';
    $clockNow = evenza_event_now();
    $clockSqlNow = $clockNow->format('Y-m-d H:i:s');
    $clockResult = mysqli_query($conn, "SELECT MIN(boundary) AS boundary FROM (
        SELECT TIMESTAMP(event_date, COALESCE(end_time, '23:59:59')) AS boundary FROM events WHERE status = 'published'
        UNION ALL SELECT registration_deadline FROM events WHERE status = 'published' AND registration_deadline IS NOT NULL
        UNION ALL SELECT expires_at FROM entry_passes WHERE expires_at IS NOT NULL
    ) clock_boundaries WHERE boundary > '$clockSqlNow'");
    $clockBoundary = $clockResult ? (mysqli_fetch_assoc($clockResult)['boundary'] ?? null) : null;
    if ($clockBoundary) {
        $clockEnd = new DateTimeImmutable($clockBoundary, new DateTimeZone('Asia/Kolkata'));
        $clockDelay = max(1, $clockEnd->getTimestamp() - $clockNow->getTimestamp() + 1);
        echo '<script src="../assets/js/event-clock.js" defer data-refresh-after="' . $clockDelay . '"></script>';
    }
}
