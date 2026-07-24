<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EventExportController extends Controller
{
    public function pdf(Event $event): \Illuminate\Http\Response
    {
        $this->authorizeEvent($event);

        $tasksByPhase = $event->tasks()
            ->orderByRaw("FIELD(phase, 'Pre-Planning', 'Preparation', 'Day-Of')")
            ->orderByRaw("FIELD(priority, 'High', 'Medium', 'Low')")
            ->orderBy('due_date')
            ->get()
            ->groupBy('phase');

        $pdf = Pdf::loadView('events.exports.pdf', [
            'event' => $event,
            'tasksByPhase' => $tasksByPhase,
        ])->setPaper('a4');

        return $pdf->download($this->fileName($event, 'pdf'));
    }

    public function excel(Event $event): StreamedResponse
    {
        $this->authorizeEvent($event);

        $tasks = $event->tasks()
            ->orderByRaw("FIELD(phase, 'Pre-Planning', 'Preparation', 'Day-Of')")
            ->orderByRaw("FIELD(priority, 'High', 'Medium', 'Low')")
            ->orderBy('due_date')
            ->get();

        $spreadsheet = new Spreadsheet();
        $this->buildSummarySheet($spreadsheet->getActiveSheet(), $event);
        $this->buildTasksSheet($spreadsheet->createSheet(), $event, $tasks);

        $writer = new Xlsx($spreadsheet);
        $fileName = $this->fileName($event, 'xlsx');

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    protected function buildSummarySheet(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, Event $event): void
    {
        $sheet->setTitle('Summary');
        $sheet->getColumnDimension('A')->setWidth(24);
        $sheet->getColumnDimension('B')->setWidth(40);

        $sheet->setCellValue('A1', $event->event_name);
        $sheet->mergeCells('A1:B1');
        $sheet->getStyle('A1')->getFont()->setSize(16)->setBold(true);

        $type = $event->event_type === 'Custom' ? $event->custom_event_type : $event->event_type;

        $rows = [
            ['Event Type', $type],
            ['Status', $event->status],
            ['Event Date', optional($event->event_date)->format('M d, Y') ?? 'Not set'],
            ['Event Time', $event->event_time ?: 'Not set'],
            ['Venue', $event->venue_name ?: 'Not set'],
            ['Location', $event->location ?: 'Not set'],
            ['Guests', $event->guest_count.($event->max_guests ? ' / '.$event->max_guests.' capacity' : '')],
            ['Total Budget', $event->currencySymbol().number_format((float) $event->total_budget, 2)],
            ['Budget Spent', $event->currencySymbol().number_format((float) $event->budget_spent, 2)],
            ['Budget Remaining', $event->currencySymbol().number_format($event->budget_remaining, 2)],
            ['Description', $event->description ?: 'None provided'],
        ];

        $row = 3;
        foreach ($rows as [$label, $value]) {
            $sheet->setCellValue("A{$row}", $label);
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            $sheet->setCellValue("B{$row}", $value);
            $sheet->getStyle("A{$row}:B{$row}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
            $row++;
        }

        $sheet->getStyle("B10")->getAlignment()->setWrapText(true);
    }

    protected function buildTasksSheet(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, Event $event, $tasks): void
    {
        $sheet->setTitle('Tasks');

        $headers = ['Phase', 'Task Name', 'Due Date', 'Priority', 'Status', 'Source', 'Notes'];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:G1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A1:G1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('5C3CE6');

        $row = 2;
        foreach ($tasks as $task) {
            $sheet->setCellValue("A{$row}", $task->phase);
            $sheet->setCellValue("B{$row}", $task->task_name);
            $sheet->setCellValue("C{$row}", optional($task->due_date)->format('M d, Y') ?? 'Not set');
            $sheet->setCellValue("D{$row}", $task->priority);
            $sheet->setCellValue("E{$row}", $task->status);
            $sheet->setCellValue("F{$row}", $task->source);
            $sheet->setCellValue("G{$row}", $task->notes);
            $row++;
        }

        foreach (['A' => 16, 'B' => 40, 'C' => 14, 'D' => 10, 'E' => 12, 'F' => 10, 'G' => 40] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        $sheet->getStyle("A1:G{$row}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle('B:B')->getAlignment()->setWrapText(true);
        $sheet->getStyle('G:G')->getAlignment()->setWrapText(true);
    }

    protected function fileName(Event $event, string $extension): string
    {
        return Str::slug($event->event_name).'-'.now()->format('Y-m-d').'.'.$extension;
    }

    protected function authorizeEvent(Event $event): void
    {
        abort_unless($event->user_id === Auth::id(), 403);
    }
}
