<!DOCTYPE html>
<html>
<head>
	<style>
        .error-container {
            margin: 20px;
            padding: 20px;
            border: 1px solid #ff0000;
            border-radius: 5px;
            background-color: #fff5f5;
            font-family: Arial, sans-serif;
        }
        .error-title {
            color: #cc0000;
            font-size: 1.2em;
            margin-bottom: 10px;
        }
        .error-details {
            margin: 10px 0;
            padding: 10px;
            background-color: #ffffff;
            border-left: 3px solid #cc0000;
        }
        .error-trace {
            font-family: monospace;
            white-space: pre-wrap;
            margin-top: 10px;
            padding: 10px;
            background-color: #f8f8f8;
            font-size: 0.9em;
        }
	</style>
</head>
<body>
<div class="error-container">
	<div class="error-title">Error Code: <?php echo $errorCode; ?></div>
	<div class="error-details">
		<strong>Message:</strong> <?php echo $errorMessage; ?><br>
		<strong>File:</strong> <?php echo $errorFile; ?><br>
		<strong>Line:</strong> <?php echo $errorLine; ?>
	</div>
	<div class="error-trace">
		<strong>Stack Trace:</strong><br>
		<?php echo $trace; ?>
	</div>
</div>
</body>
</html>