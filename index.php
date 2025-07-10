<?php
$autoloadPath = __DIR__ . '/vendor/autoload.php';

if (!file_exists($autoloadPath)) {
    die("❌ Autoload file not found at: " . $autoloadPath);
}

require $autoloadPath;
echo "✅ Autoloader loaded successfully.<br>";

// Try loading PhpSpreadsheet class
if (class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
    echo "✅ PhpSpreadsheet class loaded.";
} else {
    echo "❌ PhpSpreadsheet class NOT found.";
}


use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Excel banayein
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setCellValue('A1', 'Asghar Shaikh');
$sheet->setCellValue('B1', 'PhpSpreadsheet Working!');

// Excel save karein
$writer = new Xlsx($spreadsheet);
$writer->save('asghar.xlsx');

echo "✅ Excel file successfully created: asghar.xlsx";
