<?php

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

interface BaseRepositoryInterface
{
    /**
     * Get the underlying Eloquent model instance.
     */
    public function getModel(): Model;

    /**
     * Get all records.
     */
    public function all(array $columns = ['*']): Collection;

    /**
     * Paginate records.
     */
    public function paginate(int $perPage = 15, array $columns = ['*']): LengthAwarePaginator;

    /**
     * Find record by internal primary ID.
     */
    public function findById(int $id, array $columns = ['*']): ?Model;

    /**
     * Find record by UUID.
     */
    public function findByUuid(string $uuid, array $columns = ['*']): ?Model;

    /**
     * Find record by UUID or throw 404 Exception.
     */
    public function findByUuidOrFail(string $uuid, array $columns = ['*']): Model;

    /**
     * Create a new record.
     */
    public function create(array $payload): Model;

    /**
     * Update an existing record by UUID.
     */
    public function update(string $uuid, array $payload): ?Model;

    /**
     * Delete a record by UUID.
     */
    public function delete(string $uuid): bool;
}
