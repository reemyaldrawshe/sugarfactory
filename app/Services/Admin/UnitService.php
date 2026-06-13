<?php

namespace App\Services\Admin;

use App\Models\Unit;

class UnitService
{
    public function list()
    {
        return Unit::query()->get();
    }

    public function create(array $data): Unit
    {
        return Unit::query()->create($data);
    }

    public function update(int $id, array $data): Unit
    {
        $unit = Unit::query()->findOrFail($id);

        $unit->update($data);
        return $unit->refresh();
    }

    public function delete(int $id): bool
    {
        $unit = Unit::query()->findOrFail($id);
        return $unit->delete();
    }

    public function show(int $id): Unit
    {
        return Unit::query()->findOrFail($id);
    }
}