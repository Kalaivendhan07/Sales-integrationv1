<?php
/**
 * Sales Data Upload Interface
 * PHP 5.3 Compatible
 */

require_once __DIR__ . '/../classes/IntegrationProcessor.php';

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['sales_file'])) {
    try {
        $uploadDir = __DIR__ . '/../uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $fileName = $_FILES['sales_file']['name'];
        $tmpName = $_FILES['sales_file']['tmp_name'];
        $fileSize = $_FILES['sales_file']['size'];
        $fileError = $_FILES['sales_file']['error'];
        
        // Validate file
        if ($fileError !== UPLOAD_ERR_OK) {
            throw new Exception('File upload error: ' . $fileError);
        }
        
        if ($fileSize > 10 * 1024 * 1024) { // 10MB limit
            throw new Exception('File size too large. Maximum 10MB allowed.');
        }
        
        $allowedExtensions = array('csv', 'xls', 'xlsx');
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        if (!in_array($fileExtension, $allowedExtensions)) {
            throw new Exception('Invalid file type. Only CSV, XLS, and XLSX files are allowed.');
        }
        
        // Save uploaded file
        $savedFileName = 'sales_' . date('Ymd_His') . '_' . rand(1000, 9999) . '.' . $fileExtension;
        $savedFilePath = $uploadDir . $savedFileName;
        
        if (!move_uploaded_file($tmpName, $savedFilePath)) {
            throw new Exception('Failed to save uploaded file.');
        }
        
        // Process the file
        $processor = new IntegrationProcessor();
        $result = $processor->processSalesFile($savedFilePath, $fileExtension == 'csv' ? 'csv' : 'excel');
        
        // Clean up uploaded file
        unlink($savedFilePath);
        
        if ($result['status'] == 'SUCCESS') {
            $message = "<strong>Processing Completed Successfully!</strong><br>";
            $message .= "Batch ID: {$result['batch_id']}<br>";
            $message .= "Total Records: {$result['total_records']}<br>";
            $message .= "Processed: {$result['processed_records']}<br>";
            $message .= "New Opportunities: {$result['new_opportunities']}<br>";
            $message .= "Updated Opportunities: {$result['updated_opportunities']}<br>";
            $message .= "Failed Records: {$result['failed_records']}<br>";
            $message .= "DSM Actions Created: {$result['dsm_actions_created']}<br>";
            
            if (!empty($result['messages'])) {
                $message .= "<br><strong>Processing Messages:</strong><br>";
                $message .= implode('<br>', array_slice($result['messages'], 0, 10)); // Show first 10 messages
                if (count($result['messages']) > 10) {
                    $message .= "<br>... and " . (count($result['messages']) - 10) . " more messages";
                }
            }
            
            $messageType = 'success';
        } else {
            $message = "<strong>Processing Failed!</strong><br>";
            $message .= implode('<br>', $result['errors']);
            $messageType = 'danger';
        }
        
    } catch (Exception $e) {
        $message = '<strong>Error:</strong> ' . $e->getMessage();
        $messageType = 'danger';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Sales Data - Pipeline Manager Integration</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header { border-bottom: 3px solid #007bff; padding-bottom: 20px; margin-bottom: 30px; }
        .header h1 { color: #007bff; margin: 0; }
        .btn { background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; display: inline-block; margin: 5px; border: none; cursor: pointer; }
        .btn:hover { background: #0056b3; }
        .btn-secondary { background: #6c757d; }
        .btn-secondary:hover { background: #545b62; }
        .form-group { margin: 20px 0; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; color: #495057; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #ced4da; border-radius: 4px; font-size: 16px; }
        .alert { padding: 15px; margin: 20px 0; border-radius: 4px; }
        .alert-success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .alert-danger { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        .alert-info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; }
        .file-requirements { background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 6px; padding: 15px; margin: 15px 0; }
        .sample-data { background: #e9ecef; padding: 10px; border-radius: 4px; font-family: monospace; font-size: 12px; overflow-x: auto; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Upload Sales Data</h1>
            <p>Upload CSV or Excel files for sales-opportunity integration processing</p>
        </div>

        <a href="../index.php" class="btn btn-secondary">← Back to Dashboard</a>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="sales_file">Select Sales Data File:</label>
                <input type="file" name="sales_file" id="sales_file" class="form-control" accept=".csv,.xls,.xlsx" required>
            </div>

            <button type="submit" class="btn">
                🚀 Process Sales Data
            </button>
        </form>

        <div class="file-requirements">
            <h3>File Requirements</h3>
            <ul>
                <li><strong>Supported Formats:</strong> CSV, XLS, XLSX</li>
                <li><strong>Maximum File Size:</strong> 10MB</li>
                <li><strong>Expected Columns:</strong>
                    <ul>
                        <li>Invoice Date. (Format: YYYYMMDD)</li>
                        <li>DSR Name</li>
                        <li>Customer Name</li>
                        <li>Sector</li>
                        <li>Sub Sector</li>
                        <li>SKU Code</li>
                        <li>Volume (L)</li>
                        <li>Invoice No.</li>
                        <li>Registration No (GSTIN)</li>
                        <li>Product Family</li>
                    </ul>
                </li>
            </ul>
        </div>

        <div class="file-requirements">
            <h3>Sample CSV Format</h3>
            <div class="sample-data">
Invoice Date.,DSR Name,Customer Name,Sector,Sub Sector,SKU Code,Volume (L),Invoice No.,Registration No,Product Family<br>
20250804,Vashisth Suthar,FCC CLUTCH INDIA PVT. LTD,General Manufacturing,GM Other,550065314,360.00,SHELL/2021-22/11558,24AACCF4739N1ZC,Gadus<br>
20250804,Maulik Pandit,Nova Textile Pvt Ltd,B2B Other,Textile,550031252,209.00,SHELL/2021-22/11560,24AAECN0382K1ZH,Morlina
            </div>
        </div>

        <div class="alert alert-info">
            <strong>Processing Information:</strong><br>
            • The system will validate each record through a 6-level hierarchy<br>
            • New opportunities will be created automatically for new GSTINs<br>
            • Existing opportunities will be updated based on validation rules<br>
            • DSM actions will be created for records requiring manual intervention<br>
            • Complete audit trail will be maintained for all changes<br>
            • Processing supports 500+ records per batch
        </div>
    </div>
</body>
</html>