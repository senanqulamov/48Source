<?php

namespace App\Livewire\Categories;

use App\Livewire\Concerns\Page;
use App\Models\Category;
use App\Support\Audit;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Categories')]
class Index extends Component
{
    use Page;

    public array $f = ['id' => null, 'name' => '', 'parent_id' => ''];

    public function create(): void
    {
        $this->need('catalog', 'C');
        $this->f = ['id' => null, 'name' => '', 'parent_id' => ''];
        $this->open('form');
    }

    public function edit(int $id): void
    {
        $this->need('catalog', 'E');
        $c = Category::findOrFail($id);
        $this->f = ['id' => $c->id, 'name' => $c->name, 'parent_id' => (string) $c->parent_id];
        $this->open('form');
    }

    public function save(): void
    {
        $this->need('catalog', $this->f['id'] ? 'E' : 'C');
        $this->validate(['f.name' => 'required|string|min:2|max:80', 'f.parent_id' => 'nullable|exists:categories,id'], [], ['f.name' => 'name', 'f.parent_id' => 'group']);
        $parent = $this->f['parent_id'] ?: null;
        if ($this->f['id'] && (int) $parent === (int) $this->f['id']) {
            $this->addError('f.parent_id', 'A category cannot be its own group.');

            return;
        }
        $c = $this->f['id'] ? Category::findOrFail($this->f['id']) : new Category;
        if ($c->exists && $parent && $c->children()->exists()) {
            $this->addError('f.parent_id', 'This is a group with categories inside it. Move those first.');

            return;
        }
        $slug = Str::slug($this->f['name']);
        $n = 1;
        while (Category::where('slug', $slug)->where('id', '!=', $c->id ?? 0)->exists()) {
            $slug = Str::slug($this->f['name']).'-'.(++$n);
        }
        $c->fill(['name' => trim($this->f['name']), 'parent_id' => $parent, 'slug' => $slug])->save();
        Audit::log('Category', $this->f['id'] ? 'Edited category' : 'Added category', $c);
        $this->close();
        $this->toast("{$c->name} saved.");
    }

    public function render()
    {
        return view('livewire.categories.index', [
            'groups' => Category::whereNull('parent_id')->with(['children' => fn ($q) => $q->withCount('suppliers', 'rfqs')])->orderBy('name')->get(),
            'parents' => Category::whereNull('parent_id')->orderBy('name')->pluck('name', 'id'),
        ]);
    }
}
