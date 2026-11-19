<?php

namespace App\Repositories\Eloquent;

use App\Repositories\Contracts\BaseRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

abstract class BaseRepository implements BaseRepositoryInterface
{
    /**
     * @var Model
     */
    protected Model $model;

    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    public function getModel(): Model
    {
        return $this->model;
    }

    public function all(array $columns = ['*']): Collection
    {
        return $this->model->all($columns);
    }

    public function paginate(int $perPage = 15, array $columns = ['*']): LengthAwarePaginator
    {
        return $this->model->paginate($perPage, $columns);
    }

    public function findById(int $id, array $columns = ['*']): ?Model
    {
        return $this->model->find($id, $columns);
    }

    public function findByUuid(string $uuid, array $columns = ['*']): ?Model
    {
        return $this->model->where('uuid', $uuid)->first($columns);
    }

    public function findByUuidOrFail(string $uuid, array $columns = ['*']): Model
    {
        return $this->model->where('uuid', $uuid)->firstOrFail($columns);
    }

    public function create(array $payload): Model
    {
        return $this->model->create($payload);
    }

    public function update(string $uuid, array $payload): ?Model
    {
        $record = $this->findByUuid($uuid);
        if (! $record) {
            return null;
        }

        $record->update($payload);

        return $record;
    }

    public function delete(string $uuid): bool
    {
        $record = $this->findByUuid($uuid);
        if (! $record) {
            return false;
        }

        return (bool) $record->delete();
    }
}
