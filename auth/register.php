<?php
include "../includes/database.php";


 $message = "";
if(isset($_POST['register'])){
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $gender = mysqli_real_escape_string($conn, $_POST['gender']);
    $password = $_POST['password'];
    $role = 1; 

    $check = mysqli_query($conn, "SELECT * FROM users WHERE email='$email'");

    if(mysqli_num_rows($check) > 0){
        $message = "Email already exists.";
    }else{
        mysqli_query($conn, "INSERT INTO users(name, email, phone, gender, password, role) 
        VALUES('$name', '$email', '$phone', '$gender', '$password', '$role')");
        $message = "Registration Successful.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Register</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-5">
<div class="col-md-5 mx-auto">
<div class="card shadow">
<div class="card-header bg-primary text-white">
    <h3>Create Account</h3>
</div>
<div class="card-body">

<?php if($message != ""){ ?>
<div class="alert alert-info"><?= $message ?></div>
<?php } ?>

<form method="POST">

    <div class="mb-3">
        <label>Name</label>
        <input type="text" name="name" class="form-control" required>
    </div>

    <div class="mb-3">
        <label>Email</label>
        <input type="email" name="email" class="form-control" required>
    </div>

    <div class="mb-3">
        <label>Phone Number</label>
        <input type="text" name="phone" class="form-control" required>
    </div>

    <div class="mb-3">
        <label>Gender</label>
        <select name="gender" class="form-control" required>
            <option value="">Select Gender</option>
            <option value="Male">Male</option>
            <option value="Female">Female</option>
            <option value="Other">Other</option>
        </select>
    </div>

    <div class="mb-3">
        <label>Password</label>
        <input type="password" name="password" class="form-control" required>
    </div>

    <button class="btn btn-success w-100" name="register">Register</button>

</form>

<hr>

<a href="../index.php" class="btn btn-outline-primary w-100">Already have an account?</a>

</div>
</div>
</div>
</div>

</body>
</html>