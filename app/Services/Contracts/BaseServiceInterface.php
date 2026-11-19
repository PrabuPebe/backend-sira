<?php

namespace App\Services\Contracts;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

interface BaseServiceInterface
{
    /**
     * Get all items.
     */
    public function getAll(): Collection;

    /**
     * Get paginated items.
     */
    public function getPaginated(int $perPage = 15): LengthAwarePaginator;

    /**
     * Get single item by UUID.
     */
    public function getByUuid(string $uuid): Model;

    /**
     * Create new item.
     */
    public function create(array $payload): Model;

    /**
     * Update item by UUID.
     */
    public function update(string $uuid, array $payload): Model;

    /**
     * Delete item by UUID.
     */
    public function delete(string $uuid): bool;
}
