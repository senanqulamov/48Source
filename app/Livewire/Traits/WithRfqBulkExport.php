<?php

namespace App\Livewire\Traits;

use App\Models\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Shared, server-authorized selection and export for the RFQ listing.
 * Selection is limited to the visible page; exports re-check ownership.
 */
trait WithRfqBulkExport
{
    /** @var array<int,int> */
    public array $selectedRfqIds = [];

    public function toggleRfqSelection(int $rfqId): void
    {
        $visible = $this->rows->getCollection()->pluck('id')->map(fn ($id) => (int) $id)->all();
        if (! in_array($rfqId, $visible, true)) {
            abort(403);
        }

        $selected = array_map('intval', $this->selectedRfqIds);
        if (in_array($rfqId, $selected, true)) {
            $this->selectedRfqIds = array_values(array_diff($selected, [$rfqId]));
        } else {
            $this->selectedRfqIds = array_values(array_unique([...$selected, $rfqId]));
        }
    }

    public function togglePageRfqSelection(): void
    {
        $visible = $this->rows->getCollection()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $selected = array_map('intval', $this->selectedRfqIds);
        if ($visible !== [] && count(array_intersect($visible, $selected)) === count($visible)) {
            $this->selectedRfqIds = array_values(array_diff($selected, $visible));
        } else {
            $this->selectedRfqIds = array_values(array_unique([...$selected, ...$visible]));
        }
    }

    public function clearRfqSelection(): void
    {
        $this->selectedRfqIds = [];
    }

    public function exportSelectedRfqsCsv(): StreamedResponse
    {
        $actor = auth()->user();
        abort_unless($actor && $actor->can('view_rfqs') && ($actor->isAdmin() || $actor->isBuyer()), 403);
        $ids = array_values(array_unique(array_filter(array_map('intval', $this->selectedRfqIds), fn (int $id) => $id > 0)));
        if ($ids === [] || count($ids) > 1000) {
            abort(422, 'Select between 1 and 1000 RFQs to export.');
        }

        $query = Request::query()->whereIn('id', $ids)->with('buyer:id,name')->orderBy('id');
        if ($actor->isBuyer() && ! $actor->isAdmin()) {
            $query->where('buyer_id', $actor->id);
        }
        $records = $query->get();

        // A forged ID is never silently exported or treated as a valid selection.
        abort_unless($records->count() === count($ids), 403);

        return response()->streamDownload(function () use ($records): void {
            $stream = fopen('php://output', 'w');
            if ($stream === false) {
                return;
            }
            fputcsv($stream, ['Reference', 'Title', 'Buyer', 'Type', 'Status', 'Deadline', 'Created']);
            foreach ($records as $record) {
                // Guard against spreadsheet formula injection from user-provided strings.
                $safe = static function (?string $text): string {
                    $text = (string) $text;
                    return preg_match('/^\\s*[=+\\-@]/u', $text) ? "'".$text : $text;
                };
                fputcsv($stream, [
                    $record->id,
                    $safe($record->title),
                    $safe($record->buyer?->name),
                    $safe($record->request_type),
                    $safe($record->status),
                    $record->deadline?->format('Y-m-d'),
                    $record->created_at?->format('Y-m-d'),
                ]);
            }
            fclose($stream);
        }, 'rfqs-'.now()->format('Y-m-d-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
