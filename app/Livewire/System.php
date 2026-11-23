<?php

namespace App\Livewire;

use App\Exceptions\BusinessRule;
use App\Livewire\Concerns\Page;
use App\Models\ActivityLog;
use App\Models\Currency;
use App\Models\FeatureFlag;
use App\Services\SystemService;
use App\Support\Audit;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;

#[Layout('components.layouts.app')]
#[Title('System')]
class System extends Component
{
    use Page, WithFileUploads;

    #[Url(except: 'health')]
    public string $tab = 'health';

    public array $cur = ['code' => '', 'name' => ''];

    public $file = null;

    public array $report = [];

    public function mount(): void
    {
        $this->need('system');
    }

    public function toggleFlag(string $key): void
    {
        $this->need('system', 'E');
        abort_unless(isset(FeatureFlag::DEFAULTS[$key]), 404);
        $on = ! FeatureFlag::isOn($key);
        FeatureFlag::updateOrCreate(['key' => $key], ['label' => FeatureFlag::DEFAULTS[$key][0], 'enabled' => $on]);
        Audit::log('System', ($on ? 'Switched on' : 'Switched off') . ': ' . FeatureFlag::DEFAULTS[$key][0]);
        $this->toast(FeatureFlag::DEFAULTS[$key][0] . ' is now ' . ($on ? 'on' : 'off') . '.');
    }

    public function addCurrency(): void
    {
        $this->need('system', 'E');
        if ($this->attempt(function () {
            $code = mb_strtoupper(trim($this->cur['code']));
            if (! preg_match('/^[A-Z]{3}$/', $code)) {
                throw new BusinessRule('A currency code is three letters, for example GBP.');
            }
            if (trim($this->cur['name']) === '') {
                throw new BusinessRule('Give the currency a name.');
            }
            if (Currency::whereKey($code)->exists()) {
                throw new BusinessRule("{$code} is already in the list.");
            }
            Currency::create(['code' => $code, 'name' => trim($this->cur['name'])]);
            Audit::log('System', "Added currency {$code}");
        }, 'Currency added.')) {
            $this->reset('cur');
        }
    }

    public function toggleCurrency(string $code): void
    {
        $this->need('system', 'E');
        $this->attempt(function () use ($code) {
            $c = Currency::findOrFail($code);
            if ($c->active && Currency::where('active', true)->count() === 1) {
                throw new BusinessRule('At least one currency has to stay active.');
            }
            $c->update(['active' => ! $c->active]);
            Audit::log('System', ($c->active ? 'Activated' : 'Deactivated') . " currency {$code}");
        });
    }

    public function importSuppliers(SystemService $system): void
    {
        $this->need('suppliers', 'C');
        $this->validate(['file' => 'required|file|mimes:xlsx,csv,txt|max:2048'], ['file.required' => 'Choose a file first.', 'file.mimes' => 'Upload an Excel (.xlsx) or CSV file.', 'file.max' => 'The file is larger than 2 MB.']);
        $this->attempt(function () use ($system) {
            $this->report = $system->importSuppliers(Excel::toArray(new \stdClass, $this->file)[0] ?? []);
            $this->toast("{$this->report['saved']} supplier(s) added, waiting for approval" . ($this->report['errors'] ? '; ' . count($this->report['errors']) . ' row(s) skipped.' : '.'), $this->report['errors'] ? 'alert' : 'check');
        });
        $this->reset('file');
    }

    public function template()
    {
        return response()->streamDownload(function () {
            echo "name,email,city,contact,phone,category\nExample Supplies Ltd,sales@example.test,Baku,Jane Doe,+994 12 000 00 00,Steel & metals\n";
        }, 'suppliers-template.csv', ['Content-Type' => 'text/csv']);
    }

    public function render(SystemService $system)
    {
        return view('livewire.system', ['health' => $this->tab === 'health' ? $system->health() : [], 'flags' => collect(FeatureFlag::DEFAULTS)->map(fn ($d, $k) => ['label' => $d[0], 'on' => FeatureFlag::isOn($k)]),
            'currencies' => Currency::orderBy('code')->get(), 'transfers' => $this->tab === 'data' ? ActivityLog::whereIn('area', ['Import', 'Catalog'])->where('action', 'like', 'Imported%')->latest('created_at')->limit(30)->get() : collect()]);
    }
}
