<?php
$users = [
    "androbuddies" => ["password" => password_hash("070996yogi", PASSWORD_DEFAULT)],
    "ihsan" => ["password" => password_hash("ihs4n", PASSWORD_DEFAULT)]
];
file_put_contents("users.json", json_encode($users, JSON_PRETTY_PRINT));
echo " File users.json berhasil dibuat.";