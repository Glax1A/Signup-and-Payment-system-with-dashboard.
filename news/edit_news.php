<?php
require 'config.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$title = $content = "";
$isEditMode = false;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['submit'])) {
        $title = $_POST['title'];
        $content = $_POST['content'];

        $sql = "INSERT INTO news (title, content) VALUES ('$title', '$content')";

        if ($conn->query($sql) === TRUE) {
            echo "<p>News added successfully!</p>";
            $title = $content = "";
        } else {
            echo "Error: " . $sql . "<br>" . $conn->error;
        }
    } elseif (isset($_POST['update'])) {
        $id = $_POST['id'];
        $title = $_POST['title'];
        $content = $_POST['content'];

        $sql = "UPDATE news SET title='$title', content='$content' WHERE id=$id";

        if ($conn->query($sql) === TRUE) {
            echo "<p>News updated successfully!</p>";
        } else {
            echo "Error: " . $sql . "<br>" . $conn->error;
        }
    }
}

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $sql = "SELECT * FROM news WHERE id=$id";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $title = $row['title'];
        $content = $row['content'];
        $isEditMode = true;
    } else {
        echo "<p>News article not found.</p>";
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo $isEditMode ? 'Edit News' : 'Add News'; ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h1><?php echo $isEditMode ? 'Edit News' : 'Add News'; ?></h1>
        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <?php if ($isEditMode): ?>
                <input type="hidden" name="id" value="<?php echo $id; ?>">
                <input type="hidden" name="update">
            <?php endif; ?>
            <label>Title:</label><br>
            <input type="text" name="title" value="<?php echo htmlspecialchars($title); ?>"><br><br>
            <label>Content:</label><br>
            <textarea name="content"><?php echo htmlspecialchars($content); ?></textarea><br><br>
            <?php if ($isEditMode): ?>
                <input type="submit" value="Update News">
            <?php else: ?>
                <input type="submit" name="submit" value="Add News">
            <?php endif; ?>
        </form>
    </div>
</body>
</html>
