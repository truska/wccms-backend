<?php
/** Possible UTC instants for a legacy UK wall-clock value (0, 1, or 2). */
function oli_utc_candidates(string $local): array
{
    $utc = new DateTimeZone('UTC');
    $london = new DateTimeZone('Europe/London');
    $wall = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $local, $utc);
    if (!$wall || $wall->format('Y-m-d H:i:s') !== $local) {
        return [];
    }
    $offsets = [];
    foreach ($london->getTransitions($wall->getTimestamp() - 172800, $wall->getTimestamp() + 172800) as $transition) {
        $offsets[$transition['offset']] = true;
    }
    $candidates = [];
    foreach (array_keys($offsets) as $offset) {
        $instant = $wall->setTimestamp($wall->getTimestamp() - $offset);
        if ($instant->setTimezone($london)->format('Y-m-d H:i:s') === $local) {
            $candidates[] = $instant->format('Y-m-d H:i:s');
        }
    }
    sort($candidates);
    return $candidates;
}
