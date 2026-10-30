<?php

namespace App\Services\Reports;

use App\Support\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ReportExporter
{
    /** Indian digit grouping (12,34,56,789.00) with negatives in brackets. */
    public const INR_FORMAT = '[>=10000000]##\,##\,##\,##0.00;[>=100000]##\,##\,##0.00;##,##0.00';
    public const PDF_ROW_LIMIT = 1500;

    private function filename(array $r, string $ext): string
    {
        return Str::slug((Tenant::company()?->code ?? 'assetpulse').' '.$r['title']).'-'.now()->format('Ymd-His').'.'.$ext;
    }

    public function xlsx(array $r)
    {
        $company = Tenant::company();
        $book = new Spreadsheet;
        $book->getProperties()->setCreator('AssetPulse')->setTitle($r['title'])->setCompany($company?->name ?? '');
        $ws = $book->getActiveSheet();
        $ws->setTitle(Str::limit(preg_replace('/[\\\\\/\?\*\[\]:]/', '', $r['title']), 28, ''));
        $cols = $r['columns'];
        $n = max(1, count($cols));
        $last = Coordinate::stringFromColumnIndex($n);

        $ws->setCellValue('A1', $company?->legal_name ?: $company?->name);
        $ws->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $addr = collect([$company?->address_line1, $company?->city, $company?->state])->filter()->implode(', ');
        $ws->setCellValue('A2', trim($addr.($company?->gstin ? '  ·  GSTIN '.$company->gstin : '')));
        $ws->setCellValue('A3', $r['title']);
        $ws->getStyle('A3')->getFont()->setBold(true)->setSize(12)->getColor()->setRGB('1D4ED8');
        $applied = collect($r['applied'] ?? [])->map(fn ($v, $k) => "$k: $v")->implode('   |   ');
        $ws->setCellValue('A4', ($applied ? $applied.'   |   ' : '').'Generated '.now()->format('d M Y, h:i A').' IST by '.(auth()->user()?->name ?? 'system'));
        $ws->getStyle('A2:A4')->getFont()->setSize(9)->getColor()->setRGB('475569');
        $ws->getStyle('A3')->getFont()->setSize(12)->getColor()->setRGB('1D4ED8');

        $row = 6;
        if (! empty($r['groupHeaders'])) {
            $c = 1;
            foreach ($r['groupHeaders'] as [$label, $span]) {
                $a = Coordinate::stringFromColumnIndex($c);
                $b = Coordinate::stringFromColumnIndex($c + $span - 1);
                $ws->setCellValue("{$a}{$row}", $label);
                if ($span > 1) {
                    $ws->mergeCells("{$a}{$row}:{$b}{$row}");
                }
                $c += $span;
            }
            $ws->getStyle("A{$row}:{$last}{$row}")->applyFromArray(['font' => ['bold' => true, 'color' => ['rgb' => '1E293B']], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E2E8F0']]]);
            $row++;
        }
        $head = $row;
        foreach ($cols as $i => $c) {
            $ws->setCellValue(Coordinate::stringFromColumnIndex($i + 1).$row, $c['label']);
        }
        $ws->getStyle("A{$row}:{$last}{$row}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2563EB']],
            'alignment' => ['wrapText' => true, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $ws->getRowDimension($row)->setRowHeight(30);

        foreach ($r['rows'] as $data) {
            $row++;
            foreach ($cols as $i => $c) {
                $cell = Coordinate::stringFromColumnIndex($i + 1).$row;
                $v = $data[$c['key']] ?? null;
                if ($v === null) {
                    continue;
                }
                match ($c['type']) {
                    'money', 'pl', 'number', 'int' => $ws->setCellValue($cell, (float) $v),
                    'percent' => $ws->setCellValue($cell, (float) $v / 100),
                    'date' => $ws->setCellValue($cell, \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel(new \DateTime(substr((string) $v, 0, 10)))),
                    'pill' => $ws->setCellValueExplicit($cell, Str::headline((string) $v), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING),
                    default => $ws->setCellValueExplicit($cell, (string) $v, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING),
                };
            }
        }
        $first = $head + 1;
        if (! empty($r['totals'])) {
            $row++;
            $ws->setCellValue("A{$row}", 'Total');
            foreach ($cols as $i => $c) {
                if (array_key_exists($c['key'], $r['totals'])) {
                    $L = Coordinate::stringFromColumnIndex($i + 1);
                    $ws->setCellValue("{$L}{$row}", $row > $first ? (! empty($c['sum']) && $c['key'] !== 'amount' ? "=SUBTOTAL(9,{$L}{$first}:{$L}".($row - 1).')' : (float) $r['totals'][$c['key']]) : 0);
                }
            }
            $ws->getStyle("A{$row}:{$last}{$row}")->applyFromArray(['font' => ['bold' => true], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F1F5F9']],
                'borders' => ['top' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '94A3B8']], 'bottom' => ['borderStyle' => Border::BORDER_DOUBLE, 'color' => ['rgb' => '94A3B8']]]]);
        }
        foreach ($cols as $i => $c) {
            $L = Coordinate::stringFromColumnIndex($i + 1);
            $range = "{$L}{$first}:{$L}".max($first, $row);
            match ($c['type']) {
                'money' => $ws->getStyle($range)->getNumberFormat()->setFormatCode(self::INR_FORMAT),
                'pl' => $ws->getStyle($range)->getNumberFormat()->setFormatCode('#,##0.00;[Red](#,##0.00)'),
                'number' => $ws->getStyle($range)->getNumberFormat()->setFormatCode('#,##0.00'),
                'int' => $ws->getStyle($range)->getNumberFormat()->setFormatCode('#,##0'),
                'percent' => $ws->getStyle($range)->getNumberFormat()->setFormatCode('0.0%'),
                'date' => $ws->getStyle($range)->getNumberFormat()->setFormatCode('dd-mmm-yyyy'),
                default => null,
            };
            $wide = in_array($c['key'], ['name', 'changes', 'remarks', 'title', 'category', 'from', 'to'], true);
            $ws->getColumnDimension($L)->setWidth(match (true) {
                $wide => 34, in_array($c['type'], ['money', 'pl'], true) => 17, $c['type'] === 'date' => 12.5, default => 14,
            });
        }
        if (count($r['rows'])) {
            $ws->setAutoFilter("A{$head}:{$last}".($head + count($r['rows'])));
        }
        $ws->freezePane('A'.($head + 1));
        $row += 2;
        foreach ($r['notes'] ?? [] as $note) {
            $ws->setCellValue("A{$row}", 'Note: '.$note);
            $ws->getStyle("A{$row}")->getFont()->setItalic(true)->setSize(9)->getColor()->setRGB('64748B');
            $row++;
        }
        $ws->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setPaperSize(PageSetup::PAPERSIZE_A4)->setFitToWidth(1)->setFitToHeight(0);
        $ws->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd($head, $head);
        $ws->setSelectedCell('A1');

        $name = $this->filename($r, 'xlsx');

        return response()->streamDownload(function () use ($book) {
            (new Xlsx($book))->save('php://output');
        }, $name, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Cache-Control' => 'max-age=0']);
    }

    public function pdf(array $r)
    {
        // dompdf keeps the whole layout tree in memory; long tables need headroom
        @ini_set('memory_limit', '1024M');
        @set_time_limit(180);
        $truncated = count($r['rows']) > self::PDF_ROW_LIMIT;
        if ($truncated) {
            $r['rows'] = array_slice($r['rows'], 0, self::PDF_ROW_LIMIT);
            $r['notes'][] = 'PDF limited to the first '.number_format(self::PDF_ROW_LIMIT).' rows (totals cover all rows); use the Excel export for the full data.';
        }
        $pdf = Pdf::loadView('reports.pdf', ['r' => $r, 'company' => Tenant::company()])
            ->setPaper('a4', 'landscape')
            ->setOption(['defaultFont' => 'DejaVu Sans', 'isPhpEnabled' => true, 'isRemoteEnabled' => false, 'isFontSubsettingEnabled' => true]);

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->filename($r, 'pdf').'"',
        ]);
    }
}
