<?php

require_once "../../api/db.php";


if (
    $_SERVER['REQUEST_METHOD']
    !== 'POST'
) {

    http_response_code(405);

    exit(
        "Method Not Allowed"
    );
}


$id =
    filter_input(
        INPUT_POST,
        'id',
        FILTER_VALIDATE_INT
    );


if (!$id) {

    header(
        "Location: index.php"
    );

    exit;
}


$stmt =
    $pdo->prepare("
        DELETE
        FROM academic_calendar_events
        WHERE id = :id
    ");


$stmt->execute([
    ':id' => $id
]);


header(
    "Location: index.php?success=1"
);

exit;