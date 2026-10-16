<?php
include_once('includes/database.php');
$res = mysqli_query($conn, 'DESCRIBE bids');
while ($row = mysqli_fetch_assoc($res)) {
    echo $row['Field'] . ' (' . $row['Type'] . ")\n";
}
