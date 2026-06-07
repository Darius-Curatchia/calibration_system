<?php
echo "SuperAdmin: " . password_hash("SAdmin123", PASSWORD_DEFAULT) . "<br>";
echo "User1: " . password_hash("user123", PASSWORD_DEFAULT) . "<br>";
?>
