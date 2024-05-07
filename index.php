<?php
session_start(); // Start the session
include 'connectie.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $message = isset($_POST['message']) ? sanitizeInput($_POST['message']) : '';
}

function sanitizeInput($input) {
    // Remove leading and trailing whitespace
    $input = trim($input);
    
    // Convert special characters to HTML entities to prevent XSS attacks
    $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    
    // Additional sanitization steps can be added here
    
    return $input;
}

// Function to upload a message to the database
function uploadMessage($image, $message, $name, $conn) {
    try {
        // Check if the user has already posted within the last two hours
        if (isset($_SESSION['last_post_time']) && time() - $_SESSION['last_post_time'] < 7200) {
            echo "You can only send one message every two hours.";
            return false;
        }

        // Filter message and name to prevent script injection
        $message = htmlspecialchars($message);
        $name = htmlspecialchars($name);

        // Prepare SQL statement
        $stmt = $conn->prepare("INSERT INTO berichten (name, message, image, date_time) VALUES (:name, :message, :image, NOW())");
        
        // Bind parameters
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':message', $message);
        $stmt->bindParam(':image', $image);
        
        // Execute the query
        $stmt->execute();
        
        // Check if the query was successful
        if ($stmt->rowCount() > 0) {
            // Update last post time in session
            $_SESSION['last_post_time'] = time();
            return true; // Message uploaded successfully
        } else {
            return false; // Failed to upload message
        }
    } catch(PDOException $e) {
        // Handle errors
        echo "Error: " . $e->getMessage();
        return false; // Failed to upload message
    }
}

// If the form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Check if all fields are filled
    if (!empty($_POST['name']) && !empty($_POST['message'])) {
        // Get the input data
        $name = $_POST['name'];
        $message = $_POST['message'];
        
        // Check if an image is uploaded
        $image = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            // Check if the uploaded file is a GIF or image
            $allowedTypes = array(IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_PNG);
            $detectedType = exif_imagetype($_FILES['image']['tmp_name']);
            if (in_array($detectedType, $allowedTypes)) {
                $image = $_FILES['image']['name'];
                move_uploaded_file($_FILES['image']['tmp_name'], "uploads/" . $image);
            } else {
                echo "Only GIF or image files are allowed.";
            }
        }
        
        // Upload the message to the database if image is valid
        if ($image !== null && uploadMessage($image, $message, $name, $pdo)) {
            // Redirect to the same page to prevent resubmitting the form
            header("Location: {$_SERVER['PHP_SELF']}", true, 303);
            exit();
        } else {
            echo "Failed to upload message.";
        }
    } else {
        echo "Please fill in all fields.";
    }
}

// Function to get messages from the database
function getMessages($conn) {
    try {
        // Prepare SQL statement
        $stmt = $conn->query("SELECT * FROM berichten ORDER BY date_time DESC");
        
        // Fetch all rows
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $messages;
    } catch(PDOException $e) {
        // Handle errors
        echo "Error: " . $e->getMessage();
        return [];
    }
}

// Get messages from the database
$messages = getMessages($pdo);
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gastenboek</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }

        .container {
            max-width: 800px;
            margin: 20px auto; /* Add margin around the container */
            padding: 20px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .message {
            margin-bottom: 20px;
            padding: 10px;
            background-color: #f9f9f9;
            border-radius: 8px;
            box-shadow: 0 0 5px rgba(0, 0, 0, 0.1);
        }

        .message p {
            margin: 5px 0;
        }

        .message img {
            max-width: 100%;
            border-radius: 8px;
            max-height: 300px; /* Limiting maximum height */
        }

        form {
            margin-top: 20px; /* Move the form below the messages */
        }

        label {
            font-weight: bold;
        }

        input[type="text"],
        textarea {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }

        input[type="submit"] {
            background-color: #4caf50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        input[type="submit"]:hover {
            background-color: #45a049;
        }

        input[type="file"] {
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="container"> <!-- Add a container around all content -->
        <h1>Gastenboek</h1>
        <section class="send_form">
            <h2>Send a Message</h2>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data">
                <label for="name">Name:</label><br>
                <input type="text" id="name" name="name"><br>
                <label for="message">Message:</label><br>
                <textarea id="message" name="message" rows="4" cols="50"></textarea><br>
                <label for="image">Upload Image:</label><br>
                <input type="file" id="image" name="image"><br><br>
                <input type="submit" value="Send">
            </form>
        </section>
        <?php if (!empty($messages)): ?>
            <?php foreach ($messages as $message): ?>
                <div class="message">
                    <p><strong><?php echo htmlspecialchars($message['name']); ?>:</strong> <?php echo htmlspecialchars($message['message']); ?></p>
                    <?php if (!empty($message['image'])): ?>
                        <img src="uploads/<?php echo htmlspecialchars($message['image']); ?>" alt="Uploaded Image">
                    <?php endif; ?>
                    <p><em><?php echo htmlspecialchars($message['date_time']); ?></em></p>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>No messages yet.</p>
        <?php endif; ?>
    </div>
</body>
</html>
