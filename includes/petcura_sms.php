<?php

function send_sms_template($phone, array $variables) {

    $api_key = 'nsms_live_f78b8a9d27fb97f2becc172897e808acf094481dfc6c3e8dfed2b8ea85d5361c';
    $template_id = 'caf46676-92d7-48d6-aaf2-0c8b73392e70';

    $url = 'https://auth.nestsms.com/api/v1/sms/send';

    $data = [
        "to" => $phone,
        "template_id" => $template_id,
        "variables" => $variables,
    ];

    $headers = [
        "X-API-Key: $api_key",
        "Content-Type: application/json"
    ];

    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        return ['success' => false, 'error' => curl_error($ch)];
    }

    curl_close($ch);

    $result = json_decode($response, true);

    if (!empty($result['success'])) {
        return ['success' => true];
    }

    return [
        'success' => false,
        'error' => $result['error'] ?? $response
    ];
}