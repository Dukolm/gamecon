<?php
$job ??= null;

// Pozor, pořadí je důležité - úkoly na prvním místě jsou ty, co mají být puštěny před ostatními
if (in_array($job, ['odhlaseni_neplaticu', 'aktivity_hromadne'])) {
    require __DIR__ . '/odhlaseni_neplaticu.php';
    if ($job === 'odhlaseni_neplaticu') {
        return;
    }
}
if (in_array($job, ['aktivace_aktivit', 'aktivity_hromadne'])) {
    require __DIR__ . '/aktivace_aktivit.php';
    if ($job === 'aktivace_aktivit') {
        return;
    }
}
if ($job !== 'aktivity_hromadne') {
    throw new \RuntimeException(sprintf("Invalid job '%s'", $job));
}
