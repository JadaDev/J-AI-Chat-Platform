<?php
// Security measures
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'");

// Get the posted code
$html = isset($_POST['html']) ? $_POST['html'] : '';
$css = isset($_POST['css']) ? $_POST['css'] : '';
$js = isset($_POST['js']) ? $_POST['js'] : '';

// Sanitize the input (basic sanitization)
function sanitizeCode($code) {
    // Remove any PHP tags
    $code = preg_replace('/<\?.*?\?>/s', '', $code);
    return $code;
}

$html = sanitizeCode($html);
$css = sanitizeCode($css);
$js = sanitizeCode($js);

// Determine if dark mode should be used based on parent frame
$isDarkMode = isset($_COOKIE['theme']) && $_COOKIE['theme'] === 'dark';
$bgColor = $isDarkMode ? '#1e293b' : '#ffffff';
$textColor = $isDarkMode ? '#ffffff' : '#0f172a';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Code Preview</title>
    <style>
        /* Default styling */
        body {
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 20px;
            background-color: <?php echo $bgColor; ?>;
            color: <?php echo $textColor; ?>;
        }
        
        /* Container for preview header */
        .preview-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            margin-bottom: 20px;
            border-bottom: 1px solid #ccc;
        }
        
        .preview-title {
            font-weight: bold;
            font-size: 18px;
        }
        
        .preview-container {
            border: 1px solid #ccc;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            background-color: white;
            color: black;
        }
        
        /* Apply custom CSS */
        <?php echo $css; ?>
    </style>
</head>
<body>
    <div class="preview-header">
        <div class="preview-title">Code Preview</div>
    </div>
    
    <div class="preview-container">
        <?php echo $html; ?>
    </div>
    
    <script>
        // Execute the JavaScript after the HTML is loaded
        document.addEventListener('DOMContentLoaded', function() {
            try {
                <?php echo $js; ?>
            } catch (error) {
                console.error('JavaScript execution error:', error);
            }
        });
    </script>
</body>
</html>