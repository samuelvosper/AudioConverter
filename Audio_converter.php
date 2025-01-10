<?php
// audio_converter.php
session_start();

// Configuration
define('FFMPEG_PATH', 'C:\ProgramData\chocolatey\bin\ffmpeg.exe');
define('UPLOAD_DIR', sys_get_temp_dir());
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB

// Define allowed audio MIME types
$allowedMimeTypes = array(
    'audio/mpeg',
    'audio/wav',
    'audio/x-wav',
    'audio/mp3',
    'audio/ogg',
    'audio/x-m4a',
    'audio/m4a',
    'audio/aac'
);

function handleConversion($files, $outputFormat) {
    global $allowedMimeTypes;
    
    // Always set JSON content type at the start
    header('Content-Type: application/json');
    
    // Validate output format
    $allowedFormats = array('mp3', 'wav');
    if (!in_array($outputFormat, $allowedFormats)) {
        echo json_encode(array(
            'status' => 0,
            'error' => 'Invalid output format selected.'
        ));
        exit;
    }
    
    // Initialize results array
    $conversionResults = array();

    // Handle uploaded files
    $uploadedFiles = reArrayFiles($files);
    foreach ($uploadedFiles as $file) {
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $conversionResults[] = array(
                'filename' => basename($file['name']),
                'status' => 'error',
                'message' => getUploadErrorMessage($file['error'])
            );
            continue;
        }

        // Validate file type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowedMimeTypes)) {
            echo json_encode(array(
                'status' => 0,
                'error' => 'Invalid file type: Only audio files are allowed'
            ));
            exit;
        }

        // Validate file size
        if ($file['size'] > MAX_FILE_SIZE) {
            $conversionResults[] = array(
                'filename' => basename($file['name']),
                'status' => 'error',
                'message' => 'File too large (limit 10MB per file)'
            );
            continue;
        }

        // Process the conversion
        $result = convertFile($file, $outputFormat);
        $conversionResults[] = $result;
    }

    return $conversionResults;
}

function convertFile($file, $outputFormat) {
    $uploadPath = UPLOAD_DIR . '/' . basename($file['name']);
    
    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
        return array(
            'filename' => basename($file['name']),
            'status' => 'error',
            'message' => 'Failed to save uploaded file'
        );
    }

    $outputPath = UPLOAD_DIR . '/converted_' . time() . '_' . 
                 basename($file['name'], '.' . pathinfo($file['name'], PATHINFO_EXTENSION)) . 
                 '.' . $outputFormat;

    // Build FFmpeg command
    $ffmpegCommand = escapeshellcmd(FFMPEG_PATH) . " -i " . escapeshellarg($uploadPath);
    if ($outputFormat === 'mp3') {
        $ffmpegCommand .= " -acodec libmp3lame -ab 128k ";
    } else {
        $ffmpegCommand .= " -acodec pcm_s16le -ar 8000 -ac 1 ";
    }
    $ffmpegCommand .= escapeshellarg($outputPath) . " -y";

    exec($ffmpegCommand, $output, $returnCode);
    unlink($uploadPath); // Clean up the uploaded file

    if ($returnCode === 0) {
        return array(
            'filename' => basename($file['name']),
            'status' => 'success',
            'outputPath' => $outputPath
        );
    } else {
        return array(
            'filename' => basename($file['name']),
            'status' => 'error',
            'message' => 'Conversion failed'
        );
    }
}

function getUploadErrorMessage($error_code) {
    switch ($error_code) {
        case UPLOAD_ERR_INI_SIZE:
            return 'File exceeds upload_max_filesize directive in php.ini';
        case UPLOAD_ERR_FORM_SIZE:
            return 'File exceeds MAX_FILE_SIZE directive specified in the HTML form';
        case UPLOAD_ERR_PARTIAL:
            return 'File was only partially uploaded';
        case UPLOAD_ERR_NO_FILE:
            return 'No file was uploaded';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'Missing a temporary folder';
        case UPLOAD_ERR_CANT_WRITE:
            return 'Failed to write file to disk';
        case UPLOAD_ERR_EXTENSION:
            return 'File upload stopped by extension';
        default:
            return 'Unknown upload error';
    }
}

function reArrayFiles($files) {
    if (!is_array($files['name'])) {
        return array($files);
    }
    
    $fileArray = array();
    $fileCount = count($files['name']);
    $fileKeys = array_keys($files);

    for ($i = 0; $i < $fileCount; $i++) {
        foreach ($fileKeys as $key) {
            $fileArray[$i][$key] = $files[$key][$i];
        }
    }

    return $fileArray;
}

// Handle POST requests for conversion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    if (!isset($_FILES['files'])) {
        echo json_encode(array(
            'status' => 0,
            'error' => 'No files uploaded'
        ));
        exit;
    }

    $outputFormat = isset($_POST['format']) ? $_POST['format'] : 'mp3';
    $results = handleConversion($_FILES['files'], $outputFormat);
    
    // Check if any conversion failed
    $hasErrors = false;
    $errorMessage = '';
    foreach ($results as $result) {
        if ($result['status'] === 'error') {
            $hasErrors = true;
            $errorMessage = $result['message'];
            break;
        }
    }
    
    if ($hasErrors) {
        echo json_encode(array(
            'status' => 0,
            'error' => $errorMessage,
            'details' => $results
        ));
    } else {
        // Store successful results in session for download handling
        $_SESSION['conversion_results'] = $results;
        echo json_encode(array(
            'status' => 1,
            'data' => $results
        ));
    }
    exit;
}

// Handle download requests
if (isset($_GET['download'])) {
    $filePath = $_GET['download'];
    if (file_exists($filePath) && strpos(realpath($filePath), realpath(UPLOAD_DIR)) === 0) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        unlink($filePath);
        exit;
    }
}

// Handle ZIP downloads
if (isset($_GET['downloadAll'])) {
    $successfulConversions = empty($_SESSION['conversion_results']) ? array() : array_filter($_SESSION['conversion_results'], function($result) {
        return $result['status'] === 'success';
    });
    
    if (!empty($successfulConversions)) {
        $zipPath = createZipArchive($successfulConversions);
        
        if ($zipPath && file_exists($zipPath)) {
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="All Converted Files.zip"');
            header('Content-Length: ' . filesize($zipPath));
            readfile($zipPath);
            
            // Clean up ZIP file and converted files
            unlink($zipPath);
            foreach ($successfulConversions as $file) {
                if (file_exists($file['outputPath'])) {
                    unlink($file['outputPath']);
                }
            }
            
            // Clear the session data
            unset($_SESSION['conversion_results']);
            exit;
        }
    }
}

function createZipArchive($files) {
    $zipPath = UPLOAD_DIR . '/All Converted Files.zip';
    
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
        return false;
    }
    
    foreach ($files as $file) {
        if (file_exists($file['outputPath'])) {
            $originalName = pathinfo($file['filename'], PATHINFO_FILENAME);
            $extension = pathinfo($file['outputPath'], PATHINFO_EXTENSION);
            $newFilename = $originalName . '.' . $extension;
            
            $zip->addFile($file['outputPath'], $newFilename);
        }
    }
    
    $zip->close();
    return $zipPath;
}
