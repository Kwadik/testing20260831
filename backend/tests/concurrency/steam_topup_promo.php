<?php

$requests = [];

for ($i = 1; $i <= 10; $i++) {
    $requests[$i] = curl_init('http://localhost:8000/api/steam-topups');

    curl_setopt_array($requests[$i], [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'amount' => 1000,
            'currency' => 'RUB',
            'promo_code' => 'LIMIT3',
        ]),
    ]);
}

$multi = curl_multi_init();

foreach ($requests as $request) {
    curl_multi_add_handle($multi, $request);
}

$running = null;

do {
    curl_multi_exec($multi, $running);

    if ($running) {
        curl_multi_select($multi);
    }
} while ($running);

$success = 0;
$failed = 0;

foreach ($requests as $number => $request) {
    $status = curl_getinfo($request, CURLINFO_HTTP_CODE);
    $body = curl_multi_getcontent($request);

    echo "Request #{$number}: HTTP {$status}\n";
    echo $body . "\n\n";

    if ($status === 201) {
        $success++;
    } else {
        $failed++;
    }

    curl_multi_remove_handle($multi, $request);
    curl_close($request);
}

curl_multi_close($multi);

echo "Successful: {$success}\n";
echo "Failed: {$failed}\n";
