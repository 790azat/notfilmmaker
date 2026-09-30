<?php

namespace App\Livewire\Admin;

use App\Models\Work;
use App\Models\WorkMedia;
use App\Support\Media;
use App\Support\YouTube;
use Illuminate\Validation\Rule;
use Livewire\Component;

class WorkForm extends Component
{
    public ?Work $work = null;

    public array $title = ['hy' => '', 'ru' => '', 'en' => ''];

    public array $excerpt = ['hy' => '', 'ru' => '', 'en' => ''];

    public array $description = ['hy' => '', 'ru' => '', 'en' => ''];

    public array $role = ['hy' => '', 'ru' => '', 'en' => ''];

    public string $category = 'film';

    public string $video_url = '';

    public ?string $cover = null;

    public ?int $year = null;

    public string $client = '';

    public bool $is_featured = false;

    public bool $is_published = true;

    /** @var array<int, array{id: ?int, path: string, type: string}> */
    public array $gallery = [];

    /** Файлы, которые нужно удалить из хранилища после сохранения. */
    public array $removed = [];

    public function mount(?Work $work = null): void
    {
        if ($work?->exists) {
            $this->work = $work;
            foreach (['title', 'excerpt', 'description', 'role'] as $field) {
                $this->{$field} = array_merge($this->{$field}, $work->getTranslations($field));
            }
            $this->category = $work->category;
            $this->video_url = (string) $work->video_url;
            $this->cover = $work->cover;
            $this->year = $work->year;
            $this->client = (string) $work->client;
            $this->is_featured = $work->is_featured;
            $this->is_published = $work->is_published;
            $this->gallery = $work->media->map(fn (WorkMedia $m) => ['id' => $m->id, 'path' => $m->path, 'type' => $m->type])->all();
        } else {
            $this->year = (int) date('Y');
        }
    }

    public function setCover(string $path): void
    {
        if ($this->cover) {
            $this->removed[] = $this->cover;
        }
        $this->cover = $path;
    }

    public function removeCover(): void
    {
        if ($this->cover) {
            $this->removed[] = $this->cover;
        }
        $this->cover = null;
    }

    public function setVideo(string $path): void
    {
        $this->video_url = $path;
    }

    public function addMedia(string $path, string $mime = ''): void
    {
        $this->gallery[] = [
            'id' => null,
            'path' => $path,
            'type' => str_starts_with($mime, 'video/') || Media::isVideo($path) ? 'video' : 'image',
        ];
    }

    public function removeMedia(int $index): void
    {
        if (isset($this->gallery[$index])) {
            $this->removed[] = $this->gallery[$index]['path'];
            unset($this->gallery[$index]);
            $this->gallery = array_values($this->gallery);
        }
    }

    public function moveMedia(int $index, int $direction): void
    {
        $target = $index + $direction;
        if (isset($this->gallery[$index], $this->gallery[$target])) {
            [$this->gallery[$index], $this->gallery[$target]] = [$this->gallery[$target], $this->gallery[$index]];
        }
    }

    public function save()
    {
        $this->validate([
            'title.hy' => ['nullable', 'string', 'max:200'],
            'title.ru' => ['nullable', 'string', 'max:200'],
            'title.en' => ['nullable', 'string', 'max:200'],
            'title' => [function ($attr, $value, $fail) {
                if (! array_filter($value)) {
                    $fail(__('admin.works.title_required'));
                }
            }],
            'excerpt.*' => ['nullable', 'string', 'max:500'],
            'description.*' => ['nullable', 'string', 'max:20000'],
            'role.*' => ['nullable', 'string', 'max:120'],
            'category' => ['required', Rule::in(Work::CATEGORIES)],
            'video_url' => ['nullable', 'string', 'max:500'],
            'year' => ['nullable', 'integer', 'between:1950,2100'],
            'client' => ['nullable', 'string', 'max:160'],
        ]);

        // Пустые переводы заполняем первым заполненным языком, чтобы на сайте не было пустых мест.
        $fill = function (array $values): ?array {
            $first = collect($values)->first(fn ($v) => trim((string) $v) !== '');
            if ($first === null) {
                return null;
            }

            return collect($values)->map(fn ($v) => trim((string) $v) !== '' ? trim($v) : $first)->all();
        };

        $data = [
            'title' => $fill($this->title),
            'excerpt' => $fill($this->excerpt),
            'description' => $fill($this->description),
            'role' => $fill($this->role),
            'category' => $this->category,
            'video_url' => trim($this->video_url) ?: null,
            'cover' => $this->cover,
            'year' => $this->year ?: null,
            'client' => trim($this->client) ?: null,
            'is_featured' => $this->is_featured,
            'is_published' => $this->is_published,
        ];

        $work = $this->work ?? new Work(['published_at' => now()]);
        foreach (['title', 'excerpt', 'description', 'role'] as $field) {
            $work->setTranslations($field, $data[$field] ?? []);
            unset($data[$field]);
        }
        $work->fill($data)->save();

        $keep = [];
        foreach ($this->gallery as $i => $item) {
            $media = $item['id'] ? WorkMedia::find($item['id']) : null;
            $media ??= $work->media()->make();
            $media->fill(['path' => $item['path'], 'type' => $item['type'], 'sort_order' => $i])->save();
            $keep[] = $media->id;
        }
        $work->media()->whereNotIn('id', $keep)->delete();

        foreach (array_unique($this->removed) as $path) {
            if ($path !== $work->cover && ! in_array($path, array_column($this->gallery, 'path'), true)) {
                Media::delete($path);
            }
        }

        session()->flash('toast', __('admin.saved'));

        return $this->redirectRoute('admin.works.edit', $work->id, navigate: true);
    }

    public function render()
    {
        return view('livewire.admin.work-form', [
            'youtubeId' => YouTube::parseId($this->video_url),
        ])->layout('layouts.admin', ['title' => $this->work ? __('admin.works.edit') : __('admin.works.add')]);
    }
}
