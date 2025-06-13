<?php
// $data = json_decode(file_get_contents("php://input"), true);
// file_put_contents('data.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
// echo "Data saved successfully.";

header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);
file_put_contents('data.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo json_encode([
    "success" => true,
    "message" => "Data saved successfully."
]);