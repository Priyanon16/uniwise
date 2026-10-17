<?php
// COPY this file to config.local.php and edit it ON THE SERVER.
// If PHP and Dify share a VPS, prefer the internal API URL, e.g. http://127.0.0.1:8081/v1
// Otherwise confirm the correct API base URL from Dify > API Access.
return [
    'dify_api_base' => 'http://127.0.0.1:8081/v1',
    'dify_api_key'  => 'YOUR_DIFY_API_KEY',
    // Optional: required Chatflow input variable values, if configured in Dify.
    'default_inputs' => [],
];
