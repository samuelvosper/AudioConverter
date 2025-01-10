<?php
// index.php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Convert Deez</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 2rem auto; padding: 0 15px; }
        .container { background: #f9f9f9; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); }
        form { margin-bottom: 20px; }
        label { display: block; margin: 10px 0 5px; }
        select, button { display: block; width: 100%; padding: 10px; margin-bottom: 15px; }
        button { background: #007bff; color: #fff; border: none; cursor: pointer; }
        button:hover { background: #0056b3; }

        /* Drop Zone Styles */
        .drop-zone {
            border: 2px dashed #007bff;
            border-radius: 8px;
            padding: 30px;
            text-align: center;
            background: #f8f9fa;
            cursor: pointer;
            margin-bottom: 20px;
            -webkit-transition: all 0.2s ease-in-out;
            -moz-transition: all 0.2s ease-in-out;
            -o-transition: all 0.2s ease-in-out;
            transition: all 0.2s ease-in-out;
        }

        .drop-zone.dragover {
            background: #e3f2fd;
            border-color: #0056b3;
            -webkit-transform: scale(1.02);
            -moz-transform: scale(1.02);
            -ms-transform: scale(1.02);
            -o-transform: scale(1.02);
            transform: scale(1.02);
            -webkit-box-shadow: 0 0 15px rgba(0, 123, 255, 0.2);
            -moz-box-shadow: 0 0 15px rgba(0, 123, 255, 0.2);
            box-shadow: 0 0 15px rgba(0, 123, 255, 0.2);
        }

        .drop-zone-content {
            color: #666;
        }

        .drop-zone-content p {
            margin: 5px 0;
        }

        .drop-zone-content p:first-child {
            font-size: 1.2em;
            color: #007bff;
        }

        /* Hide default file input */
        #files {
            display: none;
        }

        /* Selected Files List */
        .selected-files-list {
            margin: 10px 0;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            max-height: 200px;
            overflow-y: auto;
        }

        .file-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #e9ecef;
            padding: 8px 12px;
            margin: 5px 0;
            border-radius: 4px;
            -webkit-transition: background-color 0.2s;
            -moz-transition: background-color 0.2s;
            -o-transition: background-color 0.2s;
            transition: background-color 0.2s;
        }

        .file-item:hover {
            background: #dee2e6;
        }

        .file-item.invalid {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
        }

        .file-item.valid {
            border-left: 4px solid #28a745;
        }

        .remove-file {
            background: #dc3545;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            cursor: pointer;
        }

        .remove-file:hover {
            background: #c82333;
        }

        /* Progress Indicators */
        .upload-progress {
            height: 4px;
            background: #e9ecef;
            margin-top: 10px;
            border-radius: 2px;
            overflow: hidden;
        }

        .upload-progress-bar {
            height: 100%;
            background: #007bff;
            width: 0;
            -webkit-transition: width 0.3s ease;
            -moz-transition: width 0.3s ease;
            -o-transition: width 0.3s ease;
            transition: width 0.3s ease;
        }

        #conversionProgress {
            width: 100%;
            min-height: 100px;
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #ccc;
            padding: 10px;
            margin-top: 20px;
            background: #fff;
            font-family: monospace;
        }

        .success { color: green; }
        .error { color: red; }

        /* Download Links */
        #downloadLinks {
            margin-top: 20px;
        }

        .download-link {
            display: inline-block;
            padding: 5px 10px;
            margin: 5px;
            background: #28a745;
            color: white;
            text-decoration: none;
            border-radius: 4px;
        }

        .download-link:hover {
            background: #218838;
        }

        /* Mobile Responsiveness */
        @media (max-width: 600px) {
            body {
                margin: 1rem;
            }
            
            .container {
                padding: 15px;
            }
            
            .drop-zone {
                padding: 15px;
            }
            
            .download-link {
                display: block;
                margin: 10px 0;
                text-align: center;
            }
        }

        /* IE9+ Compatibility */
        .clearfix:after {
            content: "";
            display: table;
            clear: both;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Convert Deez</h1>
        <div id="dropZone" class="drop-zone">
            <div class="drop-zone-content">
                <p>Drag and drop audio files here</p>
                <p>- or -</p>
                <p>Click to select files</p>
            </div>
        </div>
        <form id="converterForm" enctype="multipart/form-data">
            <input type="file" id="files" name="files[]" accept="audio/*" multiple="multiple" required="required">
            <div id="selectedFiles" class="selected-files-list"></div>
            
            <label for="format">Output Format:</label>
            <select id="format" name="format">
                <option value="mp3">MP3 (128kbps)</option>
                <option value="wav">WAV (16-bit, 8kHz, Mono)</option>
            </select>
            
            <button type="submit">Make the audio do the thing</button>
        </form>

        <div id="conversionProgress" class="clearfix">
            No conversions yet. Select files to begin.
        </div>

        <div id="downloadLinks" class="clearfix"></div>
    </div>

    <script src="converter.js"></script>
</body>
</html>