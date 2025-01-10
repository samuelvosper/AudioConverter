// converter.js
var selectedFiles = []; // Changed let to var for older browser compatibility

// Define allowed MIME types and max file size
var ALLOWED_MIME_TYPES = [
    'audio/mpeg',
    'audio/wav',
    'audio/x-wav',
    'audio/mp3',
    'audio/ogg',
    'audio/x-m4a',
    'audio/aac'
];
var MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB

// Add drag and drop functionality
var dropZone = document.getElementById('dropZone');
var fileInput = document.getElementById('files');

// Make drop zone clickable
dropZone.addEventListener('click', function() {
    fileInput.click();
});

// Prevent defaults for all drag events
['dragenter', 'dragover', 'dragleave', 'drop'].forEach(function(eventName) {
    dropZone.addEventListener(eventName, preventDefaults, false);
    document.body.addEventListener(eventName, preventDefaults, false);
});

function preventDefaults(e) {
    e.preventDefault();
    e.stopPropagation();
}

// Visual feedback for drag events
['dragenter', 'dragover'].forEach(function(eventName) {
    dropZone.addEventListener(eventName, highlight, false);
});

['dragleave', 'drop'].forEach(function(eventName) {
    dropZone.addEventListener(eventName, unhighlight, false);
});

function highlight(e) {
    dropZone.className += ' dragover';
}

function unhighlight(e) {
    dropZone.className = dropZone.className.replace(/\bdragover\b/g, '');
}

// Handle dropped files
dropZone.addEventListener('drop', handleDrop, false);

function handleDrop(e) {
    var dt = e.dataTransfer;
    var newFiles = Array.prototype.slice.call(dt.files);
    
    // Filter for valid audio files
    var validFiles = newFiles.filter(function(file) {
        // Check file type
        if (ALLOWED_MIME_TYPES.indexOf(file.type) === -1) {
            console.warn(file.name + ' skipped: Invalid file type');
            return false;
        }
        
        // Check file size
        if (file.size > MAX_FILE_SIZE) {
            console.warn(file.name + ' skipped: File too large (max 10MB)');
            return false;
        }
        
        return true;
    });
    
    if (validFiles.length < newFiles.length) {
        alert('Some files were skipped:\n- File must be audio format\n- Maximum size is 10MB');
    }
    
    validFiles.forEach(function(file) {
        var fileExists = selectedFiles.some(function(existingFile) {
            return existingFile.name === file.name;
        });
        if (!fileExists) {
            selectedFiles.push(file);
        }
    });
    
    updateFileList();
}

document.getElementById('files').addEventListener('change', function(e) {
    var newFiles = Array.prototype.slice.call(e.target.files);
    
    newFiles.forEach(function(file) {
        var fileExists = selectedFiles.some(function(existingFile) {
            return existingFile.name === file.name;
        });
        if (!fileExists) {
            selectedFiles.push(file);
        }
    });

    updateFileList();
});

document.getElementById('selectedFiles').addEventListener('click', function(e) {
    if (e.target.className === 'remove-file') {
        var indexToRemove = parseInt(e.target.getAttribute('data-index'), 10);
        selectedFiles.splice(indexToRemove, 1);
        updateFileList();
    }
});

function updateFileList() {
    var selectedFilesDiv = document.getElementById('selectedFiles');
    selectedFilesDiv.innerHTML = '';
    
    selectedFiles.forEach(function(file, index) {
        var fileDiv = document.createElement('div');
        fileDiv.className = 'file-item';
        fileDiv.innerHTML = 
            '<span>' + file.name + '</span>' +
            '<span class="remove-file" data-index="' + index + '">✕</span>';
        selectedFilesDiv.appendChild(fileDiv);
    });

    // Update form data
    if (window.DataTransfer && document.getElementById('files').files) {
        try {
            var dt = new DataTransfer();
            selectedFiles.forEach(function(file) {
                dt.items.add(file);
            });
            document.getElementById('files').files = dt.files;
        } catch (e) {
            console.warn('DataTransfer API not fully supported');
        }
    }
}

document.getElementById('converterForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    if (selectedFiles.length === 0) {
        alert('Please select files to convert');
        return;
    }

    var progressDiv = document.getElementById('conversionProgress');
    var downloadLinksDiv = document.getElementById('downloadLinks');
    progressDiv.innerHTML = 'Starting conversion...\n';
    downloadLinksDiv.innerHTML = '';

    var formData = new FormData();
    selectedFiles.forEach(function(file) {
        formData.append('files[]', file);
    });
    formData.append('format', document.getElementById('format').value);

    // Use XMLHttpRequest for better compatibility
    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'audio_converter.php', true);

    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            if (xhr.status === 200) {
                try {
                    var response = JSON.parse(xhr.responseText);
                    displayResults(response);
                } catch (jsonError) {
                    progressDiv.innerHTML = 
                        '<div class="error">' +
                        '<strong>JSON Parsing Error:</strong> ' + jsonError.message + '<br>' +
                        '<strong>Raw Server Response:</strong><br>' +
                        '<pre style="background: #f5f5f5; padding: 10px; margin-top: 5px; white-space: pre-wrap;">' + 
                        xhr.responseText + '</pre></div>';
                }
            } else {
                progressDiv.innerHTML = '<div class="error">Server Error: ' + xhr.status + '</div>';
            }
        }
    };

    xhr.send(formData);
});

function displayResults(response) {
    var progressDiv = document.getElementById('conversionProgress');
    var downloadLinksDiv = document.getElementById('downloadLinks');
    progressDiv.innerHTML = '';
    downloadLinksDiv.innerHTML = '';
    
    var successCount = 0;

    if (response.status === 0) {
        // Error case
        var errorDiv = document.createElement('div');
        errorDiv.className = 'error';
        errorDiv.innerHTML = 
            'Error Message: ' + response.error + '<br>' +
            'Raw Server Response: <pre>' + JSON.stringify(response, null, 2) + '</pre>';
        progressDiv.appendChild(errorDiv);
        return;
    }

    // Success case - process each result
    response.data.forEach(function(result) {
        var div = document.createElement('div');
        if (result.status === 'success') {
            successCount++;
            div.className = 'success';
            div.textContent = '✓ Successfully converted: ' + result.filename;
            
            var downloadLink = document.createElement('a');
            downloadLink.href = 'audio_converter.php?download=' + encodeURIComponent(result.outputPath);
            downloadLink.className = 'download-link';
            downloadLink.textContent = 'Download ' + result.filename;
            downloadLinksDiv.appendChild(downloadLink);
        } else {
            div.className = 'error';
            div.textContent = '✗ ' + result.message + ' (' + result.filename + ')';
        }
        progressDiv.appendChild(div);
    });

    if (successCount > 1) {
        var downloadAllLink = document.createElement('a');
        downloadAllLink.href = 'audio_converter.php?downloadAll=true';
        downloadAllLink.className = 'download-link';
        downloadAllLink.style.background = '#FFA500';
        downloadAllLink.textContent = 'Download All Files';
        downloadLinksDiv.appendChild(downloadAllLink);
    }

    // Clear selected files after conversion attempts
    selectedFiles = [];
    updateFileList();
}