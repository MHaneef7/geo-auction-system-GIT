<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once("includes/database.php");
include_once("includes/auth.php");

require_admin("login.php");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin - All Data</title>
    <style>
        body{
            font-family: Arial;
            margin:20px;
            background:#f5f5f5;
        }
        h2{
            background:#333;
            color:#fff;
            padding:10px;
        }
        table{
            width:100%;
            border-collapse:collapse;
            margin-bottom:30px;
            background:#fff;
        }
        table,th,td{
            border:1px solid #ccc;
        }
        th{
            background:#007bff;
            color:#fff;
        }
        th,td{
            padding:8px;
            text-align:left;
        }
    </style>
</head>
<body>

<h2>All Users</h2>

<table>
<tr>
    <th>ID</th>
    <th>Name</th>
    <th>Email</th>
    <th>Phone</th>
    <th>Role</th>
    <th>Status</th>
    <th>Gender</th>
</tr>

<?php
$result = mysqli_query($conn,"SELECT * FROM users");

while($row = mysqli_fetch_assoc($result))
{
?>
<tr>
    <td><?= $row['id']; ?></td>
    <td><?= $row['name']; ?></td>
    <td><?= $row['email']; ?></td>
    <td><?= $row['phone']; ?></td>
    <td><?= $row['role']; ?></td>
    <td><?= $row['status']; ?></td>
    <td><?= $row['gender']; ?></td>
</tr>
<?php } ?>
</table>


<h2>All Items</h2>

<table>
<tr>
    <th>ID</th>
    <th>Title</th>
    <th>Seller ID</th>
    <th>Starting Price</th>
    <th>Current Price</th>
    <th>Status</th>
    <th>Featured</th>
</tr>

<?php
$result = mysqli_query($conn,"SELECT * FROM items");

while($row = mysqli_fetch_assoc($result))
{
?>
<tr>
    <td><?= $row['id']; ?></td>
    <td><?= $row['title']; ?></td>
    <td><?= $row['seller_id']; ?></td>
    <td><?= $row['starting_price']; ?></td>
    <td><?= $row['current_price']; ?></td>
    <td><?= $row['status']; ?></td>
    <td><?= $row['featured']; ?></td>
</tr>
<?php } ?>
</table>


<h2>All Bids</h2>

<table>
<tr>
    <th>ID</th>
    <th>Item ID</th>
    <th>User ID</th>
    <th>Bidder Name</th>
    <th>Bid Amount</th>
    <th>Winning</th>
    <th>Date</th>
</tr>

<?php
$result = mysqli_query($conn,"SELECT * FROM bids");

while($row = mysqli_fetch_assoc($result))
{
?>
<tr>
    <td><?= $row['id']; ?></td>
    <td><?= $row['item_id']; ?></td>
    <td><?= $row['user_id']; ?></td>
    <td><?= $row['bidder_name']; ?></td>
    <td><?= $row['bid_amount']; ?></td>
    <td><?= $row['is_winning']; ?></td>
    <td><?= $row['created_at']; ?></td>
</tr>
<?php } ?>
</table>

</body>
</html>