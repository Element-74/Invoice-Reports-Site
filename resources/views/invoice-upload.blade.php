<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LBS Invoice Report Generator</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #1a1a3e 0%, #ed1c24 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 40px;
            max-width: 500px;
            width: 100%;
        }

        .logo {
            position: fixed;
            top: 20px;
            left: 20px;
            width: 120px;
            height: auto;
            z-index: 1000;
        }

        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }

        p {
            color: #666;
            margin-bottom: 30px;
        }

        .upload-area {
            border: 2px dashed #ddd;
            border-radius: 8px;
            padding: 40px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            background: #fafafa;
        }

        .upload-area:hover {
            border-color: #667eea;
            background: #f0f0ff;
        }

        .upload-area.dragover {
            border-color: #667eea;
            background: #e6e9ff;
        }

        input[type="file"] {
            display: none;
        }

        .upload-icon {
            font-size: 48px;
            margin-bottom: 15px;
            color: #667eea;
        }

        .upload-text {
            color: #333;
            font-weight: 500;
            margin-bottom: 5px;
        }

        .upload-subtext {
            color: #999;
            font-size: 14px;
        }

        .file-name {
            margin-top: 15px;
            padding: 10px;
            background: #e6e9ff;
            border-radius: 6px;
            color: #667eea;
            font-weight: 500;
        }

        button {
            width: 100%;
            padding: 15px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 20px;
            transition: background 0.3s ease;
        }

        button:hover {
            background: #5568d3;
        }

        button:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-error {
            background: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }

        .alert-success {
            background: #efe;
            color: #3c3;
            border: 1px solid #cfc;
        }
    </style>
</head>
<body>
    <img src="{{ asset('storage/images/_RL_Primary_Red.png') }}" alt="Red Letter Logo" class="logo">
    <div class="container">
        <h1>Invoice Report Generator</h1>
        <p>Upload your Excel file to generate a PDF report</p>

        @if(session('error'))
            <div class="alert alert-error">
                {{ session('error') }}
            </div>
        @endif

        @if(session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif

        <form action="{{ route('invoice.upload') }}" method="POST" enctype="multipart/form-data" id="uploadForm">
            @csrf

            <div class="upload-area" id="uploadArea">
                <div class="upload-icon">📄</div>
                <div class="upload-text">Click to upload or drag and drop</div>
                <div class="upload-subtext">Excel files only (.xlsx, .xls)</div>
                <input type="file" name="invoice_file" id="fileInput" accept=".xlsx,.xls" required>
                <div class="file-name" id="fileName" style="display: none;"></div>
            </div>

            @error('invoice_file')
                <div class="alert alert-error" style="margin-top: 15px;">
                    {{ $message }}
                </div>
            @enderror

            <button type="submit" id="submitBtn" disabled>Generate PDF Report</button>
        </form>
    </div>

    <script>
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('fileInput');
        const fileName = document.getElementById('fileName');
        const submitBtn = document.getElementById('submitBtn');

        // Click to upload - simpler version
        uploadArea.addEventListener('click', function() {
            fileInput.click();
        });

        // File selected
        fileInput.addEventListener('change', function(e) {
            if (e.target.files.length > 0) {
                handleFile(e.target.files[0]);
            }
        });

        function handleFile(file) {
            if (file) {
                fileName.textContent = '📎 ' + file.name;
                fileName.style.display = 'block';
                submitBtn.disabled = false;
            }
        }

        // Prevent default drag behaviors
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            uploadArea.addEventListener(eventName, function(e) {
                e.preventDefault();
                e.stopPropagation();
            }, false);
        });

        // Highlight on drag over
        ['dragenter', 'dragover'].forEach(eventName => {
            uploadArea.addEventListener(eventName, function() {
                uploadArea.classList.add('dragover');
            }, false);
        });

        // Remove highlight on drag leave or drop
        ['dragleave', 'drop'].forEach(eventName => {
            uploadArea.addEventListener(eventName, function() {
                uploadArea.classList.remove('dragover');
            }, false);
        });

        // Handle dropped files
        uploadArea.addEventListener('drop', function(e) {
            const files = e.dataTransfer.files;

            if (files.length > 0) {
                fileInput.files = files;
                handleFile(files[0]);
            }
        }, false);
    </script>
</body>
</html>
