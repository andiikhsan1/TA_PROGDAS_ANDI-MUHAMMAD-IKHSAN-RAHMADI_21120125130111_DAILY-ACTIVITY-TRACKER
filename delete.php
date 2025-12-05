<?php
include "config.php";

$id = $_GET['id'];
$type = $_GET['type'] ?? 'notes';

if ($type == 'notes') {
    mysqli_query($conn, "DELETE FROM activities WHERE id='$id'");
    header("Location: index.php?type=notes");
} else {
    $db2 = new mysqli("localhost", "root", "", "tracker2");
    $db2->query("DELETE FROM activities WHERE id='$id'");
    header("Location: index.php?type=daily");
}
?>
