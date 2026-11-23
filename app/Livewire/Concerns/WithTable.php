<?php

namespace App\Livewire\Concerns;

use Livewire\Attributes\Url;
use Livewire\WithPagination;

/** Search, quick filter, facet filters, sorting, column chooser, selection, paging and CSV export for list pages. */
trait WithTable
{
    use WithPagination;

    #[Url(except: '')]
    public string $q = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $sort = '';

    #[Url(except: 'desc')]
    public string $dir = 'desc';

    public int $per = 10;

    /** Facet filters: facet key => chosen values. @var array<string, array<int, string>> */
    public array $f = [];

    /** Keys of hidden columns. @var array<int, string> */
    public array $hide = [];

    /** @var array<int, string> */
    public array $selected = [];

    public function sortBy(string $col): void
    {
        if (! in_array($col, $this->sortable(), true)) {
            return;
        }
        $this->dir = $this->sort === $col && $this->dir === 'asc' ? 'desc' : 'asc';
        $this->sort = $col;
        $this->resetPage();
    }

    public function updated($name): void
    {
        if (in_array($name, ['q', 'status', 'per'], true) || str_starts_with((string) $name, 'f.')) {
            $this->resetPage();
            $this->selected = [];
        }
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
        $this->resetPage();
        $this->selected = [];
    }

    public function clearFacet(string $key): void
    {
        unset($this->f[$key]);
        $this->resetPage();
    }

    public function dropFacet(string $key, string $value): void
    {
        $this->f[$key] = array_values(array_diff($this->f[$key] ?? [], [$value]));
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->q = '';
        $this->status = '';
        $this->f = [];
        $this->selected = [];
        $this->resetPage();
    }

    public function toggleCol(string $key): void
    {
        $this->hide = in_array($key, $this->hide, true) ? array_values(array_diff($this->hide, [$key])) : [...$this->hide, $key];
    }

    /** Select or clear every row of the page that is on screen. */
    public function togglePage(array $keys): void
    {
        $keys = array_map('strval', $keys);
        $this->selected = count(array_intersect($keys, $this->selected)) === count($keys)
            ? array_values(array_diff($this->selected, $keys))
            : array_values(array_unique([...$this->selected, ...$keys]));
    }

    /** Chosen values of one facet, limited to the values that facet allows. */
    protected function facet(string $key, array $allowed): array
    {
        return array_values(array_intersect(array_map('strval', (array) ($this->f[$key] ?? [])), array_map('strval', $allowed)));
    }

    protected function sortCol(string $default): string
    {
        return in_array($this->sort, $this->sortable(), true) ? $this->sort : $default;
    }

    protected function sortDir(): string
    {
        return $this->dir === 'asc' ? 'asc' : 'desc';
    }

    protected function perPage(): int
    {
        return in_array($this->per, [10, 25, 50], true) ? $this->per : 10;
    }

    /** Download what the current filters show, as Excel or CSV. */
    public function export(string $format = 'xlsx')
    {
        [$name, $head, $rows] = $this->exportData();

        return \App\Support\Sheet::download($name, $head, $rows, $format === 'csv' ? 'csv' : 'xlsx');
    }

    /** @return array{0:string,1:array<int,string>,2:iterable<int,array<int,mixed>>} file name, headings, rows */
    protected function exportData(): array
    {
        return ['export', [], []];
    }

    /** @return array<int, string> */
    abstract protected function sortable(): array;
}
