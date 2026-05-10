<?php
require_once __DIR__ . '/../config/database.php';
requireLogin();
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

$accountId = (int) ($_GET['account_id'] ?? 0);
$format = strtolower(trim((string) ($_GET['format'] ?? 'csv')));
$dateFrom = trim((string) ($_GET['date_from'] ?? ''));
$dateTo = trim((string) ($_GET['date_to'] ?? ''));

if (!in_array($format, ['csv', 'xlsx', 'pdf'], true)) {
    $format = 'csv';
}

$isValidDate = static function (string $value): bool {
    if ($value === '') {
        return true;
    }
    $dt = DateTime::createFromFormat('Y-m-d', $value);
    return $dt instanceof DateTime && $dt->format('Y-m-d') === $value;
};

if (!$isValidDate($dateFrom)) {
    $dateFrom = '';
}
if (!$isValidDate($dateTo)) {
    $dateTo = '';
}
if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
    [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
}

if ($accountId > 0 && !findAccount($accountId)) {
    $accountId = 0;
}
if (!isAdmin() && $accountId === 0) {
    $accountId = currentUserAccountId() ?? 0;
}

$account = $accountId > 0 ? findAccount($accountId) : null;
$scopeLabel = $account ? $account['account_name'] : 'Overall Report';
$periodLabel = ($dateFrom !== '' || $dateTo !== '')
    ? (($dateFrom !== '' ? $dateFrom : 'Start') . ' to ' . ($dateTo !== '' ? $dateTo : 'Today'))
    : 'All dates';

$params = [];
$where = 'WHERE t.deleted_at IS NULL';
if ($accountId > 0) {
    $where .= ' AND t.account_id = :account_id';
    $params['account_id'] = $accountId;
}
if ($dateFrom !== '') {
    $where .= ' AND t.transaction_date >= :date_from';
    $params['date_from'] = $dateFrom;
}
if ($dateTo !== '') {
    $where .= ' AND t.transaction_date <= :date_to';
    $params['date_to'] = $dateTo;
}

$stmt = db()->prepare(
    "SELECT t.transaction_date, a.account_name, t.transaction_type, t.category, t.amount, t.status, t.running_balance, t.description
     FROM transactions t
     JOIN accounts a ON a.id = t.account_id
     $where
     ORDER BY t.transaction_date DESC, t.id DESC"
);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$filenameBase = 'atm-audit-report-' . date('Ymd-His');

if ($format === 'xlsx') {
    $sheetHeaders = ['Date', 'Account', 'Type', 'Category', 'Amount', 'Status', 'Running Balance', 'Description'];

    $spreadsheet = new PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Report');

    $sheet->setCellValue('A1', 'ATM Audit and Liquidation Report');
    $sheet->setCellValue('A2', 'Scope: ' . $scopeLabel);
    $sheet->setCellValue('A3', 'Period: ' . $periodLabel);
    $sheet->setCellValue('A4', 'Generated: ' . date('Y-m-d H:i:s'));

    $headerRow = 6;
    foreach ($sheetHeaders as $index => $header) {
        $column = Coordinate::stringFromColumnIndex($index + 1);
        $sheet->setCellValue($column . $headerRow, $header);
    }

    $rowIndex = $headerRow + 1;
    foreach ($rows as $row) {
        $sheet->setCellValue('A' . $rowIndex, (string) $row['transaction_date']);
        $sheet->setCellValue('B' . $rowIndex, (string) $row['account_name']);
        $sheet->setCellValue('C' . $rowIndex, (string) $row['transaction_type']);
        $sheet->setCellValue('D' . $rowIndex, (string) $row['category']);
        $sheet->setCellValue('E' . $rowIndex, (float) $row['amount']);
        $sheet->setCellValue('F' . $rowIndex, (string) $row['status']);
        $sheet->setCellValue('G' . $rowIndex, (float) $row['running_balance']);
        $sheet->setCellValue('H' . $rowIndex, (string) ($row['description'] ?? ''));
        $rowIndex++;
    }

    $sheet->getStyle('A6:H6')->getFont()->setBold(true);
    $sheet->getStyle('E7:G' . max(7, $rowIndex))->getNumberFormat()->setFormatCode('#,##0.00');
    $sheet->getStyle('A6:H' . max(6, $rowIndex - 1))->getAlignment()->setVertical(PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP);
    $sheet->getStyle('H7:H' . max(7, $rowIndex))->getAlignment()->setWrapText(true);

    foreach (range('A', 'H') as $column) {
        $sheet->getColumnDimension($column)->setAutoSize(true);
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filenameBase . '.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

if ($format === 'pdf') {
    $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator(APP_NAME);
    $pdf->SetAuthor(APP_NAME);
    $pdf->SetTitle('ATM Audit Report');
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(10, 10, 10);
    $pdf->SetAutoPageBreak(true, 10);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->AddPage();

    $html = '<h2 style="margin:0 0 6px 0;">ATM Audit and Liquidation Report</h2>';
    $html .= '<div style="margin-bottom:8px;"><strong>Scope:</strong> ' . htmlspecialchars($scopeLabel, ENT_QUOTES, 'UTF-8') . '<br><strong>Period:</strong> ' . htmlspecialchars($periodLabel, ENT_QUOTES, 'UTF-8') . '<br><strong>Generated:</strong> ' . date('Y-m-d H:i:s') . '</div>';
    $html .= '<table border="1" cellpadding="4" cellspacing="0" width="100%">';
    $html .= '<thead><tr style="font-weight:bold;background-color:#f3f4f6;"><th width="10%">Date</th><th width="15%">Account</th><th width="12%">Type</th><th width="12%">Category</th><th width="9%">Amount</th><th width="12%">Status</th><th width="10%">Running Bal.</th><th width="20%">Description</th></tr></thead><tbody>';

    if (!$rows) {
        $html .= '<tr><td colspan="8" align="center">No transactions found for the selected scope.</td></tr>';
    } else {
        foreach ($rows as $row) {
            $html .= '<tr>'
                . '<td>' . htmlspecialchars((string) $row['transaction_date'], ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td>' . htmlspecialchars((string) $row['account_name'], ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td>' . htmlspecialchars((string) $row['transaction_type'], ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td>' . htmlspecialchars((string) $row['category'], ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td align="right">' . number_format((float) $row['amount'], 2) . '</td>'
                . '<td>' . htmlspecialchars((string) $row['status'], ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td align="right">' . number_format((float) $row['running_balance'], 2) . '</td>'
                . '<td>' . htmlspecialchars((string) ($row['description'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>'
                . '</tr>';
        }
    }

    $html .= '</tbody></table>';
    $pdf->writeHTML($html, true, false, true, false, '');

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filenameBase . '.pdf"');
    $pdf->Output($filenameBase . '.pdf', 'D');
    exit;
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filenameBase . '.csv"');

$out = fopen('php://output', 'w');
fputcsv($out, ['ATM Audit and Liquidation Report']);
fputcsv($out, ['Scope', $scopeLabel]);
fputcsv($out, ['Period', $periodLabel]);
fputcsv($out, ['Generated', date('Y-m-d H:i:s')]);
fputcsv($out, []);
fputcsv($out, ['Date', 'Account', 'Type', 'Category', 'Amount', 'Status', 'Running Balance', 'Description']);
foreach ($rows as $row) {
    fputcsv($out, $row);
}
fclose($out);
exit;
