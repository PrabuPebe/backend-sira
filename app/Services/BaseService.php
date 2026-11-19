<?php

namespace App\Services;

use App\Repositories\Contracts\BaseRepositoryInterface;
use App\Services\Contracts\BaseServiceInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

abstract class BaseService implements BaseServiceInterface
{
    protected BaseRepositoryInterface $repository;

    public function __construct(BaseRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function getAll(): Collection
    {
        return $this->repository->all();
    }

    public function getPaginated(int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->paginate($perPage);
    }

    public function getByUuid(string $uuid): Model
    {
        return $this->repository->findByUuidOrFail($uuid);
    }

    public function create(array $payload): Model
    {
        return DB::transaction(function () use ($payload) {
            return $this->repository->create($payload);
        });
    }

    public function update(string $uuid, array $payload): Model
    {
        return DB::transaction(function () use ($uuid, $payload) {
            $updated = $this->repository->update($uuid, $payload);
            if (! $updated) {
                throw (new ModelNotFoundException)->setModel(get_class($this->repository->getModel()), [$uuid]);
            }

            return $updated;
        });
    }

    public function delete(string $uuid): bool
    {
        return DB::transaction(function () use ($uuid) {
            $deleted = $this->repository->delete($uuid);
            if (! $deleted) {
                throw (new ModelNotFoundException)->setModel(get_class($this->repository->getModel()), [$uuid]);
            }

            return $deleted;
        });
    }
}
