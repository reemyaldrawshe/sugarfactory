<?php

namespace App\Services\Admin;

use App\Models\Section;
class SectionService
{
    public function list()
    {
        return Section::query()
          ->get();
    }

    public function create(array $data): Section
    {
        return Section::query()->create($data);
    }

    public function update(int $id, array $data): Section
    {
        $category = Section::query()->findOrFail($id);

        $category->update($data);
        return $category->refresh();
    }

    public function delete(int $id): bool
    {
        $category = Section::query()->findOrFail($id);
        return $category->delete();
    }

    public function show(int $id): Section
    {
        return Section::query()->findOrFail($id);
    }

}
