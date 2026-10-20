<?php
namespace InvoiceTool;

use Dompdf\Dompdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/** Renders a finalised invoice as PDF or Excel (generated on demand, nothing stored on disk). */
class Documents
{
    /** @var string */
    private $jiraBase;

    public function __construct(string $jiraBase)
    {
        $this->jiraBase = rtrim($jiraBase, '/');
    }

    public function pdf(array $invoice, array $user): string
    {
        $jiraBase = $this->jiraBase;
        ob_start();
        require __DIR__ . '/../templates/pdf.php';
        $html = ob_get_clean();

        $dompdf = new Dompdf(['isRemoteEnabled' => false]);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('letter');
        $dompdf->render();
        return $dompdf->output();
    }

    /**
     * Excel sheet in the agreed layout:
     * Invoice # | Project | JIRA/Project | Bill Code | Details (link + title) | Time | Amount
     */
    public function xlsx(array $invoice, array $user): string
    {
        $book  = new Spreadsheet();
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Invoice');
        $book->getDefaultStyle()->getFont()->setName('Helvetica Neue')->setSize(8);

        $sheet->fromArray(['', 'Project', 'JIRA/Project', 'Bill Code', 'Details', 'Time ', 'Amount'], null, 'A1');
        $sheet->getStyle('A1:G1')->getFont()->setSize(9);

        foreach (['A' => 9.75, 'B' => 20, 'C' => 11, 'D' => 11, 'E' => 58.75, 'F' => 11.38, 'G' => 13.13] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        // Items are per ticket per day; the sheet shows one row per ticket
        $tickets = [];
        foreach ($invoice['items'] as $item) {
            $group = $item['ticket_key'] !== '' ? $item['ticket_key'] : 'task:' . $item['title'];
            if (isset($tickets[$group])) {
                $tickets[$group]['seconds'] += (int) $item['seconds'];
            } else {
                $tickets[$group] = $item;
            }
        }

        $rate = (float) $invoice['rate'];
        $row  = 2;
        foreach ($tickets as $item) {
            $url = $item['ticket_key'] !== '' ? $this->jiraBase . '/browse/' . $item['ticket_key'] : '';
            $sheet->setCellValueExplicit("A$row", $invoice['invoice_no'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValue("B$row", $item['project_name']);
            $sheet->setCellValue("C$row", $item['ticket_key']);
            $sheet->setCellValue("D$row", $item['bill_code']);
            $sheet->setCellValue("E$row", trim($url . "\n" . $item['title']));
            $sheet->setCellValue("F$row", Format::hours((int) $item['seconds']));
            $sheet->setCellValue("G$row", Format::amount((int) $item['seconds'], $rate));
            if ($url !== '') {
                $sheet->getCell("E$row")->getHyperlink()->setUrl($url);
                $sheet->getStyle("E$row")->getFont()->setUnderline(true)->getColor()->setARGB('FF0000FF');
            }
            $row++;
        }

        $last = $row - 1;
        $sheet->getStyle("A2:G$last")->getAlignment()->setHorizontal('left')->setVertical('center')->setWrapText(true);
        $sheet->getStyle("C2:C$last")->getAlignment()->setWrapText(false); // keep ticket keys on one line
        $sheet->getStyle("F2:G$last")->getAlignment()->setHorizontal('right');
        $sheet->getStyle("F2:F$last")->getNumberFormat()->setFormatCode('0.00');
        $sheet->getStyle("G2:G$last")->getNumberFormat()->setFormatCode('_(* #,##0.00_);_(* \(#,##0.00\);_(* "-"??_);_(@_)');

        ob_start();
        (new Xlsx($book))->save('php://output');
        return ob_get_clean();
    }
}
